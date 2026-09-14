<?php declare(strict_types=1);

namespace Zikan\Http;

class Cookie
{
	public function __construct(
		public string $value = '',
		public int $expires = 0,
		public string $path = '',
		public string $domain = '',
		public bool $secure = false,
		public bool $httponly = false,
	) {
	}
}
