<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vm_leases', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->unsignedBigInteger('vm_slot_id');
            $table->string('token_hash', 64)->unique(); // sha256 hex

            $table->unsignedInteger('pid')->nullable();
            $table->string('overlay_path')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            $table->timestamp('hard_deadline_at');
            $table->timestamp('idle_deadline_at');

            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 32)->nullable();

            $table->timestamps();

            $table->foreign('vm_slot_id')->references('id')->on('vm_slots');
            $table->index(['vm_slot_id', 'ended_at']);
            $table->index(['hard_deadline_at', 'ended_at']);
            $table->index(['idle_deadline_at', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_leases');
    }
};
