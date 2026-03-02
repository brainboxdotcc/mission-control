<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vm_slots', function (Blueprint $table): void {
            $table->id();

            $table->unsignedInteger('slot_index')->unique(); // 0..2
            $table->unsignedInteger('display');              // VNC display number, e.g. 1..3
            $table->unsignedInteger('ws_port');              // 5701..5703
            $table->string('bind_host', 64)->default('127.0.0.1');

            $table->boolean('in_use')->default(false);
            $table->uuid('current_lease_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_slots');
    }
};
