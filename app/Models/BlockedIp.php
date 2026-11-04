<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An IP that tripped the abuse detector. A first offense blocks for 24 hours;
 * a returning offender is blocked permanently until an admin approves it.
 */
class BlockedIp extends Model
{
    protected $fillable = ['ip', 'reason', 'hits', 'blocked_until', 'permanent', 'approved', 'last_seen_at'];

    protected $casts = [
        'blocked_until' => 'datetime',
        'last_seen_at' => 'datetime',
        'permanent' => 'boolean',
        'approved' => 'boolean',
    ];

    public function isBlocking(): bool
    {
        if ($this->approved) {
            return false;
        }

        return $this->permanent || ($this->blocked_until?->isFuture() ?? false);
    }
}
