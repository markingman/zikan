<?php

namespace Zikan\Http;

use Zikan\TestHelpersTrait;
use PHPUnit\Framework\TestCase;

class ResponseTestOrig extends TestCase
{
	use TestHelpersTrait;

	protected Response $Response;

	public static function setUpBeforeClass(): void
	{
		static::tmpdir_make();
	}

	public static function tearDownAfterClass(): void
	{
		static::tmpdir_remove();
	}

	public function setUp(): void
	{
		$this->Response = new Response(false);
	}

	public function testCreate(): void
	{
		$this->assertInstanceOf(ResponseInterface::class, $this->Response);
	}

	public function testSetCharSet(): void
	{
		$res = $this->Response->set_char_set('UTF-16');
		$this->assertSame($this->Response, $res);
	}

	public function testSetResponseCode(): void
	{
		$res = $this->Response->set_response_code(500);
		$this->assertSame($this->Response, $res);
	}

	public function testSetHeader(): void
	{
		$res = $this->Response->set_header('X-Test', 'test');
		$this->assertSame($this->Response, $res);
	}

	public function testUnSetHeader(): void
	{
		$this->Response->unset_header('test');

		$this->expectNotToPerformAssertions();
	}

	public function testSetCookie(): void
	{
		$res = $this->Response->set_cookie(
			name: 'test',
			value: 'value',
			expires: time() + 60,
			path: '/',
			domain: 'example.com',
			secure: true,
			httponly: true,
		);
		$this->assertSame($this->Response, $res);
	}

	public function testUnSetCookie(): void
	{
		$res = $this->Response->unset_cookie('test');
		$this->assertSame($this->Response, $res);
	}

	public function testHtml(): void
	{
		ob_start();
		$this->Response->html('<p>test</p>');
		$res = ob_get_clean();

		$this->assertEquals('<p>test</p>', $res);

		$res = http_response_code();
		$this->assertEquals(200, $res);

		$res = xdebug_get_headers();
		$this->assertEquals(['Content-Type: text/html; charset=UTF-8'], $res);
	}

	public function testText(): void
	{
		ob_start();
		$this->Response->text('test');
		$res = ob_get_clean();

		$this->assertEquals('test', $res);

		$res = http_response_code();
		$this->assertEquals(200, $res);

		$res = xdebug_get_headers();
		$this->assertEquals(['Content-Type: text/plain; charset=UTF-8'], $res);
	}

	public function testJson(): void
	{
		ob_start();
		$this->Response->json('{"test": true}');
		$res = ob_get_clean();

		$this->assertEquals('{"test": true}', $res);

		$res = http_response_code();
		$this->assertEquals(200, $res);

		$res = xdebug_get_headers();
		$this->assertEquals(['Content-Type: text/javascript; charset=UTF-8'], $res);
	}

	public function testFile(): void
	{
		file_put_contents(static::$tmpdir . '/file.csv', "line1,a\nline2,b\nline3,c\n");

		ob_start();
		$this->Response->file(static::$tmpdir . '/file.csv', true);
		$res = ob_get_clean();

		$this->assertEquals("line1,a\nline2,b\nline3,c\n", $res);

		$res = http_response_code();
		$this->assertEquals(200, $res);

		$res = xdebug_get_headers();
		$this->assertEquals([
			'Content-Type: text/csv; charset=UTF-8',
			'Content-Disposition: attachment;filename=file.csv',
			'Content-Length: 24',
		], $res);

		$this->assertFalse(file_exists(static::$tmpdir . '/file.csv'));
	}

	public function testRespond(): void
	{
		$this->Response->set_header('X-Test', 'test');
		$this->Response->set_response_code(401);
		$this->Response->set_cookie(
			name: 'test',
			value: 'value',
			expires: 1,
			path: '/',
			domain: 'example.com',
			secure: true,
			httponly: true,
		);

		ob_start();
		$this->Response->respond('test1');
		$res = ob_get_clean();

		$this->assertEquals('test1', $res);

		$res = http_response_code();
		$this->assertEquals(401, $res);

		$res = xdebug_get_headers();
		$this->assertEquals([
			'X-Test: test',
			'Set-Cookie: test=value; expires=Thu, 01 Jan 1970 00:00:01 GMT; Max-Age=0; path=/; domain=example.com; secure; HttpOnly',
		], $res);

		file_put_contents(static::$tmpdir . 'file.html', "line1\nline2\nline3\n");

		ob_start();
		$this->Response->respond(static::$tmpdir . 'file.html', true);
		$res = ob_get_clean();

		$this->assertEquals("line1\nline2\nline3\n", $res);
	}

	public function testRedirect(): void
	{
		$this->Response->redirect('http://example.com', code: 307);

		$res = http_response_code();
		$this->assertEquals(307, $res);

		$res = xdebug_get_headers();
		$this->assertEquals(['Location: http://example.com'], $res);
	}
}
