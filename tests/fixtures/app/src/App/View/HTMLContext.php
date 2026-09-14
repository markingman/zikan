<?php

namespace Zikan\Test\App\View;

use RuntimeException;

class HTMLContext
{
	public static function ob_start(): void
	{
		ob_start();
	}

	public static function ob_get_clean(): string
	{
		if (!$ob = ob_get_clean()) {
			throw new RuntimeException('Could not get clean obout buffer');
		}

		return $ob;
	}

	public function path_css(string $file = ''): string
	{
		return $this->path_asset('css', $file);
	}

	protected function path_asset(string $path, string $file): string
	{
		return '/' . $path . ($file ? '/' . htmlentities($file) : '');
	}
}
