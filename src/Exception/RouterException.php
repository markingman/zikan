<?php declare(strict_types=1);

namespace Zikan\Exception;

use LogicException;
use Throwable;

class RouterException extends LogicException
{
	private readonly RouterError $error;

	public function __construct(
		string $message,
		RouterError $error,
		int $code = 500,
		?Throwable $previous = null
	) {
		$this->error = $error;
		parent::__construct($error->value . '; ' . $message, $code, $previous);
	}

	public function getErrorCode(): RouterError
	{
		return $this->error;
	}
}
