<?php declare(strict_types=1);

namespace Zikan\Http;

use DomainException;
use RuntimeException;

/**
 * Immutable wrapper around PHP's superglobals, with sanitization and filtering utilities.
 *
 * Handles GET, POST, FILES, SERVER, and COOKIE input, plus GETX override values.
 */
class Request implements RequestInterface
{
	public const string REGX_VAR = '~^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$~i';
	public const string INPUT_TYPE_GET = 'GET';
	public const string INPUT_TYPE_POST = 'POST';
	public const string INPUT_TYPE_GETX = 'GETX';

	protected ?bool $is_ajax = null;
	protected ?bool $is_ssl = null;
	/** @var array<string, mixed> $GETX Manually set GETX values, e.g. from /url/path/{var} */
	protected array $GETX = [];
	protected ?string $method = null;
	protected ?string $path = null;
	protected ?string $ua = null;
	protected ?string $ip = null;
	protected ?string $ref = null;
	/** @var array<string, string>|null $headers */
	protected ?array $headers = null;

	/**
	 * Remember COW semantics, omitted &'s are intentional
	 * @param array<string, mixed> $GET
	 * @param array<string, mixed> $POST
	 * @param array<string, mixed> $FILES
	 * @param array<string, mixed> $SERVER
	 * @param array<string, mixed> $COOKIE
	 */
	public function __construct(
		protected readonly array $GET,
		protected readonly array $POST,
		protected readonly array $FILES,
		protected readonly array $SERVER,
		protected readonly array $COOKIE,
	) {
	}

	/** @param array<string, string>|null $headers */
	public function set_headers(?array $headers = null): void
	{
		if (is_null($headers)) {
			$headers = str_contains($this->php_sapi_name(), 'cli') ? [] : $this->getallheaders();
		}

		$this->headers = $headers ? array_change_key_case($headers) : [];
	}

	public function set_get_value(string $k, string $v): void
	{
		$this->GETX[$k] = $v;
	}

	public function get_header(string $header): string|null
	{
		if (is_null($this->headers)) {
			$this->set_headers();
		}

		return $this->headers[strtolower($header)] ?? null;
	}

	/** Retrieves a GET parameter, using GETX override if defined */
	public function get_get(string $key): ?string
	{
		if (isset($this->GETX[$key]) and is_string($this->GETX[$key])) {
			return $this->GETX[$key];
		} else {
			return (isset($this->GET[$key]) and is_string($this->GET[$key])) ? $this->GET[$key] : null;
		}
	}

	public function get_post(string $key): ?string
	{
		return (isset($this->POST[$key]) and is_string($this->POST[$key])) ? $this->POST[$key] : null;
	}

	/** @return array<mixed, mixed>|null */
	public function get_file(string $key): ?array
	{
		return (!empty($this->FILES[$key]) and is_array($this->FILES[$key])) ? $this->FILES[$key] : null;
	}

	public function get_server(string $key): ?string
	{
		return (isset($this->SERVER[$key]) and is_string($this->SERVER[$key])) ? $this->SERVER[$key] : null;
	}

	public function get_cookie(string $key): ?string
	{
		return (isset($this->COOKIE[$key]) and is_string($this->COOKIE[$key])) ? $this->COOKIE[$key] : null;
	}

	/**  @param string|string[] $vars */
	public function get_int_from_get(string|array $vars = 'id', int $default = 0, int $min_range = 0): int
	{
		return $this->get_int_request($this->use_getx_input_type($vars) ? static::INPUT_TYPE_GETX : static::INPUT_TYPE_GET, $vars, $default, $min_range);
	}

	/**  @param string|string[] $vars */
	public function get_int_from_post(string|array $vars = 'id', int $default = 0, int $min_range = 0): int
	{
		return $this->get_int_request(static::INPUT_TYPE_POST, $vars, $default, $min_range);
	}

	/**  @param string|string[] $vars */
	public function get_var_from_get(string|array $vars = 'arg1', string $default = ''): string
	{
		return $this->get_var_request($this->use_getx_input_type($vars) ? static::INPUT_TYPE_GETX : static::INPUT_TYPE_GET, $vars, $default);
	}

	/**  @param string|string[] $vars */
	public function get_var_from_post(string|array $vars = 'arg1', string $default = ''): string
	{
		return $this->get_var_request(static::INPUT_TYPE_POST, $vars, $default);
	}

	/**  @param string|string[] $vars */
	public function get_val_from_get(string|array $vars = 'arg1', string $default = ''): string
	{
		return $this->get_val_request($this->use_getx_input_type($vars) ? static::INPUT_TYPE_GETX : static::INPUT_TYPE_GET, $vars, $default);
	}

	/**  @param string|string[] $vars */
	public function get_val_from_post(string|array $vars = 'arg1', string $default = ''): string
	{
		return $this->get_val_request(static::INPUT_TYPE_POST, $vars, $default);
	}

