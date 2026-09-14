<?php declare(strict_types=1);

namespace Zikan\Exception;

use RuntimeException;
use Throwable;

class ApplicationException extends RuntimeException
{
	private readonly ApplicationError $error;

	public function __construct(
		string $message,
		ApplicationError $error,
		int $code = 0,
		?Throwable $previous = null
	) {
		$this->error = $error;
		parent::__construct($error->value . '; ' . $message, $code, $previous);
	}

	public function getErrorCode(): ApplicationError
	{
		return $this->error;
	}
}
