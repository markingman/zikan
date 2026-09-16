<?php

namespace Zikan\Routing;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use Zikan\Exception\RouterError;
use Zikan\Exception\RouterException;
use Zikan\Test\TestRouteMatchFalse;

class RouterTest extends TestCase
{
	protected Router $Router;

	public function setUp(): void
	{
		try {
			$this->Router = new Router('default');
		} catch (Throwable $e) {
			throw new RuntimeException('Could not create Router', previous: $e);
		}
	}

	public function testAddRouteNoPathFailure(): void
	{
		$this->expectException(RouterException::class);
		$this->expectExceptionMessage(RouterError::NO_PATH->value);
		$this->expectExceptionCode(500);

		try {
			$this->Router->add_route(name: 'test', path: '');
		} catch (RouterException $e) {
			$this->assertSame(RouterError::NO_PATH, $e->getErrorCode());
			throw $e;
		}
	}

	public function testAddRouteVarFirstFailure(): void
	{
		$this->expectException(RouterException::class);
		$this->expectExceptionMessage(RouterError::NO_PATH->value);
		$this->expectExceptionCode(500);

		try {
			$this->Router->add_route(name: 'test', path: '{var}');
		} catch (RouterException $e) {
			$this->assertSame(RouterError::NO_PATH, $e->getErrorCode());
			throw $e;
		}
	}

	public function testAddRouteNoControllerFailure(): void
	{
		$this->expectException(RouterException::class);
		$this->expectExceptionMessage(RouterError::NO_CONTROLLER->value);
		$this->expectExceptionCode(500);

		try {
			$this->Router->add_route(name: 'test', path: '/');
		} catch (RouterException $e) {
			$this->assertSame(RouterError::NO_CONTROLLER, $e->getErrorCode());
			throw $e;
		}
	}

	public function testAddRouteIndexExistsFailure(): void
	{
		$Router = new class('default') extends Router {
			/** @var array<string, int> $iname */
			protected array $iname = [
				'test' => 1,
			];
		};
		$this->expectException(RouterException::class);
		$this->expectExceptionMessage(RouterError::INDEX_COLLISION->value);
		$this->expectExceptionCode(500);

		try {
			$Router->add_route(name: 'test', path: '/', controller: 'TextController');
		} catch (RouterException $e) {
			$this->assertSame(RouterError::INDEX_COLLISION, $e->getErrorCode());
			throw $e;
		}
	}

	public function testGetActionDefault(): void
	{
		$res = $this->Router->get_action_default();
		$this->assertEquals('default', $res);
	}

	public function testGetRouteMethods(): void
	{
		$this->assertSame(['GET'], $this->Router->get_route_methods(Router::METHODS['GET']));
		$this->assertSame(['PATCH'], $this->Router->get_route_methods(Router::METHODS['PATCH']));
		$this->assertSame(['GET', 'POST'], $this->Router->get_route_methods(Router::METHODS['GET'] + Router::METHODS['POST']));
	}

	public function testRouteEmptyRoute(): void
	{
		$route = [
			'path' => '/',
			'controller' => 'App\\Controller\\Test'
		];

		$exp = new RouteMatch(
			controller: 'App\\Controller\\Test',
			action: 'default',
			vars: []
		);

		$res = $this->Router->add_route(name: 'test', path: $route['path'], controller: $route['controller']);
		$this->assertTrue($res, 'Should add route');

		$res = $this->Router->match_route('GET', '/');
		$this->assertEquals($exp, $res, 'Should get expected route from path');

		$res = $this->Router->match_route('GET', '');
		$this->assertEquals($exp, $res, 'Should get expected route from path');

		$res = $this->Router->match_route('GET', '//');
		$this->assertEquals($exp, $res, 'Should get expected route from path');

		$res = $this->Router->match_route('GET', '///');
		$this->assertEquals($exp, $res, 'Should get expected route from path');

		$res = $this->Router->get_rewrite('test');
		$this->assertEquals('/', $res, 'Should get expected rewrite from link name');
	}

