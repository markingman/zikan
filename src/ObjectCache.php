<?php declare(strict_types=1);

namespace Zikan;

use Zikan\Exception\ObjectCacheError;
use Zikan\Exception\ObjectCacheException;

class ObjectCache implements ObjectCacheInterface
{
	public const string FILE_PREFIX = 'cache';
	private readonly string $cache_path;

	/** @param $cache_path string Serialized classes cache dir */
	public function __construct(string $cache_path)
	{
		if (!$real_cache_path = realpath($cache_path)) {
			throw new ObjectCacheException('Could not resolve cache path', ObjectCacheError::INVALID_PATH);
		}

		$this->cache_path = $real_cache_path;
	}

	public function get_cache_path(): string
	{
		return $this->cache_path;
	}

	/**
	 * @template T of object
	 * @param string $name
	 * @param class-string<T> $instanceof
	 * @return T|null
	 * @throws ObjectCacheException When cannot read file or cannot unserialize
	 */
	public function cache_get(string $name, string $instanceof): ?object
	{
		$cache_path = $this->cache_path . DIRECTORY_SEPARATOR . $this->get_cache_key($name);

		if (!file_exists($cache_path)) {
			return null;
		}

		if (!$this->is_readable($cache_path) or !$serialized = $this->file_get_contents($cache_path)) {
			throw new ObjectCacheException('Could not read cache file', ObjectCacheError::FILE_READ);
		}

		if (!$class = @unserialize($serialized, ['allowed_classes' => true])) {
			throw new ObjectCacheException('Could not unserialize cache file', ObjectCacheError::UNSERIALIZE);
		}

		if (!is_object($class)) {
			return null;
		}

		if (!$class instanceof $instanceof) {
			return null;
		}

		return $class;
	}

	/** @throws ObjectCacheException When cannot write file */
	public function cache_put(string $name, object $object): bool
	{
		if (!$this->file_put_contents($this->cache_path . DIRECTORY_SEPARATOR . $this->get_cache_key($name), $this->serialize($object))) {
			throw new ObjectCacheException('Could not write cache file', ObjectCacheError::FILE_WRITE);
		}

		return true;
	}

	/**
	 * Lightweight delete all cache files
	 *
	 * @throws ObjectCacheException if file deletion fails
	 */
	public function cache_drop(): void
	{
		if (($files = @$this->scandir($this->cache_path)) === false) {
			throw new ObjectCacheException('Could not delete cache files', ObjectCacheError::UNLINK_FILE);
		}

		foreach ($files as $file) {
			if (!str_starts_with($file, static::FILE_PREFIX)) {
				continue;
			}

			$file_path = $this->cache_path . DIRECTORY_SEPARATOR . $file;
			if (is_file($file_path)) {
				if (!$this->unlink($file_path)) {
					throw new ObjectCacheException('Could not delete cache file', ObjectCacheError::UNLINK_FILE);
				}
			}
		}
	}

	/**
	 * Returns a filesystem-safe cache filename.
	 * Example: 'cache' + md5('MyService') => 'cache1a79a4d60de6718e8e5b326e338ae533'
	 */
	public function get_cache_key(string $name): string
	{
		return static::FILE_PREFIX . md5($name);
	}

	protected function is_readable(string $file): bool
	{
		return is_readable($file);
	}

	protected function serialize(object $object): string
	{
		return serialize($object);
	}

	/** @return array<string>|false */
	protected function scandir(string $dir): array|false
	{
		return scandir($dir);
	}

	protected function unlink(string $file): bool
	{
		return unlink($file);
	}

	protected function file_put_contents(string $file, string $contents): int|false
	{
		return @file_put_contents($file, $contents);
	}

	protected function file_get_contents(string $file): string|false
	{
		return @file_get_contents($file);
	}

}
