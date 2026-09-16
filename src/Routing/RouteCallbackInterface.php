<?php

namespace Zikan\Routing;

interface RouteCallbackInterface
{
	/** @param array<string> $m */
	public function __invoke(string $method, Route $route, array $m, string $url): RouteMatch|false;
}
