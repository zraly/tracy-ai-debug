<?php

declare(strict_types=1);

use Tester\Assert;
use Zraly\AiDebug\AiDebugLogger;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Tracy\ILogger;

require __DIR__ . '/bootstrap.php';

$tempDir = createTempDir();
$logDir = $tempDir . '/log';
$errorLogDir = $tempDir . '/ai-debug';

// Mock original logger
class MockLogger implements ILogger
{
	public array $logs = [];

	public function log(mixed $value, string $priority = self::INFO): ?string
	{
		$this->logs[] = [$value, $priority];
		return 'logged';
	}
}

test('Logger delegates to original logger', function () use ($logDir, $errorLogDir) {
	$mockLogger = new MockLogger;
	$logger = new AiDebugLogger($errorLogDir);
	$logger->setOriginalLogger($mockLogger);

	$result = $logger->log('info message', ILogger::INFO);

	Assert::same('logged', $result);
	Assert::count(1, $mockLogger->logs);
	Assert::same(['info message', ILogger::INFO], $mockLogger->logs[0]);
});

test('Logger creates JSON file for exceptions', function () use ($errorLogDir) {
	FileSystem::delete($errorLogDir); // Clean up
	$logger = new AiDebugLogger($errorLogDir);

	$exception = new RuntimeException('Test Exception', 123);
	$logger->log($exception, ILogger::ERROR);

	$files = glob($errorLogDir . '/*.json');
    $files = array_filter($files, fn($f) => basename($f) !== 'latest.json');
	Assert::count(1, $files);

	$latest = $errorLogDir . '/latest.json';
	Assert::true(is_link($latest));

	$content = Json::decode(FileSystem::read($latest));
	Assert::same('RuntimeException', $content->type);
	Assert::same('Test Exception', $content->message);
	Assert::same(123, $content->code);
});

test('Logger creates JSON file for string messages', function () use ($errorLogDir) {
	FileSystem::delete($errorLogDir);
	$logger = new AiDebugLogger($errorLogDir);

	$logger->log('Just a string error', ILogger::ERROR);

	$files = glob($errorLogDir . '/*.json');
	$files = array_filter($files, fn($f) => basename($f) !== 'latest.json');
	Assert::count(1, $files);

	$content = Json::decode(FileSystem::read($errorLogDir . '/latest.json'));
	Assert::same('StringMessage', $content->type);
	Assert::same('Just a string error', $content->message);
});
