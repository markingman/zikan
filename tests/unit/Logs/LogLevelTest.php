<?php declare(strict_types=1);

namespace Zikan\Logs;

use PHPUnit\Framework\TestCase;

class LogLevelTest extends TestCase
{
	public function testLabelReturnsExpectedEnumNames(): void
	{
		$map = [
			LOG_EMERG => 'EMERGENCY',
			LOG_ALERT => 'ALERT',
			LOG_CRIT => 'CRITICAL',
			LOG_ERR => 'ERROR',
			LOG_WARNING => 'WARNING',
			LOG_NOTICE => 'NOTICE',
			LOG_INFO => 'INFO',
			LOG_DEBUG => 'DEBUG',
		];

		foreach ($map as $int => $expected) {
			$this->assertSame($expected, LogLevel::label($int), "Failed asserting level $int returns $expected");
		}
	}

	public function testLabelReturnsUnknownForInvalidLevel(): void
	{
		$this->assertSame('UNKNOWN', LogLevel::label(99999));
	}
}
