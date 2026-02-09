<?php

declare(strict_types=1);

namespace Zraly\AiDebug;

use DateTimeImmutable;
use Tracy\ILogger;
use Throwable;
use Nette\Utils\Json;
use Nette\Utils\FileSystem;
use function count;
use function date;
use function get_class;
use function is_link;
use function max;
use function md5;
use function min;
use function rtrim;
use function substr;
use function symlink;
use function unlink;
use function mb_strlen;
use function mb_substr;
use function gettype;
use function is_null;
use function is_bool;
use function is_int;
use function is_float;
use function is_string;
use function is_array;
use function is_object;
use function is_resource;
use function get_resource_type;
use function method_exists;
use function is_readable;
use function is_file;
use function str_contains;
use function strtolower;

/**
 * Logger that exports exceptions to JSON files for AI agents.
 */
class AiDebugLogger implements ILogger
{
    private const REDACTED_VALUE = '[REDACTED]';

    private const SENSITIVE_KEYS = [
        'password',
        'passwd',
        'pwd',
        'token',
        'secret',
        'apikey',
        'api_key',
        'authorization',
        'cookie',
        'session',
        'jwt',
        'bearer',
        'private_key',
        'client_secret',
    ];

    private ?ILogger $originalLogger = null;

    public function __construct(
        private string $logDir,
        private int $snippetLines = 10,
        private bool $enabled = true,
    ) {}

    public function setOriginalLogger(?ILogger $logger): void
    {
        $this->originalLogger = $logger;
    }

