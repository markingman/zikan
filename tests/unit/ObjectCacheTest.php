<?php

namespace Zikan;

use Zikan\Exception\ObjectCacheError;
use Zikan\Exception\ObjectCacheException;
use Zikan\Test\TestClassPlainSimple;
use PHPUnit\Framework\TestCase;

class ObjectCacheTest extends TestCase
{
	use TestHelpersTrait;

	protected ObjectCacheInterface $ObjectCache;
	private string $dir_cache;

	public static function setUpBeforeClass(): void
	{
		static::tmpdir_make();
	}

	public static function tearDownAfterClass(): void
	{
		static::tmpdir_remove();
	}

	public function setUp(): void
	{
		if (is_null(static::$tmpdir) or !is_dir(static::$tmpdir)) {
			$this->fail('Could not find tmpdir dir');
		}

		$this->dir_cache = static::$tmpdir;

		$this->ObjectCache = new ObjectCache($this->dir_cache);
	}

	public function testCreate(): void
	{
		$this->assertInstanceOf(ObjectCacheInterface::class, $this->ObjectCache);
	}

	public function testConstructorInvalidPath(): void
	{
		$this->expectException(ObjectCacheException::class);
		$this->expectExceptionMessage('OBJECT_CACHE_INVALID_PATH');
		$this->expectExceptionCode(500);

		try {
			new ObjectCache('/non/existing/path/xyz');
		} catch (ObjectCacheException $e) {
			$this->assertSame(ObjectCacheError::INVALID_PATH, $e->getErrorCode());
			throw $e;
		}
	}

	public function testCachePath(): void
	{
		$this->assertSame($this->dir_cache, $this->ObjectCache->get_cache_path(), 'Can set the cache path');
		$ObjectCache = new ObjectCache(__DIR__);
		$this->assertSame(__DIR__, $ObjectCache->get_cache_path(), 'Can set the cache path');
	}

	public function testCacheKey(): void
	{
		$res = $this->ObjectCache->get_cache_key('class');
		$this->assertStringStartsWith(ObjectCache::FILE_PREFIX, $res);
	}

	public function testCachePut(): void
	{
		$this->assertTrue(
			$this->ObjectCache->cache_put('put', new TestClassPlainSimple()),
			'Can put an object to cache'
		);

		$this->assertFileExists($this->cache_file('put'));
	}

