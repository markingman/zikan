<?php

namespace Zikan\Errors;

use Closure;
use ErrorException;
use Zikan\Logs\LogHandler;
use Throwable;

/*
Example:

1) Set handlers:

register_shutdown_function([$ErrorHandler, 'handle_shutdown']);
set_error_handler([$ErrorHandler, 'handle_error']);
set_exception_handler([$ErrorHandler, 'handle_exception']);

2) Set log and view: (any Closures)

$ErrorHandler->set_log(function (Throwable $e) use ($LogHandler, $debug) {
	return (include '/app/error_log_function.php')($e, $LogHandler, $debug);
});

$ErrorHandler->set_view(function (Throwable $e, $m = null) use ($Response, $Page) {
	return (include '/app/error_view_function.php')($Response, $Page, $e);
});
*/

class ErrorHandler
{
	/** @var array<int, string> */
	protected const array ERROR_CODES = [
		400 => '400 Bad Request',
		401 => '401 Unauthorized',
		403 => '403 Forbidden',
		404 => '404 Not Found',
		405 => '405 Method Not Allowed',
		408 => '408 Request Timeout',
		409 => '409 Conflict',
		410 => '410 Gone',
		413 => '413 Payload Too Large',
		415 => '415 Unsupported Media Type',
		422 => '422 Unprocessable Entity',
		429 => '429 Too Many Requests',
		500 => '500 Internal Server Error',
		501 => '501 Not Implemented',
		502 => '502 Bad Gateway',
		503 => '503 Service Unavailable',
		504 => '504 Gateway Timeout',
	];

	protected static int $error_log_msg_type = 0;
	protected static ?string $error_log_destination = null;

	protected bool $terminate = true;
	protected bool $debug = false;

	/** @var Closure(Throwable): void|null */
	protected ?Closure $log = null;

	/** @var Closure(Throwable, mixed): void|null */
	protected ?Closure $view = null;

	public static function log(Throwable $e, ?LogHandler $LogHandler = null, bool $debug = false): void
	{
		// basic placeholder, override with custom function
		// only logs first and root exceptions, not full chain

		$sev = $e instanceof ErrorException ? $e->getSeverity() : E_ERROR;

		$log = '';
		$i = 0;

		do {
			if ($i === 0 or $e->getPrevious() === null) {
				$log .= sprintf(
						"%s[%d] %s in %s:%d",
						$i === 0 ? static::severity_label($sev) : 'CAUSE: ',
						$e->getCode(),
						get_class($e) . ': ' . $e->getMessage(),
						$e->getFile(),
						$e->getLine()
					) . PHP_EOL;

				if ($debug and $i === 0) {
					$log .= $e->getTraceAsString() . PHP_EOL;
				}
			}
			$e = $e->getPrevious();
			$i++;
		} while ($e instanceof Throwable);

		if ($LogHandler) {
			$LogHandler->log($log, $sev);
		} else {
			static::error_log(date('c') . "\t" . $log);
		}
	}

	public static function view(Throwable $e, mixed $m = null): void
	{
		// basic placeholder, override with custom function

		if (static::is_cli()) {
			static::echo(vsprintf(
				PHP_EOL .
				"\e[3;101m  %1\$s  \e[0m" . PHP_EOL .
				'%2$s' . PHP_EOL .
				'%3$s:%4$s' . PHP_EOL .
				PHP_EOL,
				[
					1 => get_class($e),
					2 => $e->getMessage(),
					3 => $e->getFile(),
					4 => $e->getLine(),
				]
			));
		} else {
			$code = array_key_exists($e->getCode(), static::ERROR_CODES) ? $e->getCode() : 500;

			if (is_array($m) and ($m['format'] ?? '') === 'json') {
				static::header('Content-Type: application/json', true, $code);
				$res = '{"error": "%s"}';
			} else {
				static::header('Content-Type: text/html', true, $code);
				$res = <<<__
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>%1\$s</title>
	<style>*{color:#111;background:#ddd;line-height:1.6}body{font-size:16px;}</style>
</head>
<body>
	<pre><b>%1\$s</b></pre>
</body>
</html>
__;
			}

			static::echo(vsprintf($res, [static::ERROR_CODES[$code]]));
		}
	}

	protected static function error_log(string $log): void
	{
		error_log($log, match(static::$error_log_msg_type){
			0, 1, 3, 4 => static::$error_log_msg_type,
			default => 0
		}, static::$error_log_destination);
	}

	protected static function severity_label(int $severity): string
	{
		return match ($severity) {
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
			default => 'E_UNKNOWN',
		};
	}

	protected static function is_cli(): bool
	{
		return str_contains(static::php_sapi_name(), 'cli');
	}

	protected static function echo(string $string): void
	{
		echo $string;
	}

	protected static function php_sapi_name(): string
	{
		return php_sapi_name();
	}

	protected static function header(string $header, bool $replace = true, int $response_code = 0): void
	{
		header($header, $replace, $response_code);
	}

	public function set_terminate(bool $terminate): void
	{
		$this->terminate = $terminate;
	}

	public function set_debug(bool $active): void
	{
		$this->debug = $active;
	}

	public function set_view(Closure $view): void
	{
		$this->view = $view;
	}

	public function set_log(Closure $log): void
	{
		$this->log = $log;
	}

	public function handle_error(int $errno, string $errstr, ?string $errfile = null, ?int $errline = null): bool
	{
		// change error messages into ErrorException
		$this->handle_exception(
		// note this is not `throw new ...`
			new ErrorException($errstr, 0, $errno, $errfile, $errline)
		);

		return true;
	}

	public function handle_exception(Throwable $e): void
	{
		if ($this->log) {// callback can ignore or log
			($this->log)($e);
		}

		if ($this->view) {// callback can ignore or view
			($this->view)($e, null);
		} elseif (static::is_cli()) {
			static::view($e);
		}

		if ($this->terminate) {
			$this->exit();
		}
	}

	public function handle_shutdown(): void
	{
		if (($err = $this->error_get_last()) !== null) {
			if (((E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR) & $err['type']) !== 0) {
				// fatal error handler
				$this->handle_error($err['type'], $err['message'], $err['file'], $err['line']);
			}
		}
	}

	/** @return array{'type': int, 'message': string, 'file': ?string, 'line': ?int}|null */
	protected function error_get_last(): ?array
	{
		return error_get_last();
	}

	protected function exit(int $code = 1): void
	{
		exit(($code >= 1 and $code <= 255) ? $code : 1);// @codeCoverageIgnore
	}
}
