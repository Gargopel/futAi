<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bet;
use App\Models\Bankroll;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Bet::query()
                ->with(['bankroll:id,name,currency', 'bookmaker:id,name', 'match.league:id,name,season', 'match.homeTeam:id,name', 'match.awayTeam:id,name'])
                ->when($request->filled('bankroll_id'), fn ($query) => $query->where('bankroll_id', $request->integer('bankroll_id')))
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
                ->latest('placed_at')
                ->get()
                ->map(fn (Bet $bet) => $this->serialize($bet))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateBet($request);

        $bet = Bet::create($this->normalize($data));

        return response()->json($this->serialize($bet->load(['bankroll', 'bookmaker', 'match.league', 'match.homeTeam', 'match.awayTeam'])), 201);
    }

    public function update(Request $request, Bet $bet): JsonResponse
    {
        $data = $this->validateBet($request, true);

        $base = $bet->only([
            'bankroll_id',
            'bookmaker_id',
            'match_id',
            'placed_at',
            'market',
            'selection',
            'stake',
            'odds',
            'status',
            'payout',
            'settled_at',
            'notes',
        ]);

        $bet->update($this->normalize(array_replace($base, $data)));

        return response()->json($this->serialize($bet->load(['bankroll', 'bookmaker', 'match.league', 'match.homeTeam', 'match.awayTeam'])));
    }

    private function validateBet(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'bankroll_id' => [$required, 'exists:bankrolls,id'],
            'bookmaker_id' => ['nullable', 'exists:bookmakers,id'],
            'match_id' => ['nullable', 'exists:football_matches,id'],
            'placed_at' => [$required, 'date'],
            'market' => [$required, 'string', 'max:255'],
            'selection' => [$required, 'string', 'max:255'],
            'stake' => [$required, 'numeric', 'min:0.01'],
            'odds' => [$required, 'numeric', 'min:1.01'],
            'status' => [$required, Rule::in(['pending', 'won', 'lost', 'void', 'cashed_out'])],
            'payout' => ['nullable', 'numeric', 'min:0'],
            'settled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function normalize(array $data): array
    {
        if (empty($data['bookmaker_id']) && ! empty($data['bankroll_id'])) {
            $data['bookmaker_id'] = Bankroll::query()->whereKey($data['bankroll_id'])->value('bookmaker_id');
        }

        if (($data['status'] ?? null) === 'won' && ! isset($data['payout']) && isset($data['stake'], $data['odds'])) {
            $data['payout'] = round((float) $data['stake'] * (float) $data['odds'], 2);
        }

        if (($data['status'] ?? null) === 'lost') {
            $data['payout'] = 0;
        }

        if (($data['status'] ?? null) === 'void' && isset($data['stake'])) {
            $data['payout'] = $data['stake'];
        }

        if (($data['status'] ?? null) === 'pending') {
            $data['payout'] = null;
            $data['settled_at'] = null;
        }

        return $data;
    }

    private function serialize(Bet $bet): array
    {
        $match = $bet->match;

        return [
            'id' => $bet->id,
            'bankroll' => $bet->bankroll,
            'bookmaker' => $bet->bookmaker,
            'match' => $match ? [
                'id' => $match->id,
                'label' => "{$match->homeTeam?->name} x {$match->awayTeam?->name}",
                'starts_at' => $match->starts_at?->toISOString(),
                'league' => $match->league,
            ] : null,
            'placed_at' => $bet->placed_at?->toISOString(),
            'market' => $bet->market,
            'selection' => $bet->selection,
            'stake' => (float) $bet->stake,
            'odds' => (float) $bet->odds,
            'status' => $bet->status,
            'payout' => $bet->payout === null ? null : (float) $bet->payout,
            'profit' => $bet->profit(),
            'settled_at' => $bet->settled_at?->toISOString(),
            'notes' => $bet->notes,
        ];
    }
}