	public function testRouteSimpleRoute(): void
	{
		$route = [
			'path' => '/simple',
			'controller' => 'App\\Controller\\Simple'
		];

		$exp = new RouteMatch(
			controller: 'App\\Controller\\Simple',
			action: 'default',
			vars: []
		);

		$res = $this->Router->add_route(name: 'test', path: $route['path'], controller: $route['controller']);
		$this->assertTrue($res, 'Should add route');

		$res = $this->Router->match_route('GET', '/simple');
		$this->assertEquals($exp, $res, 'Should get expected route from path');

		$res = $this->Router->match_route('GET', '/simple/');
		$this->assertEquals($exp, $res, 'Should get expected route from path');

		$res = $this->Router->match_route('GET', '/simple///');
		$this->assertEquals($exp, $res, 'Should get expected route from path');

		$res = $this->Router->match_route('GET', '///simple///');
		$this->assertEquals($exp, $res, 'Should get expected route from path');

		$res = $this->Router->get_rewrite('test');
		$this->assertEquals('/simple', $res, 'Should get expected rewrite from link name');
	}

	public function testRouteWithScopeResolutionOperatorSyntax(): void
	{
		$route = [
			'path' => '/simple',
			'controller' => 'App\\Controller\\Simple::method_name'
		];

		$exp = new RouteMatch(
			controller: 'App\\Controller\\Simple',
			action: 'method_name',
			vars: []
		);

		$res = $this->Router->add_route(name: 'test', path: $route['path'], controller: $route['controller']);
		$this->assertTrue($res, 'Should add route');

		$res = $this->Router->match_route('GET', '/simple');
		$this->assertEquals($exp, $res, 'Should get expected route from path');
	}

	public function testRouteWithAnyWildcardSyntax(): void
	{
		$route = [
			'path' => '/simple/path**',
			'controller' => 'App\\Controller\\Simple'
		];

		$res = $this->Router->add_route(name: 'test', path: $route['path'], controller: $route['controller']);
		$this->assertTrue($res, 'Should add route');

		$exp = new RouteMatch(
			controller: 'App\\Controller\\Simple',
			action: 'default',
			vars: []
		);

		$res = $this->Router->match_route('GET', '/simple/path');
		$this->assertEquals($exp, $res, 'Should get expected route from path');

		$res = $this->Router->match_route('GET', '/simple/path123');
		$this->assertEquals($exp, $res, 'Should get expected route from path');
	}

	public function testRouteWithSetIndex(): void
	{
		$route = [
			'path' => '/simple/path/example/{var}',
			'controller' => 'App\\Controller\\Simple',
			'index' => 'simple/path/example',
		];

		$this->Router->add_route(
			name: 'test', path: $route['path'], controller: $route['controller'],
			index: $route['index'],
		);
		$res = $this->Router->dump();

		$exp = [
			'iname' => [
				'test' => 0,
			],
			'routes' => [
				0 => new Route(
					controller: 'App\\Controller\\Simple',
					vars: [
						'var' => '',
					],
					action: 'default',
					method: 0,
					sprintf: 'simple/path/example/%s',
					name: 'test',
					regx: '~^simple/path/example/([^/]+)$~',
					callback: null,
				),
			],
			'index' => [
				'simple/path/example' => [
					0 => 0,
				],
			]
		];

		$this->assertEquals($exp, $res, 'Should set specified index');
	}

	public function testRouteSimpleVarRoute(): void
	{
		$route = [
			'path' => 'test/{id}',
			'controller' => 'App\\Controller\\Test'
		];

		$exp = new RouteMatch(
			controller: 'App\\Controller\\Test',
			action: 'default',
			vars: ['id' => '123']
		);

		$res = $this->Router->add_route(name: 'test', path: $route['path'], controller: $route['controller']);
		$this->assertTrue($res, 'Should add route');

		$res = $this->Router->match_route('GET', '/test/123');
		$this->assertEquals($exp, $res);

		$res = $this->Router->match_route('GET', '/test/123//');
		$this->assertEquals($exp, $res);

		$res = $this->Router->get_rewrite('test', ['id' => '123']);
		$this->assertEquals('/test/123', $res);

		$res = $this->Router->get_rewrite('test', ['id' => 'abc']);
		$this->assertEquals('/test/abc', $res);

		$res = $this->Router->get_rewrite('test');
		$this->assertEquals('/test/', $res);
	}

