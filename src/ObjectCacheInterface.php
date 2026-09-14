<?php declare(strict_types=1);

namespace Zikan;

use Zikan\Exception\ObjectCacheException;

interface ObjectCacheInterface
{
	public function get_cache_path(): string;

	/**
	 * @template T of object
	 * @param string $name
	 * @param class-string<T> $instanceof
	 * @return T|null
	 * @throws ObjectCacheException When cannot read file or cannot unserialize
	 */
	public function cache_get(string $name, string $instanceof): ?object;

	/** @throws ObjectCacheException When cannot write file */
	public function cache_put(string $name, object $object): bool;

	public function get_cache_key(string $name): string;

	public function cache_drop(): void;
}
