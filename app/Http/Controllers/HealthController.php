<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;

/**
 * Diagnóstico em produção: confirma que o pedido chegou ao PHP e grava um rasto em disco.
 */
final class HealthController extends Controller
{
    public function health(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $this->appendLog(
            'health.log',
            'HEALTH ' . ($_SERVER['REQUEST_METHOD'] ?? '?') . ' ' . ($_SERVER['REQUEST_URI'] ?? '/')
        );

        echo json_encode([
            'ok' => true,
            'time' => date('c'),
            'php' => PHP_VERSION,
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
            'via' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function appendLog(string $filename, string $message): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '-';
        if (is_string($ip) && str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $line = date('c') . ' ' . $message . ' ip=' . $ip . "\n";
        @file_put_contents($dir . '/' . $filename, $line, FILE_APPEND | LOCK_EX);
    }
}
