<?php declare(strict_types=1);

namespace Zikan;

use Closure;
use Zikan\Exception\ContainerException;

interface ContainerInterface
{
	public function set_registrations_path(string $path): void;

	public function get_registrations_path(): string;

	public function set_alias(string $alias_name, string $target_name): void;

	public function get_alias(string $alias_name): ?string;

	/** @param ?array<mixed> $args */
	public function register_path(string $name, string $path, ?array $args = null): void;

	public function register(string $name, Closure $closure): void;

	public function create(string $name, bool $store = false): object;

	/** @param array<mixed> $args */
	public function call(object $class, string $method_name, array $args = [], bool $store = false, bool $force_new = false, bool $store_reflection = false): mixed;

	/**
	 * Retrieve an instance by name or alias.
	 * If not yet created, will attempt to build it via registered closure, location path, or reflection.
	 *
	 * @param string $name Service name or alias
	 * @param bool $store_created Whether to cache the created instance
	 * @return object
	 */
	public function get(string $name, bool $store_created = true): object;

	/**
	 * Retrieve an instance and confirm its type or fail
	 *
	 * @template T of object
	 * @param string $name Service name or alias
	 * @param class-string<T> $instanceof Optionally confirm the class type
	 * @param bool $store_created Whether to cache the instance if it's newly created
	 * @return T
	 * @throws ContainerException If instantiation fails
	 */
	public function get_as(string $name, string $instanceof, bool $store_created = true): object;

	public function set(string $name, object $value): void;

	public function exists(string $name): bool;

	public function unset(string $name): void;

	/** @return array<string, array{0: string, 1?: array<mixed>}> */
	public function list_registrations(): array;

	/** @return array<string, Closure> */
	public function list_registry(): array;

	/** @return array<string, object> */
	public function list_instances(): array;
}
