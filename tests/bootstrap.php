<?php

declare(strict_types=1);

(static function (): void {
    if (!is_file($autoloadFile = __DIR__.'/../vendor/autoload.php')) {
        throw new RuntimeException('Did not find vendor/autoload.php. Did you run "composer install --dev"?');
    }
    $loader = require $autoloadFile;
    $loader->add('JMS\Serializer\Tests', __DIR__);
})();
