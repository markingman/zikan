<?php

namespace Zikan\Test;

class TestClassWithSimpleMethods
{
	protected int $i = 0;

	public function test(): bool
	{
		return true;
	}

	public function req(int $i): int
	{
		return $i;
	}

	public function inc(): int
	{
		return $this->i++;
	}

	public function get_string(): string
	{
		return 'abc';
	}

	public function iface(TestClassWithIfaceInterface $iface): string
	{
		return $iface->test();
	}

	public function echo(): void
	{
		echo 'value';
	}

	protected function test2(): bool
	{
		return true;
	}
}
