<?php

namespace App\Services\Integrations;

use App\Models\FootballMatch;
use App\Models\ApiIntegration;
use App\Models\League;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ApiFootballService
{
    public function importFixtures(array $filters): array
    {
        $payload = $this->get('fixtures', $this->cleanFilters($filters));
        $fixtures = collect($payload['response'] ?? []);

        $summary = [
            'source' => 'api-football',
            'total_rows' => $fixtures->count(),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        $rows = DB::transaction(function () use ($fixtures, &$summary) {
            return $fixtures
                ->map(function (array $fixture) use (&$summary) {
                    $normalized = $this->normalizeFixture($fixture);

                    if ($normalized === null) {
                        $summary['skipped']++;

                        return [
                            'status' => 'skipped',
                            'action' => 'skip',
                            'errors' => ['Fixture sem dados suficientes para importar.'],
                        ];
                    }

                    $match = $this->persistFixture($normalized);
                    $wasRecentlyCreated = $match->wasRecentlyCreated;

                    $summary[$wasRecentlyCreated ? 'created' : 'updated']++;

                    return [
                        'status' => 'valid',
                        'action' => $wasRecentlyCreated ? 'created' : 'updated',
                        'match_id' => $match->id,
                        'data' => [
                            'date' => $match->starts_at?->toDateTimeString(),
                            'league' => $match->league->name,
                            'home_team' => $match->homeTeam->name,
                            'away_team' => $match->awayTeam->name,
                            'status' => $match->status,
                            'home_goals' => $match->home_goals,
                            'away_goals' => $match->away_goals,
                        ],
                    ];
                })
                ->values()
                ->all();
        });

        return [
            'summary' => $summary,
            'rows' => $rows,
        ];
    }

    public function get(string $endpoint, array $query = []): array
    {
        $key = (string) (ApiIntegration::query()->where('provider', 'api-football')->first()?->api_key ?: config('services.api_football.key'));

        if ($key === '') {
            throw new RuntimeException('Configure a chave da API-Football na tela Integracoes ou em API_FOOTBALL_KEY no .env antes de importar.');
        }

        $response = Http::baseUrl(rtrim((string) config('services.api_football.base_url'), '/'))
            ->acceptJson()
            ->withHeaders(['x-apisports-key' => $key])
            ->timeout(20)
            ->retry(2, 500)
            ->get(ltrim($endpoint, '/'), $query);

        if ($response->failed()) {
            throw new RuntimeException("API-Football respondeu HTTP {$response->status()}: {$response->body()}");
        }

        $payload = $response->json();
        $errors = $payload['errors'] ?? [];

        if ($errors !== [] && $errors !== null) {
            throw new RuntimeException('API-Football retornou erro: '.json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        return is_array($payload) ? $payload : [];
    }

    private function cleanFilters(array $filters): array
    {
        return collect($filters)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    private function normalizeFixture(array $item): ?array
    {
        $league = Arr::get($item, 'league');
        $fixture = Arr::get($item, 'fixture');
        $teams = Arr::get($item, 'teams');

        if (! is_array($league) || ! is_array($fixture) || ! is_array($teams)) {
            return null;
        }

        $startsAt = Arr::get($fixture, 'date');
        $homeName = Arr::get($teams, 'home.name');
        $awayName = Arr::get($teams, 'away.name');
        $leagueName = Arr::get($league, 'name');

        if (! $startsAt || ! $homeName || ! $awayName || ! $leagueName) {
            return null;
        }

        $status = $this->mapStatus((string) Arr::get($fixture, 'status.short', 'NS'));
        $homeGoals = Arr::get($item, 'goals.home');
        $awayGoals = Arr::get($item, 'goals.away');

        if ($status !== 'finished') {
            $homeGoals = null;
            $awayGoals = null;
        }

        return [
            'league' => [
                'name' => (string) $leagueName,
                'country' => Arr::get($league, 'country'),
                'season' => (string) Arr::get($league, 'season'),
            ],
            'home_team' => [
                'name' => (string) $homeName,
                'logo_url' => Arr::get($teams, 'home.logo'),
            ],
            'away_team' => [
                'name' => (string) $awayName,
                'logo_url' => Arr::get($teams, 'away.logo'),
            ],
            'match' => [
                'starts_at' => CarbonImmutable::parse($startsAt)->timezone(config('app.timezone')),
                'status' => $status,
                'home_goals' => is_numeric($homeGoals) ? (int) $homeGoals : null,
                'away_goals' => is_numeric($awayGoals) ? (int) $awayGoals : null,
                'notes' => 'Importado da API-Football. Fixture ID: '.Arr::get($fixture, 'id'),
            ],
        ];
    }

    private function persistFixture(array $data): FootballMatch
    {
        $league = League::firstOrCreate(
            [
                'name' => $data['league']['name'],
                'season' => $data['league']['season'],
            ],
            [
                'country' => $data['league']['country'],
                'is_active' => true,
            ],
        );

        $homeTeam = $this->firstOrCreateTeam($data['home_team'], $league);
        $awayTeam = $this->firstOrCreateTeam($data['away_team'], $league);

        $match = FootballMatch::updateOrCreate(
            [
                'league_id' => $league->id,
                'home_team_id' => $homeTeam->id,
                'away_team_id' => $awayTeam->id,
                'starts_at' => $data['match']['starts_at'],
            ],
            [
                'status' => $data['match']['status'],
                'home_goals' => $data['match']['home_goals'],
                'away_goals' => $data['match']['away_goals'],
                'notes' => $data['match']['notes'],
            ],
        );

        return $match->load(['league', 'homeTeam', 'awayTeam']);
    }

    private function firstOrCreateTeam(array $data, League $league): Team
    {
        $team = Team::firstOrCreate(
            ['name' => $data['name']],
            [
                'league_id' => $league->id,
                'country' => $league->country,
                'logo_url' => $data['logo_url'],
                'is_active' => true,
            ],
        );

        $updates = [];

        if ($team->league_id === null) {
            $updates['league_id'] = $league->id;
        }

        if ($team->logo_url === null && $data['logo_url']) {
            $updates['logo_url'] = $data['logo_url'];
        }

        if ($updates !== []) {
            $team->update($updates);
        }

        return $team;
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'FT', 'AET', 'PEN' => 'finished',
            'CANC', 'ABD', 'AWD', 'WO' => 'cancelled',
            default => 'scheduled',
        };
    }
}
