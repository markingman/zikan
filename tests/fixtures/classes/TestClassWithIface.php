<?php

namespace Zikan\Test;

class TestClassWithIface implements TestClassWithIfaceInterface
{
	public function test(): string
	{
		return 'iface';
	}
}
