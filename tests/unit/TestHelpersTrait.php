<?php

namespace Zikan;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

trait TestHelpersTrait
{
	protected static ?string $tmpdir = null;

	protected static function tmpdir_make(): bool
	{
		try {
			static::$tmpdir = rtrim(sys_get_temp_dir(), '/') . '/' . bin2hex(random_bytes(4));
		} catch (Throwable $e) {
			throw new RuntimeException(message: 'Could not create tmp dir', previous: $e);
		}

		return mkdir(directory: static::$tmpdir, permissions: 0755, recursive: true);
	}

	protected static function tmpdir_remove(): bool
	{
		if (is_null(static::$tmpdir)) {
			return false;
		}

		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(static::$tmpdir, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($it as $info) {
			if ($info instanceof SplFileInfo) {
				$info->isDir() ? rmdir($info->getPathname()) : unlink($info->getPathname());
			}
		}

		return rmdir(static::$tmpdir);
	}
}
