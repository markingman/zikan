<?php

namespace Zikan\Errors;

use Throwable;

interface ErrorViewInterface
{
	public function __invoke(Throwable $e, mixed $m = null): void;
}
