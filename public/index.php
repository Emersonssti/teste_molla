<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Router;
use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\TratarController;

$router = new Router();
$router
    ->get('/', HomeController::class, 'index')
    ->post('/upload', HomeController::class, 'upload')
    ->get('/dashboard', DashboardController::class, 'index')
    ->get('/debug-cache', DashboardController::class, 'debugCache')
    ->get('/tratar', TratarController::class, 'form')
    ->post('/tratar', TratarController::class, 'process')
    ->get('/api/analysis', AnalysisController::class, 'generate');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$router->dispatch($method, $uri);
