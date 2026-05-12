<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('football_matches')->cascadeOnDelete();
            $table->text('summary');
            $table->decimal('confidence', 5, 2)->nullable();
            $table->string('risk_level')->nullable();
            $table->string('suggested_market')->nullable();
            $table->string('suggested_selection')->nullable();
            $table->json('data')->nullable();
            $table->dateTime('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_results');
    }
};
