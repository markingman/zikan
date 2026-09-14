<?php declare(strict_types=1);

namespace Zikan\Test\App\View;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

readonly class Preloader
{
	public function __construct(
		protected string $dir,
		protected int $max_depth = 5,
	) {
	}

	/** @param array<string> $paths */
	public function preload(array $paths): void
	{
		foreach ($paths as $path) {
			$path = $this->dir . DIRECTORY_SEPARATOR . $path;

			if (is_file($path)) {
				$this->load($path);
				continue;
			}

			if (is_dir($path)) {
				$this->preload_dir($path);
				continue;
			}

			throw new RuntimeException("Preload path does not exist: '$path'");
		}
	}

	protected function preload_dir(string $dir): void
	{
		$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
		$it->setMaxDepth($this->max_depth);

		foreach ($it as $file) {
			if (				$file instanceof SplFileInfo				and $file->isFile()				and $file->getExtension() === 'php'			) {
				$this->load($file->getPathname());
			}
		}
	}

	protected function load(string $file): void
	{
		require_once $file;
	}
}
