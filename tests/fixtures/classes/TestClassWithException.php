<?php

namespace Zikan\Test;

use RuntimeException;

class TestClassWithException
{
	public function __construct()
	{
		throw new RuntimeException('Test runtime exception');
	}
}
