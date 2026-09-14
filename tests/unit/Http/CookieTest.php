<?php

namespace Zikan\Http;

use PHPUnit\Framework\TestCase;

class CookieTest extends TestCase
{
	public function testDefaultValues(): void
	{
		$cookie = new Cookie();

		$this->assertSame('', $cookie->value);
		$this->assertSame(0, $cookie->expires);
		$this->assertSame('', $cookie->path);
		$this->assertSame('', $cookie->domain);
		$this->assertFalse($cookie->secure);
		$this->assertFalse($cookie->httponly);
	}

	public function testCustomValues(): void
	{
		$cookie = new Cookie(
			value: 'abc123',
			expires: 1672531199,
			path: '/app',
			domain: 'example.com',
			secure: true,
			httponly: true
		);

		$this->assertSame('abc123', $cookie->value);
		$this->assertSame(1672531199, $cookie->expires);
		$this->assertSame('/app', $cookie->path);
		$this->assertSame('example.com', $cookie->domain);
		$this->assertTrue($cookie->secure);
		$this->assertTrue($cookie->httponly);
	}
}
