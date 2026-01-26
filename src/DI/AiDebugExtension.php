<?php

declare(strict_types=1);

namespace Zraly\AiDebug\DI;

use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Zraly\AiDebug\AiDebugLogger;
use Tracy\Debugger;
use Nette\PhpGenerator\ClassType;

/**
 * Nette DI extension for AI Debug Logger integration.
 */
class AiDebugExtension extends CompilerExtension
{
    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'logDir' => Expect::string()->default('%appDir%/../log/ai-debug'),
            'enabled' => Expect::bool()->default(true),
            'snippetLines' => Expect::int()->default(10),
        ]);
    }

    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();
        /** @var \stdClass $config */
        $config = $this->config;

        $builder->addDefinition($this->prefix('logger'))
            ->setFactory(AiDebugLogger::class, [
                $config->logDir,
                $config->snippetLines,
                $config->enabled,
            ])
            ->setAutowired(false);
    }

    public function afterCompile(ClassType $class): void
    {
        $initialize = $class->getMethod('initialize');
        $initialize->addBody(
            '// AI Debug Logger initialization
			$aiDebugLogger = $this->getService(?);
			$originalLogger = \Tracy\Debugger::getLogger();
			$aiDebugLogger->setOriginalLogger($originalLogger);
			\Tracy\Debugger::setLogger($aiDebugLogger);

			// Also hook into onFatalError to capture BlueScreen errors in development mode
			\Tracy\Debugger::$onFatalError[] = function (\Throwable $exception) use ($aiDebugLogger) {
				$aiDebugLogger->logFatalError($exception);
			};',
            [$this->prefix('logger')],
        );
    }
}