	public function testRouteMultiVarRoute(): void
	{
		$route = [
			'path' => 'test/{var1}/test/{var2}/{var3}',
			'controller' => 'App\\Controller\\MultiVar'
		];

		$exp = new RouteMatch(
			controller: 'App\\Controller\\MultiVar',
			action: 'default',
			vars: ['var1' => '123', 'var2' => 'abc', 'var3' => 'xyz']
		);

		$res = $this->Router->add_route(name: 'test', path: $route['path'], controller: $route['controller']);
		$this->assertTrue($res, 'Should add route');

		$res = $this->Router->match_route('GET', '/test/123/test/abc/xyz');
		$this->assertEquals($exp, $res);

		$res = $this->Router->get_rewrite('test', ['var1' => '123', 'var2' => 'abc', 'var3' => 'xyz']);
		$this->assertEquals('/test/123/test/abc/xyz', $res);

		$res = $this->Router->get_rewrite('test', ['var1' => 'aaa', 'var2' => '999', 'var3' => '---']);
		$this->assertEquals('/test/aaa/test/999/---', $res);
	}

	public function testRouteSimpleMethodRoute(): void
	{
		$route = [
			'path' => '/test',
			'controller' => 'App\\Controller\\Test',
			'method' => 'POST'
		];

		$exp = new RouteMatch(
			controller: 'App\\Controller\\Test',
			action: 'default',
			vars: []
		);

		$res = $this->Router->add_route(name: 'test', path: $route['path'], controller: $route['controller'], method: $route['method']);
		$this->assertTrue($res, 'Should add route');

		foreach (array_keys(Router::METHODS) as $test) {
			if ($test === 'POST') {
				continue;
			}
			$res = $this->Router->match_route($test, '/test');
			$this->assertFalse($res);
		}

		$res = $this->Router->match_route('POST', '/test');
		$this->assertEquals($exp, $res);

		$res = $this->Router->get_rewrite('test');
		$this->assertEquals('/test', $res);
	}

	public function testRouteMultiMethodRoute(): void
	{
		$path = '/method';
		$route = [
			'path' => '/method',
			'controller' => 'App\\Controller\\Test',
			'method' => ['POST', 'PUT', 'PATCH']
		];

		$res = $this->Router->add_route(name: 'method', path: $route['path'], controller: $route['controller'], method: $route['method']);
		$this->assertTrue($res, 'Should add route');

		foreach (array_keys(Router::METHODS) as $test) {
			if (in_array($test, ['POST', 'PUT', 'PATCH'])) {
				continue;
			}
			$this->assertFalse($this->Router->match_route($test, '/method'));
		}

		$exp = new RouteMatch(
			controller: 'App\\Controller\\Test',
			action: 'default',
			vars: []
		);

		$res = $this->Router->match_route('PATCH', $path);
		$this->assertEquals($exp, $res);

		$res = $this->Router->match_route('PUT', $path);
		$this->assertEquals($exp, $res);

		$res = $this->Router->match_route('POST', $path);
		$this->assertEquals($exp, $res);

		$res = $this->Router->get_rewrite('method');
		$this->assertEquals($res, $path);
	}

	public function testRouteSimpleAction(): void
	{
		$route = [
			'path' => '/simple/action',
			'controller' => 'App\\Controller\\Simple',
			'action' => 'simple_action',
		];

		$exp = new RouteMatch(
			controller: 'App\\Controller\\Simple',
			action: 'simple_action',
			vars: []
		);

		$res = $this->Router->add_route(name: 'simple', path: $route['path'], controller: $route['controller'], action: $route['action']);
		$this->assertTrue($res, 'Should add route');

		$res = $this->Router->match_route('GET', '/simple/action');
		$this->assertEquals($exp, $res);

		$res = $this->Router->get_rewrite('simple');
		$this->assertEquals('/simple/action', $res);
	}

	public function testRouteRewriteWithQueryVars(): void
	{
		$route = [
			'path' => '/with/queries',
			'controller' => 'App\\Controller\\TestClass',
			'action' => 'test_action',
		];

		$exp = new RouteMatch(
			controller: 'App\\Controller\\TestClass',
			action: 'test_action',
			vars: []
		);

		$res = $this->Router->add_route(name: 'test', path: $route['path'], controller: $route['controller'], action: $route['action']);
		$this->assertTrue($res, 'Should add route');

		$res = $this->Router->match_route('GET', '/with/queries');
		$this->assertEquals($exp, $res, 'Should get expected query var route');

		$res = $this->Router->get_rewrite('test', ['var1' => '1', 'var2' => '2']);
		$this->assertEquals('/with/queries?var1=1&var2=2', $res, 'Should write expected link');
	}

