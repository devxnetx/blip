<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * bcrypt hash for the two opening admin accounts. The plain password is not stored.
     */
    private const ADMIN_PASSWORD = '$2y$12$DGBZ/JkLIoNCptE7sJ9HpeXp5Arkkneq8RV1IEpz1yAGSZGeRO1h.';

    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_hash', 64)->unique();
            $table->string('label')->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('name');
        });

        $this->backfillDevices();
        $this->seedAdmins();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });

        Schema::dropIfExists('devices');
    }

    private function backfillDevices(): void
    {
        $hashes = collect()
            ->merge(DB::table('pins')->distinct()->pluck('device_hash'))
            ->merge(DB::table('replies')->distinct()->pluck('device_hash'))
            ->merge(DB::table('reports')->distinct()->pluck('device_hash'))
            ->filter(fn ($hash) => is_string($hash) && $hash !== '')
            ->unique()
            ->values();

        $now = now();

        foreach ($hashes as $hash) {
            $first = DB::table('pins')->where('device_hash', $hash)->min('created_at')
                ?? DB::table('replies')->where('device_hash', $hash)->min('created_at')
                ?? DB::table('reports')->where('device_hash', $hash)->min('created_at')
                ?? $now;
            $last = DB::table('pins')->where('device_hash', $hash)->max('created_at')
                ?? DB::table('replies')->where('device_hash', $hash)->max('created_at')
                ?? DB::table('reports')->where('device_hash', $hash)->max('created_at')
                ?? $first;

            DB::table('devices')->insert([
                'device_hash' => $hash,
                'first_seen_at' => $first,
                'last_seen_at' => $last,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedAdmins(): void
    {
        $now = now();

        foreach ([
            'ZenSorcerer' => 'zensorcerer@admins.kostaa',
            'devxnetx' => 'devxnetx@admins.kostaa',
        ] as $name => $email) {
            $exists = DB::table('users')->where('email', $email)->orWhere('name', $name)->exists();

            if ($exists) {
                continue;
            }

            DB::table('users')->insert([
                'name' => $name,
                'email' => $email,
                'password' => self::ADMIN_PASSWORD,
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
