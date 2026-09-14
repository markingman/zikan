<?php

namespace App;

use Closure;
use LogicException;
use Zikan\Application;
use Zikan\Config;
use Zikan\Container;
use Zikan\ContainerInterface;
use Zikan\Errors\ErrorHandler;
use Zikan\Http\Request;
use Zikan\Http\Response;
use Zikan\Logs\LogFormatterString;
use Zikan\Logs\LogHandler;
use Zikan\Logs\WriteStdErr;
use Zikan\ObjectCache;
use Zikan\Test\App\View\Preloader;
use Zikan\Routing\Dispatch;
use Zikan\Routing\Links;
use Zikan\Routing\Router;
use Throwable;

// Generic bootstrap (copy and create new as required)
// Hint: consider Composer autoload files 

function app(): Application
{
	$regs = __DIR__ . '/registry';
	$tmpdir = sys_get_temp_dir();// TEMP DIR NOT FOR PRODUCTION (Only this test example)

	$ObjectCache = new ObjectCache($tmpdir);
	$Container = new Container($regs);

	$Container->set('Log', new LogHandler(new LogFormatterString(new WriteStdErr())));

	// Set error handlers

	$ErrorHandler = new ErrorHandler;

	register_shutdown_function([$ErrorHandler, 'handle_shutdown']);
	set_error_handler([$ErrorHandler, 'handle_error']);
	set_exception_handler([$ErrorHandler, 'handle_exception']);

	$ErrorHandler->set_log(function (Throwable $e) use ($Container): void {
		ErrorHandler::log($e, $Container->get_as('Log', LogHandler::class), true);
	});

	$ErrorHandler->set_view(function (Throwable $e): void {
		ErrorHandler::view($e);
	});

	// Example object cache

	if (!$Config = $ObjectCache->cache_get('Config', Config::class)) {
		$Config = new Config([__DIR__ . '/config.php']);
		$ObjectCache->cache_put('Config', $Config);
	}
	$Container->set('Config', $Config);

	if (!$Router = $ObjectCache->cache_get('Router', Router::class)) {
		$Router = new Router();
		$routes = (Closure::bind(function (): mixed {
			return include(__DIR__ . '/routes.php');
		}, null)());
		if (is_array($routes)) {
			foreach ($routes as $name => $route) {
				if (is_string($name) and is_array($route)) {
					/** @var array{
					 *     path?: string,
					 *     controller?: string,
					 *     action?: string|null,
					 *     callback?: Closure|null,
					 *     index?: string|null,
					 *     vars?: array<string, string>|null,
					 *     method?: string|null
					 * } $route
					 */
					$Router->add_route(
						name: $name,
						path: $route['path'] ?? '',
						controller: $route['controller'] ?? '',
						action: $route['action'] ?? null,
						callback: $route['callback'] ?? null,
						index: $route['index'] ?? null,
						vars: $route['vars'] ?? null,
						method: $route['method'] ?? null,
					);
				}
			}
		}
		
		$ObjectCache->cache_put('Router', $Router);
	}
	$Container->set('Router', $Router);

	// Example inline register

	$Container->register('Request', function (): Request {
		return new Request($_GET, $_POST, $_FILES, $_SERVER, $_COOKIE);
	});

	$Container->register('Response', function (): Response {
		return new Response();
	});

	$Container->register('Links', function (ContainerInterface $Container): Links {
		$Config = $Container->get_as('Config', Config::class);

		return new Links($Container->get_as('Router', Router::Class), $Config->list()['SITE_URL'] ?? '', $Config->list()['SITE_PATH'] ?? '');
	});

	$Container->register('Dispatch', function (ContainerInterface $Container): Dispatch {
		return new Dispatch(
			Container: $Container,
			Request: $Container->get_as('Request', Request::class),
			Response: $Container->get_as('Response', Response::class),
			Router: $Container->get_as('Router', Router::class),
			Links: $Container->get_as('Links', Links::class),
			action_prefix: 'action_',
		);
	});

	$Container->register('App', function (ContainerInterface $Container): Application {
		return new Application(
			Config: $Container->get_as('Config', Config::class),
			Container: $Container,
			Request: $Container->get_as('Request', Request::class),
			Response: $Container->get_as('Response', Response::class),
			Dispatch: $Container->get_as('Dispatch', Dispatch::class),
		);
	});

	$Container->register('Preloader', function (): Preloader {
		if (!is_dir($dir = __DIR__ . '/src/App/View')) {
			throw new LogicException('Could not find Partials dir');
		}

		return new Preloader($dir);
	});

	// Example locate distinct path
	// 	$Container->register_path('Links', $regs . '/Links.php');

	return $Container->get_as('App', Application::class);
}
