<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

Tester\Environment::setup();
Tester\Environment::setupFunctions();
date_default_timezone_set('Europe/Prague');

// Helper to create a temp directory
function createTempDir(): string
{
    $dir = __DIR__ . '/../temp/tests/' . getmypid();
    Nette\Utils\FileSystem::createDir($dir);
    return $dir;
}
