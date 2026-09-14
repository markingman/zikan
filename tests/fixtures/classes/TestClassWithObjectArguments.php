<?php

namespace Zikan\Test;

class TestClassWithObjectArguments
{
	public function test(TestClassWithSimpleMethods $obj): bool
	{
		return $obj->test();
	}

	public function inc(TestClassWithSimpleMethods $TestClassWithSimpleMethods): int
	{
		return $TestClassWithSimpleMethods->inc();
	}

	public function get_string(TestClassWithSimpleMethods $TestClassWithSimpleMethods): string
	{
		return $TestClassWithSimpleMethods->get_string();
	}
}
