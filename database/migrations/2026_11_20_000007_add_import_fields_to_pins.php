<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pins', function (Blueprint $table) {
            $table->string('source', 40)->nullable();
            $table->string('external_id', 40)->nullable();
            $table->boolean('placed_manually')->default(false);
            $table->timestamp('suppressed_at')->nullable();
            $table->unique(['source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pins', function (Blueprint $table) {
            $table->dropUnique(['source', 'external_id']);
            $table->dropColumn(['source', 'external_id', 'placed_manually', 'suppressed_at']);
        });
    }
};
