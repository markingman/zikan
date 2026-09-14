<?php declare(strict_types=1);

namespace Zikan;

use Closure;
use Zikan\Exception\ContainerError;
use Zikan\Exception\ContainerException;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionUnionType;
use Throwable;

class Container implements ContainerInterface
{
	/** @var string Default registrations dir */
	protected string $registrations_path = '';
	/** @var array<string, array{0: string, 1?: array<mixed>}> List of file paths to closures */
	protected array $registrations = [];
	/** @var array<string, Closure> $registry List of loaded closures */
	protected array $registry = [];
	/** @var array<string, ReflectionClass<object>> $reflections Cache of reflections */
	protected array $reflections = [];
	/** @var array<string, object> $instances List of instantiated objects */
	protected array $instances = [];
	/** @var array<string, string> $aliases List of name aliases */
	protected array $aliases = [];

	public function __construct(?string $registrations_path = null)
	{
		if ($registrations_path) {
			$this->set_registrations_path($registrations_path);
		}
	}

	public function get_registrations_path(): string
	{
		return $this->registrations_path;
	}

	public function set_registrations_path(string $path): void
	{
		$this->registrations_path = realpath($path) ?: '';
	}

	public function set_alias(string $alias_name, string $target_name): void
	{
		if ($alias_name === $target_name or (isset($this->aliases[$target_name]) and $this->aliases[$target_name] === $alias_name)) {
			throw new ContainerException("Recursive alias definition: '$alias_name' > '$target_name'", ContainerError::ALIAS_RECURSION);
		}

		$this->aliases[$alias_name] = $target_name;
	}

	public function get_alias(string $alias_name): ?string
	{
		return $this->aliases[$alias_name] ?? null;
	}

	/** @param ?array<mixed> $args */
	public function register_path(string $name, string $path, ?array $args = null): void
	{
		$this->registrations[$name] = $args ? [$path, $args] : [$path];
	}

	public function register(string $name, Closure $closure): void
	{
		$this->registry[$name] = $closure;
	}

	/**
	 * Create object and return it; if not in registry, try to register from file path, if in registry create from Closure, or finally attempt to instantiate from class name
	 */
	public function create(string $name, bool $store = false): object
	{
		if (!isset($this->registry[$name])) {
			$this->attempt_register_from_path($name);
		}

		if (isset($this->registry[$name])) {
			try {
				$instance = $this->registry[$name]($this);
			} catch (Throwable $e) {
				throw new ContainerException("Could not create '$name'", ContainerError::CREATE_FAILURE, previous: $e);
			}

			if (!is_object($instance)) {
				throw new ContainerException("Object not created for '$name'", ContainerError::NOT_OBJECT);
			}

			if ($store) {
				$this->instances[$name] = $instance;
			}

			return $instance;
		} else {
			// for this edge case it tries presuming $name is class name
			return $this->instantiate($name, $name, $store);
		}
	}

	/**
	 * Attempt to call an object's method and attempt to instantiate any objects that are parameters.
	 * @param array<mixed> $args
	 */
	public function call(object $class, string $method_name, array $args = [], bool $store = false, bool $force_new = false, bool $store_reflection = false): mixed
	{
		$class_name = get_class($class);

		try {
			$reflection = $this->reflections[$class_name] ?? new ReflectionClass($class_name);
			$method = $reflection->getMethod($method_name);
			$method_params = $method->getParameters();
			$params = ($args or $method_params) ? $this->set_params($method_params, $args, $store, $force_new) : [];
		} catch (Throwable $e) {
			throw new ContainerException("Could not call $class_name::$method_name", ContainerError::CALL_FAILURE, previous: $e);
		}

		if ($store_reflection) {
			$this->reflections[$class_name] = $reflection;
		}

		$callable = [$class, $method_name];
		if (!is_callable($callable)) {
			throw new ContainerException("Not callable $class_name::$method_name", ContainerError::NOT_CALLABLE);
		}

		return call_user_func_array($callable, $params);
	}

	/**
	 * Retrieve an instance by name or alias.
	 * If not yet created, will attempt to build it via registered closure, location path, or reflection.
	 *
	 * @param string $name Service name or alias
	 * @param bool $store_created Whether to cache the created instance
	 * @return object
	 */
	public function get(string $name, bool $store_created = true): object
	{
		$name = $this->aliases[$name] ?? $name;

		return $this->instances[$name] ?? $this->create($name, $store_created);
	}

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
	public function get_as(string $name, string $instanceof, bool $store_created = true): object
	{
		$instance = $this->get($name, $store_created);

		if (!$instance instanceof $instanceof) {
			throw new ContainerException("Object '$name' not instanceof '$instanceof'", ContainerError::UNEXPECTED_CLASS);
		}

		return $instance;
	}

	public function set(string $name, object $value): void
	{
		if ($value instanceof Closure) {
			$this->register($name, $value);
		} else {
			$this->instances[$name] = $value;
		}
	}

