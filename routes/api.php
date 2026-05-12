<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ApiIntegrationController;
use App\Http\Controllers\Api\BankrollController;
use App\Http\Controllers\Api\BetController;
use App\Http\Controllers\Api\BookmakerController;
use App\Http\Controllers\Api\LeagueController;
use App\Http\Controllers\Api\MatchAnalysisController;
use App\Http\Controllers\Api\MatchImportController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\AnalysisPerformanceController;
use App\Http\Controllers\Api\AnalysisResultController;
use App\Http\Controllers\Api\AnalysisRuleController;
use App\Http\Controllers\Api\BacktestController;
use App\Http\Controllers\Api\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', DashboardController::class);
Route::get('/analysis-results', [AnalysisResultController::class, 'index']);
Route::get('/analysis-performance', AnalysisPerformanceController::class);
Route::get('/analysis-rules', [AnalysisRuleController::class, 'index']);
Route::patch('/analysis-rules/{key}', [AnalysisRuleController::class, 'update'])->where('key', '.*');
Route::post('/backtests', BacktestController::class);
Route::post('/imports/matches', MatchImportController::class);
Route::get('/integrations', [ApiIntegrationController::class, 'index']);
Route::patch('/integrations/{provider}', [ApiIntegrationController::class, 'update']);
Route::post('/integrations/{provider}/sync', [ApiIntegrationController::class, 'sync']);
Route::get('/bookmakers', [BookmakerController::class, 'index']);
Route::apiResource('bankrolls', BankrollController::class)->only(['index', 'store']);
Route::apiResource('bets', BetController::class)->only(['index', 'store', 'update']);
Route::get('/matches/{match}/analysis', MatchAnalysisController::class);
Route::apiResource('leagues', LeagueController::class)->only(['index', 'store']);
Route::apiResource('teams', TeamController::class)->only(['index', 'store']);
Route::apiResource('matches', MatchController::class)->only(['index', 'store', 'show', 'update']);
