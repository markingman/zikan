<?php

namespace Zikan\Logs;

use PHPUnit\Framework\TestCase;

class LogHandlerTest extends TestCase
{
	public function testCreate(): void
	{
		$this->assertInstanceOf(LogHandler::class, new LogHandler(new LogFormatterString(new WriteStdErr())));
	}

	public function testLogEmerg(): void
	{
		$MockLogFormatter = new class implements LogFormatterInterface {
			protected string $test_value = '';

			public function set_test_value(string $test_value): void
			{
				$this->test_value = $test_value;
			}

			public function get_test_value(): string
			{
				return $this->test_value;
			}

			/** @param array<string, mixed> $context */
			public function write(string $log, string $type = '', int $level = 0, array $context = []): void
			{
				$this->test_value = sprintf(
					'log: %s; type: %s; level: %d; context: %s',
					$log, $type, $level, json_encode((object)$context) ?: 'JSON_ENCODE_ERR'
				);
			}
		};

		foreach ([
					 'emerg' => LOG_EMERG,
					 'alert' => LOG_ALERT,
					 'crit' => LOG_CRIT,
					 'err' => LOG_ERR,
					 'warning' => LOG_WARNING,
					 'notice' => LOG_NOTICE,
					 'info' => LOG_INFO,
					 'debug' => LOG_DEBUG,
				 ] as $method => $level) {

			$MockLogFormatter->set_test_value('');
			$LogHandler = new LogHandler($MockLogFormatter, 'test');
			$LogHandler->$method('Log message', ['test' => 'value']);
			$this->assertSame(
				sprintf('log: Log message; type: test; level: %d; context: {"test":"value"}', $level),
				$MockLogFormatter->get_test_value(),
				"Method '$method' should log correct message"
			);
		}
	}

	protected function createMockLogFormatter(): LogFormatterInterface
	{
		return new class implements LogFormatterInterface {
			public string $test_value = '';

			/** @param array<string, mixed> $context */
			public function write(string $log, string $type = '', int $level = 0, array $context = []): void
			{
				$this->test_value = sprintf(
					'log: %s; type: %s; level: %d; context: %s',
					$log, $type, $level, json_encode((object)$context) ?: 'JSON_ENCODE_ERR'
				);
			}
		};
	}
}