	public function exists(string $name): bool
	{
		$name = $this->aliases[$name] ?? $name;

		return isset($this->instances[$name]) or isset($this->registrations[$name]) or isset($this->registry[$name]);
	}

	public function unset(string $name): void
	{
		unset($this->instances[$name]);
	}

	/** @return array<string, array{0: string, 1?: array<mixed>}> */
	public function list_registrations(): array
	{
		return $this->registrations;
	}

	/** @return array<string, Closure> */
	public function list_registry(): array
	{
		return $this->registry;
	}

	/** @return array<string, object> */
	public function list_instances(): array
	{
		return $this->instances;
	}

	/** Attempt to register from a file path */
	protected function attempt_register_from_path(string $name): void
	{
		if ($this->registrations_path and !isset($this->registrations[$name])) {
			if ($registrations_path = $this->get_registrations_realpath($name)) {
				$this->register_path($name, $registrations_path);
			}
		}

		if (isset($this->registrations[$name])) {
			$path = $this->registrations[$name][0];
			$args = $this->registrations[$name][1] ?? [];
			try {
				$closure = (Closure::bind(function () use ($path, $args): mixed {
					extract($args);

					return include $path;
				}, null)());

			} catch (Throwable $e) {
				throw new ContainerException("Could not create '$name'", ContainerError::LOAD_FAILURE, previous: $e);
			}

			if (!($closure instanceof Closure)) {
				throw new ContainerException("Location for '$name' must return \Closure", ContainerError::TYPE_FAILURE);
			}

			$this->register($name, $closure);
		}
	}

	protected function get_registrations_realpath(string $name): string|false
	{
		static $paths = [];

		if (!isset($paths[$name])) {
			$realpath = realpath($this->registrations_path . DIRECTORY_SEPARATOR . $name . '.php');
			$paths[$name] = (
				$realpath
				and str_starts_with($realpath, $this->registrations_path)
				and basename($realpath, '.php') === $name
			) ? $realpath : false;
		}

		return $paths[$name];
	}

	protected function get_class_name_from_type(string $name): string
	{
		if (str_ends_with($name, 'Interface') and ($len = strlen($name)) > 9) { // strlen('Interface') === 9
			$name = substr($name, 0, $len - 9);
		}

		return $name;
	}

	/**
	 * Set parameters, including attempt to retrieve or instantiate objects — note fallback, try using class type name if nothing registered with param name
	 * @param array<ReflectionParameter> $params
	 * @param array<mixed> $args
	 * @return array<mixed>
	 */
	protected function set_params(array $params, array $args = [], bool $store = false, bool $force_new = false): array
	{
		foreach ($params as $i => $param) {
			if (isset($args[$i])) {
				$params[$i] = $args[$i];
				continue;
			}

			$p_opt = $param->isOptional();
			$p_name = $param->getName();
			if ($p_opt) {
				try {
					$params[$i] = $param->getDefaultValue();
				} catch (ReflectionException $e) {
					throw new ContainerException("cannot resolve default value for parameter '$p_name'; " . $e->getMessage(), ContainerError::INVALID_ARGUMENT);
				}
				continue;
			}

			$p_type = $param->getType();
			if (is_null($p_type)) {
				throw new ContainerException("cannot resolve parameter '$p_name'", ContainerError::INVALID_ARGUMENT);
			}

			$p_null = $param->allowsNull();
			if ($p_null) {
				$params[$i] = null;
				continue;
			}

			if ($p_type instanceof ReflectionUnionType) {
				throw new ContainerException("cannot handle union type parameter '$p_name'", ContainerError::UNION_TYPE);
			}

			$p_type_name = (string)$p_type;

			if (!in_array($p_type_name, ['int', 'string', 'array', 'bool', 'float', 'callable', 'iterable'])) {
				if (!$force_new and isset($this->instances[$p_name])) {
					$params[$i] = $this->instances[$p_name];
				} else {
					$name = $this->aliases[$p_name] ?? $p_name;
					if (!$this->exists($name)) {
						$name = $this->get_class_name_from_type($p_type_name);
					}
					$params[$i] = $this->create($name, $store);
				}
				continue;
			}

			throw new ContainerException("cannot resolve parameter '$p_name'", ContainerError::UNRESOLVED_PARAMETER);
		}

		return $params;
	}

	protected function instantiate(string $class_name, ?string $name = null, bool $store = false): object
	{
		if (!class_exists($class_name)) {
			throw new ContainerException("Could not find '$class_name'", ContainerError::CLASS_NOT_FOUND);
		}

		$reflection = new ReflectionClass($class_name);
		$constructor = $reflection->getConstructor();

		try {
			if (!is_null($constructor)) {
				$class = $reflection->newInstanceArgs(
					$this->set_params($constructor->getParameters())
				);
			} else {
				$class = $reflection->newInstanceArgs();
			}
		} catch (Throwable $e) {
			throw new ContainerException("Could not instantiate '$class_name'", ContainerError::INSTANTIATE_FAILURE, previous: $e);
		}

		if ($store) {
			$this->instances[$name ?: $class_name] = $class;
		}

		return $class;
	}
}
