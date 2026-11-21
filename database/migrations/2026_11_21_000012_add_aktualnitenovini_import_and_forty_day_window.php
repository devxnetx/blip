<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('settings')->updateOrInsert(
            ['key' => 'import_days_ahead'],
            ['value' => '40', 'created_at' => $now, 'updated_at' => $now],
        );

        if (! DB::table('settings')->where('key', 'import_aktualnitenovini_enabled')->exists()) {
            DB::table('settings')->insert([
                'key' => 'import_aktualnitenovini_enabled',
                'value' => '1',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'import_aktualnitenovini_enabled')->delete();

        DB::table('settings')
            ->where('key', 'import_days_ahead')
            ->where('value', '40')
            ->update(['value' => '14', 'updated_at' => now()]);
    }
};
