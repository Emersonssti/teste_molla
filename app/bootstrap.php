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
