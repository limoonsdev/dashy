<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use ZipArchive;

/**
 * Reads a project archive (uploaded zip or a GitHub repository) and answers the
 * questions the deploy form normally asks: language, framework, build pack,
 * port, commands and environment variables.
 */
class ProjectAnalyzer
{
    private const SKIP = ['node_modules/', 'vendor/', '.git/', 'dist/', 'build/', '.next/', 'target/', '__pycache__/'];

    private const MAX_ENTRIES = 4000;

    private const MAX_READ = 200_000;

    /**
     * @return array<string, mixed>
     */
    public function fromGithub(string $url): array
    {
        $parts = $this->parseGithubUrl($url);

        if ($parts === null) {
            throw new \RuntimeException('Use a GitHub URL such as https://github.com/owner/repo.');
        }

        [$owner, $repo, $ref] = $parts;
        $response = Http::timeout(120)->withHeaders(['User-Agent' => 'Dashy project analyzer'])->get(
            "https://codeload.github.com/{$owner}/{$repo}/zip/".($ref ?: 'HEAD')
        );

        if ($response->failed()) {
            throw new \RuntimeException('GitHub returned HTTP '.$response->status().'.');
        }

        $path = tempnam(sys_get_temp_dir(), 'dashy-analyze-').'.zip';
        file_put_contents($path, $response->body());

        try {
            return $this->fromZip($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function fromZip(string $zipPath): array
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('The archive could not be opened.');
        }

        $files = [];
        $scanned = 0;

        for ($index = 0; $index < min($zip->numFiles, self::MAX_ENTRIES); $index++) {
            $name = $zip->getNameIndex($index);

            if ($name === false || str_ends_with($name, '/') || $this->skipped($name)) {
                continue;
            }

            $scanned++;
            $files[$this->stripRoot($name)] = null;
        }

        foreach (['composer.json', 'package.json', 'requirements.txt', 'pyproject.toml', 'go.mod', 'Gemfile', 'Cargo.toml', 'Dockerfile', 'docker-compose.yml', 'compose.yml', '.env.example'] as $candidate) {
            foreach ($files as $path => $_) {
                if (basename($path) === $candidate || $path === $candidate) {
                    $content = $zip->getFromName($this->originalName($zip, $path));
                    if ($content !== false && strlen($content) <= self::MAX_READ) {
                        $files[$path] = $content;
                    }
                }
            }
        }

        $zip->close();

        return $this->detect($files, $scanned);
    }

    /**
     * @param  array<string, string|null>  $files
     * @return array<string, mixed>
     */
    public function detect(array $files, int $scanned = 0): array
    {
        $extensions = [];
        $names = array_keys($files);
        $lower = array_map('strtolower', $names);

        foreach ($names as $path) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if ($extension !== '' && $extension !== 'md') {
                $extensions[$extension] = ($extensions[$extension] ?? 0) + 1;
            }
        }

        arsort($extensions);
        $language = $this->languageFor((string) array_key_first($extensions));
        $framework = null;
        $install = null;
        $build = null;
        $start = null;
        $port = 3000;
        $env = [];

        $package = $this->json($files, 'package.json');
        $composer = $this->json($files, 'composer.json');
        $requirements = $this->text($files, 'requirements.txt').$this->text($files, 'pyproject.toml');

        if ($package !== null) {
            $language = 'Node.js';
            $deps = array_merge($package['dependencies'] ?? [], $package['devDependencies'] ?? []);
            $framework = match (true) {
                isset($deps['next']) => 'Next.js',
                isset($deps['nuxt']) => 'Nuxt',
                isset($deps['@sveltejs/kit']) => 'SvelteKit',
                isset($deps['astro']) => 'Astro',
                isset($deps['vue']) => 'Vue',
                isset($deps['react']) => 'React',
                isset($deps['express']) => 'Express',
                isset($deps['@nestjs/core']) => 'NestJS',
                isset($deps['hono']) => 'Hono',
                default => null,
            };
            $manager = match (true) {
                in_array('pnpm-lock.yaml', $names, true) => 'pnpm',
                in_array('yarn.lock', $names, true) => 'yarn',
                in_array('bun.lockb', $names, true) => 'bun',
                default => 'npm',
            };
            $install = $manager.' install';
            $build = isset($package['scripts']['build']) ? $manager.' run build' : null;
            $start = match ($manager) {
                'pnpm' => 'pnpm start',
                'yarn' => 'yarn start',
                'bun' => 'bun start',
                default => 'npm start',
            };
            $port = in_array($framework, ['Next.js', 'Nuxt', 'Astro', 'SvelteKit'], true) ? 3000 : (isset($deps['express']) ? 3000 : 8080);
            $env = ['NODE_ENV' => 'production'];
        } elseif ($composer !== null) {
            $language = 'PHP';
            $framework = isset($composer['require']['laravel/framework']) ? 'Laravel' : (isset($composer['require']['symfony/framework-bundle']) ? 'Symfony' : null);
            $install = 'composer install --no-dev --optimize-autoloader';
            $build = $framework === 'Laravel' ? 'php artisan migrate --force && php artisan config:cache' : null;
            $start = $framework === 'Laravel' ? 'php artisan serve --host=0.0.0.0 --port=8000' : 'php -S 0.0.0.0:8000 -t public';
            $port = 8000;
            $env = ['APP_ENV' => 'production', 'APP_DEBUG' => 'false'];
        } elseif ($requirements !== '') {
            $language = 'Python';
            $framework = match (true) {
                str_contains($requirements, 'django') => 'Django',
                str_contains($requirements, 'fastapi') => 'FastAPI',
                str_contains($requirements, 'flask') => 'Flask',
                default => null,
            };
            $install = 'pip install -r requirements.txt';
            $start = match ($framework) {
                'Django' => 'gunicorn project.wsgi:application --bind 0.0.0.0:8000',
                'FastAPI' => 'uvicorn main:app --host 0.0.0.0 --port 8000',
                default => 'python app.py',
            };
            $port = 8000;
        } elseif (in_array('go.mod', array_map('basename', $names), true) || in_array('go.mod', $names, true)) {
            $language = 'Go';
            $install = 'go mod download';
            $build = 'go build -o app .';
            $start = './app';
            $port = 8080;
        } elseif (in_array('Cargo.toml', array_map('basename', $names), true) || in_array('cargo.toml', $lower, true)) {
            $language = 'Rust';
            $install = 'cargo fetch';
            $build = 'cargo build --release';
            $start = './target/release/app';
            $port = 8080;
        } elseif (in_array('gemfile', array_map('strtolower', array_map('basename', $names)), true)) {
            $language = 'Ruby';
            $install = 'bundle install';
            $start = 'bundle exec rails server -b 0.0.0.0 -p 3000';
            $port = 3000;
        } elseif (in_array('index.html', array_map('basename', $names), true)) {
            $language = 'HTML';
            $start = 'static';
            $port = 80;
        }

        $hasCompose = in_array('docker-compose.yml', array_map('basename', $names), true) || in_array('compose.yml', array_map('basename', $names), true);
        $hasDockerfile = in_array('dockerfile', array_map('strtolower', array_map('basename', $names)), true);

        $buildPack = match (true) {
            $hasCompose => 'dockercompose',
            $hasDockerfile => 'dockerfile',
            $language === 'HTML' && ! $package => 'static',
            default => 'nixpacks',
        };

        $notes = [];
        if (in_array('pnpm-lock.yaml', $names, true)) {
            $notes[] = 'pnpm workspace detected; install uses pnpm.';
        }
        if (in_array('Dockerfile', array_map('basename', $names), true)) {
            $notes[] = 'A Dockerfile is present; the image is built from it.';
        }
        if (in_array('docker-compose.yml', array_map('basename', $names), true) || in_array('compose.yml', array_map('basename', $names), true)) {
            $notes[] = 'Compose file found; services and volumes come from it.';
        }
        if (in_array('.env.example', array_map('basename', $names), true)) {
            $notes[] = 'A .env.example is available; copy it and fill the secrets.';
        }
        if (in_array('.github/workflows', $names, true) || in_array('.github', array_map('basename', $names), true)) {
            $notes[] = 'CI workflows found; the same commands are a good deployment check.';
        }
        if (count(array_filter($names, fn ($name) => str_ends_with($name, '.test.js') || str_ends_with($name, '.spec.ts') || str_contains($name, 'tests/'))) > 0) {
            $notes[] = 'Tests are present.';
        }
        if (count($extensions) > 4) {
            $notes[] = 'Several languages are mixed; the top one decides the default build pack.';
        }
        if ($scanned >= self::MAX_ENTRIES) {
            $notes[] = 'Only the first '.self::MAX_ENTRIES.' files were read.';
        }

        return [
            'language' => $language,
            'framework' => $framework,
            'build_pack' => $buildPack,
            'port' => $port,
            'install' => $install,
            'build' => $build,
            'start' => $start,
            'env' => $env,
            'notes' => $notes,
            'files_scanned' => $scanned,
            'extensions' => array_slice($extensions, 0, 8, true),
            'confidence' => $this->confidence($package, $composer, $requirements, $hasDockerfile, $hasCompose),
            'dockerfile' => $hasDockerfile,
            'compose' => $hasCompose,
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string|null}|null
     */
    private function parseGithubUrl(string $url): ?array
    {
        if (preg_match('~github\.com/([^/]+)/([^/#?]+)(?:/tree/([^/#?]+))?~i', $url, $matches) !== 1) {
            return null;
        }

        return [$matches[1], preg_replace('/\.git$/', '', $matches[2]), $matches[3] ?? null];
    }

    private function skipped(string $name): bool
    {
        foreach (self::SKIP as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function stripRoot(string $name): string
    {
        $slash = strpos($name, '/');

        return $slash === false ? $name : substr($name, $slash + 1);
    }

    private function originalName(ZipArchive $zip, string $path): string
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (is_string($name) && str_ends_with($name, '/'.$path)) {
                return $name;
            }
        }

        return $path;
    }

    /**
     * @param  array<string, string|null>  $files
     * @return array<string, mixed>|null
     */
    private function json(array $files, string $name): ?array
    {
        foreach ($files as $path => $content) {
            if (basename($path) === $name && is_string($content)) {
                $decoded = json_decode($content, true);

                return is_array($decoded) ? $decoded : null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|null>  $files
     */
    private function text(array $files, string $name): string
    {
        foreach ($files as $path => $content) {
            if (basename($path) === $name && is_string($content)) {
                return strtolower($content);
            }
        }

        return '';
    }

    private function languageFor(string $extension): string
    {
        return match ($extension) {
            'php' => 'PHP',
            'js', 'mjs', 'cjs', 'jsx' => 'Node.js',
            'ts', 'tsx' => 'TypeScript',
            'py' => 'Python',
            'go' => 'Go',
            'rs' => 'Rust',
            'rb' => 'Ruby',
            'java' => 'Java',
            'kt' => 'Kotlin',
            'cs' => 'C#',
            'html', 'htm' => 'HTML',
            'vue' => 'Vue',
            'svelte' => 'Svelte',
            default => 'Unknown',
        };
    }

    private function confidence(?array $package, ?array $composer, string $requirements, bool $dockerfile, bool $compose): string
    {
        $signals = ($package ? 1 : 0) + ($composer ? 1 : 0) + ($requirements !== '' ? 1 : 0) + ($dockerfile ? 1 : 0) + ($compose ? 1 : 0);

        return $signals >= 2 ? 'high' : ($signals === 1 ? 'medium' : 'low');
    }
}
