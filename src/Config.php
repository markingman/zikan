<?php declare(strict_types=1);

namespace Zikan;

use Zikan\Exception\ConfigUnexpectedValueException;

final class Config implements ConfigInterface
{
	/** @var array<string, string> @config */
	protected array $config = [];

	/**
	 * @param array<int, string> $paths
	 * @param ?array<string, string> $values
	 */
	public function __construct(array $paths = [], ?array $values = null)
	{
		$config = [];
		foreach ($paths as $path) {
			$config[] = require $path;
		}

		if ($values) {
			$config[] = $values;
		}

		if ($config) {
			foreach (array_replace_recursive(...$config) as $k => $v) {
				if (is_string($k) and is_string($v)) {
					$this->config[$k] = $v;
				}
			}
		}
	}

	public function get(string $k): ?string
	{
		return $this->config[$k] ?? null;
	}

	public function isset(string $k): bool
	{
		return isset($this->config[$k]);
	}

	/** @return array<string, string> */
	public function list(): array
	{
		return $this->config;
	}

	public function __serialize(): array
	{
		return ['config' => $this->config];
	}

	/** @param array<string, mixed> $data */
	public function __unserialize(array $data): void
	{
		if (!isset($data['config']) or !is_array($data['config'])) {
			throw new ConfigUnexpectedValueException('Config requires a "config" array');
		}

		$config = [];
		foreach ($data['config'] as $k => $v) {
			if (!is_string($k)) {
				throw new ConfigUnexpectedValueException('Tried to load non-string Config key');
			}

			if (!is_string($v)) {
				throw new ConfigUnexpectedValueException("Tried to load non-string Config value for key '$k'");
			}

			$config[$k] = $v;
		}

		$this->config = $config;
	}
}
