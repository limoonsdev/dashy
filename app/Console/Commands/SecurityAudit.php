<?php

namespace App\Console\Commands;

use App\Models\EmailNotificationSettings;
use App\Models\InstanceSettings;
use Illuminate\Console\Command;

class SecurityAudit extends Command
{
    protected $signature = 'security:audit';

    protected $description = 'Check Dashy security settings, encrypted fields and malware scanning';

    public function handle(): int
    {
        $findings = [];
        $failures = 0;

        $this->check($findings, 'APP_KEY is set', filled(config('app.key')), true);
        $this->check($findings, 'Debug mode is off', config('app.debug') === false, true);
        $this->check($findings, 'APP_URL uses HTTPS', str_starts_with((string) config('app.url'), 'https://'), false);
        $this->check($findings, 'Session cookies are HTTP-only', config('session.http_only') === true, true);
        $this->check($findings, 'Session cookies are Secure', config('session.secure') === true, false);
        $this->check($findings, 'Session SameSite is lax or strict', in_array(config('session.same_site'), ['lax', 'strict'], true), false);
        $this->check($findings, 'Security headers are enabled', config('security.headers.enabled') === true, true);

        $cspMode = config('security.csp_report_only') ? 'report-only' : 'enforced';
        $findings[] = ['CSP mode', $cspMode, $cspMode === 'enforced' ? 'ok' : 'warn'];

        $clamav = config('security.clamav');
        $scannerState = empty($clamav['host']) ? 'disabled' : ($clamav['required'] ? 'required' : 'optional');
        $findings[] = ['Malware scanning', $scannerState, empty($clamav['host']) ? 'warn' : 'ok'];

        $this->auditEncryptedCasts($findings, InstanceSettings::class, ['smtp_password', 'resend_api_key', 'smtp_username']);
        $this->auditEncryptedCasts($findings, EmailNotificationSettings::class, ['smtp_password']);

        foreach ($findings as [$label, $value, $state]) {
            $prefix = match ($state) {
                'ok' => '<fg=green>ok</>  ',
                'warn' => '<fg=yellow>warn</>',
                default => '<fg=red>FAIL</>',
            };

            $this->line(sprintf('%s %-38s %s', $prefix, $label, $value));

            if ($state === 'fail') {
                $failures++;
            }
        }

        if ($failures > 0) {
            $this->newLine();
            $this->error("{$failures} critical check(s) failed.");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('No critical security findings.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string}>  $findings
     */
    private function check(array &$findings, string $label, bool $passed, bool $critical): void
    {
        $findings[] = [
            $label,
            $passed ? 'yes' : 'no',
            $passed ? 'ok' : ($critical ? 'fail' : 'warn'),
        ];
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string}>  $findings
     * @param  array<int, string>  $fields
     */
    private function auditEncryptedCasts(array &$findings, string $model, array $fields): void
    {
        if (! class_exists($model)) {
            return;
        }

        $instance = new $model;
        $casts = $instance->getCasts();
        $fillable = $instance->getFillable();

        // A field the model does not declare at all belongs to another model;
        // skip it instead of reporting a false failure.
        $fields = array_values(array_filter($fields, function (string $field) use ($casts, $fillable): bool {
            return array_key_exists($field, $casts) || in_array($field, $fillable, true);
        }));

        if ($fields === []) {
            return;
        }

        $plain = array_values(array_filter($fields, function (string $field) use ($casts): bool {
            return ! str_contains((string) ($casts[$field] ?? ''), 'encrypted');
        }));

        $findings[] = [
            class_basename($model).' secrets encrypted',
            $plain === [] ? 'all' : 'missing: '.implode(', ', $plain),
            $plain === [] ? 'ok' : 'fail',
        ];
    }
}
