<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

$autoload = BASE_PATH . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException(
        'Execute "composer install" na raiz do projeto para gerar o autoload PSR-4.'
    );
}

require_once $autoload;

/**
 * Inicializar sessão para armazenamento de dados do usuário.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * URL pública de asset (document root = public/).
 */
function asset(string $path): string
{
    $rel = ltrim($path, '/');
    $url = '/assets/' . $rel;
    $abs = BASE_PATH . '/public' . $url;
    if (is_file($abs)) {
        $url .= '?v=' . (string) filemtime($abs);
    }
    return $url;
}

/**
 * Regista cada pedido em storage/logs/requests.log quando APP_LOG_REQUESTS=1 (Coolify → Environment).
 */
function app_log_request_if_enabled(): void
{
    if (getenv('APP_LOG_REQUESTS') !== '1') {
        return;
    }
    $dir = BASE_PATH . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '-';
    if (is_string($ip) && str_contains($ip, ',')) {
        $ip = trim(explode(',', $ip)[0]);
    }
    $line = date('c')
        . ' ' . ($_SERVER['REQUEST_METHOD'] ?? '?')
        . ' ' . ($_SERVER['REQUEST_URI'] ?? '/')
        . ' ip=' . $ip
        . "\n";
    @file_put_contents($dir . '/requests.log', $line, FILE_APPEND | LOCK_EX);
}

/**
 * @return array<string, mixed>|mixed
 */
function config(?string $key = null): mixed
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require BASE_PATH . '/config/app.php';
    }
    if ($key === null) {
        return $cfg;
    }
    return $cfg[$key] ?? null;
}
