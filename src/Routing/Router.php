<?php

namespace Zikan\Routing;

use Throwable;
use Zikan\Exception\RouterError;
use Zikan\Exception\RouterException;

class Router implements RouterInterface
{
	/** @var array|int[] */
	public const array METHODS = [
		'GET' => 1,
		'HEAD' => 2,
		'POST' => 4,
		'PUT' => 8,
		'DELETE' => 16,
		'CONNECT' => 32,
		'OPTIONS' => 128,
		'TRACE' => 256,
		'PATCH' => 512,
	];

	/** @var array<string, int> $iname */
	protected array $iname = [];
	/** @var array<int, Route> $routes */
	protected array $routes = [];
	/** @var array<string, int[]> $index */
	protected array $index = [];
	protected int $i = 0;
	protected string $action_default = 'default';

	public function __construct(string $action_default = 'default')
	{
		$this->action_default = $action_default;
	}

	public static function is_route_method(string $method, int $route_method): bool
	{
		// public static so callback functions can use this too

		if ($route_method > 0) {
			if (isset(static::METHODS[$method])) {
				if (!(static::METHODS[$method] & $route_method)) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * @param array<string, string> $route_vars
	 * @param array<string> $m
	 * @return array<string, string>
	 */
	public static function get_route_vars(array $route_vars, array $m): array
	{
		// public static so callback functions can use this too

		array_shift($m);

		return array_combine(array_keys($route_vars), $m);
	}

	/**
	 * @param ?array<string, string> $vars
	 * @param array<string>|string|null $method
	 */
	public function add_route(
		string $name,
		string $path,
		string $controller = '',
		?string $action = null,
		?RouteCallbackInterface $callback = null,
		?string $index = null,
		?array $vars = null,
		string|array|null $method = null,
	): bool {
		$action = $action ?? $this->action_default;

		if (empty($path)) {
			throw new RouterException('No path set for route', RouterError::NO_PATH);
		}

		if ($path[0] === '{') {
			throw new RouterException('Path cannot start with a variable', RouterError::NO_PATH);
		}

		if (empty($controller) and is_null($callback)) {
			throw new RouterException('No controller set for route', RouterError::NO_CONTROLLER);
		}

		// can set action like 'name' => ['controller' => 'MyController::action']

		if (str_contains($controller, '::')) {
			$parts = explode('::', $controller, 2);
			$controller = $parts[0];
			$action = $parts[1];
		}

		// 'any', e.g. 'foo/{var}' or 'foo' can be set like foo/{var}**

		$any = false;
		if (str_ends_with($path, '**')) {
			$path = substr($path, 0, -2);
			$any = true;
		}

		// normalise path

		$path = ltrim($path, '/');

		// get index (any override value or, if present, the first path fragment)

		$index = $this->add_route_index($index, $path);

		// get vars (all the {var} items and any explicitly set values)

		$vars = $this->add_route_vars($vars, $path);

		// create URL regx (convert foo/{bar} notation to regx)

		$regx = $this->add_route_regx($any, $path);

		// create rewrite sprintf (so get link uses sprintf() rather than str_replace

		$sprintf = $this->add_route_rewrite($any, $path);

		// normalise method

		$methodi = $this->add_route_normalise_method($method);

		// store as integer

		$i = $this->i++;
		if (isset($this->iname[$name])) {
			throw new RouterException('Index collision', RouterError::INDEX_COLLISION);
		}
		$this->iname[$name] = $i;

		// route (each route leads to a controller from URL string)

		$this->routes[$i] = new Route(
			controller: $controller,
			vars: $vars,
			action: $action,
			method: $methodi,
			sprintf: $sprintf,
			name: $name,
			regx: $regx,
			callback: $callback,
		);

		// routes can be indexed (first path fragment)

		if (strlen($index)) {
			if (!isset($this->index[$index])) {
				$this->index[$index] = [];
			}

			$this->index[$index][] = $i;
		}

		return true;
	}

	public function delete_route(string $name): bool
	{
		if (!isset($this->iname[$name])) {
			return false;
		}

		$i = $this->iname[$name];
		unset($this->routes[$i], $this->iname[$name]);
		foreach ($this->index as $index => $items) {
			foreach ($items as $k => $v) {
				if ($v === $i) {
					unset($this->index[$index][$k]);
				}
			}
			if (empty($this->index[$index])) {
				unset($this->index[$index]);
			}
		}

		return true;
	}

	/** @return array<int, Route> */
	public function get_routes(): array
	{
		return $this->routes;
	}

	/** @return array{iname: array<string, int>, routes: array<int, Route>, index: array<string, int[]>} */
	public function dump(): array
	{
		return [
			'iname' => $this->iname,
			'routes' => $this->routes,
			'index' => $this->index,
		];
	}

	public function match_route(string $method, string $url): RouteMatch|false
	{
		$url = trim($url, '/');
		$url_index = strstr($url . '/', '/', true);
		$tried = [];

		if (isset($this->index[$url_index])) {
			foreach ($this->index[$url_index] as $i) {
				if (preg_match($this->routes[$i]->regx, $url, $m)) {
					if (($return = $this->parse_route($method, $this->routes[$i], $m, $url)) !== false) {
						return $return;
					}
				}
				$tried[$i] = true;
			}
		}

		foreach ($this->iname as $i) {
			if (isset($tried[$i])) {
				continue;
			}
			if (preg_match($this->routes[$i]->regx, $url, $m)) {
				if (($return = $this->parse_route($method, $this->routes[$i], $m, $url)) !== false) {
					return $return;
				}
			}
		}

		return false;
	}

	/**
	 * @param array<string, string> $vars
	 * @throws RouterException If no route found (should always exist)
	 */
	public function get_rewrite(string $name, array $vars = []): string
	{
		if (isset($this->iname[$name])) {
			$i = $this->iname[$name];
			$route =& $this->routes[$i];

			if ($vars === []) {
				if ($route->vars === []) {
					return '/' . $route->sprintf;
				}

				return '/' . vsprintf($route->sprintf, $route->vars);
			}

			$query_vars = array_diff_key($vars, $route->vars);
			$vars = array_replace($route->vars, array_intersect_key($vars, $route->vars));
			$link = vsprintf($route->sprintf, $vars);

			if ($query_vars) {
				$link .= '?' . http_build_query($query_vars, '', '&', PHP_QUERY_RFC3986);
			}

			return '/' . $link;
		} else {
			throw new RouterException(sprintf('No link named %s', $name), RouterError::NO_ROUTE);
		}
	}

	public function get_action_default(): string
	{
		return $this->action_default;
	}

	/** @return array<string> */
	public function get_route_methods(int $route_method): array
	{
		$methods = [];

		foreach (static::METHODS as $k => $v) {
			if (static::METHODS[$k] & $route_method) {
				$methods[] = $k;
			}
		}

		return $methods;
	}


	/**
	 * @return array{
	 *     iname: array<string, int>,
	 *     routes: array<int, Route>,
	 *     index: array<string, int[]>,
	 *     i: int,
	 *     action_default: string
	 * }
	 */
	public function __serialize(): array
	{
		return [
			'iname' => $this->iname,
			'routes' => $this->routes,
			'index' => $this->index,
			'i' => $this->i,
			'action_default' => $this->action_default,
		];
	}

	/** @param array<string, mixed> $data */
	public function __unserialize(array $data): void
	{
		if (!isset($data['iname']) or !is_array($data['iname'])) {
			throw new RouterException('Router requires valid \'iname\' array', RouterError::UNSERIALIZE);
		}
		$iname = [];
		foreach ($data['iname'] as $k => $v) {
			if (!is_string($k) or !is_int($v)) {
				throw new RouterException('Router requires valid \'iname\' array', RouterError::UNSERIALIZE);
			}
			$iname[$k] = $v;
		}

		if (!isset($data['routes']) or !is_array($data['routes'])) {
			throw new RouterException('Router requires a \'routes\' array', RouterError::UNSERIALIZE);
		}
		$routes = [];
		foreach ($data['routes'] as $k => $v) {
			if (!is_int($k) or !$v instanceof Route) {
				throw new RouterException('Router requires valid \'routes\' array', RouterError::UNSERIALIZE);
			}
			$routes[$k] = $v;
		}

		if (!isset($data['index']) or !is_array($data['index'])) {
			throw new RouterException('Router requires valid \'routes\' array', RouterError::UNSERIALIZE);
		}
		$index = [];
		foreach ($data['index'] as $k => $v) {
			if (!is_string($k) or !is_array($v)) {
				throw new RouterException('Router requires valid \'index\' array', RouterError::UNSERIALIZE);
			}
			$indexes = [];
			foreach ($v as $i) {
				if (!is_int($i)) {
					throw new RouterException('Router requires valid \'index\' array', RouterError::UNSERIALIZE);
				}
				$indexes[] = $i;
			}
			$index[$k] = $indexes;
		}

		if (!isset($data['i']) or !is_int($data['i'])) {
			throw new RouterException('Router requires \'i\' integer', RouterError::UNSERIALIZE);
		}

		if (!isset($data['action_default']) or !is_string($data['action_default'])) {
			throw new RouterException('Router requires \'action_default\' string', RouterError::UNSERIALIZE);
		}

		$this->iname = $iname;
		$this->routes = $routes;
		$this->index = $index;
		$this->i = $data['i'];
		$this->action_default = $data['action_default'];
	}

	/** @param array<string> $m */
	protected function parse_route(string $method, Route $route, array $m, string $url): RouteMatch|false
	{
		if (!is_null($route->callback)) {
			try {
				$ret = ($route->callback)($method, $route, $m, $url);
			} catch (Throwable $e) {
				throw new RouterException('callback returned invalid structure', RouterError::INVALID_CALLBACK, previous: $e);
			}

			return $ret;
		}

		if (empty($route->controller)) {
			return false;
		}

		if (!$this->is_route_method($method, $route->method)) {
			return false;
		}

		return new RouteMatch(
			controller: $route->controller,
			action: $route->action,
			vars: $this->get_route_vars($route->vars, $m)
		);
	}

	/** @return string|string[]|null */
	protected function preg_replace(string $pattern, string $replacement, string $subject): string|array|null
	{
		return preg_replace($pattern, $replacement, $subject);
	}

	/**
	 * Determines the route index used for fast lookup.
	 * Defaults to the first static path segment if none provided.
	 */
	private function add_route_index(?string $index, string $path): string
	{
		$index = $index ?? '';

		if (empty($index)) {
			preg_match('~^([^/{]+/)~', $path, $m);

			if (count($m) === 2) {
				$index = substr($m[1], 0, -1);
			}
		}

		return $index;
	}

	/**
	 * @param ?array<string, string> $vars
	 * @return array<string, string>
	 */
	private function add_route_vars(?array $vars, string $path): array
	{
		preg_match_all('~{([^}]+)}~', $path, $m);

		$inline_vars = [];
		if (!empty($m[1])) {
			$inline_vars = array_fill_keys($m[1], '');
		}

		if (is_null($vars)) {
			$vars = [];
		}

		foreach ($inline_vars as $k => $v) {
			if (!isset($vars[$k])) {
				$vars[(string)$k] = (string)$v;
			}
		}

		return $vars;
	}

	private function add_route_regx(bool $any, string $path): string
	{
		$esc = '~';
		$route_esc = $this->preg_replace('~\{[^}]+}~', PHP_EOL,
			$path);//temporarily mark var positions ("{markers}") with EOL placeholder chars safely escape preg_quote()
		if (!is_string($route_esc)) {
			throw new RouterException('Cannot add route path', RouterError::ADD_ROUTE);
		}
		$route_esc = preg_quote($route_esc, $esc);//ensure anything in /url/path is now preg escaped
		$route_esc = str_replace(PHP_EOL, '([^/]+)', $route_esc);//replace placeholders with capture groups for path variables
		if ($any) {
			$route_esc .= '.*';//TODO is this .* or just * ?
		}

		return $esc . '^' . $route_esc . '$' . $esc;//make regx using $esc chars
	}

	private function add_route_rewrite(bool $any, string $path): string
	{
		if (!is_string($sprintf = $this->preg_replace('~{([^}]+)}\**~', '%s', $path))) {
			throw new RouterException("Invalid regx in '$path'", RouterError::REWRITE_FAIL);
		}

		if ($any) {
			$sprintf .= '%s';
		}

		return $sprintf;
	}

	/** @param array<string>|string|null $method */
	private function add_route_normalise_method(array|string|null $method): int
	{
		if (is_string($method)) {
			$method = [strtoupper($method)];
		} elseif (is_array($method)) {
			$method = array_map('strtoupper', $method);
		} else {
			$method = [];
		}

		$methodi = 0;
		foreach (static::METHODS as $method_type => $method_val) {
			if (in_array($method_type, $method)) {
				$methodi += $method_val;
			}
		}

		return $methodi;
	}
}
