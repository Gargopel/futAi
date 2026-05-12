<?php

namespace App\Console\Commands;

use App\Services\Integrations\ApiFootballService;
use Illuminate\Console\Command;
use Throwable;

class ImportApiFootballFixturesCommand extends Command
{
    protected $signature = 'futia:import-api-football
        {--league= : ID da liga na API-Football}
        {--season= : Temporada, por exemplo 2025}
        {--date= : Data unica YYYY-MM-DD}
        {--from= : Inicio do periodo YYYY-MM-DD}
        {--to= : Fim do periodo YYYY-MM-DD}
        {--next= : Proximas N partidas}
        {--last= : Ultimas N partidas}
        {--timezone=America/Sao_Paulo : Timezone da consulta}';

    protected $description = 'Importa fixtures reais da API-Football para o banco local.';

    public function handle(ApiFootballService $apiFootball): int
    {
        $filters = [
            'league' => $this->option('league'),
            'season' => $this->option('season'),
            'date' => $this->option('date'),
            'from' => $this->option('from'),
            'to' => $this->option('to'),
            'next' => $this->option('next'),
            'last' => $this->option('last'),
            'timezone' => $this->option('timezone'),
        ];

        if (! $this->hasUsefulFilter($filters)) {
            $this->error('Informe pelo menos --league + --season, --date, --from/--to, --next ou --last.');

            return self::FAILURE;
        }

        try {
            $result = $apiFootball->importFixtures($filters);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $summary = $result['summary'];

        $this->info("Importacao concluida: {$summary['created']} criadas, {$summary['updated']} atualizadas, {$summary['skipped']} puladas de {$summary['total_rows']} fixtures.");

        return self::SUCCESS;
    }

    private function hasUsefulFilter(array $filters): bool
    {
        return ($filters['league'] && $filters['season'])
            || $filters['date']
            || ($filters['from'] && $filters['to'])
            || $filters['next']
            || $filters['last'];
    }
}
