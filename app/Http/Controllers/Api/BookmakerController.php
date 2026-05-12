<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bookmaker;
use Illuminate\Http\JsonResponse;

class BookmakerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Bookmaker::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
        );
    }
}
