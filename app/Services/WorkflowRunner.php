<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Server;
use App\Models\Workflow;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Runs the steps of a workflow in order and collects one result per step.
 *
 * Steps are plain arrays so a workflow can be edited as JSON in the UI:
 *   {"type":"command","server":"<uuid>","command":"docker ps"}
 *   {"type":"http","url":"https://...","method":"POST","body":"..."}
 *   {"type":"deploy","application":"<uuid>"}
 *   {"type":"delay","seconds":5}
 */
class WorkflowRunner
{
    use AsAction;

    /**
     * @return array{status: string, results: array<int, array{index: int, type: string, ok: bool, output: string}>}
     */
    public function handle(Workflow $workflow): array
    {
        $results = [];
        $status = 'success';

        foreach ($workflow->stepList() as $index => $step) {
            $type = (string) ($step['type'] ?? '');

            try {
                $output = match ($type) {
                    'command' => $this->command($workflow, $step),
                    'http' => $this->http($step),
                    'deploy' => $this->deploy($workflow, $step),
                    'delay' => $this->delay($step),
                    default => throw new \RuntimeException('Unknown step type ['.$type.'].'),
                };

                $results[] = ['index' => $index, 'type' => $type, 'ok' => true, 'output' => $output];
            } catch (\Throwable $e) {
                $status = 'failed';
                $results[] = ['index' => $index, 'type' => $type, 'ok' => false, 'output' => $e->getMessage()];

                break;
            }
        }

        $workflow->forceFill([
            'last_run_at' => now(),
            'last_status' => $status,
        ])->save();

        return ['status' => $status, 'results' => $results];
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function command(Workflow $workflow, array $step): string
    {
        $command = trim((string) ($step['command'] ?? ''));

        if ($command === '') {
            throw new \RuntimeException('The step has no command.');
        }

        if (strlen($command) > 4000) {
            throw new \RuntimeException('The command is too long.');
        }

        $server = Server::query()->where('team_id', $workflow->team_id)->where('uuid', (string) ($step['server'] ?? ''))->first();

        if ($server === null) {
            throw new \RuntimeException('The server of this step does not belong to the team.');
        }

        return Str::limit((string) instant_remote_process([$command], $server), 4000);
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function http(array $step): string
    {
        $url = (string) ($step['url'] ?? '');

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new \RuntimeException('The step URL is invalid.');
        }

        $method = strtoupper((string) ($step['method'] ?? 'GET'));

        if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new \RuntimeException('Unsupported HTTP method.');
        }

        $request = Http::timeout(30)->acceptJson();

        if (filled($step['body'] ?? null)) {
            $request = $request->withBody((string) $step['body'], 'application/json');
        }

        $response = $request->send($method, $url);

        return 'HTTP '.$response->status();
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function deploy(Workflow $workflow, array $step): string
    {
        $application = Application::query()->where('team_id', $workflow->team_id)->where('uuid', (string) ($step['application'] ?? ''))->first();

        if ($application === null) {
            throw new \RuntimeException('The application of this step does not belong to the team.');
        }

        $result = queue_application_deployment(
            application: $application,
            deployment_uuid: new_public_id(),
            no_questions_asked: true,
        );

        if (($result['status'] ?? 'queued') === 'skipped') {
            return 'Deployment skipped for '.$application->name.': '.($result['message'] ?? 'no change');
        }

        return 'Deployment queued for '.$application->name.'.';
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function delay(array $step): string
    {
        $seconds = max(0, min(120, (int) ($step['seconds'] ?? 0)));
        sleep($seconds);

        return 'Waited '.$seconds.'s.';
    }
}
