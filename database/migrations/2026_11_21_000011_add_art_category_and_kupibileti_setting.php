<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        if (! DB::table('categories')->where('slug', 'art')->exists()) {
            DB::table('categories')->insert([
                'slug' => 'art',
                'name' => 'Арт',
                'position' => ((int) DB::table('categories')->max('position')) + 1,
                'listed' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('settings')->where('key', 'import_kupibileti_enabled')->exists()) {
            DB::table('settings')->insert([
                'key' => 'import_kupibileti_enabled',
                'value' => '1',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('categories')->where('slug', 'art')->delete();
        DB::table('settings')->where('key', 'import_kupibileti_enabled')->delete();
    }
};
