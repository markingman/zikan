<?php declare(strict_types=1);

namespace Zikan\Logs;

use PHPUnit\Framework\TestCase;
use RuntimeException;

class LogFormatterStringTest extends TestCase
{
	public function testWriteOutputsValidJsonToWriter(): void
	{
		$LogWrite = new class implements LogWriteInterface {
			public string $test_write_log_value = '';
			public string $test_write_type_value = '';

			public function write(string $log, string $type = ''): void
			{
				$this->test_write_log_value = $log;
				$this->test_write_type_value = $type;
			}
		};

		$LogFormatterString = new class($LogWrite) extends LogFormatterString {
			public function now(): string
			{
				return '2025-05-10T10:00:00+00:00';
			}
		};

		$LogFormatterString->write('Test message', 'INFO', 1, ['test' => 'value']);

		$this->assertSame(
			"2025-05-10T10:00:00+00:00\t" .
			"INFO\t" .
			"ALERT\t" .
			"Test message\t" .
			'{"test":"value"}', $LogWrite->test_write_log_value);

		$this->assertSame('INFO', $LogWrite->test_write_type_value);
	}

	public function testWriteThrowsOnJsonError(): void
	{
		$LogFormatterString = new class($this->createMock(LogWriteInterface::class)) extends LogFormatterString {
			protected function json_encode(mixed $value, int $flags = 0): string|false
			{
				return false;
			}
		};

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Unable to encode log context');

		$LogFormatterString->write('log', 'ERROR', 1, ['test' => 'invalid']);
	}

	public function testNow(): void
	{
//		$LogWrite = new class implements LogWriteInterface {
//			public string $test_write_log_value = '';
//			public string $test_write_type_value = '';
//
//			public function write(string $log, string $type = ''): void
//			{
//				$this->test_write_log_value = $log;
//				$this->test_write_type_value = $type;
//			}
//		};

		$LogFormatterString = new class($this->createMock(LogWriteInterface::class)) extends LogFormatterString {
			public function getNow(): string
			{
				return $this->now();
			}
		};

		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+\-]\d{2}:\d{2})$/', $LogFormatterString->getNow());
	}
}
