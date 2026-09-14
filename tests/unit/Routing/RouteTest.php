<?php

namespace Zikan\Routing;

use PHPUnit\Framework\TestCase;

class RouteTest extends TestCase
{
	public function testRouteConstruction(): void
	{
		$controller = 'HomeController';
		$vars = ['id' => '123'];
		$action = 'index';
		$method = 1; // could be GET/POST constant in real case
		$sprintf = '/user/%d';
		$name = 'user.show';
		$regx = '#^/user/(\d+)$#';
		$callback = fn() => 'Hello';

		$route = new Route(
			controller: $controller,
			vars: $vars,
			action: $action,
			method: $method,
			sprintf: $sprintf,
			name: $name,
			regx: $regx,
			callback: $callback
		);

		$this->assertSame($controller, $route->controller);
		$this->assertSame($vars, $route->vars);
		$this->assertSame($action, $route->action);
		$this->assertSame($method, $route->method);
		$this->assertSame($sprintf, $route->sprintf);
		$this->assertSame($name, $route->name);
		$this->assertSame($regx, $route->regx);
		$this->assertIsCallable($route->callback);
		$this->assertSame('Hello', ($route->callback)());
	}
}
