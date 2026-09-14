<?php declare(strict_types=1);

namespace Zikan\Exception;

use Throwable;
use UnexpectedValueException;

class ConfigUnexpectedValueException extends UnexpectedValueException
{
	public function __construct(
		string $message,
		int $code = 500,
		?Throwable $previous = null
	) {
		parent::__construct('CONFIG; ' . $message, $code, $previous);
	}
}
