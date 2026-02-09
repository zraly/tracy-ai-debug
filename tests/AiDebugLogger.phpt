<?php

declare(strict_types=1);

use Tester\Assert;
use Zraly\AiDebug\AiDebugLogger;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Tracy\ILogger;

require __DIR__ . '/bootstrap.php';

$tempDir = createTempDir();
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

class ContextException extends RuntimeException
{
	public function __construct(
		private array $context,
	) {
		parent::__construct('Context exception');
	}

	public function getContext(): array
	{
		return $this->context;
	}
}

class NoSymlinkLogger extends AiDebugLogger
{
	protected function tryCreateLatestSymlink(string $filename, string $latestPath): bool
	{
		return false;
	}
}

test('Logger delegates to original logger', function () use ($errorLogDir) {
	$mockLogger = new MockLogger;
	$logger = new AiDebugLogger($errorLogDir);
	$logger->setOriginalLogger($mockLogger);

	$result = $logger->log('info message', ILogger::INFO);

	Assert::same('logged', $result);
	Assert::count(1, $mockLogger->logs);
	Assert::same(['info message', ILogger::INFO], $mockLogger->logs[0]);
});

test('Logger creates JSON file for exceptions', function () use ($errorLogDir) {
	FileSystem::delete($errorLogDir);
	$logger = new AiDebugLogger($errorLogDir);

	$exception = new RuntimeException('Test Exception', 123);
	$logger->log($exception, ILogger::ERROR);

	$files = glob($errorLogDir . '/*.json');
	$files = array_filter($files, fn($f) => basename($f) !== 'latest.json');
	Assert::count(1, $files);

	$latest = $errorLogDir . '/latest.json';
	Assert::true(is_link($latest) || is_file($latest));

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

test('Logger does not write JSON when disabled', function () use ($errorLogDir) {
	FileSystem::delete($errorLogDir);

	$mockLogger = new MockLogger;
	$logger = new AiDebugLogger($errorLogDir, 10, false);
	$logger->setOriginalLogger($mockLogger);

	$result = $logger->log(new RuntimeException('Disabled exception'), ILogger::ERROR);

	Assert::same('logged', $result);
	Assert::count(1, $mockLogger->logs);
	Assert::false(is_dir($errorLogDir));
});

test('Logger creates unique filenames for repeated exceptions', function () use ($errorLogDir) {
	FileSystem::delete($errorLogDir);
	$logger = new AiDebugLogger($errorLogDir);

	$logger->log(new RuntimeException('Repeated exception'), ILogger::ERROR);
	$logger->log(new RuntimeException('Repeated exception'), ILogger::ERROR);

	$files = glob($errorLogDir . '/*.json');
	$files = array_filter($files, fn($f) => basename($f) !== 'latest.json');
	Assert::count(2, $files);
});

test('Logger redacts sensitive context variables', function () use ($errorLogDir) {
	FileSystem::delete($errorLogDir);
	$logger = new AiDebugLogger($errorLogDir);

	$exception = new ContextException([
		'password' => 'top-secret',
		'apiToken' => 'token-value',
		'nested' => [
			'client_secret' => 'hidden',
			'safeValue' => 'visible',
		],
	]);
	$logger->log($exception, ILogger::ERROR);

	$content = Json::decode(FileSystem::read($errorLogDir . '/latest.json'), true);
	Assert::same('[REDACTED]', $content['context']['variables']['password']);
	Assert::same('[REDACTED]', $content['context']['variables']['apiToken']);
	Assert::same('[REDACTED]', $content['context']['variables']['nested']['client_secret']);
	Assert::same('visible', $content['context']['variables']['nested']['safeValue']);
});

test('Logger falls back to regular latest.json file when symlink fails', function () use ($errorLogDir) {
	FileSystem::delete($errorLogDir);
	$logger = new NoSymlinkLogger($errorLogDir);

	$logger->log(new RuntimeException('Fallback symlink exception'), ILogger::ERROR);

	$latestPath = $errorLogDir . '/latest.json';
	Assert::true(is_file($latestPath));
	Assert::false(is_link($latestPath));

	$content = Json::decode(FileSystem::read($latestPath));
	Assert::same('RuntimeException', $content->type);
	Assert::same('Fallback symlink exception', $content->message);
});