	public function testRouteRewriteWithPathVarsAndQueryVars(): void
	{
		$route = [
			'path' => '/with/{var1}/{var2}/queries',
			'controller' => 'App\\Controller\\TestClass',
			'action' => 'test_action',
		];

		$exp = new RouteMatch(
			controller: 'App\\Controller\\TestClass',
			action: 'test_action',
			vars: ['var1' => 'a', 'var2' => 'b']
		);

		$res = $this->Router->add_route(name: 'test', path: $route['path'], controller: $route['controller'], action: $route['action']);
		$this->assertTrue($res, 'Should add route');

		$res = $this->Router->match_route('GET', '/with/a/b/queries');
		$this->assertEquals($exp, $res, 'Should get expected query var route');

		$res = $this->Router->get_rewrite('test', ['var1' => 'a', 'var2' => 'b', 'var3' => 'c']);
		$this->assertEquals('/with/a/b/queries?var3=c', $res, 'Should write expected link');

		$res = $this->Router->get_rewrite('test', ['var1' => '1', 'var2' => '2', 'var3' => '3', 'var4' => '4']);
		$this->assertEquals('/with/1/2/queries?var3=3&var4=4', $res, 'Should write expected link');
	}

	public function testRouteCallback(): void
	{
		$route = [
			'path' => '/callback',
			'callback' => new class implements RouteCallbackInterface {
				/** @param array<string> $m */
				public function __invoke(string $method, Route $route, array $m, string $url): RouteMatch|false
				{
					return false;
				}
			}
		];

		$res = $this->Router->add_route(name: 'callback', path: $route['path'], callback: $route['callback']);
		$this->assertTrue($res, 'Should add route');

		$res = $this->Router->match_route('GET', '/callback');
		$this->assertFalse($res, 'Callback can resolve FALSE itself');

		$res = $this->Router->get_rewrite('callback');
		$this->assertEquals('/callback', $res);
	}

	public function testRouteInvalidCallback(): void
	{
		$route = [
			'path' => '/callback',
			'callback' => new class implements RouteCallbackInterface {
				/** @param array<string> $m */
				public function __invoke(string $method, Route $route, array $m, string $url): RouteMatch|false
				{
					throw new RuntimeException();
				}
			}
		];

		$res = $this->Router->add_route(name: 'callback', path: $route['path'], callback: $route['callback']);
		$this->assertTrue($res, 'Should add route');


		$this->expectException(RouterException::class);
		$this->expectExceptionMessage(RouterError::INVALID_CALLBACK->value);
		$this->expectExceptionCode(500);

		try {
			$this->Router->match_route('GET', '/callback');
		} catch (RouterException $e) {
			$this->assertSame(RouterError::INVALID_CALLBACK, $e->getErrorCode());
			throw $e;
		}
	}

	public function testRouteCallbackRoute(): void
	{
		$route = [
			'path' => '/callback',
			'callback' => new class implements RouteCallbackInterface {
				/** @param array<string> $m */
				public function __invoke(string $method, Route $route, array $m, string $url): RouteMatch|false
				{
					if ($method !== 'POST') {
						return false;
					}

					return new RouteMatch(
						controller: 'App\\Controller\\Callback',
						action: 'callback_action',
						vars: ['var1' => 'ONE', 'var2' => 'TWO'],
					);
				}
			}
		];

		$exp = new RouteMatch(
			controller: 'App\\Controller\\Callback',
			action: 'callback_action',
			vars: ['var1' => 'ONE', 'var2' => 'TWO']
		);

		$res = $this->Router->add_route(name: 'callback', path: $route['path'], callback: $route['callback']);
		$this->assertTrue($res, 'Should add route');

		$res = $this->Router->match_route('GET', '/callback');
		$this->assertFalse($res);

		$res = $this->Router->match_route('POST', '/callback');
		$this->assertEquals($exp, $res);

		$res = $this->Router->get_rewrite('callback');
		$this->assertEquals('/callback', $res);
	}

