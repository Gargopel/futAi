<?php

namespace App\Console\Commands;

use App\Models\AnalysisResult;
use App\Services\FootballAnalysis\AnalysisEvaluationService;
use Illuminate\Console\Command;

class EvaluateAnalysesCommand extends Command
{
    protected $signature = 'futia:evaluate-analyses {--all : Reavaliar todas as analises, inclusive ja avaliadas}';

    protected $description = 'Avalia analises persistidas com base nos placares finais cadastrados.';

    public function handle(AnalysisEvaluationService $evaluationService): int
    {
        $query = AnalysisResult::query()->with('match');

        if (! $this->option('all')) {
            $query->where(fn ($query) => $query
                ->whereNull('result_status')
                ->orWhereIn('result_status', ['pending', 'unknown']));
        }

        $counts = ['evaluated' => 0, 'won' => 0, 'lost' => 0, 'void' => 0, 'unknown' => 0, 'pending' => 0];

        $query->get()->each(function (AnalysisResult $analysisResult) use ($evaluationService, &$counts) {
            $evaluationService->evaluate($analysisResult);
            $status = $analysisResult->refresh()->result_status ?? 'pending';

            $counts['evaluated']++;
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        });

        $this->info("Analises processadas: {$counts['evaluated']}");
        $this->line("Ganhas: {$counts['won']}");
        $this->line("Perdidas: {$counts['lost']}");
        $this->line("Void: {$counts['void']}");
        $this->line("Unknown: {$counts['unknown']}");
        $this->line("Pendentes: {$counts['pending']}");

        return self::SUCCESS;
    }
}
