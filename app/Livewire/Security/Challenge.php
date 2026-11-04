<?php

namespace App\Livewire\Security;

use App\Services\Security\OtpService;
use Livewire\Component;

class Challenge extends Component
{
    public string $code = '';

    public string $message = '';

    public function mount(): void
    {
        if (! auth()->check() || ! auth()->user()->two_factor_enabled) {
            $this->redirectRoute('dashboard');

            return;
        }

        if (session('dashy_2fa') === auth()->id()) {
            $this->redirectRoute('dashboard');

            return;
        }

        $this->send();
    }

    public function send(): void
    {
        $sent = (new OtpService)->send(auth()->user(), 'login', request()->ip());
        $this->message = $sent
            ? 'A code was sent to '.(auth()->user()->two_factor_email ?: auth()->user()->email).'.'
            : 'Email is not configured. Ask an instance admin to set it up.';
    }

    public function verify(): void
    {
        $this->validate(['code' => ['required', 'digits:6']]);

        if (! (new OtpService)->verify(auth()->user(), $this->code)) {
            $this->addError('code', 'That code is wrong or has expired.');

            return;
        }

        session(['dashy_2fa' => auth()->id()]);
        $this->redirectIntended(route('dashboard'));
    }

    public function render()
    {
        return view('livewire.security.challenge');
    }
}
