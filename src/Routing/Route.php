<?php

namespace Zikan\Routing;

use Closure;

readonly class Route
{
	/** @param array<string, string> $vars */
	public function __construct(
		public string $controller,
		public array $vars,
		public string $action,
		public int $method,
		public string $sprintf,
		public string $name,
		public string $regx,
		public ?Closure $callback = null
	) {
	}
}
