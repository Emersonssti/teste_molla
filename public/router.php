<?php

declare(strict_types=1);

/**
 * Router para o servidor embutido do PHP:
 * php -S localhost:8080 -t public public/router.php
 */

// Sempre processar pelo index.php para evitar problemas com arquivos estáticos
require __DIR__ . '/index.php';
