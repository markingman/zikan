<?php declare(strict_types=1);

namespace Zikan\Http;

interface RequestInterface
{
	/** @param array<string, string>|null $headers */
	public function set_headers(?array $headers = null): void;

	public function set_get_value(string $k, string $v): void;

	public function get_header(string $header): ?string;

	public function get_get(string $key): ?string;

	public function get_post(string $key): ?string;

	/** @return array<string, string|int>|null */
	public function get_file(string $key): ?array;

	public function get_server(string $key): ?string;

	public function get_cookie(string $key): ?string;

	/** @param string|string[] $vars */
	public function get_int_from_get(string|array $vars = 'id', int $default = 0, int $min_range = 0): int;

	/** @param string|string[] $vars */
	public function get_int_from_post(string|array $vars = 'id', int $default = 0, int $min_range = 0): int;

	/** @param string|string[] $vars */
	public function get_var_from_get(string|array $vars = 'arg1', string $default = ''): string;

	/** @param string|string[] $vars */
	public function get_var_from_post(string|array $vars = 'arg1', string $default = ''): string;

	/** @param string|string[] $vars */
	public function get_val_from_get(string|array $vars = 'arg1', string $default = ''): string;

	/** @param string|string[] $vars */
	public function get_val_from_post(string|array $vars = 'arg1', string $default = ''): string;

	/**
	 * @param string $var
	 * @param array<string> $opts
	 * @param string $default
	 */
	public function get_sel_from_get(string $var = 'arg1', array $opts = [], string $default = ''): string;

	/**
	 * @param string $var
	 * @param array<string> $opts
	 * @param string $default
	 */
	public function get_sel_from_post(string $var = 'arg1', array $opts = [], string $default = ''): string;

	/**
	 * @param string $var
	 * @param string[] $default
	 * @param string $filter
	 * @return string[]
	 */
	public function get_array_from_get(string $var, array $default = [], string $filter = 'filter_val'): array;

	/**
	 * @param string $var
	 * @param string[] $default
	 * @param string $filter
	 * @return string[]
	 */
	public function get_array_from_post(string $var, array $default = [], string $filter = 'filter_val'): array;

	public function get_ua(): string;

	public function set_ua(string $ua): void;

	public function get_ip(): string;

	public function set_ip(string $ip): void;

	public function get_ref(): string;

	public function set_ref(?string $ref = null): void;

	public function get_method(): string;

	public function set_method(?string $method = null): void;

	public function get_path(): string;

	public function set_path(string $url): void;

	/**
	 * @param string|null $method
	 * @param string|null $path
	 * @return array{0: string, 1: string}
	 */
	public function get_request(?string $method = null, ?string $path = null): array;

	public function is_ssl(?int $port = 443): bool;

	public function clear_cache(): void;

	public function is_post(): bool;

	public function is_ajax(): bool;
}
