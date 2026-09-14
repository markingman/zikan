<?php

namespace Zikan\Http;

use Zikan\Exception\ResponseError;
use Zikan\Exception\ResponseException;
use Zikan\TestHelpersTrait;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
	use TestHelpersTrait;

	public static function setUpBeforeClass(): void
	{
		static::tmpdir_make();
	}

	public static function tearDownAfterClass(): void
	{
		static::tmpdir_remove();
	}

	public function testCreate(): void
	{
		$this->assertInstanceOf(ResponseInterface::class, new Response());

		$Response = new class extends Response {
			public function get_terminate_after_response(): bool
			{
				return $this->terminate_after_response;
			}
		};

		$Response = new $Response();
		$this->assertTrue($Response->get_terminate_after_response());

		$Response = new $Response(false);
		$this->assertFalse($Response->get_terminate_after_response());
	}

	public function testSetCharSet(): void
	{
		$Response = new class(false) extends Response {
			public function get_char_set(): string
			{
				return $this->char_set;
			}
		};

		$this->assertInstanceOf(ResponseInterface::class, $Response->set_char_set('UTF-16'));
		$this->assertSame('UTF-16', $Response->get_char_set());
	}

	public function testSetResponseCode(): void
	{
		$Response = new class(false) extends Response {
			public function get_response_code(): int
			{
				return $this->response_code;
			}
		};

		$this->assertInstanceOf(ResponseInterface::class, $Response->set_response_code(500));
		$this->assertSame(500, $Response->get_response_code());
	}

	public function testSetAndUnsetHeader(): void
	{
		$Response = new class(false) extends Response {
			/** @return array<string, string> */
			public function get_headers(): array
			{
				return $this->headers;
			}
		};

		$this->assertSame([], $Response->get_headers());

		$this->assertInstanceOf(ResponseInterface::class, $Response->set_header('X-Test', 'test'));
		$this->assertSame(['X-Test' => 'test'], $Response->get_headers());

		$Response->unset_header('X-Test');
		$this->assertSame([], $Response->get_headers());
	}

	public function testSetAndUnsetCookie(): void
	{
		$Response = new class(false) extends Response {
			/** @return array<string, Cookie> */
			public function get_cookies(): array
			{
				return $this->cookies;
			}
		};
		$this->assertSame([], $Response->get_cookies());

		$t = time() + 60;
		$this->assertInstanceOf(
			ResponseInterface::class,
			$Response->set_cookie(
				name: 'test',
				value: 'value',
				expires: $t,
				path: '/',
				domain: 'example.com',
				secure: true,
				httponly: true,
			)
		);

		$this->assertEquals([
			'test' =>
				new Cookie(
					value: 'value',
					expires: $t,
					path: '/',
					domain: 'example.com',
					secure: true,
					httponly: true,
				)
		], $Response->get_cookies());

		$this->assertInstanceOf(ResponseInterface::class, $Response->unset_cookie('test'));
		$this->assertSame([], $Response->get_cookies());
	}

	public function testHtml(): void
	{
		$mock = $this->getMockBuilder(Response::class)
			->onlyMethods(['http_response_code', 'terminate'])
			->setConstructorArgs([false])
			->getMock();

		$mock->expects($this->once())
			->method('http_response_code')
			->with($this->equalTo(200));

		ob_start();
		$mock->html('<p>test</p>');
		$output = ob_get_clean();

		$this->assertEquals('<p>test</p>', $output);
	}

	public function testText(): void
	{
		$mock = $this->getMockBuilder(Response::class)
			->onlyMethods(['http_response_code', 'terminate'])
			->setConstructorArgs([false])
			->getMock();

		$mock->expects($this->once())
			->method('http_response_code')
			->with($this->equalTo(200));

		ob_start();
		$mock->text('test');
		$output = ob_get_clean();

		$this->assertEquals('test', $output);
	}

	public function testJson(): void
	{
		$mock = $this->getMockBuilder(Response::class)
			->onlyMethods(['http_response_code', 'terminate'])
			->setConstructorArgs([false])
			->getMock();

		$mock->expects($this->once())
			->method('http_response_code')
			->with($this->equalTo(200));

		ob_start();
		$mock->json('{"test": true}');
		$output = ob_get_clean();

		$this->assertEquals('{"test": true}', $output);
	}

	public function testFile(): void
	{
		$Response = new class(false) extends Response {
			public int $http_response_code_value;
			/** @var array<string> */
			public array $sent_headers;

			protected function http_response_code(int $response_code = 0): int
			{
				$this->http_response_code_value = $response_code;

				return $response_code;
			}

			protected function header(string $header): void
			{
				$this->sent_headers[] = $header;
			}
		};

		$file = $this->createFileForTest(__FUNCTION__ . '.csv', "line1,a\nline2,b\nline3,c\n");

		ob_start();
		$Response->file($file, true);
		$res = ob_get_clean();

		$this->assertEquals("line1,a\nline2,b\nline3,c\n", $res);

		$this->assertEquals(200, $Response->http_response_code_value);
		$this->assertSame([
			'Content-Type: text/csv; charset=UTF-8',
			'Content-Disposition: attachment; filename=' . __FUNCTION__ . '.csv',
			'Content-Length: 24',
			'Content-Transfer-Encoding: binary',
		], $Response->sent_headers);

		$this->assertFalse(file_exists(static::$tmpdir . '/file.csv'));
	}

	public function testFileInvalidMime(): void
	{
		$Response = new class(false) extends Response {
			protected function mime_content_type(string $filename): string|false
			{
				return false;
			}
		};

		$file = $this->createFileForTest(__FUNCTION__, "test\n");

		$this->expectException(ResponseException::class);
		$this->expectExceptionMessage('RESPONSE_MIME_TYPE');
		$this->expectExceptionCode(500);

		try {
			$Response->file($file);
		} catch (ResponseException $e) {
			$this->assertSame(ResponseError::MIME_TYPE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testFileInvalidSize(): void
	{
		$Response = new class(false) extends Response {
			protected function filesize(string $filename): int|false
			{
				return false;
			}
		};

		$file = $this->createFileForTest(__FUNCTION__, "test\n");

		$this->expectException(ResponseException::class);
		$this->expectExceptionMessage('RESPONSE_FILE_SIZE');
		$this->expectExceptionCode(500);

		try {
			$Response->file($file, true);
		} catch (ResponseException $e) {
			$this->assertSame(ResponseError::FILE_SIZE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testFileInvalidReadfile(): void
	{
		$Response = new class(false) extends Response {
			protected function readfile(string $filename): int|false
			{
				return false;
			}
		};

		$file = $this->createFileForTest(__FUNCTION__, "test\n");

		$this->expectException(ResponseException::class);
		$this->expectExceptionMessage('READFILE_FAIL');
		$this->expectExceptionCode(500);

		try {
			$Response->file($file);
		} catch (ResponseException $e) {
			$this->assertSame(ResponseError::READFILE_FAIL, $e->getErrorCode());
			throw $e;
		}
	}

	public function testFileInvalidUnlink(): void
	{
		$Response = new class(false) extends Response {
			protected function readfile(string $filename): int
			{
				return 1;
			}

			protected function unlink(string $file): bool
			{
				return false;
			}
		};

		$file = $this->createFileForTest(__FUNCTION__, "test\n");

		$this->expectException(ResponseException::class);
		$this->expectExceptionMessage('UNLINK_FAIL');
		$this->expectExceptionCode(500);

		try {
			$Response->file($file);
		} catch (ResponseException $e) {
			$this->assertSame(ResponseError::UNLINK_FAIL, $e->getErrorCode());
			throw $e;
		}
	}

	public function testRespond(): void
	{
		$Response = new class(false) extends Response {
			public string $output = '';
			public int $http_response_code_value;
			/** @var array<string> */
			public array $sent_headers;

			protected function setcookie(
				string $name,
				string $value = '',
				int $expires_or_options = 0,
				string $path = '',
				string $domain = '',
				bool $secure = false,
				bool $httponly = false
			): bool {
				$time = time();
				$this->sent_headers[] = vsprintf(
					'Set-Cookie: %1$s=%2$s; expires=%3$s; Max-Age=%4$d; path=%5$s; domain=%6$s%7$s%8$s',
					[
						1 => $name,
						2 => $value,
						3 => gmdate('D, d M Y H:i:s \G\M\T', $expires_or_options),
						4 => $expires_or_options < $time ? 0 : $expires_or_options - $time,
						5 => $path,
						6 => $domain,
						7 => $secure ? '; secure' : '',
						8 => $httponly ? '; HttpOnly' : '',
					],
				);

				return parent::setcookie($name, $value, $expires_or_options, $path, $domain, $secure, $httponly);
			}

			protected function http_response_code(int $response_code = 0): int
			{
				$this->http_response_code_value = $response_code;

				return $response_code;
			}

			protected function header(string $header): void
			{
				$this->sent_headers[] = $header;
			}

			protected function echo(string $echo): void
			{
				$this->output = $echo;
			}
		};

		$Response->set_header('X-Test', 'test');
		$Response->set_response_code(401);
		$Response->set_cookie(
			name: 'test',
			value: 'value',
			expires: 1,
			path: '/',
			domain: 'example.com',
			secure: true,
			httponly: true,
		);

		$Response->respond('test1');

		$this->assertSame('test1', $Response->output);

		$this->assertEquals(401, $Response->http_response_code_value);
		$this->assertSame([
			'X-Test: test',
			'Set-Cookie: test=value; expires=Thu, 01 Jan 1970 00:00:01 GMT; Max-Age=0; path=/; domain=example.com; secure; HttpOnly',
		], $Response->sent_headers);
	}

	public function testRespondAndRemoveHeaders(): void
	{
		$mock = $this->getMockBuilder(Response::class)
			->onlyMethods(['header_remove', 'http_response_code', 'header', 'setcookie', 'terminate', 'echo'])
			->setConstructorArgs([false])
			->getMock();

		$mock->expects($this->once())->method('header_remove');
		$mock->expects($this->once())->method('http_response_code')->with($this->anything());
		$mock->expects($this->any())->method('header');
		$mock->expects($this->any())->method('setcookie');
		$mock->expects($this->once())->method('terminate');
		$mock->expects($this->once())->method('echo')->with('test content');

		$mock->respond('test content', remove_headers: true);
	}

	public function testHeaderRemoveIsActuallyExecuted(): void
	{
		$Response = new class(false) extends Response {
			/** @var array<string> $called */
			public array $called = [];

			protected function echo(string $echo): void
			{
				$this->called[] = 'echo';
			}

			protected function header(string $header): void
			{
				$this->called[] = 'header';
			}

			protected function setcookie(
				string $name,
				string $value = '',
				int $expires_or_options = 0,
				string $path = '',
				string $domain = '',
				bool $secure = false,
				bool $httponly = false
			): bool {
				$this->called[] = 'setcookie';

				return true;
			}

			protected function http_response_code(int $response_code = 0): int
			{
				$this->called[] = 'http_response_code';

				return $response_code;
			}

			protected function terminate(): void
			{
				$this->called[] = 'terminate';
			}
		};

		$Response->respond('content', true);
		$this->assertContains('echo', $Response->called);
	}


	public function testRedirect(): void
	{
		$mock = $this->getMockBuilder(Response::class)
			->onlyMethods(['http_response_code', 'terminate'])
			->setConstructorArgs([false])
			->getMock();

		$mock->expects($this->once())
			->method('http_response_code')
			->with($this->equalTo(307));

		$mock->expects($this->once())
			->method('terminate');

		$mock->redirect('https://example.com/', 307);
	}

	private function createFileForTest(string $name, string $content = ''): string
	{
		$path = static::$tmpdir . '/' . $name;
		if (!file_put_contents($path, $content)) {
			$this->fail("Could not write test file '$name'");
		}

		return $path;
	}
}
