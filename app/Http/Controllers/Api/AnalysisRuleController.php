<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalysisRule;
use App\Services\FootballAnalysis\AnalysisRuleCatalog;
use App\Services\FootballAnalysis\AnalysisRuleResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalysisRuleController extends Controller
{
    public function index(AnalysisRuleResolver $resolver): JsonResponse
    {
        return response()->json(array_values($resolver->all()));
    }

    public function update(string $key, Request $request, AnalysisRuleCatalog $catalog, AnalysisRuleResolver $resolver): JsonResponse
    {
        $default = $catalog->get($key);

        abort_if($default === null, 404, 'Regra nao reconhecida pelo catalogo.');

        $data = $request->validate([
            'value' => ['sometimes'],
            'weight' => ['sometimes', 'numeric', 'min:0', 'max:10'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $config = $default['config'];

        if (array_key_exists('value', $data)) {
            $config['value'] = $this->cast($data['value'], $config['type']);
        }

        $rule = AnalysisRule::updateOrCreate(
            ['key' => $key],
            [
                'name' => $default['name'],
                'description' => $default['description'],
                'weight' => $data['weight'] ?? (is_numeric($config['value']) ? (float) $config['value'] : $default['weight']),
                'is_active' => $data['is_active'] ?? true,
                'config' => $config,
            ],
        );

        $resolver->forget();

        return response()->json($resolver->all()[$rule->key]);
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) round((float) $value),
            default => (float) $value,
        };
    }
}
