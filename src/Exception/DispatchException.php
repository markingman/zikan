<?php declare(strict_types=1);

namespace Zikan\Exception;

use RuntimeException;
use Throwable;

class DispatchException extends RuntimeException
{
	private readonly DispatchError $error;

	public function __construct(
		string $message,
		DispatchError $error,
		int $code = 500,
		?Throwable $previous = null
	) {
		$this->error = $error;
		parent::__construct($error->value . '; ' . $message, $code, $previous);
	}

	public function getErrorCode(): DispatchError
	{
		return $this->error;
	}
}
