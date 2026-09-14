<?php declare(strict_types=1);

namespace Zikan\Logs;

use PHPUnit\Framework\TestCase;
use RuntimeException;

class LogFormatterJSONTest extends TestCase
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

		$LogFormatterJSON = new class($LogWrite) extends LogFormatterJSON {
			public function now(): string
			{
				return '2025-05-10T10:00:00+00:00';
			}
		};

		$LogFormatterJSON->write('Test message', 'INFO', 1, ['test' => 'value']);

		$decoded = json_decode($LogWrite->test_write_log_value, true);
		if (!is_array($decoded)) {
			$this->fail('Unable to decode log');
		}

		$this->assertSame($decoded['timestamp'], '2025-05-10T10:00:00+00:00');
		$this->assertSame($decoded['type'], 'INFO');
		$this->assertSame($decoded['level'], LogLevel::label(1));
		$this->assertSame($decoded['message'], 'Test message');
		$this->assertSame(['test' => 'value'], $decoded['context']);

		$this->assertSame('INFO', $LogWrite->test_write_type_value);
	}

	public function testWriteThrowsOnJsonError(): void
	{
		$LogFormatterJSON = new class($this->createMock(LogWriteInterface::class)) extends LogFormatterJSON {
			protected function json_encode(mixed $value, int $flags = 0): string|false
			{
				return false;
			}
		};

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Unable to encode log');

		$LogFormatterJSON->write('log', 'ERROR', 1, ['test' => 'invalid']);
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

		$LogFormatterJSON = new class($this->createMock(LogWriteInterface::class)) extends LogFormatterJSON {
			public function getNow(): string
			{
				return $this->now();
			}
		};

		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+\-]\d{2}:\d{2})$/', $LogFormatterJSON->getNow());
	}
}
