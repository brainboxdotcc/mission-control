<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $vm_slot_id
 * @property string $token_hash
 * @property int|null $pid
 * @property string|null $overlay_path
 * @property Carbon|null $started_at
 * @property Carbon|null $last_activity_at
 * @property Carbon $hard_deadline_at
 * @property Carbon $idle_deadline_at
 * @property Carbon|null $ended_at
 * @property string|null $end_reason
 * @property VmSlot|BelongsTo<VmSlot,$this> $slot
 */
final class VmLease extends Model
{
    protected $table = 'vm_leases';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'vm_slot_id',
        'token_hash',
        'pid',
        'overlay_path',
        'started_at',
        'last_activity_at',
        'hard_deadline_at',
        'idle_deadline_at',
        'ended_at',
        'end_reason',
    ];

    /**
     * @var array<string,string>
     */
    protected $casts = [
        'started_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'hard_deadline_at' => 'datetime',
        'idle_deadline_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<VmSlot, $this>
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(VmSlot::class, 'vm_slot_id');
    }

    public function is_active(): bool
    {
        return $this->ended_at === null;
    }
}
