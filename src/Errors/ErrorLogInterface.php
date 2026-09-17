<?php

namespace Zikan\Errors;

use Throwable;

interface ErrorLogInterface
{
	public function __invoke(Throwable $e): void;
}
