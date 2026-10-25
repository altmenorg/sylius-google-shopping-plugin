<?php

declare(strict_types=1);

/*
 * Uses the plugin's own vendor/ when installed standalone, or the autoloader of a Sylius application given by
 * GOOGLE_SHOPPING_AUTOLOAD (the plugin installed in its vendor/, or checked out next to it):
 *   GOOGLE_SHOPPING_AUTOLOAD=/path/to/shop/vendor/autoload.php /path/to/shop/vendor/bin/phpunit
 */
$autoload = getenv('GOOGLE_SHOPPING_AUTOLOAD') ?: __DIR__ . '/../vendor/autoload.php';
require $autoload;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Altmenorg\\SyliusGoogleShoppingPlugin\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = str_starts_with($relative, 'Tests/')
        ? __DIR__ . '/' . substr($relative, 6) . '.php'
        : __DIR__ . '/../src/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
}, true, true);
