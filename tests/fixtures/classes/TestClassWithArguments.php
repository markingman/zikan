<?php

namespace Zikan\Test;

class TestClassWithArguments
{
	/**
	 * @param array<int, string> $a
	 * @param array<int, string> $a2
	 *
	 * @return array{
	 *     0: int,
	 *     1: string,
	 *     2: array<int, string>,
	 *     3: bool,
	 *     4: float,
	 *     5: int,
	 *     6: string,
	 *     7: array<int, string>,
	 *     8: bool,
	 *     9: float
	 * }
	 */
	public function test(
		int $i,
		string $s,
		array $a,
		bool $b,
		float $f,
		int $i2 = 2,
		string $s2 = 'S2',
		array $a2 = ['A2'],
		bool $b2 = false,
		float $f2 = 0.2
	): array {
		return [
			$i,
			$s,
			$a,
			$b,
			$f,
			$i2,
			$s2,
			$a2,
			$b2,
			$f2
		];
	}
}
