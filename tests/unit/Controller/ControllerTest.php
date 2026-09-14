<?php

namespace Zikan\Controller;

use Zikan\Config;
use Zikan\Http\Request;
use Zikan\Http\Response;
use Zikan\Routing\Dispatch;
use PHPUnit\Framework\TestCase;

class ControllerTest extends TestCase
{
	public function testCanBeInstantiated(): void
	{
		$Config = new Config();
		$mockDispatch = $this->createMock(Dispatch::class);
		$mockRequest = $this->createMock(Request::class);
		$mockResponse = $this->createMock(Response::class);

		$this->assertInstanceOf(Controller::class, new Controller($Config, $mockDispatch, $mockRequest, $mockResponse));
	}
}
