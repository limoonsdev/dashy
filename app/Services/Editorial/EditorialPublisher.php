<?php

namespace App\Services\Editorial;

use App\Models\EditorialPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Delivers a post to the channels stored on it. Each channel is independent:
 * one failing endpoint never blocks the others, and every attempt is written
 * back to `meta.last_publish` so the UI can show exactly what happened.
 */
class EditorialPublisher
{
    /**
     * @return array<int, array{channel: string, ok: bool, message: string}>
     */
    public function publish(EditorialPost $post): array
    {
        $results = [];
        $channels = array_values(array_filter((array) $post->channels));

        if ($channels === []) {
            $results[] = ['channel' => 'none', 'ok' => false, 'message' => 'Pick at least one channel first.'];
        }

        foreach ($channels as $channel) {
            $config = config('editorial.channels.'.$channel);

            if (! is_array($config)) {
                $results[] = ['channel' => $channel, 'ok' => false, 'message' => 'Unknown channel.'];

                continue;
            }

            try {
                $results[] = [
                    'channel' => $channel,
                    'ok' => true,
                    'message' => $this->send($channel, $config, $post),
                ];
            } catch (\Throwable $exception) {
                report($exception);
                $results[] = ['channel' => $channel, 'ok' => false, 'message' => Str::limit($exception->getMessage(), 180)];
            }
        }

        $delivered = collect($results)->contains(fn (array $result) => $result['ok']);

        $post->forceFill([
            'status' => $delivered ? 'published' : $post->status,
            'published_at' => $delivered ? ($post->published_at ?? now()) : $post->published_at,
            'meta' => array_merge((array) $post->meta, [
                'last_publish' => $results,
                'last_publish_at' => now()->toIso8601String(),
            ]),
        ])->save();

        return $results;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function send(string $channel, array $config, EditorialPost $post): string
    {
        $payload = [
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'body' => $post->body,
            'url' => route('editorial.index'),
        ];

        return match ($channel) {
            'webhook' => $this->sendWebhook($config, $payload),
            'slack' => $this->sendSlack($config, $post),
            'discord' => $this->sendDiscord($config, $post),
            'wordpress' => $this->sendWordPress($config, $post),
            'ghost' => $this->sendGhost($config, $post),
            'github' => $this->sendGithub($config, $post),
            'notion' => $this->sendNotion($config, $post),
            default => throw new \RuntimeException('No connector for '.$channel.'.'),
        };
    }

    private function require(array $config, string $key, string $channel): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new \RuntimeException('The '.$channel.' connector is missing '.$key.'.');
        }

        return $value;
    }

    private function sendWebhook(array $config, array $payload): string
    {
        $response = Http::timeout(10)->post($this->require($config, 'url', 'webhook'), $payload);
        $response->throw();

        return 'Delivered (HTTP '.$response->status().').';
    }

    private function sendSlack(array $config, EditorialPost $post): string
    {
        $url = route('editorial.index');
        $response = Http::timeout(10)->post($this->require($config, 'url', 'slack'), [
            'text' => "*{$post->title}*\n".($post->excerpt ?: Str::limit(strip_tags((string) $post->body), 240))."\n<{$url}|Open in Dashy>",
        ]);
        $response->throw();

        return 'Posted to the Slack webhook.';
    }

    private function sendDiscord(array $config, EditorialPost $post): string
    {
        $response = Http::timeout(10)->post($this->require($config, 'url', 'discord'), [
            'content' => $post->title,
            'embeds' => [[
                'description' => $post->excerpt ?: Str::limit(strip_tags((string) $post->body), 400),
                'url' => route('editorial.index'),
                'color' => 0x8B5CF6,
                'footer' => ['text' => 'Dashy editorial'],
            ]],
        ]);
        $response->throw();

        return 'Posted to the Discord channel.';
    }

    private function sendWordPress(array $config, EditorialPost $post): string
    {
        $response = Http::timeout(15)
            ->withBasicAuth(
                $this->require($config, 'username', 'wordpress'),
                $this->require($config, 'password', 'wordpress'),
            )
            ->post(rtrim($this->require($config, 'url', 'wordpress'), '/').'/wp-json/wp/v2/posts', [
                'title' => $post->title,
                'content' => (string) $post->body,
                'excerpt' => (string) $post->excerpt,
                'slug' => $post->slug,
                'status' => 'publish',
            ]);
        $response->throw();

        return 'WordPress post #'.($response->json('id') ?? '?').' created.';
    }

    private function sendGhost(array $config, EditorialPost $post): string
    {
        $key = $this->require($config, 'admin_key', 'ghost');
        $parts = explode(':', $key, 2);

        if (count($parts) !== 2) {
            throw new \RuntimeException('The Ghost admin key must look like id:secret.');
        }

        [$id, $secret] = $parts;
        $header = $this->base64Url((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT', 'kid' => $id]));
        $claims = $this->base64Url((string) json_encode([
            'iat' => time(),
            'exp' => time() + 300,
            'aud' => '/admin/',
        ]));
        $signature = $this->base64Url(hash_hmac('sha256', $header.'.'.$claims, (string) hex2bin($secret), true));

        $response = Http::timeout(15)
            ->withToken($header.'.'.$claims.'.'.$signature)
            ->post(rtrim($this->require($config, 'url', 'ghost'), '/').'/ghost/api/admin/posts/?source=html', [
                'posts' => [[
                    'title' => $post->title,
                    'html' => (string) $post->body,
                    'custom_excerpt' => (string) $post->excerpt,
                    'slug' => $post->slug,
                    'status' => 'published',
                ]],
            ]);
        $response->throw();

        return 'Ghost post created.';
    }

    private function sendGithub(array $config, EditorialPost $post): string
    {
        $response = Http::timeout(15)
            ->withToken($this->require($config, 'token', 'github'))
            ->acceptJson()
            ->post('https://api.github.com/repos/'.$this->require($config, 'repository', 'github').'/issues', [
                'title' => $post->title,
                'body' => ($post->excerpt ? $post->excerpt."\n\n" : '').(string) $post->body,
            ]);
        $response->throw();

        return 'GitHub issue #'.($response->json('number') ?? '?').' opened.';
    }

    private function sendNotion(array $config, EditorialPost $post): string
    {
        $response = Http::timeout(15)
            ->withToken($this->require($config, 'token', 'notion'))
            ->withHeaders(['Notion-Version' => '2022-06-28'])
            ->post('https://api.notion.com/v1/pages', [
                'parent' => ['database_id' => $this->require($config, 'database_id', 'notion')],
                'properties' => [
                    'Name' => ['title' => [['text' => ['content' => $post->title]]]],
                ],
                'children' => [[
                    'object' => 'block',
                    'type' => 'paragraph',
                    'paragraph' => ['rich_text' => [['type' => 'text', 'text' => ['content' => Str::limit(strip_tags((string) $post->body), 1900)]]]],
                ]],
            ]);
        $response->throw();

        return 'Notion page created.';
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
