<?php

namespace App\Services\Imports;

use App\Models\FootballMatch;
use App\Models\League;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatchImportService
{
    private const REQUIRED_FIELDS = ['date', 'league', 'home_team', 'away_team', 'status', 'home_goals', 'away_goals'];

    public function preview(string $format, ?UploadedFile $file = null, ?string $content = null): array
    {
        $rows = $this->parse($format, $file, $content);

        return $this->buildResult($rows, false);
    }

    public function commit(string $format, ?UploadedFile $file = null, ?string $content = null): array
    {
        $rows = $this->parse($format, $file, $content);

        return DB::transaction(fn () => $this->buildResult($rows, true));
    }

    private function parse(string $format, ?UploadedFile $file, ?string $content): Collection
    {
        $raw = $file ? file_get_contents($file->getRealPath()) : $content;

        if (blank($raw)) {
            throw ValidationException::withMessages(['file' => 'Informe um arquivo ou conteudo para importar.']);
        }

        return match (strtolower($format)) {
            'csv' => $this->parseCsv($raw),
            'json' => $this->parseJson($raw),
            default => throw ValidationException::withMessages(['format' => 'Formato deve ser csv ou json.']),
        };
    }

    private function parseCsv(string $content): Collection
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $headers = fgetcsv($stream);

        if (! is_array($headers)) {
            throw ValidationException::withMessages(['file' => 'CSV sem cabecalho valido.']);
        }

        $headers = array_map(fn ($header) => trim((string) $header), $headers);
        $this->assertRequiredFields($headers);

        $rows = collect();
        $line = 1;

        while (($data = fgetcsv($stream)) !== false) {
            $line++;

            if ($data === [null] || $data === false) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = $data[$index] ?? null;
            }

            $row['_line'] = $line;
            $rows->push($row);
        }

        fclose($stream);

        return $rows;
    }

    private function parseJson(string $content): Collection
    {
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['file' => 'JSON invalido.']);
        }

        $items = Arr::isAssoc($decoded) ? ($decoded['matches'] ?? []) : $decoded;

        if (! is_array($items)) {
            throw ValidationException::withMessages(['file' => 'JSON deve ser uma lista ou conter a chave matches.']);
        }

        return collect($items)
            ->values()
            ->map(function ($row, int $index) {
                if (! is_array($row)) {
                    return ['_line' => $index + 1, '_invalid_shape' => true];
                }

                return [...$row, '_line' => $index + 1];
            });
    }

    private function assertRequiredFields(array $headers): void
    {
        $missing = array_values(array_diff(self::REQUIRED_FIELDS, $headers));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => 'Campos obrigatorios ausentes: '.implode(', ', $missing),
            ]);
        }
    }

    private function buildResult(Collection $rows, bool $commit): array
    {
        $resultRows = [];
        $summary = [
            'total_rows' => $rows->count(),
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'duplicates' => 0,
            'created' => 0,
            'skipped' => 0,
            'mode' => $commit ? 'commit' : 'preview',
        ];

        foreach ($rows as $row) {
            $normalized = $this->normalize($row);

            if ($normalized['errors'] !== []) {
                $summary['invalid_rows']++;
                $summary['skipped']++;
                $resultRows[] = [
                    'line' => $row['_line'] ?? null,
                    'status' => 'invalid',
                    'action' => 'skip',
                    'errors' => $normalized['errors'],
                    'data' => $normalized['data'],
                ];
                continue;
            }

            $summary['valid_rows']++;
            $duplicate = $this->findDuplicate($normalized['data']);

            if ($duplicate) {
                $summary['duplicates']++;
                $summary['skipped']++;
                $resultRows[] = [
                    'line' => $row['_line'] ?? null,
                    'status' => 'duplicate',
                    'action' => 'skip',
                    'match_id' => $duplicate->id,
                    'errors' => [],
                    'data' => $normalized['data'],
                ];
                continue;
            }

            $match = $commit ? $this->createMatch($normalized['data']) : null;

            if ($commit) {
                $summary['created']++;
            }

            $resultRows[] = [
                'line' => $row['_line'] ?? null,
                'status' => 'valid',
                'action' => $commit ? 'created' : 'create',
                'match_id' => $match?->id,
                'errors' => [],
                'data' => $normalized['data'],
            ];
        }

        return [
            'summary' => $summary,
            'rows' => $resultRows,
        ];
    }

    private function normalize(array $row): array
    {
        $errors = [];

        if (($row['_invalid_shape'] ?? false) === true) {
            return ['errors' => ['Linha JSON deve ser um objeto.'], 'data' => []];
        }

        $data = [
            'date' => trim((string) ($row['date'] ?? '')),
            'league' => trim((string) ($row['league'] ?? '')),
            'home_team' => trim((string) ($row['home_team'] ?? '')),
            'away_team' => trim((string) ($row['away_team'] ?? '')),
            'status' => strtolower(trim((string) ($row['status'] ?? ''))),
            'home_goals' => $this->nullableInteger($row['home_goals'] ?? null),
            'away_goals' => $this->nullableInteger($row['away_goals'] ?? null),
            'country' => trim((string) ($row['country'] ?? '')) ?: null,
            'season' => trim((string) ($row['season'] ?? '')) ?: null,
            'notes' => trim((string) ($row['notes'] ?? '')) ?: null,
        ];

        foreach (['date', 'league', 'home_team', 'away_team', 'status'] as $field) {
            if ($data[$field] === '') {
                $errors[] = "Campo {$field} e obrigatorio.";
            }
        }

        if (! in_array($data['status'], ['scheduled', 'finished', 'cancelled'], true)) {
            $errors[] = 'Status deve ser scheduled, finished ou cancelled.';
        }

        try {
            $data['starts_at'] = $data['date'] === '' ? null : CarbonImmutable::parse($data['date']);
        } catch (\Throwable) {
            $errors[] = 'Data invalida.';
            $data['starts_at'] = null;
        }

        if ($data['home_team'] !== '' && $data['home_team'] === $data['away_team']) {
            $errors[] = 'Mandante e visitante nao podem ser iguais.';
        }

        if ($data['status'] === 'finished' && ($data['home_goals'] === null || $data['away_goals'] === null)) {
            $errors[] = 'Partida finished exige home_goals e away_goals.';
        }

        if ($data['status'] !== 'finished') {
            $data['home_goals'] = null;
            $data['away_goals'] = null;
        }

        return ['errors' => $errors, 'data' => $data];
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function findDuplicate(array $data): ?FootballMatch
    {
        return FootballMatch::query()
            ->whereHas('league', fn ($query) => $query->where('name', $data['league']))
            ->whereHas('homeTeam', fn ($query) => $query->where('name', $data['home_team']))
            ->whereHas('awayTeam', fn ($query) => $query->where('name', $data['away_team']))
            ->where('starts_at', $data['starts_at'])
            ->first();
    }

    private function createMatch(array $data): FootballMatch
    {
        $league = League::firstOrCreate(
            ['name' => $data['league']],
            ['country' => $data['country'], 'season' => $data['season'], 'is_active' => true],
        );

        $homeTeam = Team::firstOrCreate(
            ['name' => $data['home_team']],
            ['league_id' => $league->id, 'country' => $data['country'], 'is_active' => true],
        );

        $awayTeam = Team::firstOrCreate(
            ['name' => $data['away_team']],
            ['league_id' => $league->id, 'country' => $data['country'], 'is_active' => true],
        );

        foreach ([$homeTeam, $awayTeam] as $team) {
            if ($team->league_id === null) {
                $team->update(['league_id' => $league->id]);
            }
        }

        return FootballMatch::create([
            'league_id' => $league->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'starts_at' => $data['starts_at'],
            'status' => $data['status'],
            'home_goals' => $data['home_goals'],
            'away_goals' => $data['away_goals'],
            'notes' => $data['notes'],
        ]);
    }
}
