<?php declare(strict_types=1);

namespace Zikan\Logs;

use RuntimeException;

class LogFormatterString implements LogFormatterInterface
{
	public function __construct(
		private readonly LogWriteInterface $writer
	) {
	}

	/** @param array<string, mixed> $context */
	public function write(string $log, string $type = '', int $level = 0, array $context = []): void
	{
		$enc_context = $this->json_encode((object)$context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		if (!$enc_context) {
			throw new RuntimeException('Unable to encode log context');
		}

		$log = sprintf(
			"%s\t%s\t%s\t%s\t%s",
			$this->now(), $type ?: 'UNKNOWN', LogLevel::label($level), $log,
			$enc_context
		);


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

