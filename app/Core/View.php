<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $name, array $data = [], ?string $layout = 'layouts/default'): void
    {
        $viewFile = self::path($name);
        if (!is_file($viewFile)) {
            throw new \InvalidArgumentException('View não encontrada: ' . $name);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout !== null) {
            $layoutFile = self::path($layout);
            if (!is_file($layoutFile)) {
                throw new \InvalidArgumentException('Layout não encontrado: ' . $layout);
            }
            require $layoutFile;
            return;
        }

        echo $content;
    }

    private static function path(string $dotName): string
    {
        return BASE_PATH . '/app/Views/' . str_replace('.', '/', $dotName) . '.php';
    }
}
