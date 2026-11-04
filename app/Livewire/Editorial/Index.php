<?php

namespace App\Livewire\Editorial;

use App\Models\EditorialPost;
use App\Services\Editorial\EditorialPublisher;
use Illuminate\Support\Str;
use Livewire\Component;

class Index extends Component
{
    public ?int $editing = null;

    public string $title = '';

    public string $excerpt = '';

    public string $body = '';

    public string $status = 'draft';

    /** @var array<int, string> */
    public array $channels = [];

    public ?string $scheduledAt = null;

    public string $filter = 'all';

    /** @var array<int, array{channel: string, ok: bool, message: string}> */
    public array $results = [];

    public string $resultsFor = '';

    public function create(): void
    {
        $this->resetForm();
        $this->editing = 0;
    }

    public function edit(int $id): void
    {
        $post = $this->query()->findOrFail($id);

        $this->editing = $post->id;
        $this->title = $post->title;
        $this->excerpt = (string) $post->excerpt;
        $this->body = (string) $post->body;
        $this->status = $post->status;
        $this->channels = array_values((array) $post->channels);
        $this->scheduledAt = $post->scheduled_at?->format('Y-m-d\TH:i');
        $this->results = (array) data_get($post->meta, 'last_publish', []);
        $this->resultsFor = $post->title;
    }

    public function close(): void
    {
        $this->editing = null;
        $this->results = [];
        $this->resultsFor = '';
    }

    public function save(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:200000'],
            'status' => ['required', 'in:'.implode(',', array_keys(EditorialPost::STATUSES))],
            'channels' => ['array'],
            'channels.*' => ['string', 'in:'.implode(',', array_keys((array) config('editorial.channels')))],
            'scheduledAt' => ['nullable', 'date'],
        ]);

        $post = $this->editing
            ? $this->query()->findOrFail($this->editing)
            : new EditorialPost(['team_id' => currentTeam()->id, 'user_id' => auth()->id()]);

        $status = $data['status'];
        $scheduled = $data['scheduledAt'] ? \Illuminate\Support\Carbon::parse($data['scheduledAt']) : null;

        if ($status === 'scheduled' && $scheduled === null) {
            $scheduled = now()->addHour();
        }

        if ($scheduled !== null && in_array($status, ['idea', 'draft', 'review'], true)) {
            $status = 'scheduled';
        }

        $post->fill([
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['title'], $post->id),
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'] ?? null,
            'status' => $status,
            'channels' => array_values(array_unique($data['channels'] ?? [])),
            'scheduled_at' => $scheduled,
            'published_at' => $post->published_at,
        ]);

        $post->save();

        $this->editing = $post->id;
        auditLog('ui.editorial.saved', [
            'team_id' => currentTeam()->id,
            'resource' => 'editorial',
            'post_id' => $post->id,
            'status' => $post->status,
        ]);

        $this->dispatch('success', 'Post saved as '.EditorialPost::STATUSES[$post->status].'.');
    }

    public function transition(int $id, string $status): void
    {
        if (! array_key_exists($status, EditorialPost::STATUSES)) {
            return;
        }

        $post = $this->query()->findOrFail($id);
        $post->status = $status;
        $post->published_at = $status === 'published' ? ($post->published_at ?? now()) : null;

        if ($status === 'scheduled' && $post->scheduled_at === null) {
            $post->scheduled_at = now()->addHour();
        }

        $post->save();

        $this->dispatch('success', 'Moved to '.EditorialPost::STATUSES[$status].'.');
    }

    public function publish(int $id, EditorialPublisher $publisher): void
    {
        $post = $this->query()->findOrFail($id);
        $this->results = $publisher->publish($post);
        $this->resultsFor = $post->title;
        $this->editing = $post->id;

        $delivered = collect($this->results)->where('ok', true)->count();
        $this->dispatch(
            $delivered > 0 ? 'success' : 'error',
            $delivered > 0
                ? 'Published to '.$delivered.' channel(s).'
                : 'Nothing was delivered — check the connector configuration.',
        );
    }

    public function remove(int $id): void
    {
        $this->query()->whereKey($id)->delete();

        if ($this->editing === $id) {
            $this->close();
        }

        $this->dispatch('success', 'Post deleted.');
    }

    public function render()
    {
        $posts = $this->query()
            ->when($this->filter !== 'all', fn ($query) => $query->where('status', $this->filter))
            ->latest()
            ->get();

        $counts = $this->query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return view('livewire.editorial.index', [
            'posts' => $posts,
            'counts' => $counts,
            'statuses' => EditorialPost::STATUSES,
            'channelOptions' => $this->channelOptions(),
        ]);
    }

    /**
     * @return array<string, array{label: string, description: string, configured: bool}>
     */
    private function channelOptions(): array
    {
        $options = [];

        foreach ((array) config('editorial.channels') as $key => $channel) {
            $values = collect($channel)->except(['label', 'icon', 'description']);
            $options[$key] = [
                'label' => (string) ($channel['label'] ?? $key),
                'description' => (string) ($channel['description'] ?? ''),
                'configured' => $values->filter(fn ($value) => filled($value))->count() === $values->count(),
            ];
        }

        return $options;
    }

    private function uniqueSlug(string $title, ?int $ignoreId): string
    {
        $base = Str::slug($title) ?: 'post';
        $slug = $base;
        $suffix = 2;

        while (
            $this->query()
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function resetForm(): void
    {
        $this->title = '';
        $this->excerpt = '';
        $this->body = '';
        $this->status = 'draft';
        $this->channels = [];
        $this->scheduledAt = null;
        $this->results = [];
        $this->resultsFor = '';
    }

    private function query()
    {
        return EditorialPost::query()->where('team_id', currentTeam()->id);
    }
}
