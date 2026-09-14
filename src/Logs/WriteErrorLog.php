<?php declare(strict_types=1);

namespace Zikan\Logs;

class WriteErrorLog implements LogWriteInterface
{
	private string $path;

	public function __construct(string $path)
	{
		$this->path = $path;
	}

	public function write(string $log, string $type = ''): void
	{
		$filename = substr(trim(preg_replace('/[^a-zA-Z0-9._-]/', '', $type) ?: '', '.'), 0, 32) ?: 'error';

		error_log($log . PHP_EOL, 3, $this->path . '/' . basename($filename, '.log') . '.log');
	}
}
