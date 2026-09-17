<?php

namespace Zikan\Test\App\Errors;

use Throwable;
use Zikan\Errors\ErrorHandler;
use Zikan\Errors\ErrorLogInterface;
use Zikan\Logs\LogHandler;

class ErrorLog implements ErrorLogInterface
{
	public function __construct(
		protected LogHandler $LogHandler
	) {
	}

	public function __invoke(Throwable $e): void
	{
		ErrorHandler::log($e, $this->LogHandler, true);
	}
}
