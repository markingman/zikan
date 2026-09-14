<?php declare(strict_types=1);

namespace Zikan\Exception;

use LogicException;
use Throwable;

class ResponseException extends LogicException
{
	private readonly ResponseError $error;

	public function __construct(
		string $message,
		ResponseError $error,
		int $code = 500,
		?Throwable $previous = null
	) {
		$this->error = $error;
		parent::__construct($error->value . '; ' . $message, $code, $previous);
	}

	public function getErrorCode(): ResponseError
	{
		return $this->error;
	}
}
