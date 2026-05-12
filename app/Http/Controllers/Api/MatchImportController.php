<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Imports\MatchImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MatchImportController extends Controller
{
    public function __invoke(Request $request, MatchImportService $importService): JsonResponse
    {
        $data = $request->validate([
            'format' => ['required', Rule::in(['csv', 'json'])],
            'mode' => ['required', Rule::in(['preview', 'commit'])],
            'file' => ['nullable', 'file', 'max:10240'],
            'content' => ['nullable', 'string'],
        ]);

        $result = $data['mode'] === 'commit'
            ? $importService->commit($data['format'], $request->file('file'), $data['content'] ?? null)
            : $importService->preview($data['format'], $request->file('file'), $data['content'] ?? null);

        return response()->json($result);
    }
}
