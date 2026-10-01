<?php

declare(strict_types=1);

/**
 * Front controller used only when the hosting document root is the project
 * folder. When the document root is public/, Apache never loads this file.
 */

require __DIR__ . '/public/index.php';
