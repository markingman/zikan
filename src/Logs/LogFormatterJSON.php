<?php declare(strict_types=1);

namespace Zikan\Logs;

use RuntimeException;

class LogFormatterJSON implements LogFormatterInterface
{
	public function __construct(
		private readonly LogWriteInterface $writer
	) {
	}

	/** @param array<string, mixed> $context */
	public function write(string $log, string $type = '', int $level = 0, array $context = []): void
	{
		$log = $this->json_encode([
			'timestamp' => $this->now(),
			'type' => $type ?: 'UNKNOWN',
			'level' => LogLevel::label($level),
			'message' => $log,
			'context' => (object)$context, // Cast to object for empty = {}
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		if (!$log) {
			throw new RuntimeException('Unable to encode log');
		}

		$this->writer->write($log, $type);
	}

	protected function now(): string
	{
		return date('c');
	}

	protected function json_encode(mixed $value, int $flags = 0): string|false
	{
		return json_encode($value, $flags);
	}
}