	/**
	 * @param string $var
	 * @param array<string> $opts
	 * @param string $default
	 * @return string
	 */
	public function get_sel_from_get(string $var = 'arg1', array $opts = [], string $default = ''): string
	{
		return $this->get_sel_request($this->use_getx_input_type($var) ? static::INPUT_TYPE_GETX : static::INPUT_TYPE_GET, $var, $opts, $default);
	}

	/**
	 * @param string $var
	 * @param array<string> $opts
	 * @param string $default
	 * @return string
	 */
	public function get_sel_from_post(string $var = 'arg1', array $opts = [], string $default = ''): string
	{
		return $this->get_sel_request(static::INPUT_TYPE_POST, $var, $opts, $default);
	}

	/**
	 * @param string $var
	 * @param string[] $default
	 * @param string $filter
	 * @return string[]
	 */
	public function get_array_from_get(string $var, array $default = [], string $filter = 'filter_val'): array
	{
		return $this->get_array($this->use_getx_input_type($var) ? static::INPUT_TYPE_GETX : static::INPUT_TYPE_GET, $var, $default, $filter);
	}

	/**
	 * @param string $var
	 * @param string[] $default
	 * @param string $filter
	 * @return string[]
	 */
	public function get_array_from_post(string $var, array $default = [], string $filter = 'filter_val'): array
	{
		return $this->get_array(static::INPUT_TYPE_POST, $var, $default, $filter);
	}

	public function get_ua(): string
	{
		if (is_null($this->ua)) {
			$this->set_ua($this->filter_var($this->get_server('HTTP_USER_AGENT') ?? '', FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH));
		}

		return $this->ua ?? '';
	}

