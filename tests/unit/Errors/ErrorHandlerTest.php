<?php

namespace Zikan\Errors;

use ErrorException;
use Zikan\Logs\LogHandler;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

class ErrorHandlerTest extends TestCase
{
	protected static function error_log(string $log): void
	{
		error_log($log);
	}

	public function testCanCreate(): void
	{
		$this->assertInstanceOf(ErrorHandler::class, new ErrorHandler());
	}

	public function testSetTerminate(): void
	{
		$ErrorHandler = new class extends ErrorHandler {
			public function test_terminate_value(): bool
			{
				return $this->terminate;
			}
		};

		$this->assertTrue($ErrorHandler->test_terminate_value());

		$ErrorHandler->set_terminate(false);
		$this->assertFalse($ErrorHandler->test_terminate_value());

		$ErrorHandler->set_terminate(true);
		$this->assertTrue($ErrorHandler->test_terminate_value());
	}

	public function testSetDebug(): void
	{
		$ErrorHandler = new class extends ErrorHandler {
			public function test_debug_value(): bool
			{
				return $this->debug;
			}
		};

		$this->assertFalse($ErrorHandler->test_debug_value());

		$ErrorHandler->set_debug(true);
		$this->assertTrue($ErrorHandler->test_debug_value());

		$ErrorHandler->set_debug(false);
		$this->assertFalse($ErrorHandler->test_debug_value());
	}

	public function testSetView(): void
	{
		$ErrorHandler = new class extends ErrorHandler {
			public function test_view_set(): bool
			{
				return !is_null($this->view);
			}
		};

		$ErrorHandler->set_view(function (Throwable $e): void {
		});
		$this->assertTrue($ErrorHandler->test_view_set());
	}

	public function testSetLog(): void
	{
		$ErrorHandler = new class extends ErrorHandler {
			public function test_log_set(): bool
			{
				return !is_null($this->log);
			}
		};

		$ErrorHandler->set_log(function (Throwable $e): void {
		});
		$this->assertTrue($ErrorHandler->test_log_set());
	}

	public function testLog(): void
	{
		$ErrorHandler = new class extends ErrorHandler {
			public static string $test_log_value = '';

			protected static function error_log(string $log): void
			{
				static::$test_log_value = $log;
			}
		};

		$ErrorHandler->log(new RuntimeException('Text exception'), null, false);

		$this->assertSame(
			'####-##-##T##:##:##+##:##	E_ERROR[0] RuntimeException: Text exception in .../unit/Errors/ErrorHandlerTest.php:###',
			$this->normalise_log_line($ErrorHandler::$test_log_value)
		);

		$ErrorHandler->log(new RuntimeException('Text exception'), null, true);

		$normalised = $this->normalise_log_line($ErrorHandler::$test_log_value);

		$lines = explode(PHP_EOL, (string)preg_replace('/^#\d+ .+$/m', '...', $normalised));
		foreach ($lines as $i => &$line) {
			if ($line === '...') {
				$line = "#$i ...";
			}
			if ($i >= 3) {
				break;
			}
		}

		$normalised = trim((string)preg_replace('/(?:^\.\.\.\r?' . PHP_EOL . '?)+/m', '...' . PHP_EOL, implode(PHP_EOL, $lines)));

		$this->assertSame(
			'####-##-##T##:##:##+##:##	E_ERROR[0] RuntimeException: Text exception in .../unit/Errors/ErrorHandlerTest.php:###' . PHP_EOL .
			'#1 ...' . PHP_EOL .
			'#2 ...' . PHP_EOL .
			'#3 ...' . PHP_EOL .
			'...',
			$normalised
		);
	}

	public function testLogWithLogHandler(): void
	{
		$mock = $this->getMockBuilder(LogHandler::class)
			->disableOriginalConstructor()
			->onlyMethods(['log'])
			->getMock();

		$mock->expects($this->once())
			->method('log')
			->with(
				$this->isType('string'),
				$this->equalTo(E_ERROR)
			);

		ErrorHandler::log(new RuntimeException("Test exception"), $mock);
	}

