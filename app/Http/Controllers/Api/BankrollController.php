<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bankroll;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankrollController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Bankroll::query()
                ->with('bookmaker:id,name')
                ->withCount('bets')
                ->latest()
                ->get()
                ->map(fn (Bankroll $bankroll) => $this->serialize($bankroll))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bookmaker_id' => ['required', 'exists:bookmakers,id'],
            'name' => ['required', 'string', 'max:255'],
            'initial_balance' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
        ]);

        $bankroll = Bankroll::create([
            ...$data,
            'currency' => strtoupper($data['currency'] ?? 'BRL'),
            'is_active' => true,
        ]);

        return response()->json($this->serialize($bankroll), 201);
    }

    private function serialize(Bankroll $bankroll): array
    {
        $profit = $bankroll->bets()->get()->sum(fn ($bet) => $bet->profit());
        $staked = (float) $bankroll->bets()->where('status', '!=', 'void')->sum('stake');
        $settled = $bankroll->bets()->whereIn('status', ['won', 'lost', 'void', 'cashed_out'])->count();

        return [
            'id' => $bankroll->id,
            'bookmaker' => $bankroll->bookmaker,
            'name' => $bankroll->name,
            'initial_balance' => (float) $bankroll->initial_balance,
            'current_balance' => round((float) $bankroll->initial_balance + $profit, 2),
            'profit' => round($profit, 2),
            'roi' => $staked > 0 ? round(($profit / $staked) * 100, 2) : 0,
            'currency' => $bankroll->currency,
            'is_active' => $bankroll->is_active,
            'notes' => $bankroll->notes,
            'bets_count' => $bankroll->bets_count ?? $bankroll->bets()->count(),
            'settled_bets_count' => $settled,
            'created_at' => $bankroll->created_at?->toISOString(),
        ];
    }
}
