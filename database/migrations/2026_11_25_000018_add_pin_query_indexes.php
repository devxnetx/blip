<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pins', function (Blueprint $table) {
            $table->index(['latitude', 'longitude']);
            $table->index(['kind', 'starts_at']);
            $table->index('starts_at');
            $table->index(['device_hash', 'expires_at']);
            $table->index('created_at');
        });

        Schema::table('changelog_entries', function (Blueprint $table) {
            $table->index('published_at');
        });

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->index('read_at');
        });

        Schema::table('pin_imports', function (Blueprint $table) {
            $table->index(['source', 'successful']);
        });
    }

    public function down(): void
    {
        Schema::table('pins', function (Blueprint $table) {
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropIndex(['kind', 'starts_at']);
            $table->dropIndex(['starts_at']);
            $table->dropIndex(['device_hash', 'expires_at']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('changelog_entries', function (Blueprint $table) {
            $table->dropIndex(['published_at']);
        });

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropIndex(['read_at']);
        });

        Schema::table('pin_imports', function (Blueprint $table) {
            $table->dropIndex(['source', 'successful']);
        });
    }
};
