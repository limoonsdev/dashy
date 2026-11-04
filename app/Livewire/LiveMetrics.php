<?php

namespace App\Livewire;

use App\Models\Server;
use Livewire\Component;

/**
 * Polls a handful of read-only commands on every reachable server and keeps a
 * short in-memory history for the sparklines.
 */
class LiveMetrics extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    /** @var array<string, array<int, float>> */
    public array $history = [];

    public string $error = '';

    public function mount(): void
    {
        $this->poll();
    }

    public function poll(): void
    {
        $this->error = '';
        $rows = [];

        foreach (Server::query()->where('team_id', currentTeam()->id)->get() as $server) {
            $rows[] = $this->read($server);
        }

        $this->rows = $rows;

        foreach ($rows as $row) {
            $key = (string) $row['uuid'];
            $history = $this->history[$key] ?? [];
            $history[] = (float) $row['cpu'];
            $this->history[$key] = array_slice($history, -30);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function read(Server $server): array
    {
        $command = <<<'SH'
awk '{print "load:"$1}' /proc/loadavg 2>/dev/null
nproc 2>/dev/null | sed 's/^/cores:/'
awk '/Mem:/{print "mem:"$3" "$2}' /proc/meminfo 2>/dev/null
df -P / 2>/dev/null | awk 'NR==2{print "disk:"$5}'
awk '/^cpu /{idle=$5; total=0; for(i=2;i<=11;i++) total+=$i; print "cpu:"(100-idle*100/total)}' /proc/stat 2>/dev/null
uptime -p 2>/dev/null | sed 's/^/uptime:/'
SH;

        try {
            $raw = (string) instant_remote_process([$command], $server, false, false, 15);
        } catch (\Throwable $e) {
            return [
                'uuid' => $server->uuid, 'name' => $server->name, 'reachable' => false,
                'cpu' => 0, 'memory' => 0, 'disk' => 0, 'cores' => 0, 'load' => 0, 'uptime' => 'unknown',
            ];
        }

        $data = ['reachable' => trim($raw) !== ''];
        foreach (preg_split('/\r?\n/', $raw) as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $data[trim($key)] = trim($value);
            }
        }

        $cores = max(1, (int) ($data['cores'] ?? 1));
        $load = (float) ($data['load'] ?? 0);
        $memory = array_map('intval', explode(' ', (string) ($data['mem'] ?? '0 1')));

        return [
            'uuid' => $server->uuid,
            'name' => $server->name,
            'reachable' => (bool) $data['reachable'],
            'cpu' => round((float) ($data['cpu'] ?? min(100, $load / $cores * 100)), 1),
            'memory' => $memory[1] > 0 ? round($memory[0] / $memory[1] * 100, 1) : 0,
            'disk' => (int) rtrim((string) ($data['disk'] ?? '0'), '%'),
            'cores' => $cores,
            'load' => round($load, 2),
            'uptime' => (string) ($data['uptime'] ?? 'unknown'),
        ];
    }

    public function render()
    {
        return view('livewire.live-metrics');
    }
}
