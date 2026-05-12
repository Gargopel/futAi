<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('football_matches')->cascadeOnDelete();
            $table->string('market');
            $table->string('selection');
            $table->decimal('confidence', 5, 2);
            $table->string('risk_level')->default('medium');
            $table->string('status')->default('pending');
            $table->text('reasoning')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