	public function testRouteCallbackWithMethod(): void
	{
		$route = [
			'path' => '/callback',
			'method' => ['POST', 'PUT'],
			'callback' => new class implements RouteCallbackInterface {
				/** @param array<string> $m */
				public function __invoke(string $method, Route $route, array $m, string $url): RouteMatch|false
				{
					if (!Router::is_route_method($method, $route->method)) {
						return false;
					}

					return new RouteMatch(
						controller: 'App\\Controller\\Callback',
						action: 'callback_action',
						vars: [],
					);
				}
			}
		];

		$exp = new RouteMatch(
			controller: 'App\\Controller\\Callback',
			action: 'callback_action',
			vars: []
		);

		$res = $this->Router->add_route(name: 'callback', path: $route['path'], callback: $route['callback'], method: $route['method']);
		$this->assertTrue($res, 'Should add route');

		$res = $this->Router->match_route('GET', '/callback');
		$this->assertFalse($res);

		$res = $this->Router->match_route('POST', '/callback');
		$this->assertEquals($exp, $res);

		$res = $this->Router->get_rewrite('callback');
		$this->assertEquals('/callback', $res);
	}

	public function testDeleteRoute(): void
	{
		$this->assertTrue(
			$this->Router->add_route(name: 'test', path: '/test', controller: 'App\\Controller\\Test'),
		);

		$this->assertTrue(
			$this->Router->add_route(name: 'test/path', path: '/test/path', controller: 'App\\Controller\\Test::test'),
		);

		$this->assertEquals(new RouteMatch(
			controller: 'App\\Controller\\Test',
			action: 'default',
			vars: []
		), $this->Router->match_route('GET', '/test'));

		$this->assertEquals(new RouteMatch(
			controller: 'App\\Controller\\Test',
			action: 'test',
			vars: []
		), $this->Router->match_route('GET', '/test/path'));

		$this->assertTrue($this->Router->delete_route('test'));
		$this->assertFalse($this->Router->match_route('GET', '/test'));
		$this->assertFalse($this->Router->delete_route('test'));

		$this->assertTrue($this->Router->delete_route('test/path'));
		$this->assertFalse($this->Router->match_route('GET', '/test/path'));
		$this->assertFalse($this->Router->delete_route('test/path'));
	}

	public function testGetRoutes(): void
	{
		$routes = [
			'test1' => [
				'path' => '/test',
				'controller' => 'App\\Controller\\Test',
			],
			'test2' => [
				'path' => '/test2/foo',
				'controller' => 'App\\Controller\\Test2',
				'action' => 'foo'
			],
			'test3' => [
				'path' => '/test3/foo/{var1}',
				'controller' => 'App\\Controller\\Test3',
				'method' => ['GET', 'POST'],
				'vars' => ['var1' => 'VAR1_DEFAULT', 'var2' => '']
			],
			'test4' => [
				'path' => '/callback',
				'method' => ['POST', 'PUT'],
				'callback' => new TestRouteMatchFalse(),
			]
		];

		foreach ($routes as $name => $route) {
			$this->Router->add_route(
				name: $name,
				path: $route['path'],
				controller: $route['controller'] ?? '',
				action: $route['action'] ?? null,
				callback: $route['callback'] ?? null,
				vars: $route['vars'] ?? null,
				method: $route['method'] ?? null,
			);
		}

		$exp = [
			new Route(
				controller: 'App\\Controller\\Test',
				vars: [],
				action: 'default',
				method: 0,
				sprintf: 'test',
				name: 'test1',
				regx: '~^test$~',
			),
			new Route(
				controller: 'App\\Controller\\Test2',
				vars: [],
				action: 'foo',
				method: 0,
				sprintf: 'test2/foo',

				name: 'test2',
				regx: '~^test2/foo$~',
			),
			new Route(
				controller: 'App\\Controller\\Test3',
				vars: [
					'var1' => 'VAR1_DEFAULT',
					'var2' => '',
				],
				action: 'default',
				method: Router::METHODS['GET'] + Router::METHODS['POST'],
				sprintf: 'test3/foo/%s',
				name: 'test3',
				regx: '~^test3/foo/([^/]+)$~',
			),
			new Route(
				controller: '',
				vars: [],
				action: 'default',
				method: Router::METHODS['POST'] + Router::METHODS['PUT'],
				sprintf: 'callback',
				name: 'test4',
				regx: '~^callback$~',
				callback: new TestRouteMatchFalse(),
			)
		];

		$res = $this->Router->get_routes();
		$this->assertEquals($exp, $res);
	}

