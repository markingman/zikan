<?php declare(strict_types=1);

namespace Zikan\Http;

interface ResponseInterface
{
	public function set_terminate_after_response(bool $terminate_after_response): self;

	public function set_char_set(string $set): self;

	public function set_response_code(int $code): self;

	public function set_header(string $key, string $value): self;

	public function unset_header(string $key): void;

	public function set_cookie(
		string $name,
		string $value = '',
		int $expires = 0,
		string $path = '',
		string $domain = '',
		bool $secure = false,
		bool $httponly = false,
	): self;

	public function unset_cookie(string $key): self;

	public function html(string $html): void;

	public function text(string $text): void;

	public function json(string $json): void;

	public function file(
		string $file,
		bool $set_content_length = false,
		bool $unlink_file = true,
		bool $inline = false
	): void;

	public function redirect(string $to, int $code = 303): void;

	public function respond(string|callable|null $content = '', bool $remove_headers = false): void;
}
