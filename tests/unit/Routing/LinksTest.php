<?php

namespace Zikan\Routing;

use Zikan\Exception\LinksException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

class LinksTest extends TestCase
{
	protected Links $Links;
	protected Router $Router;

	public function setUp(): void
	{
		try {
			$this->Router = new Router();
		} catch (Throwable $e) {
			throw new RuntimeException('Could not create Router', previous: $e);
		}

		try {
			$this->Links = new Links($this->Router, 'example.com', '');
		} catch (Throwable $e) {
			throw new RuntimeException('Could not create Links', previous: $e);
		}
	}

	public function testGetLinkFail(): void
	{
		$this->expectException(LinksException::class);
		$this->expectExceptionMessage('LINKS');

		$this->Links->get_link('not_found');
	}

	public function testGetLink(): void
	{
		$this->Router->add_route(
			name: 'test',
			path: '/simple',
			controller: 'App\\Controller\\Simple'
		);

		$res = $this->Links->get_link('test');
		$this->assertEquals('/simple', $res);

		$res = $this->Links->get_link('test', [], false);
		$this->assertEquals('//example.com/simple', $res);

		$res = $this->Links->get_link('test', [], false, 'https');
		$this->assertEquals('https://example.com/simple', $res);

		$Links = new Links($this->Router, 'example.com', '/path');

		$res = $Links->get_link('test');
		$this->assertEquals('/path/simple', $res);

		$res = $Links->get_link('test', [], false);
		$this->assertEquals('//example.com/path/simple', $res);

		$res = $Links->get_link('test', [], false, 'https');
		$this->assertEquals('https://example.com/path/simple', $res);
	}
}
