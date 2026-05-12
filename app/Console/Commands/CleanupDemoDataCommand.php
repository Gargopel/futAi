<?php

namespace App\Console\Commands;

use App\Models\AnalysisRule;
use App\Models\FootballMatch;
use App\Models\League;
use App\Models\Prediction;
use Illuminate\Console\Command;

class CleanupDemoDataCommand extends Command
{
    protected $signature = 'futia:cleanup-demo-data';

    protected $description = 'Remove dados demonstrativos dos seeders mantendo dados reais importados.';

    public function handle(): int
    {
        $demoLeagueIds = League::query()
            ->where(function ($query) {
                $query
                    ->where(fn ($query) => $query->where('name', 'Brasileirao Serie A')->where('season', '2026'))
                    ->orWhere(fn ($query) => $query->where('name', 'Copa do Brasil')->where('season', '2026'))
                    ->orWhere(fn ($query) => $query->where('name', 'Premier League')->where('season', '2026/2027'));
            })
            ->pluck('id');

        $demoMatches = FootballMatch::query()
            ->whereIn('league_id', $demoLeagueIds)
            ->count();

        $demoPredictions = Prediction::query()
            ->where('reasoning', 'like', '%demonstrativa%')
            ->delete();

        $demoRules = AnalysisRule::query()
            ->where('key', 'recent_form_v1')
            ->delete();

        $demoLeagues = League::query()
            ->whereIn('id', $demoLeagueIds)
            ->delete();

        $this->info("Dados de exemplo removidos: {$demoLeagues} ligas, {$demoMatches} partidas, {$demoPredictions} previsoes, {$demoRules} regras legadas.");

        return self::SUCCESS;
    }
}
