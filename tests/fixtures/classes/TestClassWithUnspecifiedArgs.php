<?php

namespace Zikan\Test;

class TestClassWithUnspecifiedArgs
{
	// @phpstan-ignore-next-line
	public function test($z): string
	{
		return is_bool($z) ? 'bool' : 'not-bool';
	}
}

