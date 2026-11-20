<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 40);
            $table->unsignedSmallInteger('position');
            $table->boolean('listed')->default(true);
            $table->timestamps();
        });

        $now = now();

        foreach ([
            ['seeking', 'Търся', 1],
            ['lost', 'Загубено', 2],
            ['warning', 'Внимание', 3],
            ['free', 'Безплатно', 4],
            ['other', 'Друго', 5],
        ] as [$slug, $name, $position]) {
            DB::table('categories')->insert([
                'slug' => $slug,
                'name' => $name,
                'position' => $position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('pins', function (Blueprint $table) {
            $table->string('category', 40)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pins', function (Blueprint $table) {
            $table->string('category', 16)->nullable()->change();
        });

        Schema::dropIfExists('categories');
    }
};
