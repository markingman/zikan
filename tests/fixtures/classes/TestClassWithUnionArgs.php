<?php

namespace Zikan\Test;

class TestClassWithUnionArgs
{
	public function test(false|int $i): false|int
	{
		return $i;
	}
}