	public function testView(): void
	{
		$ErrorHandler = new class extends ErrorHandler {
			public static string $test_echo_value = '';
			public static string $test_php_sapi_name = '';

			protected static function echo(string $string): void
			{
				static::$test_echo_value = $string;
			}

			protected static function php_sapi_name(): string
			{
				return static::$test_php_sapi_name;
			}
		};

		$ErrorHandler::$test_php_sapi_name = 'cli';
		$ErrorHandler->view(new RuntimeException('Text exception'), []);
		$this->assertSame(
			"\e[3;101m  RuntimeException  \e[0m" . PHP_EOL . 'Text exception' . PHP_EOL . '.../unit/Errors/ErrorHandlerTest.php:###',
			trim((string)preg_replace('#.*/tests/(.+):\d+#', '.../$1:###', $ErrorHandler::$test_echo_value))
		);

		$ErrorHandler::$test_php_sapi_name = 'server';
		$ErrorHandler->view(new RuntimeException('Text exception'), []);
		$this->assertSame(
			<<<__
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>500 Internal Server Error</title>
	<style>*{color:#111;background:#ddd;line-height:1.6}body{font-size:16px;}</style>
</head>
<body>
	<pre><b>500 Internal Server Error</b></pre>
</body>
</html>
__,
			trim((string)preg_replace('#.*/tests/(.+):\d+#', '.../$1:###', $ErrorHandler::$test_echo_value))
		);

		$ErrorHandler::$test_php_sapi_name = 'server';
		$ErrorHandler->view(new RuntimeException('Text exception'), ['format' => 'json']);
		$this->assertSame(
			'{"error": "500 Internal Server Error"}',
			trim((string)preg_replace('#.*/tests/(.+):\d+#', '.../$1:###', $ErrorHandler::$test_echo_value))
		);
	}

	public function testHandleErrorException(): void
	{
		$ErrorHandler = new class extends ErrorHandler {
			public Throwable $test_exception;

			public function handle_exception(Throwable $e): void
			{
				$this->test_exception = $e;
			}
		};
		$ErrorHandler->set_terminate(false);
		$res = $ErrorHandler->handle_error(E_WARNING, 'Error message', '/tmp/err.php', 1);
		$this->assertTrue($res);

		$this->assertInstanceOf(ErrorException::class, $ErrorHandler->test_exception);
		$this->assertEquals('Error message', $ErrorHandler->test_exception->getMessage());
		$this->assertEquals(E_WARNING, $ErrorHandler->test_exception->getSeverity());
		$this->assertEquals('/tmp/err.php', $ErrorHandler->test_exception->getFile());
		$this->assertEquals(1, $ErrorHandler->test_exception->getLine());
	}

	public function testHandleException(): void
	{
		$ErrorHandler = new class extends ErrorHandler {
			public static bool $test_is_cli_value = false;

			protected static function is_cli(): bool
			{
				return static::$test_is_cli_value;
			}
		};
		$ErrorHandler->set_terminate(false);

		$excep = new ErrorException('Test exception');

		$log_called = null;
		$ErrorHandler->set_log(function (Throwable $e) use (&$log_called) {
			$log_called = $e;
		});

		$view_called = null;
		$ErrorHandler->set_view(function (Throwable $e, $m) use (&$view_called) {
			$view_called = [$e, $m];
		});

		$ErrorHandler->handle_exception($excep);

		$this->assertSame($excep, $log_called);

		$this->assertSame([$excep, null], $view_called);

		$ErrorHandler::$test_is_cli_value = true;
		$ErrorHandler->handle_exception($excep);

		$this->assertSame($excep, $log_called);

		$this->assertSame([$excep, null], $view_called);

		$ErrorHandler = new class extends ErrorHandler {
			public static bool $test_is_cli_value = false;
			/** @var array{0: Throwable, 1: mixed}|null */
			public static ?array $test_view_called = null;
			public static bool $test_exit_called = false;

			protected static function is_cli(): bool
			{
				return static::$test_is_cli_value;
			}

			public static function view(Throwable $e, mixed $m = null): void
			{
				static::$test_view_called = [$e, $m];
			}

			protected function exit(int $code = 1): void
			{
				static::$test_exit_called = true;
			}
		};

		$ErrorHandler->set_terminate(false);

		$ErrorHandler->handle_exception($excep);

		$this->assertNull($ErrorHandler::$test_view_called);

		$ErrorHandler::$test_is_cli_value = true;
		$ErrorHandler->handle_exception($excep);

		$this->assertSame([$excep, null], $view_called);

		$ErrorHandler->set_terminate(true);
		$ErrorHandler->handle_exception($excep);

		$this->assertSame([$excep, null], $view_called);
		$this->assertTrue($ErrorHandler::$test_exit_called);
	}

	public function testHandleShutdown(): void
	{
		$ErrorHandler = new class extends ErrorHandler {
			/** @var array{'type': int, 'message': string, 'file': ?string, 'line': ?int}|null */
			public ?array $test_handle_error_called = null;
			public bool $test_error_get_last_null = true;

			public function handle_error(int $errno, string $errstr, ?string $errfile = null, ?int $errline = null): bool
			{
				$this->test_handle_error_called = ['type' => $errno, 'message' => $errstr, 'file' => $errfile, 'line' => $errline];

				return true;
			}

			/** @return array{'type': int, 'message': string, 'file': ?string, 'line': ?int}|null */
			protected function error_get_last(): ?array
			{
				return $this->test_error_get_last_null ? null : [
					'type' => E_ERROR,
					'message' => 'Fatal error occurred',
					'file' => '/path/script.php',
					'line' => 99,
				];
			}
		};

		$ErrorHandler->handle_shutdown();

		$this->assertNull($ErrorHandler->test_handle_error_called);

		$ErrorHandler->test_error_get_last_null = false;
		$ErrorHandler->handle_shutdown();

		$this->assertIsArray($ErrorHandler->test_handle_error_called);
		$this->assertSame(E_ERROR, $ErrorHandler->test_handle_error_called['type']);
		$this->assertSame('Fatal error occurred', $ErrorHandler->test_handle_error_called['message']);
		$this->assertSame('/path/script.php', $ErrorHandler->test_handle_error_called['file']);
		$this->assertSame(99, $ErrorHandler->test_handle_error_called['line']);
	}

	public function testSeverityLabel(): void
	{
		$ErrorHandler = new class extends ErrorHandler {
			public static function test_severity_label(int $severity): string
			{
				return self::severity_label($severity);
			}
		};

		foreach ([
					 E_COMPILE_ERROR => 'E_COMPILE_ERROR',
					 E_CORE_ERROR => 'E_CORE_ERROR',
					 E_DEPRECATED => 'E_DEPRECATED',
					 E_ERROR => 'E_ERROR',
					 E_NOTICE => 'E_NOTICE',
					 E_PARSE => 'E_PARSE',
					 E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
					 E_USER_DEPRECATED => 'E_USER_DEPRECATED',
					 E_USER_ERROR => 'E_USER_ERROR',
					 E_USER_WARNING => 'E_USER_WARNING',
					 E_WARNING => 'E_WARNING',
					 -1 => 'E_UNKNOWN', // Custom/unmatched
				 ] as $code => $expectedLabel) {
			$this->assertSame(
				$expectedLabel,
				$ErrorHandler::test_severity_label($code),
				"Failed asserting severity label for code $code"
			);
		}
	}

	public function testPHPSAPIName(): void
	{
		$ErrorHandler = new class extends ErrorHandler {
			public static function testPHPSAPIName(): string
			{
				return static::php_sapi_name();
			}
		};

		$this->assertTrue(strlen($ErrorHandler::testPHPSAPIName()) > 0);
	}

	public function testEcho(): void
	{
		$ErrorHandler = new class extends ErrorHandler {
			public static function testEcho(): string
			{
				ob_start();
				static::echo('test-string');

				return (string)ob_get_clean();
			}
		};

		$this->assertSame('test-string', $ErrorHandler::testEcho());
	}

	public function testErrorLast(): void
	{
		$ErrorHandler = new class extends ErrorHandler {
			/** @return array{'type': int, 'message': string, 'file': ?string, 'line': ?int}|null */
			public function testGetErrorLast(): ?array
			{
				return $this->error_get_last();
			}
		};

		$this->assertNull($ErrorHandler->testGetErrorLast());
	}

	public function testErrorLog(): void
	{

		if (!$tmpfile = tmpfile()) {
			$this->fail('Could not create tmpfile');
		}

		if (!$filename = stream_get_meta_data($tmpfile)['uri'] ?? null) {
			$this->fail('Could not get tmpfile uri');
		}


		$ErrorHandler = new class extends ErrorHandler {
			public static function testGetErrorLogMsgType(): int
			{
				return static::$error_log_msg_type;
			}

			public static function testGetErrorDestination(): ?string
			{
				return static::$error_log_destination;
			}

			public static function testSetErrorLogMsgType(int $i): void
			{
				static::$error_log_msg_type = $i;
			}

			public static function testSetErrorDestination(?string $dest): void
			{
				static::$error_log_destination = $dest;
			}

			public function testErrorLog(): void
			{
				static::error_log('test');
			}
		};

		$this->assertSame(0, $ErrorHandler::testGetErrorLogMsgType());
		$this->assertNull($ErrorHandler::testGetErrorDestination());

		$ErrorHandler::testSetErrorLogMsgType(3);
		$ErrorHandler::testSetErrorDestination($filename);

		$ErrorHandler->testErrorLog();

		rewind($tmpfile);
		$this->assertSame('test', stream_get_contents($tmpfile));
		fclose($tmpfile);

		$ErrorHandler::testSetErrorLogMsgType(0);
		$ErrorHandler::testSetErrorDestination(null);
	}

	private function normalise_log_line(string $log): string
	{
		return (string)preg_replace(
			[
				'/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2}/',
				'~ /.*/tests/(.+):\d+~',
				'/:(\d+)$/'
			],
			[
				'####-##-##T##:##:##+##:##',
				' .../$1:###',
				':###'
			],
			trim($log)
		);
	}
}