	public function set_ua(string $ua): void
	{
		$this->ua = $this->filter_var($ua, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
	}

	public function get_ip(): string
	{
		if (is_null($this->ip)) {
			if ($ip = $this->get_server('HTTP_X_FORWARDED_FOR')) {
				$this->set_ip($ip);
			} elseif ($ip = $this->get_server('REMOTE_ADDR')) {
				$this->set_ip($ip);
			}
		}

		return $this->ip ?? '';
	}

	public function set_ip(string $ip): void
	{
		$this->ip = $this->filter_var($ip, FILTER_VALIDATE_IP);
	}

	public function get_ref(): string
	{
		if (is_null($this->ref)) {
			if ($ref = $this->get_server('HTTP_REFERER')) {
				$this->set_ref($ref);
			}
		}

		return $this->ref ?? '';
	}

	public function set_ref(?string $ref = null): void
	{
		$this->ref = $this->filter_var($ref, FILTER_SANITIZE_URL);
	}

	public function get_method(): string
	{
		if (is_null($this->method)) {
			if ($method = $this->get_server('REQUEST_METHOD')) {
				$this->set_method($method);
			}
		}

		return $this->method ?? '';
	}

	public function set_method(?string $method = null): void
	{
		// TODO: thow exception for invalid?
		$this->method = strtoupper($this->filter_var($method, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH));
	}

	public function get_path(): string
	{
		if (is_null($this->path)) {
			if ($path = $this->get_server('REQUEST_URI')) {
				$this->set_path($path);
			}
		}

		return $this->path ?? '';
	}

	public function set_path(string $url): void
	{
		if (!is_string($path = $this->parse_url($this->filter_var($url, FILTER_SANITIZE_URL), PHP_URL_PATH))) {
			throw new RuntimeException('Set path parse URL failed');
		}

		$this->path = $path;
	}

	/**
	 * @param string|null $method
	 * @param string|null $path
	 * @return array{0: string, 1: string}
	 */
	public function get_request(?string $method = null, ?string $path = null): array
	{
		if (!is_null($method)) {
			$this->set_method($method);
		}

		if (!is_null($path)) {
			$this->set_path($path);
		}

		return [$this->get_method(), $this->get_path()];
	}

	/** @return array<string, mixed> */
	public function all_get(): array
	{
		return $this->GET;
	}

	/** @return array<string, mixed> */
	public function all_getx(): array
	{
		return $this->GETX;
	}

	/** @return array<string, mixed> */
	public function all_post(): array
	{
		return $this->POST;
	}

	/** @return array<string, mixed> */
	public function all_files(): array
	{
		return $this->FILES;
	}

	public function clear_cache(): void
	{
		$this->is_ssl = null;
		$this->is_ajax = null;
	}

	public function is_ssl(?int $port = 443): bool
	{
		if (is_null($this->is_ssl)) {
			$this->is_ssl = ($this->get_server('HTTPS') and (int)$this->get_server('SERVER_PORT') === $port);
		}

		return $this->is_ssl;
	}

	public function is_post(): bool
	{
		return ($this->get_method() === static::INPUT_TYPE_POST);
	}

	public function is_ajax(): bool
	{
		if (is_null($this->is_ajax)) {
			$req = strtolower($this->get_server('HTTP_X_REQUESTED_WITH') ?? '') === 'xmlhttprequest';
			$acc = str_contains(strtolower($this->get_server('ACCEPT') ?? ''), 'application/json');
			$this->is_ajax = ($req or $acc);
		}

		return $this->is_ajax;
	}

	/**
	 * @param string $input_type
	 * @param string $var
	 * @param array<string> $opts
	 * @param string $default
	 * @return string
	 */
	protected function get_sel_request(string $input_type, string $var = 'arg1', array $opts = [], string $default = ''): string
	{
		return in_array($sel = $this->get_var_request($input_type, $var), $opts) ? $sel : $default;
	}

	/**  @param string|string[] $vars */
	protected function get_int_request(string $input_type, string|array $vars = 'id', int $default = 0, int $min_range = 0): int
	{
		$this->validate_input_type($input_type);

		foreach ((array)$vars as $var) {
			if ($this->is_usable_input($this->$input_type, $var)) {
				return $this->filter_int($this->$input_type[$var], $default, $min_range);
			}
		}

		return $default;
	}

	/**  @param string|string[] $vars */
	protected function get_var_request(string $input_type, string|array $vars = 'arg1', string $default = ''): string
	{
		$this->validate_input_type($input_type);

		foreach ((array)$vars as $var) {
			if ($this->is_usable_input($this->$input_type, $var)) {
				return $this->filter_regx_var($this->$input_type[$var]);
			}
		}

		return $default;
	}

	/**  @param string|string[] $vars */
	protected function get_val_request(string $input_type, string|array $vars = 'arg1', string $default = ''): string
	{
		$this->validate_input_type($input_type);

		foreach ((array)$vars as $var) {
			if ($this->is_usable_input($this->$input_type, $var)) {
				return $this->filter_val($this->$input_type[$var]);
			}
		}

		return $default;
	}

	/**
	 * @param string $input_type
	 * @param string $var
	 * @param string[] $default
	 * @param string $filter
	 * @return array<int, string>
	 */
	protected function get_array(string $input_type, string $var, array $default = [], string $filter = 'filter_val'): array
	{
		$return = $this->get_array_single_level($input_type, $var, $default);

		$return = array_filter($return, 'is_string');

		return array_map(callback: function ($value) use ($filter): string {
			return $this->$filter($value);
		}, array: array_values($return));
	}

	protected function filter_regx_var(mixed $value): string
	{
		return $this->filter_var($value, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => static::REGX_VAR]]);
	}

	protected function filter_val(mixed $value): string
	{
		return $this->filter_var($value, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW);
	}

	protected function filter_int(mixed $value, int $default = 0, int $min_range = 0): int
	{
		return (int)$this->filter_var($value, FILTER_VALIDATE_INT, ['options' => ['default' => $default, 'min_range' => $min_range]]);
	}

	/**
	 * Applies a filter to the given value and returns the result as a trimmed string.
	 *
	 * @param mixed $value The value to filter.
	 * @param int $filter The filter to apply (e.g., FILTER_SANITIZE_STRING, FILTER_VALIDATE_INT).
	 * @param array<string, mixed>|int $options Options or flags to modify the behavior of the filter.
	 *                                          Accepts an associative array of options or a bitwise disjunction of flags.
	 *
	 * @return string The filtered and trimmed string.
	 *
	 * @throws RuntimeException If the filter fails.
	 */

	protected function filter_var(mixed $value, int $filter = FILTER_DEFAULT, array|int $options = 0): string
	{
		if (($var = filter_var($value, $filter, $options)) !== false) {
			if (is_int($var)) {
				return (string)$var;
			}
			if (is_string($var)) {
				return trim($var);
			}
		}

		return '';
	}

	/**
	 * @param string[] $default
	 * @return string[]
	 */
	protected function get_array_single_level(string $input_type, string|int $var, array $default): array
	{
		$this->validate_input_type($input_type);

		if (isset($this->$input_type[$var])) {
			return array_values((array)$this->$input_type[$var]);
		} else {
			return $default;
		}
	}

	protected function php_sapi_name(): string
	{
		return php_sapi_name();
	}

	/** @return array<string, string> */
	protected function getallheaders(): array
	{
		return getallheaders();
	}

	/** @return array{scheme?: string, host?: string, port?: int<0, 65535>, user?: string, pass?: string, path?: string, query?: string, fragment?: string}|int<0, 65535>|string|false|null */
	protected function parse_url(string $url, int $component = -1): int|string|array|null|false
	{
		return parse_url($url, $component);
	}

	/**  @param string|string[] $vars */
	private function use_getx_input_type(string|array $vars = 'id'): bool
	{
		foreach ((array)$vars as $var) {
			if (isset($this->GETX[$var]) and is_string($this->GETX[$var])) {
				return true;
			}
		}

		return false;
	}

	/** @throws DomainException If the wrong type */
	private function validate_input_type(string $input_type): void
	{
		if (!in_array($input_type, [static::INPUT_TYPE_GET, static::INPUT_TYPE_POST, static::INPUT_TYPE_GETX], true)) {
			throw new DomainException("Unsupported input source: '$input_type'");
		}
	}

	/** @param array<string, mixed> $input */
	private function is_usable_input(array $input, string $var): bool
	{
		return (isset($input[$var]) and is_string($input[$var]));
	}
}
