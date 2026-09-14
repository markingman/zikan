<?php

namespace Zikan\Http;

use DomainException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RequestTest extends TestCase
{
	/** @var array<string, string> $headers */
	protected array $headers = [];
	/** @var array<string, mixed> $get */
	protected array $get = [];
	/** @var array<string, mixed> $post */
	protected array $post = [];
	/** @var array<string, string|int> $files */
	protected array $files = [];
	/** @var array<string, mixed> $server */
	protected array $server = [];
	/** @var array<string, mixed> $cookie */
	protected array $cookie = [];

	public function testCreate(): void
	{
		$this->assertInstanceOf(RequestInterface::class, new Request([], [], [], [], []));
	}

	public function testSetGetHeaders(): void
	{
		$Request = new class([], [], [], [], []) extends Request {
			public string $test_php_sapi_name_value = '';
			/** @var array<string, string> */
			public array $test_getallheaders_value = [];

			protected function php_sapi_name(): string
			{
				return $this->test_php_sapi_name_value;
			}

			/** @return array<string, string> */
			protected function getallheaders(): array
			{
				return $this->test_getallheaders_value;
			}

			/** @return array<string, string>|null */
			public function test_get_headers(): ?array
			{
				return $this->headers;
			}

			public function test_reset_headers(): void
			{
				$this->headers = null;
			}
		};

		$Request->test_php_sapi_name_value = 'cli';
		$Request->set_headers();
		$this->assertSame([], $Request->test_get_headers());

		$Request->test_php_sapi_name_value = 'server';
		$Request->test_getallheaders_value = ['x-example' => 'example'];
		$Request->set_headers();
		$this->assertSame(['x-example' => 'example'], $Request->test_get_headers());

		$Request->set_headers(['X-Updated' => 'updated']);
		$this->assertSame(['x-updated' => 'updated'], $Request->test_get_headers());

		$Request->set_headers([]);
		$this->assertSame([], $Request->test_get_headers());

		$Request->set_headers(['X-Updated' => 'updated']);
		$this->assertSame('updated', $Request->get_header('x-updated'));
		$this->assertSame('updated', $Request->get_header('X-Updated'));
		$this->assertNull($Request->get_header('X-Test'));

		$Request->test_reset_headers();
		$Request->test_getallheaders_value = [];
		$this->assertNull($Request->get_header('X-Test'));
		$Request->test_reset_headers();
		$Request->test_getallheaders_value = ['x-test' => 'value'];
		$this->assertSame('value', $Request->get_header('X-Test'));

		$Request = new Request([], [], [], [], []);
		$this->assertNull($Request->get_header('X-Test'));
		$Request->set_headers();
		$this->assertNull($Request->get_header('X-Test'));
		$Request->set_headers(['X-Test' => 'test']);
		$this->assertSame('test', $Request->get_header('X-Test'));

		$Request = new class([], [], [], [], []) extends Request {
			protected function php_sapi_name(): string
			{
				return 'server';
			}
		};

		$Request->set_headers();
		$this->assertNull($Request->get_header('X-Test'));
		$Request->set_headers(['X-Test' => 'test']);
		$this->assertSame('test', $Request->get_header('X-Test'));
	}

	public function testSetAndGetGet(): void
	{
		$Request = new Request(GET: [
			'foo' => 'bar',
			'var' => 'value',
			'var_bad' => '!`$$',
			'var_bad2' => '1value',
			'int' => '100',
			'int_neg' => '-1',
			'int_bad' => '`@)',
			'val' => 'string value',
			'val_complex' => '[string], & (value);',
			'val_bad' => '•`value',
			'array' => ['a', 'b'],
			'array_int' => ['10', '20'],
			'array_complex' => ['a', '!`$$'],
		], POST: [], FILES: [], SERVER: [], COOKIE: []);

		$this->assertNull($Request->get_get('bar'));
		$this->assertSame('bar', $Request->get_get('foo'));

		$this->assertNull($Request->get_get('new'));
		$Request->set_get_value('new', 'value');
		$this->assertSame('value', $Request->get_get('new'));

		$this->assertSame('!`$$', $Request->get_get('var_bad'));

		$this->assertSame(0, $Request->get_int_from_get('int_unknown'));
		$this->assertSame(10, $Request->get_int_from_get('int_unknown', 10));

		$this->assertSame(100, $Request->get_int_from_get('int'));
		$this->assertSame(101, $Request->get_int_from_get('int', 101, min_range: 102));

		$this->assertSame(0, $Request->get_int_from_get('int_neg'));
		$this->assertSame(-1, $Request->get_int_from_get('int_neg', 0, -10));
		$this->assertSame(-1, $Request->get_int_from_get('int_neg', 0, -1));

		$this->assertSame(100, $Request->get_int_from_get(['int_unknown', 'int']));
		$this->assertSame(100, $Request->get_int_from_get(['int', 'int_unknown']));

		$this->assertSame(1, $Request->get_int_from_get('int_bad', 1));

		$Request->set_get_value('int_new', '200');
		$this->assertSame(200, $Request->get_int_from_get('int_new'));

		$this->assertSame('value', $Request->get_var_from_get('var'));
		$this->assertSame('', $Request->get_var_from_get('var_unknown'));
		$this->assertSame('default', $Request->get_var_from_get('var_unknown', 'default'));

		$this->assertSame('', $Request->get_var_from_get('var_bad'));
		$this->assertSame('', $Request->get_var_from_get('var_bad2'));

		$this->assertSame('string value', $Request->get_val_from_get('val'));
		$this->assertSame('[string], & (value);', $Request->get_val_from_get('val_complex'));
		$this->assertSame('', $Request->get_val_from_get('val_unknown'));
		$this->assertSame('default', $Request->get_val_from_get('val_unknown', 'default'));

		$this->assertSame('', $Request->get_var_from_get('val_bad'));

		$this->assertSame('value', $Request->get_sel_from_get('var', ['value', 'other']));
		$this->assertSame('', $Request->get_sel_from_get('other', ['value', 'other']));
		$this->assertSame('default', $Request->get_sel_from_get('other', ['value', 'other'], 'default'));

		$this->assertSame('', $Request->get_sel_from_get('var_bad', ['!`$$', 'other']));

		$this->assertSame(['a', 'b'], $Request->get_array_from_get('array'));
		$this->assertSame(['10', '20'], $Request->get_array_from_get('array_int'));
		$this->assertSame(['a', '!`$$'], $Request->get_array_from_get('array_complex'));
		$this->assertSame(['c'], $Request->get_array_from_get('array_unknown', ['c']));
	}

	public function testGetPost(): void
	{
		$Request = new Request(GET: [], POST: [
			'foo' => 'bar',
			'var' => 'value',
			'var_bad' => '!`$$',
			'var_bad2' => '1value',
			'int' => '100',
			'int_neg' => '-1',
			'int_bad' => '`@)',
			'val' => 'string value',
			'val_complex' => '[string], & (value);',
			'val_bad' => '•`value',
			'array' => ['a', 'b'],
			'array_int' => ['10', '20'],
			'array_complex' => ['a', '!`$$'],
		], FILES: [], SERVER: [], COOKIE: []);

		$this->assertNull($Request->get_post('bar'));
		$this->assertSame('bar', $Request->get_post('foo'));

		$this->assertNull($Request->get_post('new'));

		$this->assertSame('!`$$', $Request->get_post('var_bad'));

		$this->assertSame(0, $Request->get_int_from_post('int_unknown'));
		$this->assertSame(10, $Request->get_int_from_post('int_unknown', 10));

		$this->assertSame(100, $Request->get_int_from_post('int'));
		$this->assertSame(101, $Request->get_int_from_post('int', 101, min_range: 102));

		$this->assertSame(0, $Request->get_int_from_post('int_neg'));
		$this->assertSame(-1, $Request->get_int_from_post('int_neg', 0, -10));
		$this->assertSame(-1, $Request->get_int_from_post('int_neg', 0, -1));

		$this->assertSame(100, $Request->get_int_from_post(['int_unknown', 'int']));
		$this->assertSame(100, $Request->get_int_from_post(['int', 'int_unknown']));

		$this->assertSame(1, $Request->get_int_from_post('int_bad', 1));

		$this->assertSame('value', $Request->get_var_from_post('var'));
		$this->assertSame('', $Request->get_var_from_post('var_unknown'));
		$this->assertSame('default', $Request->get_var_from_post('var_unknown', 'default'));

		$this->assertSame('', $Request->get_var_from_post('var_bad'));
		$this->assertSame('', $Request->get_var_from_post('var_bad2'));

		$this->assertSame('string value', $Request->get_val_from_post('val'));
		$this->assertSame('[string], & (value);', $Request->get_val_from_post('val_complex'));
		$this->assertSame('', $Request->get_val_from_post('val_unknown'));
		$this->assertSame('default', $Request->get_val_from_post('val_unknown', 'default'));

		$this->assertSame('', $Request->get_var_from_post('val_bad'));

		$this->assertSame('value', $Request->get_sel_from_post('var', ['value', 'other']));
		$this->assertSame('', $Request->get_sel_from_post('other', ['value', 'other']));
		$this->assertSame('default', $Request->get_sel_from_post('other', ['value', 'other'], 'default'));

		$this->assertSame('', $Request->get_sel_from_post('var_bad', ['!`$$', 'other']));

		$this->assertSame(['a', 'b'], $Request->get_array_from_post('array'));
		$this->assertSame(['10', '20'], $Request->get_array_from_post('array_int'));
		$this->assertSame(['a', '!`$$'], $Request->get_array_from_post('array_complex'));
		$this->assertSame(['c'], $Request->get_array_from_post('array_unknown', ['c']));
	}


	public function testGetFiles(): void
	{
		$Request = new Request(GET: [], POST: [], FILES: [
			'file' => [
				'name' => 'test.jpg',
				'type' => 'image/jpeg',
				'tmp_name' => '/tmp/phpn3FyFr',
				'error' => 0,
				'size' => 1024,
				'full_path' => '/example/test.jpg',
			],
			'file_bad' => 'value',
		], SERVER: [], COOKIE: []);

		$this->assertEquals([
			'name' => 'test.jpg',
			'type' => 'image/jpeg',
			'tmp_name' => '/tmp/phpn3FyFr',
			'error' => 0,
			'size' => 1024,
			'full_path' => '/example/test.jpg',
		], $Request->get_file('file'));

		$this->assertNull($Request->get_file('file_bad'));
		$this->assertNull($Request->get_file('file_unknown'));
	}

	public function testGetServer(): void
	{
		$Request = new Request(GET: [], POST: [], FILES: [], SERVER: [
			'server' => 'value',
			'server_bad' => ['value'],
		], COOKIE: []);

		$this->assertEquals('value', $Request->get_server('server'));

		$this->assertNull($Request->get_server('server_bad'));
		$this->assertNull($Request->get_server('server_unknown'));
	}

	public function testGetCookie(): void
	{
		$Request = new Request(GET: [], POST: [], FILES: [], SERVER: [], COOKIE: [
			'cookie' => 'value',
			'cookie_complex' => '{"key":"value"}',
			'cookie_bad' => 100,
		]);

		$this->assertEquals('value', $Request->get_cookie('cookie'));
		$this->assertEquals('{"key":"value"}', $Request->get_cookie('cookie_complex'));

		$this->assertNull($Request->get_cookie('cookie_bad'));
		$this->assertNull($Request->get_cookie('cookie_unknown'));
	}

	public function testSetGetMethod(): void
	{
		$Request = new Request(GET: [], POST: [], FILES: [], SERVER: [
			'REQUEST_METHOD' => 'PATCH',
		], COOKIE: []);

		$this->assertEquals('PATCH', $Request->get_method());

		$Request->set_method('POST');
		$this->assertEquals('POST', $Request->get_method());
	}

	public function testSetGetPath(): void
	{
		$Request = new Request(GET: [], POST: [], FILES: [], SERVER: [
			'REQUEST_URI' => '/redirect/path',
		], COOKIE: []);

		$this->assertEquals('/redirect/path', $Request->get_path());

		$Request->set_path('http://example.com/test/path?a=1&b=2');
		$this->assertEquals('/test/path', $Request->get_path());

		$Request->set_path('/test/path/only');
		$this->assertEquals('/test/path/only', $Request->get_path());
	}

	public function testSetInvalidInput(): void
	{
		$Request = new class(GET: [], POST: [], FILES: [], SERVER: [], COOKIE: []) extends Request {
			public function test_invalid_input(): void
			{
				$this->get_int_request('INVALID');
			}
		};

		$this->expectException(DomainException::class);
		$this->expectExceptionMessage("Unsupported input source: 'INVALID'");

		$Request->test_invalid_input();
	}

	public function testSetInvalidPath(): void
	{
		$Request = new class(GET: [], POST: [], FILES: [], SERVER: [
			'REQUEST_URI' => 'BAD_PATH',
		], COOKIE: []) extends Request {
			protected function parse_url(string $url, int $component = -1): int|string|array|null|false
			{
				return false;
			}
		};

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Set path parse URL failed');
// 		$this->expectExceptionCode(500);

		$Request->get_path();
	}

	public function testGetRequest(): void
	{
		$Request = new class(GET: [], POST: [], FILES: [], SERVER: [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI' => '/redirect/path',
		], COOKIE: []) extends Request {
// 			protected function parse_url(string $url, int $component = -1): int|string|array|null|false
// 			{
// 				return false;
// 			}
		};

		$res = $Request->get_request();
		$this->assertEquals(['POST', '/redirect/path'], $res);

		$res = $Request->get_request('DELETE');
		$this->assertEquals(['DELETE', '/redirect/path'], $res);

		$res = $Request->get_request(path: '/test');
		$this->assertEquals(['DELETE', '/test'], $res);

		$res = $Request->get_request('GET', '/path');
		$this->assertEquals(['GET', '/path'], $res);
	}

	public function testGetUA(): void
	{
		$Request = new Request(GET: [], POST: [], FILES: [], SERVER: [
			'HTTP_USER_AGENT' => 'ua-string-1',
		], COOKIE: []);

		$this->assertEquals('ua-string-1', $Request->get_ua());

		$Request->set_ua('ua-string-2');
		$this->assertEquals('ua-string-2', $Request->get_ua());
	}

	public function testGetIP(): void
	{
		$Request = new Request(GET: [], POST: [], FILES: [], SERVER: [
			'REMOTE_ADDR' => '1.1.1.1',
		], COOKIE: []);

		$this->assertEquals('1.1.1.1', $Request->get_ip());

		$Request->set_ip('--invalid--');
		$this->assertEquals('', $Request->get_ip());

		$Request->set_ip('0.0.0.0');
		$this->assertEquals('0.0.0.0', $Request->get_ip());
	}

	public function testGetIPProxy(): void
	{
		$Request = new Request(GET: [], POST: [], FILES: [], SERVER: [
			'HTTP_X_FORWARDED_FOR' => '1.1.1.2',
		], COOKIE: []);

		$this->assertEquals('1.1.1.2', $Request->get_ip());
	}

	public function testSetGetReferer(): void
	{
		$Request = new Request(GET: [], POST: [], FILES: [], SERVER: [
			'HTTP_REFERER' => 'http://example.com',
		], COOKIE: []);

		$this->assertEquals('http://example.com', $Request->get_ref());

		$Request->set_ref('http://example.com/1');
		$this->assertEquals('http://example.com/1', $Request->get_ref());
	}

	public function testAllGet(): void
	{
		$Request = new Request(GET: ['a' => 'A'], POST: [], FILES: [], SERVER: [], COOKIE: []);
		$this->assertSame(['a' => 'A'], $Request->all_get());
	}

	public function testAllGetx(): void
	{
		$Request = new Request(GET: [], POST: [], FILES: [], SERVER: [], COOKIE: []);
		$Request->set_get_value('a', 'A');
		$this->assertSame(['a' => 'A'], $Request->all_getx());
	}

	public function testAllPost(): void
	{
		$Request = new Request(GET: [], POST: ['a' => 'A'], FILES: [], SERVER: [], COOKIE: []);
		$this->assertSame(['a' => 'A'], $Request->all_post());
	}

	public function testAllFiles(): void
	{
		$Request = new Request(GET: [], POST: ['a' => 'A'], FILES: ['a' => 'A'], SERVER: [], COOKIE: []);
		$this->assertSame(['a' => 'A'], $Request->all_files());
	}

	public function testIsSSL(): void
	{
		$Request = new Request(GET: [], POST: [], FILES: [], SERVER: [], COOKIE: []);
		$this->assertFalse($Request->is_ssl());

		$Request = new Request(GET: [], POST: [], FILES: [], SERVER: [
			'HTTPS' => 'yes',
			'SERVER_PORT' => '443',
		], COOKIE: []);

		$this->assertTrue($Request->is_ssl());
		$this->assertTrue($Request->is_ssl(44300));
		$Request->clear_cache();
		$this->assertFalse($Request->is_ssl(44300));
	}

	public function testIsPost(): void
	{
		$Request = new Request(GET: [], POST: [], FILES: [], SERVER: [
			'REQUEST_METHOD' => 'GET',
		], COOKIE: []);

		$this->assertFalse($Request->is_post());

		$Request->set_method('POST');
		$this->assertTrue($Request->is_post());

		$Request = new Request(GET: [], POST: [], FILES: [], SERVER: [
			'REQUEST_METHOD' => 'POST',
		], COOKIE: []);

		$this->assertTrue($Request->is_post());
	}

	public function testIsAjax(): void
	{
		$Request = new Request(GET: [], POST: [], FILES: [], SERVER: [], COOKIE: []);
		$this->assertFalse($Request->is_ajax());

		$Request = new Request(GET: [], POST: [], FILES: [], SERVER: [
			'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
		], COOKIE: []);
		$this->assertTrue($Request->is_ajax());

		$Request = new class(GET: [], POST: [], FILES: [], SERVER: [
			'ACCEPT' => 'application/json',
		], COOKIE: []) extends Request {
			public function test_unset_cache(): void
			{
				$this->is_ajax = false;
			}
		};

		$this->assertTrue($Request->is_ajax());
		$Request->test_unset_cache();
		$this->assertFalse($Request->is_ajax());
		$Request->clear_cache();
		$this->assertTrue($Request->is_ajax());
	}
}
