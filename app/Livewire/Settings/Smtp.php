<?php

namespace App\Livewire\Settings;

use App\Models\SmtpSetting;
use App\Services\Security\OtpService;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class Smtp extends Component
{
    public bool $enabled = false;

    public string $host = '127.0.0.1';

    public int $port = 1025;

    public string $username = '';

    public string $password = '';

    public string $encryption = 'none';

    public string $fromAddress = 'dashy@localhost';

    public string $fromName = 'Dashy';

    public ?string $notice = null;

    public ?string $error = null;

    public function mount(): void
    {
        if (! isInstanceAdmin() && ! auth()->user()?->isAdminOfTeam(currentTeam()->id)) {
            abort(403);
        }

        $settings = SmtpSetting::forTeam(currentTeam()->id);
        $this->enabled = $settings->enabled;
        $this->host = $settings->host;
        $this->port = $settings->port;
        $this->username = (string) $settings->username;
        $this->password = (string) $settings->password;
        $this->encryption = $settings->encryption;
        $this->fromAddress = $settings->from_address;
        $this->fromName = $settings->from_name;
    }

    public function save(): void
    {
        $this->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', 'in:none,tls,ssl'],
            'fromAddress' => ['required', 'email', 'max:255'],
            'fromName' => ['required', 'string', 'max:120'],
        ]);

        $settings = SmtpSetting::forTeam(currentTeam()->id);
        $settings->fill([
            'enabled' => $this->enabled,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'encryption' => $this->encryption,
            'from_address' => $this->fromAddress,
            'from_name' => $this->fromName,
        ]);
        $settings->password = $this->password;
        $settings->save();

        $this->notice = 'Mail settings saved.';
    }

    public function sendTest(): void
    {
        $this->save();
        $this->error = $this->notice = null;

        try {
            $settings = SmtpSetting::forTeam(currentTeam()->id);
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $settings->host,
                'mail.mailers.smtp.port' => $settings->port,
                'mail.mailers.smtp.username' => $settings->username,
                'mail.mailers.smtp.password' => $settings->password,
                'mail.mailers.smtp.encryption' => $settings->encryption === 'none' ? null : $settings->encryption,
            ]);
            Mail::purge('smtp');
            Mail::raw('Dashy can reach your SMTP server.', function ($message) use ($settings) {
                $message->from($settings->from_address, $settings->from_name)
                    ->to($settings->from_address)
                    ->subject('Dashy SMTP test');
            });
            $this->notice = 'Test email sent to '.$settings->from_address.'.';
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function sendOtpTest(): void
    {
        $service = new OtpService;
        $this->notice = $service->send(auth()->user(), 'test', request()->ip())
            ? 'A verification code was emailed to you.'
            : 'Enable and save the SMTP settings first.';
    }

    public function render()
    {
        return view('livewire.settings.smtp');
    }
}
