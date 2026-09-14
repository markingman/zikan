<?php

namespace Zikan\Logs;

use PHPUnit\Framework\TestCase;
use RuntimeException;

class WriteStdErrTest extends TestCase
{
	public function testWriteToStdErr(): void
	{
		$WriteStdErr = new class extends WriteStdErr {
			public string $test = '';

			protected function fwrite(string $log): void
			{
				$this->test = $log;
			}
		};

		$WriteStdErr->write('Test message', 'CLI');

		$this->assertSame('Test message' . PHP_EOL, $WriteStdErr->test);
	}

	public function testConstructorThrowsWhenStderrIsNotResource(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Could not open stderr');
	
		new class extends WriteStdErr {
			protected function is_resource(mixed $value): bool
			{
				return false;
			}
		};
	}

	public function testFWrite(): void
	{
		$WriteStdErr = new class() extends WriteStdErr {
			public function __construct()
			{
				$this->fp = tmpfile();
			}

			public function testFp(): string
			{
				rewind($this->fp);
				$contents = stream_get_contents($this->fp);
				fclose($this->fp);

				return (string)$contents;
			}
		};

		$WriteStdErr->write('Test message', 'CLI');

		$this->assertSame('Test message', trim($WriteStdErr->testFp()));
	}

	public function testWriteWhenFpIsNotResource(): void
	{
		$WriteStdErr = new class extends WriteStdErr {
			private int $is_resource_calls = 0;
	
			protected function is_resource(mixed $value): bool
			{
				$this->is_resource_calls++;
	
				return $this->is_resource_calls === 1;
			}
	
			public function getIsResourceCalls(): int
			{
				return $this->is_resource_calls;
			}
		};
	
		$WriteStdErr->write('Test message', 'CLI');
	
		$this->assertSame(2, $WriteStdErr->getIsResourceCalls());
	}
}