    /**
     * Called by Tracy's onFatalError callback to capture BlueScreen errors.
     */
    public function logFatalError(Throwable $exception): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->logToJson($exception, self::ERROR);
    }

    /**
     * Logs exception/error to JSON file.
     */
    public function log(mixed $value, string $priority = self::INFO): ?string
    {
        // Always pass to original logger first
        $originalResult = $this->originalLogger?->log($value, $priority);

        if (!$this->enabled) {
            return $originalResult;
        }

        // Process exceptions
        if ($value instanceof Throwable) {
            $this->logToJson($value, $priority);
            return $originalResult;
        }

        // Process string messages (warnings, notices, etc.)
        if (is_string($value)) {
            $this->logStringToJson($value, $priority);
            return $originalResult;
        }

        return $originalResult;
    }

    private function logToJson(Throwable $exception, string $priority): void
    {
        $data = [
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'priority' => $priority,
            'timestamp' => date('c'),
            'stackTrace' => $this->formatStackTrace($exception),
            'codeSnippet' => $this->extractCodeSnippet($exception->getFile(), $exception->getLine()),
            'context' => $this->extractContext($exception),
        ];

        // Add previous exception if exists
        if ($exception->getPrevious()) {
            $data['previous'] = [
                'type' => get_class($exception->getPrevious()),
                'message' => $exception->getPrevious()->getMessage(),
                'file' => $exception->getPrevious()->getFile(),
                'line' => $exception->getPrevious()->getLine(),
            ];
        }

        $this->ensureLogDir();
        $filename = $this->generateFilename($exception);
        $filepath = $this->logDir . '/' . $filename;

        FileSystem::write($filepath, Json::encode($data, Json::PRETTY));
        $this->updateLatestFile($filename);
    }

    private function logStringToJson(string $message, string $priority): void
    {
        // Try to parse Tracy's string format: "Error message in /path/to/file.php:123"
        $file = null;
        $line = null;
        if (preg_match('~ in ([^\s]+):(\d+)$~', $message, $matches)) {
            $file = $matches[1];
            $line = (int) $matches[2];
        }

        $data = [
            'type' => 'StringMessage',
            'message' => $message,
            'priority' => $priority,
            'timestamp' => date('c'),
            'file' => $file,
            'line' => $line,
            'codeSnippet' => $file && $line ? $this->extractCodeSnippet($file, $line) : null,
            'context' => $this->extractContext(new \RuntimeException($message)),
        ];

        $this->ensureLogDir();
        $filename = $this->generateStringFilename($message);
        $filepath = $this->logDir . '/' . $filename;

        FileSystem::write($filepath, Json::encode($data, Json::PRETTY));
        $this->updateLatestFile($filename);
    }

    private function generateStringFilename(string $message): string
    {
        $timestamp = $this->formatTimestampForFilename();
        $hash = substr(md5($message), 0, 8);
        return "{$timestamp}_{$hash}.json";
    }

    private function formatStackTrace(Throwable $exception): array
    {
        $trace = [];
        foreach ($exception->getTrace() as $frame) {
            $trace[] = [
                'file' => $frame['file'] ?? null,
                'line' => $frame['line'] ?? null,
                'function' => $frame['function'] ?? null,
                'class' => $frame['class'] ?? null,
                'type' => $frame['type'] ?? null,
                'args' => $this->formatArgs($frame['args'] ?? []),
            ];
        }
        return $trace;
    }

    private function formatArgs(array $args): array
    {
        $formatted = [];
        foreach ($args as $arg) {
            $formatted[] = match (true) {
                is_null($arg) => 'null',
                is_bool($arg) => $arg ? 'true' : 'false',
                is_int($arg), is_float($arg) => $arg,
                is_string($arg) => mb_strlen($arg) > 100 ? mb_substr($arg, 0, 100) . '...' : $arg,
                is_array($arg) => 'array(' . count($arg) . ')',
                is_object($arg) => get_class($arg),
                is_resource($arg) => 'resource',
                default => gettype($arg),
            };
        }
        return $formatted;
    }

    private function extractCodeSnippet(string $file, int $line): ?array
    {
        if (!is_file($file) || !is_readable($file)) {
            return null;
        }

        $lines = @file($file);
        if ($lines === false) {
            return null;
        }

        $startLine = max(1, $line - $this->snippetLines);
        $endLine = min(count($lines), $line + $this->snippetLines);

        $snippet = [];
        for ($i = $startLine; $i <= $endLine; $i++) {
            $snippet[$i] = rtrim($lines[$i - 1] ?? '');
        }

        return [
            'startLine' => $startLine,
            'endLine' => $endLine,
            'errorLine' => $line,
            'code' => $snippet,
        ];
    }

    private function extractContext(Throwable $exception): array
    {
        $context = [];

        // Extract variables from Tracy's exception if available
        if (method_exists($exception, 'getContext')) {
            $context['variables'] = $this->sanitizeVariables($exception->getContext());
        }

        // Add request info if available
        if (isset($_SERVER['REQUEST_URI'])) {
            $context['request'] = [
                'uri' => $_SERVER['REQUEST_URI'] ?? null,
                'method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ];
        }

        // Add PHP info
        $context['php'] = [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
        ];

        return $context;
    }

    private function sanitizeVariables(mixed $variables): mixed
    {
        if (!is_array($variables)) {
            return $this->sanitizeValue($variables);
        }

        $sanitized = [];
        foreach ($variables as $key => $value) {
            $sanitized[$key] = $this->sanitizeValue(
                $value,
                is_string($key) ? $key : (string) $key,
            );
        }
        return $sanitized;
    }

    private function sanitizeValue(mixed $value, ?string $key = null): mixed
    {
        if ($this->isSensitiveKey($key)) {
            return self::REDACTED_VALUE;
        }

        return match (true) {
            is_null($value) => null,
            is_bool($value) => $value,
            is_int($value), is_float($value) => $value,
            is_string($value) => mb_strlen($value) > 500 ? mb_substr($value, 0, 500) . '...' : $value,
            is_array($value) => count($value) > 50
                ? ['__truncated__' => true, 'count' => count($value)]
                : $this->sanitizeArray($value),
            is_object($value) => ['__class__' => get_class($value)],
            is_resource($value) => ['__resource__' => get_resource_type($value)],
            default => ['__type__' => gettype($value)],
        };
    }

    private function sanitizeArray(array $value): array
    {
        $sanitized = [];
        foreach ($value as $nestedKey => $nestedValue) {
            $sanitized[$nestedKey] = $this->sanitizeValue(
                $nestedValue,
                is_string($nestedKey) ? $nestedKey : (string) $nestedKey,
            );
        }

        return $sanitized;
    }

    private function isSensitiveKey(?string $key): bool
    {
        if ($key === null || $key === '') {
            return false;
        }

        $normalizedKey = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if (str_contains($normalizedKey, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }

    private function ensureLogDir(): void
    {
        FileSystem::createDir($this->logDir);
    }

    private function generateFilename(Throwable $exception): string
    {
        $timestamp = $this->formatTimestampForFilename();
        $hash = substr(md5($exception->getMessage() . $exception->getFile() . $exception->getLine()), 0, 8);
        return "{$timestamp}_{$hash}.json";
    }

    private function formatTimestampForFilename(): string
    {
        return (new DateTimeImmutable())->format('Y-m-d_H-i-s-u');
    }

    private function updateLatestFile(string $filename): void
    {
        $latestPath = $this->logDir . '/latest.json';
        $sourcePath = $this->logDir . '/' . $filename;

        if (is_link($latestPath) || is_file($latestPath)) {
            @unlink($latestPath); // @ - file may disappear between check and unlink
        }

        if ($this->tryCreateLatestSymlink($filename, $latestPath)) {
            return;
        }

        FileSystem::copy($sourcePath, $latestPath, true);
    }

    protected function tryCreateLatestSymlink(string $filename, string $latestPath): bool
    {
        return @symlink($filename, $latestPath); // @ - symlink is unavailable on some platforms/permissions
    }
}
