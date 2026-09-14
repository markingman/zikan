<?php

namespace Zikan\Test;

class TestClassWithNullableArguments
{
	/**
	 * @param array<int, string>|null $c
	 *
	 * @return array{
	 *     0: int|null,
	 *     1: string|null,
	 *     2: array<int, string>|null,
	 *     3: bool|null
	 * }
	 */
	public function test(
		?int $a = null,
		?string $b = null,
		?array $c = null,
		?bool $d = null
	): array {
		return [$a, $b, $c, $d];
	}
}
