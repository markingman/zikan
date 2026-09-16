<?php

namespace Zikan\Routing;

use Zikan\ContainerInterface;
use Zikan\Exception\DispatchError;
use Zikan\Exception\DispatchException;
use Zikan\Http\RequestInterface;
use Zikan\Http\ResponseInterface;
use Throwable;

class Dispatch implements DispatchInterface
{
	protected ?string $controller = null;
	protected ?string $action = null;

	function __construct(
		protected ContainerInterface $Container,
		protected RequestInterface $Request,
		protected ResponseInterface $Response,
		protected RouterInterface $Router,
		protected LinksInterface $Links,
		protected string $action_prefix = '',
		protected string $action_suffix = '',
	) {
	}

	public function call_controller(?string $method = null, ?string $url = null): void
	{
		if (!$RouteMatch = $this->Router->match_route($method ?? '', $url ?? '')) {
			// TODO response types, e.g. method not implemented
			throw new DispatchException(sprintf('No route found for URL "%s"', $url), DispatchError::NO_ROUTE, 404);
		}

		if (str_starts_with($RouteMatch->controller, 'http') and str_contains($RouteMatch->controller, '://')) { //Router can make http redirect
			$this->go_to($RouteMatch->controller);

			return;
		}

		$this->controller = $RouteMatch->controller;
		$this->action = $this->action_prefix . $RouteMatch->action . $this->action_suffix;

		foreach ($RouteMatch->vars as $k => $v) {
			$this->Request->set_get_value((string)$k, $v);
		}

		if (!class_exists($this->controller)) {
			throw new DispatchException(sprintf('Could not load controller "%s"', $this->controller), DispatchError::NO_CONTROLLER, 500);
		}

		try {
			$Controller = $this->instantiate($this->controller);
		} catch (Throwable $e) {
			throw new DispatchException(sprintf('Could not instantiate controller "%s"', $this->controller), DispatchError::FAILED_INSTANTIATE, 500, $e);
		}

		if (!$this->is_callable([$Controller, $this->action])) {
			throw new DispatchException(sprintf('Could not find action "%s::%s"', $this->controller, $this->action), DispatchError::NO_ACTION, 404);
		}

		try {
			if ($this->is_callable([$Controller, '__invoke'])) {
				$this->call($Controller, '__invoke');
			}
		} catch (Throwable $e) {
			throw new DispatchException(sprintf('Could not invoke controller "%s"', $this->controller), DispatchError::FAILED_INVOKE, 500, $e);
		}

		try {
			$this->call($Controller, $this->action);
		} catch (Throwable $e) {
			throw new DispatchException(sprintf('Could not call action "%s::%s"', $this->controller, $this->action), DispatchError::FAILED_ACTION, 500, $e);
		}
	}

	public function get_controller(): string
	{
		return $this->controller ?? '';
	}

	public function get_action(): string
	{
		return $this->action ?? '';
	}

	/** @param array<string, string> $vars */
	public function get_link(string $name, array $vars = [], bool $relative = true): string
	{
		return $this->Links->get_link($name, $vars, $relative);
	}

	/** @param array<string, string> $vars */
	public function go_to(string $name, array $vars = [], int $code = 307, bool $relative = true): void
	{
		$to = $this->get_link($name, $vars, $relative);
		$this->Response->redirect($to, $code);
		$this->exit();
	}

	/** @return array<int, Route> */
	public function get_routes(): array
	{
		return $this->Router->get_routes();
	}

	public function instantiate(string $class, /*string $name,*/ bool $store = false): object
	{
		return $this->Container->create($class, $store);
	}

	/** @param mixed[] $params */
	public function call(object $class, string $method, array $params = []): mixed
	{
		return $this->Container->call($class, $method, $params);
	}

	public function store(string $name, object $object): void
	{
		$this->Container->set($name, $object);
	}

	protected function exit(): void
	{
		exit;// @codeCoverageIgnore
	}

	protected function is_callable(mixed $value): bool
	{
		return is_callable($value);
	}
}
