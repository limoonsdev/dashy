<?php

namespace App\Services\Security;

use App\Models\OtpCode;
use App\Models\SmtpSetting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Emails one short-lived code. Any SMTP relay works (Mailpit, Postal, Brevo,
 * Gmail app password, your own Postfix) and the settings are stored encrypted.
 */
class OtpService
{
    public const TTL_MINUTES = 10;

    public function send(User $user, string $purpose = 'login', ?string $ip = null): bool
    {
        $settings = SmtpSetting::forTeam((int) $user->teams()->value('teams.id'));

        if (! $settings->enabled) {
            return false;
        }

        $code = (string) random_int(100000, 999999);

        OtpCode::query()->create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'ip' => $ip,
        ]);

        $this->applyMailer($settings);

        Mail::raw(
            "Your Dashy verification code is {$code}. It expires in ".self::TTL_MINUTES.' minutes.',
            function ($message) use ($user, $settings) {
                $message->from($settings->from_address, $settings->from_name)
                    ->to($user->two_factor_email ?: $user->email)
                    ->subject('Your Dashy verification code');
            }
        );

        return true;
    }

    public function verify(User $user, string $code): bool
    {
        $entry = OtpCode::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($entry === null || ! Hash::check($code, $entry->code_hash)) {
            return false;
        }

        $entry->forceFill(['used_at' => now()])->save();

        return true;
    }

    private function applyMailer(SmtpSetting $settings): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $settings->host,
            'mail.mailers.smtp.port' => $settings->port,
            'mail.mailers.smtp.username' => $settings->username,
            'mail.mailers.smtp.password' => $settings->password,
            'mail.mailers.smtp.encryption' => $settings->encryption === 'none' ? null : $settings->encryption,
        ]);

        Mail::purge('smtp');
    }
}
