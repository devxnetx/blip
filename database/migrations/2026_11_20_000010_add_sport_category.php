<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('categories')->where('slug', 'sport')->first();

        if ($existing === null) {
            DB::table('categories')->insert([
                'slug' => 'sport',
                'name' => 'Спорт',
                'position' => ((int) DB::table('categories')->max('position')) + 1,
                'listed' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        if (! $existing->listed) {
            DB::table('categories')->where('slug', 'sport')->update([
                'listed' => true,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('categories')->where('slug', 'sport')->delete();
    }
};
