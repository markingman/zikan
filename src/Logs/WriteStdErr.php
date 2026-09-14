<?php declare(strict_types=1);

namespace Zikan\Logs;

use RuntimeException;
use function is_resource;

class WriteStdErr implements LogWriteInterface
{
	/** @var resource */
	protected mixed $fp;

	public function __construct()
	{
		$fp = defined('STDERR') ? STDERR : fopen('php://stderr', 'w');

		if (!$this->is_resource($fp)) {
			throw new RuntimeException('Could not open stderr');
		}

		/** @var resource $fp */
		$this->fp = $fp;
	}

	public function write(string $log, string $type = ''): void
	{
		$this->fwrite($log . PHP_EOL);
	}

	protected function fwrite(string $log): void
	{
		if ($this->is_resource($this->fp)) {
			fwrite($this->fp, $log . PHP_EOL);
		}
	}

	protected function is_resource(mixed $value): bool
	{
		return is_resource($value);
	}
}
