<?php

namespace Zikan\Test\App\Errors;

use Throwable;
use Zikan\Errors\ErrorHandler;
use Zikan\Errors\ErrorViewInterface;

class ErrorView implements ErrorViewInterface
{
	public function __invoke(Throwable $e, mixed $m = null): void
	{
		ErrorHandler::view($e);
	}
}
