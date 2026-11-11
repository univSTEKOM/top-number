<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'contact_name',
    'phone',
    'normalized_phone',
    'period_key',
    'dedup_week',
    'team_id',
    'marketing_channel',
    'sub_leader_id',
    'input_by',
    'leader_id',
    'status',
    'is_contacted',
    'status_updated_by',
    'status_updated_at',
    'contacted_at',
    'contacted_by_leader_id',
])]
class Contact extends Model
{
    public const STATUS_UNCONTACTED = 'belum_dihubungi';

    public const STATUS_CONTACTED = 'sudah_dihubungi';

    public static function activePeriodKey(): string
    {
        return now()->format('Y-m');
    }

    /**
     * ISO-8601 week key (e.g. "2026-W30") used to scope duplicate-number
     * checks. Kept separate from period_key, which stays monthly for
     * reporting and archival.
     */
    public static function activeDedupWeekKey(): string
    {
        return now()->format('o-\WW');
    }

    public static function statusFromInput(string $value): string
    {
        return $value === 'contacted'
            ? self::STATUS_CONTACTED
            : self::STATUS_UNCONTACTED;
    }

    public function isContacted(): bool
    {
        if (array_key_exists('is_contacted', $this->attributes)) {
            return (bool) $this->is_contacted;
        }

        return $this->status === self::STATUS_CONTACTED;
    }

    public function statusLabel(): string
    {
        return $this->isContacted() ? 'Sudah Dihubungi' : 'Belum Dihubungi';
    }

    public function applyStatus(User $user, string $status): void
    {
        $this->update([
            'status'           => $status,
            'status_updated_by' => $user->id,
            'status_updated_at' => now(),
        ]);
    }

    protected function casts(): array
    {
        return [
            'contacted_at'     => 'datetime',
            'status_updated_at' => 'datetime',
            'is_contacted'     => 'boolean',
        ];
    }

    /**
     * Update the Toploker-specific (legacy) contacted fields on this contact.
     * Used when a Toploker leader marks a contact.
     */
    public function setIsContacted(User $user, bool $isContacted): void
    {
        $this->update([
            'is_contacted'          => $isContacted,
            'status'                => $isContacted ? self::STATUS_CONTACTED : self::STATUS_UNCONTACTED,
            'status_updated_by'     => $user->id,
            'status_updated_at'     => now(),
            'contacted_at'          => $isContacted ? now() : null,
            'contacted_by_leader_id' => $isContacted ? $user->id : null,
        ]);

        // Also write to channel history for consistency
        ContactChannelHistory::setContactedForChannel($this, $user, $isContacted, User::MARKETING_CHANNEL_TOPLOKER);
    }

    /**
     * Mark this contact as contacted/uncontacted for a specific marketing channel.
     * For Toploker: also updates the legacy is_contacted field.
     * For Topmatch/KerjaMalam: only writes to contact_channel_histories.
     */
    public function setChannelContacted(User $user, bool $isContacted): void
    {
        $channel = $user->marketing_channel ?? User::MARKETING_CHANNEL_TOPLOKER;

        if ($channel === User::MARKETING_CHANNEL_TOPLOKER) {
            $this->setIsContacted($user, $isContacted);

            return;
        }

        // For special channels: only track in contact_channel_histories
        ContactChannelHistory::setContactedForChannel($this, $user, $isContacted, $channel);
    }

    public function getWhatsappPhoneAttribute(): string
    {
        if ($this->normalized_phone) {
            return $this->normalized_phone;
        }

        $phone = preg_replace('/\D+/', '', (string) $this->phone) ?? '';

        if (str_starts_with($phone, '0')) {
            return '62'.substr($phone, 1);
        }

        return $phone;
    }

    public function getWhatsappUrlAttribute(): string
    {
        return 'https://wa.me/'.$this->whatsapp_phone;
    }

    public function statusUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_updated_by');
    }

    public function inputBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function subLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sub_leader_id');
    }


    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }


    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * All channel history records for this contact.
     */
    public function contactChannelHistories(): HasMany
    {
        return $this->hasMany(ContactChannelHistory::class);
    }
}
