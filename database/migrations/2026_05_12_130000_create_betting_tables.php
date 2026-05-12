<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookmakers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('country')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bankrolls', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('initial_balance', 12, 2);
            $table->string('currency', 3)->default('BRL');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('bets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bankroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bookmaker_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('match_id')->nullable()->constrained('football_matches')->nullOnDelete();
            $table->dateTime('placed_at');
            $table->string('market');
            $table->string('selection');
            $table->decimal('stake', 12, 2);
            $table->decimal('odds', 8, 2);
            $table->string('status')->default('pending');
            $table->decimal('payout', 12, 2)->nullable();
            $table->dateTime('settled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $now = now();
        collect([
            'Betano',
            'Superbet',
            'bet365',
            'Sportingbet',
            'KTO',
            'Betnacional',
            'Pixbet',
            'EstrelaBet',
            'Novibet',
            'Betfair',
            'BetMGM',
            'Betsson',
            'Stake',
            'Rei do Pitaco',
            'SportyBet',
        ])->each(fn (string $name) => DB::table('bookmakers')->insert([
            'name' => $name,
            'country' => 'Brasil',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('bets');
        Schema::dropIfExists('bankrolls');
        Schema::dropIfExists('bookmakers');
    }
};
