<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiIntegration;
use App\Services\Integrations\ApiFootballService;
use App\Services\Integrations\FootballDataOrgService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ApiIntegrationController extends Controller
{
    private const PROVIDERS = [
        'api-football' => [
            'name' => 'API-Football',
            'description' => 'Fixtures, resultados e metadados de ligas. Primeiro provedor ativo para a Serie A do Brasileiro.',
            'status' => 'ready',
            'default_config' => [
                'league' => 71,
                'season' => 2024,
                'timezone' => 'America/Sao_Paulo',
            ],
        ],
        'football-data-org' => [
            'name' => 'Football-Data.org',
            'description' => 'Provedor alternativo para redundancia de resultados e calendario do Campeonato Brasileiro Serie A.',
            'status' => 'ready',
            'default_config' => [
                'competition' => 'BSA',
                'season' => 2026,
            ],
        ],
        'csv-source' => [
            'name' => 'CSV/Planilhas',
            'description' => 'Fallback local para importar bases historicas baixadas de outras fontes.',
            'status' => 'available',
            'default_config' => [],
        ],
    ];

    public function index(): JsonResponse
    {
        $integrations = ApiIntegration::query()->get()->keyBy('provider');

        return response()->json(collect(self::PROVIDERS)->map(function (array $provider, string $key) use ($integrations) {
            $integration = $integrations->get($key);
            $config = array_replace($provider['default_config'], $integration?->config ?? []);

            return [
                'provider' => $key,
                'name' => $provider['name'],
                'description' => $provider['description'],
                'status' => $provider['status'],
                'is_active' => (bool) ($integration?->is_active ?? $key === 'api-football'),
                'configured' => $this->isConfigured($key, $integration),
                'key_preview' => $this->keyPreview($key, $integration),
                'config' => $config,
                'last_synced_at' => $integration?->last_synced_at,
                'last_sync_summary' => $integration?->last_sync_summary,
            ];
        })->values());
    }

    public function update(string $provider, Request $request): JsonResponse
    {
        $this->assertKnownProvider($provider);

        $data = $request->validate([
            'api_key' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'config' => ['nullable', 'array'],
        ]);

        $integration = ApiIntegration::firstOrNew(['provider' => $provider]);
        $integration->is_active = (bool) ($data['is_active'] ?? $integration->is_active);
        $integration->config = array_replace(self::PROVIDERS[$provider]['default_config'], $data['config'] ?? $integration->config ?? []);

        if (array_key_exists('api_key', $data) && trim((string) $data['api_key']) !== '') {
            $integration->api_key = trim((string) $data['api_key']);
        }

        $integration->save();

        return $this->index();
    }

    public function sync(string $provider, Request $request, ApiFootballService $apiFootball, FootballDataOrgService $footballDataOrg): JsonResponse
    {
        $this->assertKnownProvider($provider);

        $data = $request->validate([
            'league' => ['nullable', 'integer'],
            'competition' => ['nullable', 'string'],
            'season' => ['nullable', 'integer'],
            'timezone' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $integration = ApiIntegration::firstOrCreate(
            ['provider' => $provider],
            [
                'is_active' => true,
                'config' => self::PROVIDERS[$provider]['default_config'],
            ],
        );

        $filters = array_replace(self::PROVIDERS[$provider]['default_config'], $integration->config ?? [], $data);

        try {
            $result = match ($provider) {
                'api-football' => $apiFootball->importFixtures([
                    'league' => $filters['league'],
                    'season' => $filters['season'],
                    'timezone' => $filters['timezone'],
                ]),
                'football-data-org' => $footballDataOrg->importMatches([
                    'competition' => $filters['competition'],
                    'season' => $filters['season'],
                    'date_from' => $filters['date_from'] ?? null,
                    'date_to' => $filters['date_to'] ?? null,
                ]),
                default => abort(422, 'Este provedor ainda nao possui sincronizacao automatica.'),
            };
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $integration->update([
            'config' => $filters,
            'last_synced_at' => now(),
            'last_sync_summary' => $result['summary'],
        ]);

        return response()->json($result);
    }

    private function assertKnownProvider(string $provider): void
    {
        abort_unless(array_key_exists($provider, self::PROVIDERS), 404, 'Provedor nao reconhecido.');
    }

    private function isConfigured(string $provider, ?ApiIntegration $integration): bool
    {
        if ($integration?->api_key) {
            return true;
        }

        return ($provider === 'api-football' && filled(config('services.api_football.key')))
            || ($provider === 'football-data-org' && filled(config('services.football_data_org.key')));
    }

    private function keyPreview(string $provider, ?ApiIntegration $integration): ?string
    {
        $key = $integration?->api_key;

        if (! $key && $provider === 'api-football') {
            $key = config('services.api_football.key');
        }

        if (! $key && $provider === 'football-data-org') {
            $key = config('services.football_data_org.key');
        }

        if (! $key) {
            return null;
        }

        return str_repeat('*', max(strlen($key) - 4, 0)).substr($key, -4);
    }
}
