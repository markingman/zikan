<?php declare(strict_types=1);

namespace Zikan\Logs;

class LogHandler implements LogHandlerInterface
{
	public function __construct(
		protected LogFormatterInterface $out,
		protected string $type = ''
	) {
	}

	/** @param array<string, mixed> $context */
	public function emerg(string $log, array $context = []): void
	{
		$this->log($log, LOG_EMERG, $context);
	}

	/** @param array<string, mixed> $context */
	public function alert(string $log, array $context = []): void
	{
		$this->log($log, LOG_ALERT, $context);
	}

	/** @param array<string, mixed> $context */
	public function crit(string $log, array $context = []): void
	{
		$this->log($log, LOG_CRIT, $context);
	}

	/** @param array<string, mixed> $context */
	public function err(string $log, array $context = []): void
	{
		$this->log($log, LOG_ERR, $context);
	}

	/** @param array<string, mixed> $context */
	public function warning(string $log, array $context = []): void
	{
		$this->log($log, LOG_WARNING, $context);
	}

	/** @param array<string, mixed> $context */
	public function notice(string $log, array $context = []): void
	{
		$this->log($log, LOG_NOTICE, $context);
	}

	/** @param array<string, mixed> $context */
	public function info(string $log, array $context = []): void
	{
		$this->log($log, LOG_INFO, $context);
	}

	/** @param array<string, mixed> $context */
	public function debug(string $log, array $context = []): void
	{
		$this->log($log, LOG_DEBUG, $context);
	}

	/** @param array<string, mixed> $context */
	public function log(string $log, int $level = 0, array $context = []): void
	{
		$this->out->write($log, $this->type, $level, $context);
	}
}
