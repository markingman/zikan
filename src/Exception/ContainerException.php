<?php declare(strict_types=1);

namespace Zikan\Exception;

use RuntimeException;
use Throwable;

class ContainerException extends RuntimeException
{
	private readonly ContainerError $error;

	public function __construct(
		string $message,
		ContainerError $error,
		int $code = 500,
		?Throwable $previous = null
	) {
		$this->error = $error;
		parent::__construct($error->value . '; ' . $message, $code, $previous);
	}

	public function getErrorCode(): ContainerError
	{
		return $this->error;
	}
}
