<?php

namespace App\Services\FootballAnalysis;

use App\Models\AnalysisRule;

class AnalysisRuleResolver
{
    private ?array $resolved = null;

    public function __construct(private readonly AnalysisRuleCatalog $catalog) {}

    public function all(): array
    {
        return $this->resolved ??= $this->resolve();
    }

    public function number(string $key): float
    {
        return (float) $this->value($key);
    }

    public function integer(string $key): int
    {
        return (int) round((float) $this->value($key));
    }

    public function boolean(string $key): bool
    {
        $value = $this->value($key);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function value(string $key): mixed
    {
        return $this->all()[$key]['value'] ?? $this->catalog->get($key)['config']['value'] ?? null;
    }

    public function forget(): void
    {
        $this->resolved = null;
    }

    private function resolve(): array
    {
        $defaults = collect($this->catalog->all())
            ->mapWithKeys(fn (array $rule, string $key) => [$key => $this->normalize($rule)])
            ->all();

        $databaseRules = AnalysisRule::query()
            ->where('is_active', true)
            ->whereIn('key', array_keys($defaults))
            ->get()
            ->keyBy('key');

        foreach ($databaseRules as $key => $rule) {
            $default = $defaults[$key];
            $config = $rule->config ?? [];
            $value = array_key_exists('value', $config) ? $config['value'] : $rule->weight;

            $defaults[$key] = [
                ...$default,
                'id' => $rule->id,
                'name' => $rule->name,
                'description' => $rule->description,
                'weight' => (float) $rule->weight,
                'is_active' => (bool) $rule->is_active,
                'value' => $this->cast($value, $default['type']),
                'config' => [
                    ...$default['config'],
                    ...$config,
                    'value' => $this->cast($value, $default['type']),
                ],
            ];
        }

        return $defaults;
    }

    private function normalize(array $rule): array
    {
        $type = $rule['config']['type'];
        $value = $this->cast($rule['config']['value'], $type);

        return [
            ...$rule,
            'id' => null,
            'type' => $type,
            'value' => $value,
            'config' => [
                ...$rule['config'],
                'value' => $value,
            ],
        ];
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) round((float) $value),
            default => round((float) $value, 4),
        };
    }
}
