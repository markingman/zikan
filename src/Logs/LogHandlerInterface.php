<?php declare(strict_types=1);

namespace Zikan\Logs;

interface LogHandlerInterface
{
	/** @param array<string, mixed> $context */
	public function emerg(string $log, array $context = []): void;

	/** @param array<string, mixed> $context */
	public function alert(string $log, array $context = []): void;

	/** @param array<string, mixed> $context */
	public function crit(string $log, array $context = []): void;

	/** @param array<string, mixed> $context */
	public function err(string $log, array $context = []): void;

	/** @param array<string, mixed> $context */
	public function warning(string $log, array $context = []): void;

	/** @param array<string, mixed> $context */
	public function notice(string $log, array $context = []): void;

	/** @param array<string, mixed> $context */
	public function info(string $log, array $context = []): void;

	/** @param array<string, mixed> $context */
	public function debug(string $log, array $context = []): void;

	/** @param array<string, mixed> $context */
	public function log(string $log, int $level = 0, array $context = []): void;
}
