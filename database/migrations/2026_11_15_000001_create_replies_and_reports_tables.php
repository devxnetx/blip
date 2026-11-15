<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pin_id')->constrained()->cascadeOnDelete();
            $table->string('device_hash', 64);
            $table->string('message', 200);
            $table->timestamps();

            $table->unique(['pin_id', 'device_hash']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pin_id')->constrained()->cascadeOnDelete();
            $table->string('device_hash', 64);
            $table->timestamps();

            $table->unique(['pin_id', 'device_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('replies');
    }
};
