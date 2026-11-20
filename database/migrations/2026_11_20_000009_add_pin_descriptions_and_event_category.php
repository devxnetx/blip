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
            $table->text('description')->nullable()->after('message');
        });

        if (! DB::table('categories')->where('slug', 'event')->exists()) {
            DB::table('categories')->insert([
                'slug' => 'event',
                'name' => 'Събитие',
                'position' => ((int) DB::table('categories')->max('position')) + 1,
                'listed' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('categories')->where('slug', 'event')->delete();

        Schema::table('pins', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
