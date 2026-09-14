<?php

namespace Zikan\Routing;

use Zikan\Container;
use Zikan\ContainerInterface;
use Zikan\Exception\DispatchError;
use Zikan\Exception\DispatchException;
use Zikan\Http\Request;
use Zikan\Http\RequestInterface;
use Zikan\Http\Response;
use Zikan\Http\ResponseInterface;
use Zikan\Test\TestClassPlainSimple;
use Zikan\Test\TestClassWithInvokeException;
use Zikan\Test\TestClassWithMethodException;
use Zikan\Test\TestClassWithObjectArguments;
use Zikan\Test\TestClassWithSimpleMethods;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DispatchTest extends TestCase
{
	protected ContainerInterface $Container;
	protected RequestInterface $Request;
	protected ResponseInterface $Response;
	protected RouterInterface $Router;
	protected LinksInterface $Links;
	protected DispatchInterface $Dispatch;

	public function testCreate(): void
	{
		$Router = new Router();
		$this->assertInstanceOf(DispatchInterface::class,
			new Dispatch(
				Container: new Container(),
				Request: new Request([], [], [], [], []),
				Response: new Response(),
				Router: $Router,
				Links: new Links($Router, 'example.com', ''),
				action_prefix: 'action_',
				action_suffix: '',
			)
		);
	}

	public function testCallControllerNoMatch(): void
	{
		$Router = new Router();
		$Dispatch = new Dispatch(
			Container: new Container(),
			Request: new Request([], [], [], [], []),
			Response: new Response(),
			Router: $Router,
			Links: new Links($Router, '', ''),
			action_prefix: 'action_',
			action_suffix: '',
		);

		$this->expectException(DispatchException::class);
		$this->expectExceptionMessage(DispatchError::NO_ROUTE->value);
		$this->expectExceptionCode(404);

		try {
			$Dispatch->call_controller();
		} catch (DispatchException $e) {
			$this->assertSame(DispatchError::NO_ROUTE, $e->getErrorCode());
			throw $e;
		}
	}

// 	public function XtestCallControllerNoMatch(): void
// 	{
// 		$Router = new Router();
// 		$Dispatch = new Dispatch(
// 				Container: new Container(),
// 				Request: new Request([], [], [], [], []),
// 				Response: new Response(),
// 				Router: $Router,
// 				Links: new Links($Router, '', ''),
// 				action_prefix: 'action_',
// 				action_suffix: '',
// 		);
// 
// 		$this->expectException(DispatchException::class);
// 		$this->expectExceptionMessage(DispatchError::NO_ROUTE->value);
// 		$this->expectExceptionCode(404);
// 
// 		try {
// 			$Dispatch->call_controller();
// 		} catch (DispatchException $e) {
// 			$this->assertSame(DispatchError::NO_ROUTE, $e->getErrorCode());
// 			throw $e;
// 		}
// 	}

	public function testCallControllerRedirectsAndExits(): void
	{
		$Response = $this->createMock(ResponseInterface::class);
		$Response->expects($this->once())
			->method('redirect')
			->with('https://example.com/', 307);

		$Links = $this->createMock(LinksInterface::class);
		$Links->method('get_link')->willReturn('https://example.com/');

		$Router = $this->createMock(RouterInterface::class);
		$Router->method('match_route')->willReturn(new RouteMatch(
			controller: 'https://example.com/',
			action: '',
			vars: []
		));

		$dispatch = new class(
			$this->createMock(ContainerInterface::class),
			$this->createMock(RequestInterface::class),
			$Response,
			$Router,
			$Links
		) extends Dispatch {
			public bool $exit_called = false;

			protected function exit(): void
			{
				$this->exit_called = true;
			}
		};

		$dispatch->call_controller('DELETE');
		$this->assertTrue($dispatch->exit_called);
	}

	public function testCallControllerClassNotFound(): void
	{
		$Router = new Router();
		$Router->add_route(
			name: 'test',
			path: 'test',
			controller: 'UNKNOWN_CONTROLLER',
// 						action: $route['action'] ?? null,
// 						callback: $route['callback'] ?? null,
// 						index: $route['index'] ?? null,
// 						vars: $route['vars'] ?? null,
// 						method: $route['method'] ?? null,
		);

		$Dispatch = new Dispatch(
			Container: new Container(),
			Request: new Request([], [], [], [], []),
			Response: new Response(),
			Router: $Router,
			Links: new Links($Router, '', ''),
			action_prefix: 'action_',
			action_suffix: '',
		);

		$this->expectException(DispatchException::class);
		$this->expectExceptionMessage(DispatchError::NO_CONTROLLER->value);
		$this->expectExceptionCode(500);

		try {
			$Dispatch->call_controller('GET', 'test');
		} catch (DispatchException $e) {
			$this->assertSame(DispatchError::NO_CONTROLLER, $e->getErrorCode());
			throw $e;
		}
	}

	public function testCallControllerCannotInstantiate(): void
	{
		$Router = new Router();
		$Router->add_route(
			name: 'test',
			path: 'test',
			controller: TestClassPlainSimple::class,
		);

		$Dispatch = new Dispatch(
			Container: $this->getMockContainer(),
			Request: new Request([], [], [], [], []),
			Response: new Response(),
			Router: $Router,
			Links: new Links($Router, '', ''),
			action_prefix: 'action_',
			action_suffix: '',
		);

		$this->expectException(DispatchException::class);
		$this->expectExceptionMessage(DispatchError::FAILED_INSTANTIATE->value);
		$this->expectExceptionCode(500);

		try {
			$Dispatch->call_controller('GET', 'test');
		} catch (DispatchException $e) {
			$this->assertSame(DispatchError::FAILED_INSTANTIATE, $e->getErrorCode());
			throw $e;
		}
	}

//	public function XtestCallControllerInstantiatedNonObject(): void
//	{
//		$Router = new Router();
//		$Router->add_route(
//			name: 'test',
//			path: 'test',
//			controller: TestClassWithSimpleMethods::class,
//		);
//
//		$Dispatch =new class(
//				Container: new Container(),//$this->getMockContainer(),
//				Request: new Request([], [], [], [], []),
//				Response: new Response(),
//				Router: $Router,
//				Links: new Links($Router, '', ''),
//				action_prefix: 'action_',
//				action_suffix: '',
//		) extends Dispatch {
//			public function instantiate(string $class, bool $store = false): object
//			{
//				return true;
//			}
//		};
//
//		$this->expectException(DispatchException::class);
//		$this->expectExceptionMessage(DispatchError::FAILED_INSTANTIATE->value);
//		$this->expectExceptionCode(500);
//
//		try {
//			$Dispatch->call_controller('GET', 'test');
//		} catch (DispatchException $e) {
//			$this->assertSame(DispatchError::FAILED_INSTANTIATE, $e->getErrorCode());
//			throw $e;
//		}
//	}

	public function testCallControllerMethodNotCallable(): void
	{
		$Router = new Router();
		$Router->add_route(
			name: 'test',
			path: 'test',
			controller: TestClassWithObjectArguments::class,
		);

		$Dispatch = new Dispatch(
			Container: $this->getMockContainer(),
			Request: new Request([], [], [], [], []),
			Response: new Response(),
			Router: $Router,
			Links: new Links($Router, '', ''),
			action_prefix: 'action_',
			action_suffix: '',
		);

		$this->expectException(DispatchException::class);
		$this->expectExceptionMessage(DispatchError::NO_ACTION->value);
		$this->expectExceptionCode(404);

		try {
			$Dispatch->call_controller('GET', 'test');
		} catch (DispatchException $e) {
			$this->assertSame(DispatchError::NO_ACTION, $e->getErrorCode());
			throw $e;
		}
	}

	public function testCallControllerInvokeException(): void
	{
		$Router = new Router();
		$Router->add_route(
			name: 'test',
			path: 'test',
			controller: TestClassWithInvokeException::class,
			action: 'test',
		);

		$Dispatch = new Dispatch(
			Container: new Container(),
			Request: new Request([], [], [], [], []),
			Response: new Response(),
			Router: $Router,
			Links: new Links($Router, '', ''),
			action_prefix: '',
			action_suffix: '',
		);

		$this->expectException(DispatchException::class);
		$this->expectExceptionMessage(DispatchError::FAILED_INVOKE->value);
		$this->expectExceptionCode(500);

		try {
			$Dispatch->call_controller('GET', 'test');
		} catch (DispatchException $e) {
			$this->assertSame(DispatchError::FAILED_INVOKE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testCallControllerActionException(): void
	{
		$Router = new Router();
		$Router->add_route(
			name: 'test',
			path: 'test',
			controller: TestClassWithMethodException::class,
			action: 'test',
		);

		$Dispatch = new Dispatch(
			Container: new Container(),
			Request: new Request([], [], [], [], []),
			Response: new Response(),
			Router: $Router,
			Links: new Links($Router, '', ''),
			action_prefix: '',
			action_suffix: '',
		);

		$this->expectException(DispatchException::class);
		$this->expectExceptionMessage(DispatchError::FAILED_ACTION->value);
		$this->expectExceptionCode(500);

		try {
			$Dispatch->call_controller('GET', 'test');
		} catch (DispatchException $e) {
			$this->assertSame(DispatchError::FAILED_ACTION, $e->getErrorCode());
			throw $e;
		}
	}

	public function testCallController(): void
	{
		$Router = new Router();
		$Router->add_route(
			name: 'test',
			path: 'test/{var}',
			controller: TestClassWithSimpleMethods::class,
			action: 'echo',
		);

		$Request = new Request([], [], [], [], []);

		$Dispatch = new Dispatch(
			Container: new Container(),
			Request: $Request,
			Response: new Response(),
			Router: $Router,
			Links: new Links($Router, '', ''),
			action_prefix: '',
			action_suffix: '',
		);

		ob_start();
		$Dispatch->call_controller('GET', 'test/value');
		$output = ob_get_clean();

		$this->assertSame('value', $Request->get_get('var'));
		$this->assertSame('value', $output);
	}

	public function testGetControllerAndGetAction(): void
	{
		$Router = new Router;
		$Dispatch = new class(
			Container: new Container(),
			Request: new Request([], [], [], [], []),
			Response: new Response(),
			Router: $Router,
			Links: new Links($Router, '', ''),
			action_prefix: 'action_',
			action_suffix: '',
		) extends Dispatch {
			public function set_controller(): void
			{
				$this->controller = 'TestController';
			}

			public function set_action(): void
			{
				$this->action = 'testAction';
			}
		};

		$this->assertSame('', $Dispatch->get_controller());
		$this->assertSame('', $Dispatch->get_action());

		$Dispatch->set_controller();
		$Dispatch->set_action();

		$this->assertSame('TestController', $Dispatch->get_controller());
		$this->assertSame('testAction', $Dispatch->get_action());
	}

	public function testCall(): void
	{
		$obj = new TestClassPlainSimple();

		$Container = $this->createMock(ContainerInterface::class);
		$Container->expects($this->once())
			->method('call')
			->with($obj, 'test', ['var' => 1]);

		$Router = new Router;
		$Dispatch = new Dispatch(
			Container: $Container,
			Request: new Request([], [], [], [], []),
			Response: new Response(),
			Router: $Router,
			Links: new Links($Router, '', ''),
			action_prefix: 'action_',
			action_suffix: '',
		);

		$Dispatch->call($obj, 'test', ['var' => 1]);
	}

	public function testStore(): void
	{
		$obj = new TestClassPlainSimple();

		$Container = $this->createMock(ContainerInterface::class);
		$Container->expects($this->once())
			->method('set')
			->with('test', $obj);

		$Router = new Router;
		$Dispatch = new Dispatch(
			Container: $Container,
			Request: new Request([], [], [], [], []),
			Response: new Response(),
			Router: $Router,
			Links: new Links($Router, '', ''),
			action_prefix: 'action_',
			action_suffix: '',
		);

		$Dispatch->store('test', $obj);
	}

	public function testGetRoutes(): void
	{
		$Router = new Router;
		$Router->add_route(
			name: 'test',
			path: 'path',
			controller: 'Controller',
		);
		$Dispatch = new Dispatch(
			Container: new Container,
			Request: new Request([], [], [], [], []),
			Response: new Response(),
			Router: $Router,
			Links: new Links($Router, '', ''),
			action_prefix: 'action_',
			action_suffix: '',
		);

		$this->assertEquals([
			new Route(
				controller: 'Controller',
				vars: [],
				action: 'default',
				method: 0,
				sprintf: 'path',
				name: 'test',
				regx: '~^path$~',
			),
		], $Dispatch->get_routes());
	}


	protected function getMockLinks(): Links
	{
		return new class(new Router, '', '') extends Links {
			public string $get_link_called = '';

			/** @param array<string, string> $vars */
			public function get_link(string $name, array $vars = [], bool $relative = true, ?string $protocol = null): string
			{
				$this->get_link_called = $name;

				return 'https://example.com/';
			}
		};
	}

	protected function getMockResponse(): Response
	{
		return new class extends Response {
			public string $redirect_called = '';

			public function redirect(string $to, int $code = 303): void
			{
				$this->redirect_called = sprintf('%s%d', $to, $code);
			}
		};
	}

	protected function getMockRouter(): Router
	{
		return new class extends Router {
			public function match_route(string $method, string $url): RouteMatch|false
			{
				return false;
			}
		};
	}

	protected function getMockContainer(): Container
	{
		return new class extends Container {
// 			public bool $call_called = false;
// 			public bool $set_called = false;
			protected function instantiate(string $class_name, ?string $name = null, bool $store = false): object
			{
				if ($class_name === TestClassPlainSimple::class) {
					throw new RuntimeException("Failed to instantiate '$class_name'");
//				} elseif ($class_name === TestClassWithSimpleMethods::class) {
//					return true;
				} else {
					return parent::instantiate($class_name, $name, $store);
				}
			}

			/* * @param array<mixed> $args */
// 			public function call(object $class, string $method_name, array $args = [], bool $store = false, bool $force_new = false, bool $store_reflection = false): mixed
// 			{
// 				$this->call_called = true;
// 				return true;
// 			}
// 
// 			public function set(string $name, object $value): void
// 			{
// 				$this->set_called = true;
// 			}
		};
	}

//    protected function makeDispatch(array $routeMatchConfig = []): Dispatch
//    {
//        $Container = $this->createMock(ContainerInterface::class);
//        $Request = $this->createMock(RequestInterface::class);
//        $Response = $this->createMock(ResponseInterface::class);
//        $Router = $this->createMock(RouterInterface::class);
//        $Links = $this->createMock(LinksInterface::class);
//
//        if ($routeMatchConfig !== []) {
//            $RouteMatch = new Route(...$routeMatchConfig);
//            $Router->method('match_route')->willReturn($RouteMatch);
//        }
//
//        return new class($Container, $Request, $Response, $Router, $Links) extends Dispatch {
//            protected function exit(): void
//            {
//                // Prevent actual exit during tests
//            }
//        };
//    }

//    public function xtestNoRouteMatchThrows()
//    {
//        $this->expectException(DispatchException::class);
//        $this->expectExceptionCode(404);
//        $this->expectExceptionMessageMatches('/No route found/');
//
//        $dispatch = $this->makeDispatch(); // Router::match_route will return null
//        $dispatch->call_controller('GET', '/missing');
//    }

//    public function xtestControllerClassNotFoundThrows()
//    {
//        $this->expectException(DispatchException::class);
//        $this->expectExceptionCode(500);
//        $this->expectExceptionMessageMatches('/Could not load controller/');
//
//        $dispatch = $this->makeDispatch([
//            'controller' => 'NonExistent\\Class',
//            'action' => 'index',
//            'vars' => []
//        ]);
//        $dispatch->call_controller();
//    }

//    public function xtestCallableActionNotFoundThrows()
//    {
//        $fakeController = new class {
//            public function not_the_right_method() {}
//        };
//
//        $dispatch = $this->makeDispatch([
//            'controller' => get_class($fakeController),
//            'action' => 'missingMethod',
//            'vars' => []
//        ]);
//
//        $container = $this->createMock(ContainerInterface::class);
//        $container->method('create')->willReturn($fakeController);
//
//        $dispatch = new class(
//            $container,
//            $this->createMock(RequestInterface::class),
//            $this->createMock(ResponseInterface::class),
//            $this->createMock(RouterInterface::class),
//            $this->createMock(LinksInterface::class)
//        ) extends Dispatch {
//            protected function exit(): void {}
//        };
//
//        $this->expectException(DispatchException::class);
//        $this->expectExceptionCode(404);
//        $this->expectExceptionMessageMatches('/Could not find action/');
//
//        $dispatch->call_controller();
//    }

//    public function xtestInvokeAndActionAreCalled()
//    {
//        $calls = [];
//
//        $fakeController = new class($calls) {
//            public array $calls;
//
//            public function __construct(array &$calls) { $this->calls = &$calls; }
//
//            public function __invoke() { $this->calls[] = '__invoke'; }
//
//            public function someAction() { $this->calls[] = 'someAction'; }
//        };
//
//        $Router = $this->createMock(RouterInterface::class);
//        $Router->method('match_route')->willReturn(new Route(
//            controller: get_class($fakeController),
//            action: 'someAction',
//            vars: []
//        ));
//
//        $Container = $this->createMock(ContainerInterface::class);
//        $Container->method('create')->willReturn($fakeController);
//        $Container->method('call')->willReturnCallback(function ($obj, $method) use (&$calls) {
//            $calls[] = $method;
//        });
//
//        $dispatch = new class(
//            $Container,
//            $this->createMock(RequestInterface::class),
//            $this->createMock(ResponseInterface::class),
//            $Router,
//            $this->createMock(LinksInterface::class)
//        ) extends Dispatch {
//            protected function exit(): void {}
//        };
//
//        $dispatch->call_controller();
//
//        $this->assertContains('__invoke', $calls);
//        $this->assertContains('someAction', $calls);
//    }


	//call controller 1

	//call controller 2

	//call controller 3

	// get controller

	// get action

	// get link

	// go_to

	// get routes

	// instantiate

	// call
}
