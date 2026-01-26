<?php

declare(strict_types=1);

use Nette\DI\Compiler;
use Nette\DI\ContainerLoader;
use Tracy\Debugger;
use Tester\Assert;
use Zraly\AiDebug\AiDebugLogger;

require __DIR__ . '/bootstrap.php';

$tempDir = createTempDir();

test('Extension registers service and initializes debugger', function () use ($tempDir) {
	$loader = new ContainerLoader($tempDir, true);
	$class = $loader->load(function (Compiler $compiler) {
		$compiler->addExtension('aiDebug', new Zraly\AiDebug\DI\AiDebugExtension);
	});

	/** @var Nette\DI\Container $container */
	$container = new $class;
	$container->initialize();

	$logger = Debugger::getLogger();
	Assert::type(AiDebugLogger::class, $logger);
});
