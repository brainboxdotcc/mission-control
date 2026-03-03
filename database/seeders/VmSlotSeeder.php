<?php

namespace Database\Seeders;

use App\Models\VmSlot;
use Illuminate\Database\Seeder;

final class VmSlotSeeder extends Seeder
{
    public function run(): void
    {
        // 3 fixed slots:
        // slot 1 -> display :1 -> ws 5701
        // slot 2 -> display :2 -> ws 5702
        // slot 3 -> display :3 -> ws 5703

        $slots = [
            ['slot_index' => 1, 'display' => 1, 'ws_port' => 5701, 'bind_host' => '127.0.0.1'],
            ['slot_index' => 2, 'display' => 2, 'ws_port' => 5702, 'bind_host' => '127.0.0.1'],
            ['slot_index' => 3, 'display' => 3, 'ws_port' => 5703, 'bind_host' => '127.0.0.1'],
        ];

        foreach ($slots as $data) {
            VmSlot::query()->updateOrCreate(
                ['slot_index' => $data['slot_index']],
                $data
            );
        }
    }
}
