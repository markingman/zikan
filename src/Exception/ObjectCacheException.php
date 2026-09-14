<?php declare(strict_types=1);

namespace Zikan\Exception;

use RuntimeException;
use Throwable;

class ObjectCacheException extends RuntimeException
{
	private readonly ObjectCacheError $error;

	public function __construct(
		string $message,
		ObjectCacheError $error,
		int $code = 500,
		?Throwable $previous = null
	) {
		$this->error = $error;
		parent::__construct($error->value . '; ' . $message, $code, $previous);
	}

	public function getErrorCode(): ObjectCacheError
	{
		return $this->error;
	}
}
