<?php

namespace App\Services\Toolkit;

/**
 * Curated self-hosted tools that pair well with Dashy. Every entry is a single
 * docker run command, so it can be started on any server from the Tools page.
 */
class ToolCatalog
{
    /**
     * @return array<int, array<string, string>>
     */
    public static function all(): array
    {
        return [
            [
                'name' => 'Uptime Kuma',
                'category' => 'Monitoring',
                'description' => 'Uptime checks with status pages and alerts.',
                'url' => 'https://github.com/louislam/uptime-kuma',
                'run' => 'docker run -d --restart unless-stopped --name uptime-kuma -p 3001:3001 -v uptime_kuma:/app/data louislam/uptime-kuma:1',
            ],
            [
                'name' => 'Gitea',
                'category' => 'Source control',
                'description' => 'Lightweight Git hosting with issues and pull requests.',
                'url' => 'https://github.com/go-gitea/gitea',
                'run' => 'docker run -d --restart unless-stopped --name gitea -p 3002:3000 -p 2222:22 -v gitea_data:/data gitea/gitea:latest',
            ],
            [
                'name' => 'Vaultwarden',
                'category' => 'Security',
                'description' => 'Bitwarden-compatible password vault.',
                'url' => 'https://github.com/dani-garcia/vaultwarden',
                'run' => 'docker run -d --restart unless-stopped --name vaultwarden -p 8081:80 -e SIGNUPS_ALLOWED=false -v vw_data:/data vaultwarden/server:latest',
            ],
            [
                'name' => 'n8n',
                'category' => 'Automation',
                'description' => 'Visual workflow automation with 400+ integrations.',
                'url' => 'https://github.com/n8n-io/n8n',
                'run' => 'docker run -d --restart unless-stopped --name n8n -p 5678:5678 -v n8n_data:/home/node/.n8n docker.n8n.io/n8nio/n8n',
            ],
            [
                'name' => 'Grafana',
                'category' => 'Monitoring',
                'description' => 'Dashboards for metrics, logs and traces.',
                'url' => 'https://github.com/grafana/grafana',
                'run' => 'docker run -d --restart unless-stopped --name grafana -p 3003:3000 -v grafana_data:/var/lib/grafana grafana/grafana:latest',
            ],
            [
                'name' => 'Prometheus',
                'category' => 'Monitoring',
                'description' => 'Metrics scraping and time-series storage.',
                'url' => 'https://github.com/prometheus/prometheus',
                'run' => 'docker run -d --restart unless-stopped --name prometheus -p 9090:9090 -v prom_data:/prometheus prom/prometheus:latest',
            ],
            [
                'name' => 'MinIO',
                'category' => 'Storage',
                'description' => 'S3-compatible object storage.',
                'url' => 'https://github.com/minio/minio',
                'run' => 'docker run -d --restart unless-stopped --name minio -p 9000:9000 -p 9001:9001 -v minio_data:/data minio/minio:latest server /data --console-address ":9001"',
            ],
            [
                'name' => 'Docmost',
                'category' => 'Knowledge',
                'description' => 'Collaborative wiki and documentation, a self-hosted Notion.',
                'url' => 'https://github.com/docmost/docmost',
                'run' => 'docker run -d --restart unless-stopped --name docmost -p 3010:3000 -v docmost_data:/app/data docmost/docmost:latest',
            ],
            [
                'name' => 'Penpot',
                'category' => 'Design',
                'description' => 'Open-source design and prototyping for product teams.',
                'url' => 'https://github.com/penpot/penpot',
                'run' => 'docker run -d --restart unless-stopped --name penpot -p 3449:3449 -e PENPOT_PUBLIC_URI=http://localhost:3449 -e PENPOT_FLAGS=enable-registration penpotapp/frontend:latest',
            ],
            [
                'name' => 'Immich',
                'category' => 'Media',
                'description' => 'Photo and video library with mobile backup.',
                'url' => 'https://github.com/immich-app/immich',
                'run' => 'docker run -d --restart unless-stopped --name immich -p 2283:2283 -v immich_data:/data ghcr.io/immich-app/immich-server:release',
            ],
            [
                'name' => 'Plausible',
                'category' => 'Analytics',
                'description' => 'Privacy-friendly web analytics.',
                'url' => 'https://github.com/plausible/analytics',
                'run' => 'docker run -d --restart unless-stopped --name plausible -p 8000:8000 -e BASE_URL=http://localhost:8000 ghcr.io/plausible/community-edition:latest',
            ],
            [
                'name' => 'Beszel',
                'category' => 'Monitoring',
                'description' => 'Lightweight server monitoring with historical charts.',
                'url' => 'https://github.com/henrygd/beszel',
                'run' => 'docker run -d --restart unless-stopped --name beszel -p 8090:8090 -v beszel_data:/beszel_data henrygd/beszel:latest',
            ],
        ];
    }
}
