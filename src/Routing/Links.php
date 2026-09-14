<?php

namespace Zikan\Routing;

use Zikan\Exception\LinksException;
use Throwable;

class Links implements LinksInterface
{
	public function __construct(
		protected RouterInterface $Router,
		/** Fully qualified origin (e.g: example.com or example.com:80) */
		protected string $site_origin,
		/** Assumes $site_path includes leading slash and no trailing slash (e.g: /path or "") */
		protected string $site_path,
	) {
	}

	/**
	 * @param array<string, string> $vars Route variables and/or additional query parameters. Path variables will be substituted if defined in the route pattern.
	 * @param bool $relative Return full or relative URL
	 * @param ?string $protocol URL protocol like http, https, mailto etc.
	 * @return string Absolute or relative URL for the given route name
	 * @throws LinksException If no link found
	 */
	public function get_link(string $name, array $vars = [], bool $relative = true, ?string $protocol = null): string
	{
		try {
			$link = $this->Router->get_rewrite($name, $vars);
		} catch (Throwable $e) {
			throw new LinksException("No link found for \"$name\"", previous: $e);
		}

		if ($relative) {
			return $this->site_path . $link;
		} else {
			return ($protocol ? $protocol . '://' : '//') . $this->site_origin . $this->site_path . $link;
		}
	}
}
