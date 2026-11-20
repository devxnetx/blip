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
            $table->string('source_url', 255)->nullable()->after('external_id');
        });

        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE pins MODIFY external_id VARCHAR(191) NULL');
        }

        Schema::create('pin_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source', 40);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('pins_count')->default(0);
            $table->unsignedInteger('unassigned_count')->default(0);
            $table->boolean('successful')->default(false);
            $table->string('error', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 40)->primary();
            $table->string('value', 40);
            $table->timestamps();
        });

        $now = now();

        foreach ([
            'import_sporten_enabled' => '1',
            'import_eventibg_enabled' => '1',
            'import_days_ahead' => '14',
        ] as $key => $value) {
            DB::table('settings')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('pin_imports');

        Schema::table('pins', function (Blueprint $table) {
            $table->dropColumn('source_url');
        });
    }
};
