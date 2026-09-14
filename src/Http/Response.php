<?php declare(strict_types=1);

namespace Zikan\Http;

use Zikan\Exception\ResponseError;
use Zikan\Exception\ResponseException;

class Response implements ResponseInterface
{
	protected bool $terminate_after_response = true;
	protected int $response_code = 200;
	protected string $char_set = 'UTF-8';
	/** @var array<string, string> $headers */
	protected array $headers = [];
	/** @var array<string, Cookie> $cookies */
	protected array $cookies = [];

	public function __construct(bool $terminate_after_response = true)
	{
		$this->set_terminate_after_response($terminate_after_response);
	}

	public function set_terminate_after_response(bool $terminate_after_response): self
	{
		$this->terminate_after_response = $terminate_after_response;

		return $this;
	}

	public function set_char_set(string $set): self
	{
		$this->char_set = $set;

		return $this;
	}

	public function set_response_code(int $code): self
	{
		$this->response_code = $code;

		return $this;
	}

	public function set_header(string $key, string $value): self
	{
		$this->headers[$key] = $value;

		return $this;
	}

	public function unset_header(string $key): void
	{
		unset($this->headers[$key]);
	}

	public function set_cookie(
		string $name,
		string $value = '',
		int $expires = 0,
		string $path = '',
		string $domain = '',
		bool $secure = false,
		bool $httponly = false,
	): self {
		$this->cookies[$name] = new Cookie(
			value: $value,
			expires: $expires,
			path: $path,
			domain: $domain,
			secure: $secure,
			httponly: $httponly,
		);

		return $this;
	}

	public function unset_cookie(string $key): self
	{
		unset($this->cookies[$key]);

		return $this;
	}

	public function html(string $html): void
	{
		$this->set_header('Content-Type', sprintf('text/html; charset=%s', $this->char_set));
		$this->respond($html);
	}

	public function text(string $text): void
	{
		$this->set_header('Content-Type', sprintf('text/plain; charset=%s', $this->char_set));
		$this->respond($text);
	}

	public function json(string $json): void
	{
		$this->set_header('Content-Type', sprintf('application/json; charset=%s', $this->char_set));
		$this->respond($json);
	}

	/** If $set_content_length FALSE will cause chunked downloads in common web server environments */
	public function file(string $file, bool $set_content_length = false, bool $unlink_file = true, bool $inline = false): void
	{
		if (!$mime_type = $this->mime_content_type($file)) {
			throw new ResponseException("Could not resolve mime-type for '$file'", ResponseError::MIME_TYPE);
		}

		if ($set_content_length) {
			if (!$size = $this->filesize($file)) {
				throw new ResponseException("Could not get file size for '$file'", ResponseError::FILE_SIZE);
			}
		}

		$this->set_header('Content-Type', sprintf('%s; charset=%s', $mime_type, $this->char_set));
		$this->set_header('Content-Disposition', sprintf('%s; filename=%s', $inline ? 'inline' : 'attachment', basename($file)));
		if ($set_content_length) {
			$this->set_header('Content-Length', strval($size));
		}
		$this->set_header('Content-Transfer-Encoding', 'binary');
		$this->respond(
			function () use ($file, $set_content_length, $unlink_file) {
				if ($this->readfile($file) === false) {
					throw new ResponseException("Failed reading file '$file'", ResponseError::READFILE_FAIL);
				}
				if ($set_content_length) {
					$this->flush();
				}
				if ($unlink_file) {
					if (!$this->unlink($file)) {
						throw new ResponseException("Failed to unlink '$file'", ResponseError::UNLINK_FAIL);
					}
				}
			}
		);
	}

	public function redirect(string $to, int $code = 303): void
	{
		$this->set_response_code($code);
		$this->set_header('Location', $to);
		$this->respond(null);
	}

	public function respond(string|callable|null $content = '', bool $remove_headers = false): void
	{
		$this->http_response_code($this->response_code);

		if ($remove_headers) {
			$this->header_remove();// CAUTION this will remove Set-Cookie and Cache-Control
		}

		foreach ($this->headers as $key => $value) {
			$this->header($key . ': ' . $value);
		}

		foreach ($this->cookies as $name => $cookie) {
			$this->setcookie($name, $cookie->value, $cookie->expires, $cookie->path, $cookie->domain, $cookie->secure, $cookie->httponly);
		}

		if (is_string($content)) {
			$this->echo($content);
		} elseif (is_callable($content)) {
			$content();
		}

		$this->terminate();
	}

	protected function terminate(): void
	{
		if ($this->terminate_after_response) {
			exit;// @codeCoverageIgnore
		}
	}

	protected function http_response_code(int $response_code = 0): int|bool
	{
		return http_response_code($response_code);
	}

	protected function header_remove(): void
	{
		header_remove();
	}

	protected function readfile(string $filename): int|false
	{
		return readfile($filename);
	}

	protected function flush(): void
	{
		flush();
	}

	protected function unlink(string $file): bool
	{
		return unlink($file);
	}

	protected function header(string $header): void
	{
		header($header);
	}

	protected function setcookie(
		string $name,
		string $value = '',
		int $expires_or_options = 0,
		string $path = '',
		string $domain = '',
		bool $secure = false,
		bool $httponly = false
	): bool {
		return setcookie($name, $value, $expires_or_options, $path, $domain, $secure, $httponly);
	}

	protected function mime_content_type(string $filename): string|false
	{
		return mime_content_type($filename);
	}

	protected function filesize(string $filename): int|false
	{
		return filesize($filename);
	}

	protected function echo(string $echo): void
	{
		echo $echo;
	}
}