	public function testRouteMethod(): void
	{
		$route_method = Router::METHODS['GET'];

		$res = Router::is_route_method('GET', $route_method);
		$this->assertTrue($res);

		foreach (array_keys(Router::METHODS) as $test) {
			if ($test === 'GET') {
				continue;
			}
			$res = Router::is_route_method($test, $route_method);
			$this->assertFalse($res, $test . ' should asssert false');
		}

		$route_method += Router::METHODS['PATCH'];

		$res = Router::is_route_method('GET', $route_method);
		$this->assertTrue($res);

		$res = Router::is_route_method('PATCH', $route_method);
		$this->assertTrue($res);

		foreach (array_keys(Router::METHODS) as $test) {
			if ($test === 'GET' or $test === 'PATCH') {
				continue;
			}
			$res = Router::is_route_method($test, $route_method);
			$this->assertFalse($res);
		}

		$route_method += Router::METHODS['POST'];

		$res = Router::is_route_method('GET', $route_method);
		$this->assertTrue($res);

		$res = Router::is_route_method('PATCH', $route_method);
		$this->assertTrue($res);

		$res = Router::is_route_method('POST', $route_method);
		$this->assertTrue($res);

		foreach (array_keys(Router::METHODS) as $test) {
			if ($test === 'GET' or $test === 'PATCH' or $test === 'POST') {
				continue;
			}
			$res = Router::is_route_method($test, $route_method);
			$this->assertFalse($res);
		}
	}

// 	// TODO: move these to fixtures
// 
	public function getGetRouteMethods(): void
	{
		$res = $this->Router->get_route_methods(0);
		$exp = array_keys($this->Router::METHODS);
		$this->assertEquals($exp, $res);

		$res = $this->Router->get_route_methods($this->Router::METHODS['GET'] + $this->Router::METHODS['OPTIONS']);
		$exp = ['GET', 'OPTIONS'];
		$this->assertEquals($exp, $res);

		$res = $this->Router->get_route_methods(
			$this->Router::METHODS['GET'] +
			$this->Router::METHODS['OPTIONS'] +
			$this->Router::METHODS['POST']
		);
		$exp = ['GET', 'POST', 'OPTIONS'];
		$this->assertEquals($exp, $res);
	}

	public function testGetRouteVars(): void
	{
		preg_match('~^test3/foo/([^/]+)$~', 'test3/foo/test', $m);
		$route_vars = ['var1' => ''];
		$res = Router::get_route_vars($route_vars, $m);
		$exp = ['var1' => 'test'];
		$this->assertEquals($exp, $res);

		preg_match('~^test3/foo/([^/]+)/([^/]+)$~', 'test3/foo/test1/test2', $m);
		$route_vars = ['var1' => '', 'var2' => ''];
		$res = Router::get_route_vars($route_vars, $m);
		$exp = ['var1' => 'test1', 'var2' => 'test2'];
		$this->assertEquals($exp, $res);
	}

	public function testParseRouteEmptyController(): void
	{
		$Router = new class('default') extends Router {
			public function testParseRoute(): RouteMatch|bool
			{
				return $this->parse_route('GET', new Route(
					controller: '',
					vars: [],
					action: '',
					method: 0,
					sprintf: '',
					name: '',
					regx: '',
				), [], 'path');
			}
		};

		$this->assertFalse($Router->testParseRoute());
	}

