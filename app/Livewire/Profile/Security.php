<?php

namespace App\Livewire\Profile;

use App\Services\Security\OtpService;
use Livewire\Component;

class Security extends Component
{
    public bool $twoFactorEnabled = false;

    public string $twoFactorEmail = '';

    public ?string $notice = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->twoFactorEnabled = (bool) auth()->user()->two_factor_enabled;
        $this->twoFactorEmail = (string) (auth()->user()->two_factor_email ?: auth()->user()->email);
    }

    public function save(): void
    {
        $this->validate(['twoFactorEmail' => ['required', 'email', 'max:255']]);

        auth()->user()->forceFill([
            'two_factor_enabled' => $this->twoFactorEnabled,
            'two_factor_email' => $this->twoFactorEmail,
        ])->save();

        if ($this->twoFactorEnabled) {
            $sent = (new OtpService)->send(auth()->user(), 'enable', request()->ip());
            $this->notice = $sent
                ? 'Two-factor is on. A confirmation code was sent.'
                : 'Two-factor is on, but email is not configured yet.';
        } else {
            $this->notice = 'Two-factor is off.';
            session()->forget('dashy_2fa');
        }
    }

    public function render()
    {
        return view('livewire.profile.security');
    }
}
