<?php declare(strict_types=1);

namespace Zikan;

interface ConfigInterface
{
	/**
	 * @param array<int, string> $paths
	 * @param ?array<string, string> $config
	 */
	public function __construct(array $paths = [], ?array $config = null);

	public function get(string $k): ?string;

	public function isset(string $k): bool;

	/** @return array<string, string> */
	public function list(): array;
}
