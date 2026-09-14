<?php

namespace Zikan\Logs;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

class WriteErrorLogTest extends TestCase
{
	protected string $logDir;

	public function testWriteCreatesLogFile(): void
	{
		$writer = new WriteErrorLog($this->logDir);
		$writer->write('Test log entry', 'unit_test');

		$file = $this->logDir . '/unit_test.log';
		$this->assertFileExists($file);
		$this->assertStringContainsString('Test log entry', (string)file_get_contents($file));
	}

	public function testWriteSanitizesFilename(): void
	{
		$writer = new WriteErrorLog($this->logDir);
		$writer->write('Log data', '../tricky_path');

		// Should not write to parent directories
		$this->assertFileExists($this->logDir . '/tricky_path.log');
	}

	protected function setUp(): void
	{
		try {
			$this->logDir = sys_get_temp_dir() . '/logtest_' . bin2hex(random_bytes(4));
		} catch (Throwable $e) {
			throw new RuntimeException('Could not crate log dir', previous: $e);
		}
		mkdir($this->logDir);
	}

	protected function tearDown(): void
	{
		foreach (glob($this->logDir . '/*.log') ?: [] as $file) {
			unlink($file);
		}
		rmdir($this->logDir);
	}
}