	public function testAddRouteRegxInvalid(): void
	{
		$Router = new class('default') extends Router {
			/** @return string|string[]|null */
			protected function preg_replace(string $pattern, string $replacement, string $subject): string|array|null
			{
				return null;
			}
		};
		$this->expectException(RouterException::class);
		$this->expectExceptionMessage(RouterError::ADD_ROUTE->value);
		$this->expectExceptionCode(500);

		try {
			$Router->add_route(name: 'test', path: '/', controller: 'TextController');
		} catch (RouterException $e) {
			$this->assertSame(RouterError::ADD_ROUTE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testAddRouteRewriteRegxInvalid(): void
	{
		$Router = new class('default') extends Router {
			private int $test_preg_replace_called = 0;

			/** @return string|string[]|null */
			protected function preg_replace(string $pattern, string $replacement, string $subject): string|array|null
			{
				$this->test_preg_replace_called++;

				if ($this->test_preg_replace_called > 1) {
					return null;
				} else {
					return parent::preg_replace($pattern, $replacement, $subject);
				}
			}
		};
		$this->expectException(RouterException::class);
		$this->expectExceptionMessage(RouterError::REWRITE_FAIL->value);
		$this->expectExceptionCode(500);

		try {
			$Router->add_route(name: 'test', path: '/', controller: 'TextController');
		} catch (RouterException $e) {
			$this->assertSame(RouterError::REWRITE_FAIL, $e->getErrorCode());
			throw $e;
		}
	}

	public function testDump(): void
	{
		$routes = [
			'test' => [
				'path' => '/test',
				'controller' => 'App\\Controller\\Test',
			],
			'test2' => [
				'path' => '/test2/foo',
				'controller' => 'App\\Controller\\Test2',
				'action' => 'foo'
			],
			'test3' => [
				'path' => '/test3/foo/{var1}',
				'controller' => 'App\\Controller\\Test3',
				'method' => ['GET', 'POST'],
			]
		];

		$exp = [
			'iname' => [
				'test' => 0,
				'test2' => 1,
				'test3' => 2,
			],
			'routes' => [
				0 => new Route(
					controller: 'App\Controller\Test',
					vars: [],
					action: 'default',
					method: 0,
					sprintf: 'test',
					name: 'test',
					regx: '~^test$~',
				),
				1 => new Route(
					controller: 'App\Controller\Test2',
					vars: [],
					action: 'foo',
					method: 0,
					sprintf: 'test2/foo',
					name: 'test2',
					regx: '~^test2/foo$~',
				),
				2 => new Route(
					controller: 'App\Controller\Test3',
					vars: ['var1' => ''],
					action: 'default',
					method: Router::METHODS['GET'] + Router::METHODS['POST'],
					sprintf: 'test3/foo/%s',
					name: 'test3',
					regx: '~^test3/foo/([^/]+)$~',
				),
			],
			'index' => [
				'test2' => [1],
				'test3' => [2]
			],
		];

		foreach ($routes as $name => $route) {
			$this->Router->add_route(
				name: $name,
				path: $route['path'],
				controller: $route['controller'],
				action: $route['action'] ?? null,
				method: $route['method'] ?? null,
			);
		}

		$res = $this->Router->dump();
		$this->assertEquals($exp, $res);
	}

	public function testRouterSerializationPreservesRoutes(): void
	{
		$router = new Router();

		$router->add_route(
			name: 'test',
			path: '/foo/{id}',
			controller: 'TestController',
			action: 'show',
			callback: new TestRouteMatchFalse(),
			index: 'foo',
			vars: ['id' => ''],
			method: ['GET']
		);

		$dumpBefore = $router->dump();

		// Serialize and unserialize
		$serialized = serialize($router);
		$unserialized = unserialize($serialized);

		$this->assertInstanceOf(Router::class, $unserialized);
		$dumpAfter = $unserialized->dump();

		// Check structure consistency
		$this->assertSame($dumpBefore['iname'], $dumpAfter['iname']);
		$this->assertSame(array_keys($dumpBefore['routes']), array_keys($dumpAfter['routes']));
		$this->assertSame($dumpBefore['index'], $dumpAfter['index']);

		// Check a single route's core data (ignoring callback)
		$beforeRoute = reset($dumpBefore['routes']);
		$afterRoute = reset($dumpAfter['routes']);

		$this->assertInstanceOf(Route::class, $beforeRoute);
		$this->assertInstanceOf(Route::class, $afterRoute);
		$this->assertSame($beforeRoute->controller, $afterRoute->controller);
		$this->assertSame($beforeRoute->action, $afterRoute->action);
		$this->assertSame($beforeRoute->method, $afterRoute->method);
		$this->assertSame($beforeRoute->name, $afterRoute->name);
		$this->assertSame($beforeRoute->vars, $afterRoute->vars);
		$this->assertSame($beforeRoute->sprintf, $afterRoute->sprintf);
		$this->assertSame($beforeRoute->regx, $afterRoute->regx);
		$this->assertEquals(new TestRouteMatchFalse(), $afterRoute->callback);
	}

	public function testRouterSerializationRequiresValidIname(): void
	{
		$Router = new Router();

		$this->expectException(RouterException::class);
		$this->expectExceptionMessage(RouterError::UNSERIALIZE->value);
		$this->expectExceptionCode(500);

		try {
			$Router->__unserialize([]);
		} catch (RouterException $e) {
			$this->assertSame(RouterError::UNSERIALIZE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testRouterSerializationRequiresValidInameFormat(): void
	{
		$Router = new Router();

		$this->expectException(RouterException::class);
		$this->expectExceptionMessage(RouterError::UNSERIALIZE->value);
		$this->expectExceptionCode(500);

		try {
			$Router->__unserialize(['iname' => [100 => true]]);
		} catch (RouterException $e) {
			$this->assertSame(RouterError::UNSERIALIZE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testRouterSerializationRequiresValidRoutes(): void
	{
		$Router = new Router();

		$this->expectException(RouterException::class);
		$this->expectExceptionMessage(RouterError::UNSERIALIZE->value);
		$this->expectExceptionCode(500);

		try {
			$Router->__unserialize(['iname' => []]);
		} catch (RouterException $e) {
			$this->assertSame(RouterError::UNSERIALIZE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testRouterSerializationRequiresValidRoutesFormat(): void
	{
		$Router = new Router();

		$this->expectException(RouterException::class);
		$this->expectExceptionMessage(RouterError::UNSERIALIZE->value);
		$this->expectExceptionCode(500);

		try {
			$Router->__unserialize(['iname' => [], 'routes' => ['z' => true]]);
		} catch (RouterException $e) {
			$this->assertSame(RouterError::UNSERIALIZE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testRouterSerializationRequiresValidIndex(): void
	{
		$Router = new Router();

		$this->expectException(RouterException::class);
		$this->expectExceptionMessage(RouterError::UNSERIALIZE->value);
		$this->expectExceptionCode(500);

		try {
			$Router->__unserialize(['iname' => [], 'routes' => []]);
		} catch (RouterException $e) {
			$this->assertSame(RouterError::UNSERIALIZE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testRouterSerializationRequiresValidIndexFormat(): void
	{
		$Router = new Router();

		$this->expectException(RouterException::class);
		$this->expectExceptionMessage(RouterError::UNSERIALIZE->value);
		$this->expectExceptionCode(500);

		try {
			$Router->__unserialize(['iname' => [], 'routes' => [], 'index' => [100 => true]]);
		} catch (RouterException $e) {
			$this->assertSame(RouterError::UNSERIALIZE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testRouterSerializationRequiresValidIndexSubFormat(): void
	{
		$Router = new Router();

		$this->expectException(RouterException::class);
		$this->expectExceptionMessage(RouterError::UNSERIALIZE->value);
		$this->expectExceptionCode(500);

		try {
			$Router->__unserialize(['iname' => [], 'routes' => [], 'index' => ['test' => ['string']]]);
		} catch (RouterException $e) {
			$this->assertSame(RouterError::UNSERIALIZE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testRouterSerializationRequiresValidI(): void
	{
		$Router = new Router();

		$this->expectException(RouterException::class);
		$this->expectExceptionMessage(RouterError::UNSERIALIZE->value);
		$this->expectExceptionCode(500);

		try {
			$Router->__unserialize(['iname' => [], 'routes' => [], 'index' => [], 'i' => true]);
		} catch (RouterException $e) {
			$this->assertSame(RouterError::UNSERIALIZE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testRouterSerializationRequiresValidDefaultAction(): void
	{
		$Router = new Router();

		$this->expectException(RouterException::class);
		$this->expectExceptionMessage(RouterError::UNSERIALIZE->value);
		$this->expectExceptionCode(500);

		try {
			$Router->__unserialize(['iname' => [], 'routes' => [], 'index' => [], 'i' => 0, 'action_default' => true]);
		} catch (RouterException $e) {
			$this->assertSame(RouterError::UNSERIALIZE, $e->getErrorCode());
			throw $e;
		}
	}
}
