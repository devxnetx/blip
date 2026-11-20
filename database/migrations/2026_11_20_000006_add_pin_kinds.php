<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pins', function (Blueprint $table) {
            $table->string('kind', 16)->default('normal');
            $table->timestamp('starts_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
        });

        Schema::table('pins', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->change();
            $table->decimal('longitude', 10, 7)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('pins')->whereNull('latitude')->orWhereNull('longitude')->delete();

        Schema::table('pins', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable(false)->change();
            $table->decimal('longitude', 10, 7)->nullable(false)->change();
            $table->dropColumn(['kind', 'starts_at', 'duration_minutes']);
        });
    }
};
