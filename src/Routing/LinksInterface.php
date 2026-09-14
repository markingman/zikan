<?php

namespace Zikan\Routing;

interface LinksInterface
{
	/**
	 * @param array<string, string> $vars Route variables and/or additional query parameters. Path variables will be substituted if defined in the route pattern.
	 * @param bool $relative Return full or relative URL
	 * @param ?string $protocol URL protocol like http, https, mailto etc.
	 * @return string Absolute or relative URL for the given route name
	 */
	public function get_link(string $name, array $vars = [], bool $relative = true, ?string $protocol = null): string;
}
