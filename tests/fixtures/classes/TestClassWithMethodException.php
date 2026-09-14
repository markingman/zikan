<?php

namespace Zikan\Test;

use RuntimeException;

class TestClassWithMethodException
{
	public function test(): void
	{
		throw new RuntimeException('Test method exception');
	}
}
