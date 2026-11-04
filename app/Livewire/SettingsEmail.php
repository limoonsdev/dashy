<?php

namespace App\Livewire;

use App\Models\InstanceSettings;
use App\Models\Team;
use App\Notifications\TransactionalEmails\Test;
use App\Rules\ValidHostname;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class SettingsEmail extends Component
{
    use AuthorizesRequests;

    public InstanceSettings $settings;

    #[Locked]
    public Team $team;

    #[Validate(['boolean'])]
    public bool $smtpEnabled = false;

    #[Validate(['nullable', 'email'])]
    public ?string $smtpFromAddress = null;

    #[Validate(['nullable', 'string'])]
    public ?string $smtpFromName = null;

    #[Validate(['nullable', 'email'])]
    public ?string $notificationFromAddress = null;

    #[Validate(['nullable', 'string', 'max:255'])]
    public ?string $notificationFromName = null;

    #[Validate(['nullable', 'string'])]
    public ?string $smtpRecipients = null;

    #[Validate(['nullable', 'string'])]
    public ?string $smtpHost = null;

    #[Validate(['nullable', 'numeric', 'min:1', 'max:65535'])]
    public ?string $smtpPort = null;

    #[Validate(['nullable', 'string', 'in:starttls,tls,none'])]
    public ?string $smtpEncryption = 'starttls';

    #[Validate(['nullable', 'string'])]
    public ?string $smtpUsername = null;

    #[Validate(['nullable', 'string'])]
    public ?string $smtpPassword = null;

    #[Validate(['nullable', 'numeric'])]
    public ?string $smtpTimeout = null;

    #[Validate(['nullable', 'string'])]
    public ?string $smtpEhloDomain = null;

    #[Validate(['boolean'])]
    public bool $resendEnabled = false;

    #[Validate(['nullable', 'string'])]
    public ?string $resendApiKey = null;

    #[Validate(['nullable', 'email'])]
    public ?string $testEmailAddress = null;

    public bool $testUsesNotificationSender = false;

    /**
     * Common transactional mail providers. Filling host, port and encryption
     * from a preset saves the six lookups a first mail setup normally needs.
     * Credentials are never preset: the user still supplies username/password.
     *
     * @var array<string, array{label: string, host: string, port: int, encryption: string, hint: string}>
     */
    public const SMTP_PRESETS = [
        'gmail' => [
            'label' => 'Gmail',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'starttls',
            'hint' => 'Use a Google App Password, not your normal account password.',
        ],
        'microsoft' => [
            'label' => 'Microsoft 365',
            'host' => 'smtp.office365.com',
            'port' => 587,
            'encryption' => 'starttls',
            'hint' => 'The account must have SMTP AUTH enabled in Microsoft 365.',
        ],
        'infomaniak' => [
            'label' => 'Infomaniak',
            'host' => 'mail.infomaniak.com',
            'port' => 587,
            'encryption' => 'starttls',
            'hint' => 'Use the full email address as the username.',
        ],
        'ovh' => [
            'label' => 'OVH',
            'host' => 'ssl0.ovh.net',
            'port' => 587,
            'encryption' => 'starttls',
            'hint' => 'Use the full email address as the username.',
        ],
        'brevo' => [
            'label' => 'Brevo',
            'host' => 'smtp-relay.brevo.com',
            'port' => 587,
            'encryption' => 'starttls',
            'hint' => 'The username is your Brevo login, not the sender address.',
        ],
        'mailgun' => [
            'label' => 'Mailgun',
            'host' => 'smtp.mailgun.org',
            'port' => 587,
            'encryption' => 'starttls',
            'hint' => 'The username is the full SMTP login, such as postmaster@mg.example.com.',
        ],
        'postmark' => [
            'label' => 'Postmark',
            'host' => 'smtp.postmarkapp.com',
            'port' => 587,
            'encryption' => 'starttls',
            'hint' => 'Use the Server API token as both the username and the password.',
        ],
        'resend' => [
            'label' => 'Resend SMTP',
            'host' => 'smtp.resend.com',
            'port' => 587,
            'encryption' => 'starttls',
            'hint' => 'The username is resend and the password is your API key.',
        ],
        'scaleway' => [
            'label' => 'Scaleway',
            'host' => 'smtp.tem.scaleway.com',
            'port' => 587,
            'encryption' => 'starttls',
            'hint' => 'Generate SMTP credentials in the Scaleway Transactional Email console.',
        ],
        'fastmail' => [
            'label' => 'Fastmail',
            'host' => 'smtp.fastmail.com',
            'port' => 587,
            'encryption' => 'starttls',
            'hint' => 'Use an app-specific password created in Fastmail settings.',
        ],
    ];

    public function mount()
    {
        if (isInstanceAdmin() === false) {
            return redirect()->route('dashboard');
        }
        $this->settings = instanceSettings();
        $this->syncData();
        $this->team = auth()->user()->currentTeam();
        $this->testEmailAddress = auth()->user()->email;
    }

    private function syncData(bool $toModel = false): void
    {
        if ($toModel) {
            $this->validate();
            $this->validate(['smtpEhloDomain' => ['nullable', 'string', new ValidHostname]]);
            $this->settings->smtp_enabled = $this->smtpEnabled;
            $this->settings->smtp_host = $this->smtpHost;
            $this->settings->smtp_port = $this->smtpPort;
            $this->settings->smtp_encryption = $this->smtpEncryption;
            $this->settings->smtp_username = $this->smtpUsername;
            $this->settings->smtp_password = $this->smtpPassword;
            $this->settings->smtp_timeout = $this->smtpTimeout;
            $this->settings->smtp_ehlo_domain = $this->smtpEhloDomain;
            $this->settings->smtp_from_address = $this->smtpFromAddress;
            $this->settings->smtp_from_name = $this->smtpFromName;
            $this->settings->notification_from_address = $this->notificationFromAddress;
            $this->settings->notification_from_name = $this->notificationFromName;

            $this->settings->resend_enabled = $this->resendEnabled;
            $this->settings->resend_api_key = $this->resendApiKey;
            $this->saveAndAudit();
        } else {
            $this->smtpEnabled = $this->settings->smtp_enabled;
            $this->smtpHost = $this->settings->smtp_host;
            $this->smtpPort = $this->settings->smtp_port;
            $this->smtpEncryption = $this->settings->smtp_encryption;
            $this->smtpUsername = $this->settings->smtp_username;
            $this->smtpPassword = $this->settings->smtp_password;
            $this->smtpTimeout = $this->settings->smtp_timeout;
            $this->smtpEhloDomain = $this->settings->smtp_ehlo_domain;
            $this->smtpFromAddress = $this->settings->smtp_from_address;
            $this->smtpFromName = $this->settings->smtp_from_name;
            $this->notificationFromAddress = $this->settings->notification_from_address;
            $this->notificationFromName = $this->settings->notification_from_name;

            $this->resendEnabled = $this->settings->resend_enabled;
            $this->resendApiKey = $this->settings->resend_api_key;
        }
    }

    public function submit()
    {
        try {
            $this->authorize('update', $this->settings);
            $this->resetErrorBag();
            $this->syncData(true);
            $this->dispatch('success', 'Transactional email settings updated.');
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }

    public function instantSave(string $type)
    {
        try {
            $this->authorize('update', $this->settings);
            $currentSmtpEnabled = $this->settings->smtp_enabled;
            $currentResendEnabled = $this->settings->resend_enabled;
            $this->resetErrorBag();

            if ($type === 'SMTP') {
                $this->submitSmtp();
                $this->resendEnabled = $this->settings->resend_enabled = false;
            } elseif ($type === 'Resend') {
                $this->submitResend();
                $this->smtpEnabled = $this->settings->smtp_enabled = false;
            }
            $this->saveAndAudit();

        } catch (\Throwable $e) {
            if ($type === 'SMTP') {
                $this->smtpEnabled = $currentSmtpEnabled;
            } elseif ($type === 'Resend') {
                $this->resendEnabled = $currentResendEnabled;
            }

            return handleError($e, $this);
        }
    }

    public function instantSaveSmtp(): void
    {
        $this->instantSave('SMTP');
    }

    /**
     * Fill the connection fields from a provider preset. It never overwrites
     * the credentials the user already typed, and it does not save or enable
     * SMTP: the user reviews the values and submits.
     */
    public function applySmtpPreset(string $preset): void
    {
        try {
            $this->authorize('update', $this->settings);
            $config = self::SMTP_PRESETS[$preset] ?? null;

            if ($config === null) {
                return;
            }

            $this->resetErrorBag();
            $this->smtpHost = $config['host'];
            $this->smtpPort = (string) $config['port'];
            $this->smtpEncryption = $config['encryption'];

            $this->dispatch('success', $config['label'].' connection details filled in. Add your username and password, then save.');
        } catch (\Throwable $e) {
            handleError($e, $this);
        }
    }

    public function instantSaveResend(): void
    {
        $this->instantSave('Resend');
    }

    public function toggleSmtp()
    {
        try {
            $this->authorize('update', $this->settings);
            $this->resetErrorBag();

            if ($this->smtpEnabled) {
                $this->smtpEnabled = false;
                $this->syncData(true);
                $this->dispatch('success', 'SMTP settings updated.');
            } else {
                $this->validateSmtpSettings();
                $this->smtpEnabled = true;
                $this->resendEnabled = false;
                $this->submitSmtp();
            }
        } catch (\Throwable $e) {
            $this->syncData();

            return handleError($e, $this);
        }
    }

    public function toggleResend()
    {
        try {
            $this->authorize('update', $this->settings);
            $this->resetErrorBag();

            if ($this->resendEnabled) {
                $this->resendEnabled = false;
                $this->syncData(true);
                $this->dispatch('success', 'Resend settings updated.');
            } else {
                $this->validateResendSettings();
                $this->resendEnabled = true;
                $this->smtpEnabled = false;
                $this->submitResend();
            }
        } catch (\Throwable $e) {
            $this->syncData();

            return handleError($e, $this);
        }
    }

    public function submitSmtp()
    {
        try {
            $this->authorize('update', $this->settings);
            $this->validateSmtpSettings();

            if ($this->smtpEnabled) {
                $this->settings->resend_enabled = $this->resendEnabled = false;
            }

            $this->settings->smtp_enabled = $this->smtpEnabled;
            $this->settings->smtp_host = $this->smtpHost;
            $this->settings->smtp_port = $this->smtpPort;
            $this->settings->smtp_encryption = $this->smtpEncryption;
            $this->settings->smtp_username = $this->smtpUsername;
            $this->settings->smtp_password = $this->smtpPassword;
            $this->settings->smtp_timeout = $this->smtpTimeout;
            $this->settings->smtp_ehlo_domain = $this->smtpEhloDomain;
            $this->settings->smtp_from_address = $this->smtpFromAddress;
            $this->settings->smtp_from_name = $this->smtpFromName;

            $this->saveAndAudit();

            $this->dispatch('success', 'SMTP settings updated.');
        } catch (\Throwable $e) {
            $this->smtpEnabled = false;

            return handleError($e, $this);
        }
    }

    public function submitResend()
    {
        try {
            $this->authorize('update', $this->settings);
            $this->validateResendSettings();

            if ($this->resendEnabled) {
                $this->settings->smtp_enabled = $this->smtpEnabled = false;
            }

            $this->settings->resend_enabled = $this->resendEnabled;
            $this->settings->resend_api_key = $this->resendApiKey;
            $this->settings->smtp_from_address = $this->smtpFromAddress;
            $this->settings->smtp_from_name = $this->smtpFromName;

            $this->saveAndAudit();

            $this->dispatch('success', 'Resend settings updated.');
        } catch (\Throwable $e) {
            $this->resendEnabled = false;

            return handleError($e, $this);
        }
    }

    /**
     * Save the instance email settings and record the changed field names (never their values).
     */
    private function saveAndAudit(): void
    {
        $changedFields = auditChangedFields($this->settings);
        $this->settings->save();

        if ($changedFields === []) {
            return;
        }

        auditLog('ui.settings.email.updated', [
            'team_id' => null,
            'resource' => 'instance',
            'section' => 'email',
            'changed_fields' => $changedFields,
        ]);
    }

    private function validateSmtpSettings(): void
    {
        $this->validate([
            'smtpEnabled' => 'boolean',
            'smtpFromAddress' => 'required|email',
            'smtpFromName' => 'required|string',
            'smtpHost' => 'required|string',
            'smtpPort' => 'required|numeric',
            'smtpEncryption' => 'required|string|in:starttls,tls,none',
            'smtpUsername' => 'nullable|string',
            'smtpPassword' => 'nullable|string',
            'smtpTimeout' => 'nullable|numeric',
            'smtpEhloDomain' => ['nullable', 'string', new ValidHostname],
        ], [
            'smtpFromAddress.required' => 'From Address is required.',
            'smtpFromAddress.email' => 'Please enter a valid email address.',
            'smtpFromName.required' => 'From Name is required.',
            'smtpHost.required' => 'SMTP Host is required.',
            'smtpPort.required' => 'SMTP Port is required.',
            'smtpPort.numeric' => 'SMTP Port must be a number.',
            'smtpEncryption.required' => 'Encryption type is required.',
        ]);
    }

    private function validateResendSettings(): void
    {
        $this->validate([
            'resendEnabled' => 'boolean',
            'resendApiKey' => $this->resendEnabled ? 'required|string' : 'nullable|string',
            'smtpFromAddress' => 'required|email',
            'smtpFromName' => 'required|string',
        ], [
            'resendApiKey.required' => 'Resend API Key is required.',
            'smtpFromAddress.required' => 'From Address is required.',
            'smtpFromAddress.email' => 'Please enter a valid email address.',
            'smtpFromName.required' => 'From Name is required.',
        ]);
    }

    public function sendTestEmail()
    {
        try {
            $this->authorize('update', $this->settings);
            $this->validate([
                'testEmailAddress' => 'required|email',
                'smtpFromAddress' => 'required|email',
                'smtpFromName' => 'required|string',
                'notificationFromAddress' => $this->testUsesNotificationSender ? 'required|email' : 'nullable|email',
                'notificationFromName' => 'nullable|string|max:255',
            ], [
                'notificationFromAddress.required' => 'Notification from address is required to test the notification sender.',
                'testEmailAddress.required' => 'Test email address is required.',
                'testEmailAddress.email' => 'Please enter a valid email address.',
                'smtpFromAddress.required' => 'From Address is required.',
                'smtpFromAddress.email' => 'Please enter a valid email address.',
                'smtpFromName.required' => 'From Name is required.',
            ]);

            $this->settings->smtp_from_address = $this->smtpFromAddress;
            $this->settings->smtp_from_name = $this->smtpFromName;
            $this->settings->notification_from_address = $this->notificationFromAddress;
            $this->settings->notification_from_name = $this->notificationFromName;
            $this->saveAndAudit();

            $executed = RateLimiter::attempt(
                'test-email:'.$this->team->id,
                $perMinute = 0,
                function () {
                    $this->team?->notifyNow(new Test(
                        $this->testEmailAddress,
                        usesNotificationSender: $this->testUsesNotificationSender,
                    ));
                    $this->dispatch('success', 'Test Email sent.');
                },
                $decaySeconds = 10,
            );

            if (! $executed) {
                throw new \Exception('Too many messages sent!');
            }
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }
}
