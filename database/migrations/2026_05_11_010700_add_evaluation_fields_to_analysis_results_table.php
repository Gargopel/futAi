<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_results', function (Blueprint $table) {
            $table->string('result_status')->nullable()->after('suggested_selection');
            $table->dateTime('evaluated_at')->nullable()->after('generated_at');
            $table->text('evaluation_reason')->nullable()->after('evaluated_at');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_results', function (Blueprint $table) {
            $table->dropColumn(['result_status', 'evaluated_at', 'evaluation_reason']);
        });
    }
};
