<?php

namespace Zikan\Test;

use RuntimeException;

class TestClassWithInvokeException
{
	public function __invoke(): void
	{
		throw new RuntimeException('Test runtime exception');
	}

	public function test(): true
	{
		return true;
	}
}
