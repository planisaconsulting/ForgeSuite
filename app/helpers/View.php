<?php

declare(strict_types=1);

namespace App\Helpers;

use RuntimeException;

/**
 * Renders a PHP view, optionally inside a layout.
 *
 * Views receive data as local variables. They must not run SQL.
 * Pass $layout = null for a page that is already a full HTML document.
 */
final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $viewFile = base_path('app/views/' . $view . '.php');
        if (!is_file($viewFile)) {
            throw new RuntimeException('View not found: ' . $view);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout === null) {
            echo $content;

            return;
        }

        $layoutFile = base_path('app/views/' . $layout . '.php');
        if (!is_file($layoutFile)) {
            throw new RuntimeException('Layout not found: ' . $layout);
        }

        require $layoutFile;
    }
}
