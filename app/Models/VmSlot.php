<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $slot_index
 * @property int $display
 * @property int $ws_port
 * @property string $bind_host
 * @property bool $in_use
 * @property string|null $current_lease_id
 * @method static int count()
 */
final class VmSlot extends Model
{
    protected $table = 'vm_slots';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slot_index',
        'display',
        'ws_port',
        'bind_host',
        'in_use',
        'current_lease_id',
    ];

    /**
     * @var array<string,string>
     */
    protected $casts = [
        'in_use' => 'bool',
    ];

    /**
     * @return HasMany<VmLease, $this>
     */
    public function leases(): HasMany
    {
        return $this->hasMany(VmLease::class, 'vm_slot_id');
    }
}
