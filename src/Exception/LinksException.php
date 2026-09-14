<?php declare(strict_types=1);

namespace Zikan\Exception;

use LogicException;
use Throwable;

class LinksException extends LogicException
{
	public function __construct(
		string $message,
		int $code = 500,
		?Throwable $previous = null
	) {
		parent::__construct('LINKS; ' . $message, $code, $previous);
	}
}
