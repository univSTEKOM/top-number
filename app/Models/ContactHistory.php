<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'contact_id',
    'contact_name',
    'phone',
    'normalized_phone',
    'period_key',
    'team_id',
    'marketing_channel',
    'sub_leader_id',
    'leader_id',
    'input_by',
    'status',
    'is_contacted',
    'status_updated_by',
    'status_updated_at',
    'contacted_at',
    'contacted_by_leader_id',
    'archive_period',
    'archived_at',
    'original_created_at',
])]
class ContactHistory extends Model
{
    protected function casts(): array
    {
        return [
            'contacted_at'       => 'datetime',
            'status_updated_at'  => 'datetime',
            'archived_at'        => 'datetime',
            'original_created_at' => 'datetime',
            'is_contacted'       => 'boolean',
        ];
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
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function inputBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function statusUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_updated_by');
    }

    public function statusLabel(): string
    {
        return $this->is_contacted ? 'Sudah Dihubungi' : 'Belum Dihubungi';
    }
}
