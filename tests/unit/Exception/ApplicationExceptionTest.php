<?php

namespace Zikan\Exception;

use PHPUnit\Framework\TestCase;

class ApplicationExceptionTest extends TestCase
{
	public function testGetErrorCodeReturnsCorrectEnum(): void
	{
		$e = new ApplicationException(
			'Something went wrong',
			ApplicationError::CALL_ERR,
			500
		);

		$this->assertInstanceOf(ApplicationError::class, $e->getErrorCode());
		$this->assertSame(ApplicationError::CALL_ERR, $e->getErrorCode());
	}
}
