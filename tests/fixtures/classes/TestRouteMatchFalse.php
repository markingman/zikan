<?php

namespace Zikan\Test;

use Zikan\Routing\Route;
use Zikan\Routing\RouteCallbackInterface;

class TestRouteMatchFalse implements RouteCallbackInterface
{
	/** @param array<string> $m */
	public function __invoke(string $method, Route $route, array $m, string $url): false
	{
		return false;
	}
}
