<?php

namespace App\Livewire\Security;

use App\Models\BlockedIp;
use App\Services\Security\SuspiciousIpGuard;
use Livewire\Component;

class Ips extends Component
{
    public string $manualIp = '';

    public string $manualReason = 'Manually blocked';

    public ?string $error = null;

    public ?string $notice = null;

    public function approve(string $ip): void
    {
        (new SuspiciousIpGuard)->approve($ip);
        $this->notice = $ip.' is allowed again.';
    }

    public function block(): void
    {
        $this->validate(['manualIp' => ['required', 'ip']]);

        $entry = (new SuspiciousIpGuard)->strike($this->manualIp, $this->manualReason);
        $entry->forceFill(['hits' => 2, 'permanent' => true, 'blocked_until' => null])->save();

        $this->manualIp = '';
        $this->notice = 'Address blocked.';
    }

    public function remove(string $ip): void
    {
        BlockedIp::query()->where('ip', $ip)->delete();
        $this->notice = $ip.' removed from the list.';
    }

    public function render()
    {
        return view('livewire.security.ips', [
            'entries' => BlockedIp::query()->orderByDesc('updated_at')->limit(200)->get(),
        ]);
    }
}