	public function testCachePutFailWrite(): void
	{
		$ObjectCacheMock = new class($this->dir_cache) extends ObjectCache {
			protected function file_put_contents(string $file, string $contents): int|false
			{
				return false;
			}
		};

		$this->expectException(ObjectCacheException::class);
		$this->expectExceptionMessage('OBJECT_CACHE_FILE_WRITE');
		$this->expectExceptionCode(500);

		try {
			$ObjectCacheMock->cache_put('failwrite', new TestClassPlainSimple());
		} catch (ObjectCacheException $e) {
			$this->assertSame(ObjectCacheError::FILE_WRITE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testCachePutFailSerialize(): void
	{
		$ObjectCacheMock = new class($this->dir_cache) extends ObjectCache {
			protected function serialize(object $object): string
			{
				return '';
			}
		};

		$this->expectException(ObjectCacheException::class);
		$this->expectExceptionMessage('OBJECT_CACHE_FILE_WRITE');
		$this->expectExceptionCode(500);

		try {
			$ObjectCacheMock->cache_put('failwrite', new TestClassPlainSimple());
		} catch (ObjectCacheException $e) {
			$this->assertSame(ObjectCacheError::FILE_WRITE, $e->getErrorCode());
			throw $e;

		}
	}

	public function testGetCacheNoFile(): void
	{
		$this->assertFileDoesNotExist($this->cache_file('nofile'));
		$this->assertNull($this->ObjectCache->cache_get('nofile', TestClassPlainSimple::class), 'Cache is FALSE if file not readable');
	}

	public function testGetCacheNotReadable(): void
	{
		$ObjectCacheMock = new class($this->dir_cache) extends ObjectCache {
			protected function is_readable(string $file): bool
			{
				return false;
			}
		};

		$ObjectCacheMock->cache_put('notreadable', new TestClassPlainSimple());

		$this->expectException(ObjectCacheException::class);
		$this->expectExceptionMessage('OBJECT_CACHE_FILE_READ');
		$this->expectExceptionCode(500);
		try {
			$ObjectCacheMock->cache_get('notreadable', TestClassPlainSimple::class);
		} catch (ObjectCacheException $e) {
			$this->assertSame(ObjectCacheError::FILE_READ, $e->getErrorCode());
			throw $e;
		}
	}

	public function testGetCacheCannotRead(): void
	{
		$ObjectCacheMock = new class($this->dir_cache) extends ObjectCache {
			protected function file_get_contents(string $file): string|false
			{
				return false;
			}
		};

		$ObjectCacheMock->cache_put('notreadable', new TestClassPlainSimple());

		$this->expectException(ObjectCacheException::class);
		$this->expectExceptionMessage('OBJECT_CACHE_FILE_READ');
		$this->expectExceptionCode(500);
		try {
			$ObjectCacheMock->cache_get('notreadable', TestClassPlainSimple::class);

		} catch (ObjectCacheException $e) {
			$this->assertSame(ObjectCacheError::FILE_READ, $e->getErrorCode());
			throw $e;

		}
	}

	public function testGetCacheUnserializable(): void
	{
		file_put_contents(
			$this->cache_file('unserializable'),
			'test'
		);

		$this->expectException(ObjectCacheException::class);
		$this->expectExceptionMessage('OBJECT_CACHE_UNSERIALIZE');
		$this->expectExceptionCode(500);

		try {
			$this->ObjectCache->cache_get('unserializable', TestClassPlainSimple::class);
		} catch (ObjectCacheException $e) {
			$this->assertSame(ObjectCacheError::UNSERIALIZE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testGetCacheNotObject(): void
	{
		file_put_contents(
			$this->cache_file('notobject'),
			serialize(['a'])
		);

		$this->assertNull($this->ObjectCache->cache_get('notobject', TestClassPlainSimple::class), 'Cache is FALSE if not an object');
	}

	public function testGetCacheWrongObject(): void
	{
		$this->ObjectCache->cache_put('wrongobject', (object)[]);

		$this->assertNull($this->ObjectCache->cache_get('wrongobject', TestClassPlainSimple::class), 'Cache is FALSE if wrong type of object');
	}

	public function testGet(): void
	{
		$obj = new TestClassPlainSimple();

		$this->ObjectCache->cache_put('get', $obj);

		$this->assertInstanceOf(
			TestClassPlainSimple::class,
			$this->ObjectCache->cache_get('get', TestClassPlainSimple::class),
			'Must be able to unserialize an object'
		);
	}

	public function testDrop(): void
	{
		$obj = new TestClassPlainSimple();

		$file1 = $this->cache_file('drop1');
		$file2 = $this->cache_file('drop2');

		$this->assertFileDoesNotExist($file1);
		$this->assertFileDoesNotExist($file2);

		$this->ObjectCache->cache_put('drop1', $obj);
		$this->ObjectCache->cache_put('drop2', $obj);

		$this->assertFileExists($file1);
		$this->assertFileExists($file2);

		$this->ObjectCache->cache_drop();

		$this->assertFileDoesNotExist($file1);
		$this->assertFileDoesNotExist($file2);
	}

	public function testDropFailScanDir(): void
	{
		$ObjectCacheMock = new class($this->dir_cache) extends ObjectCache {
			/** @return array<string>|false */
			protected function scandir(string $dir): array|false
			{
				return false;
			}
		};

		$obj = new TestClassPlainSimple();

		$file1 = $this->cache_file('drop1failscan');
		$file2 = $this->cache_file('drop2failscan');

		$this->assertFileDoesNotExist($file1);
		$this->assertFileDoesNotExist($file2);

		$ObjectCacheMock->cache_put('drop1failscan', $obj);
		$ObjectCacheMock->cache_put('drop2failscan', $obj);

		$this->assertFileExists($file1);
		$this->assertFileExists($file2);

		$this->expectException(ObjectCacheException::class);
		$this->expectExceptionMessage('UNLINK_FILE');
		$this->expectExceptionCode(500);

		try {
			$ObjectCacheMock->cache_drop();
		} catch (ObjectCacheException $e) {
			$this->assertSame(ObjectCacheError::UNLINK_FILE, $e->getErrorCode());
			throw $e;
		}
	}

	public function testDropFailUnlink(): void
	{
		$ObjectCacheMock = new class($this->dir_cache) extends ObjectCache {
			protected function unlink(string $file): bool
			{
				return false;
			}
		};

		$obj = new TestClassPlainSimple();

		$file1 = $this->cache_file('drop1unlink');
		$file2 = $this->cache_file('drop2unlink');

		$this->assertFileDoesNotExist($file1);
		$this->assertFileDoesNotExist($file2);

		$ObjectCacheMock->cache_put('drop1unlink', $obj);
		$ObjectCacheMock->cache_put('drop2unlink', $obj);

		$this->assertFileExists($file1);
		$this->assertFileExists($file2);

		$this->expectException(ObjectCacheException::class);
		$this->expectExceptionMessage('UNLINK_FILE');
		$this->expectExceptionCode(500);

		try {
			$ObjectCacheMock->cache_drop();
		} catch (ObjectCacheException $e) {
			$this->assertSame(ObjectCacheError::UNLINK_FILE, $e->getErrorCode());
			throw $e;
		}
	}

	private function cache_file(string $key): string
	{
		return $this->dir_cache . DIRECTORY_SEPARATOR . $this->ObjectCache->get_cache_key($key);
	}
}
