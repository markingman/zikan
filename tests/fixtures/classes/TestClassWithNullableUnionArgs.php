<?php

namespace Zikan\Test;

class TestClassWithNullableUnionArgs
{
	public function test(null|false|int $i): null|false|int
	{
		return $i;
	}
}
