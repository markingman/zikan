<?php declare(strict_types=1);

namespace Zikan;

use Zikan\Exception\ApplicationException;
use Zikan\Exception\ApplicationError;
use Zikan\Exception\ContainerException;
use Zikan\Http\RequestInterface;
use Zikan\Http\ResponseInterface;
use Zikan\Routing\DispatchInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use PHPUnit\Framework\MockObject\MockObject;

class ApplicationTest extends TestCase
{
	/** @var MockObject&ConfigInterface */
	private ConfigInterface $Config;
	/** @var MockObject&ContainerInterface */
	private ContainerInterface $Container;
	/** @var MockObject&RequestInterface */
	private RequestInterface $Request;
	/** @var MockObject&ResponseInterface */
	private ResponseInterface $Response;
	/** @var MockObject&DispatchInterface */
	private DispatchInterface $Dispatch;

	public function testRunCallsDispatcherWithProvidedMethodAndPath(): void
	{
		$app = new Application(
			$this->Config,
			$this->Container,
			$this->Request,
			$this->Response,
			$this->Dispatch
		);

		$this->Dispatch
			->expects($this->once())
			->method('call_controller')
			->with('GET', 'home');

		$app->run('GET', 'home');
	}

	public function testRunUsesRequestValuesWhenArgumentsAreNull(): void
	{
		$this->Request->method('get_method')->willReturn('POST');
		$this->Request->method('get_path')->willReturn('dashboard');

		$this->Dispatch
			->expects($this->once())
			->method('call_controller')
			->with('POST', 'dashboard');

		$app = new Application(
			$this->Config,
			$this->Container,
			$this->Request,
			$this->Response,
			$this->Dispatch
		);

		$app->run();
	}

	public function testRunWrapsDispatchExceptions(): void
	{
		$this->Request->method('get_method')->willReturn('PUT');
		$this->Request->method('get_path')->willReturn('fail');

		$this->Dispatch
			->method('call_controller')
			->willThrowException(new RuntimeException('Original dispatch error', 400));

		$app = new Application(
			$this->Config,
			$this->Container,
			$this->Request,
			$this->Response,
			$this->Dispatch
		);

		$this->expectException(ApplicationException::class);
		$this->expectExceptionMessage('APPLICATION_CALL_ERR');
		$this->expectExceptionCode(400);

		try {
			$app->run();
		} catch (ContainerException $e) {
			$this->assertSame(ApplicationError::CALL_ERR, $e->getErrorCode());
			throw $e;
		}
	}

	public function testRunWrapsDispatchExceptionsLongMessage(): void
	{
		$this->Request->method('get_method')->willReturn('PUT');
		$this->Request->method('get_path')->willReturn('fail');

		$this->Dispatch
			->method('call_controller')
			->willThrowException(new RuntimeException(
				'Original dispatch error which is very long and will truncate at specific length'
				, 400));

		$app = new Application(
			$this->Config,
			$this->Container,
			$this->Request,
			$this->Response,
			$this->Dispatch
		);

		$this->expectException(ApplicationException::class);
		$this->expectExceptionMessage('APPLICATION_CALL_ERR');
		$this->expectExceptionCode(400);

		try {
			$app->run();
		} catch (ContainerException $e) {
			$this->assertSame(ApplicationError::CALL_ERR, $e->getErrorCode());
			throw $e;
		}
	}

	protected function setUp(): void
	{
		try {
			$this->Config = $this->createMock(ConfigInterface::class);
			$this->Container = $this->createMock(ContainerInterface::class);
			$this->Request = $this->createMock(RequestInterface::class);
			$this->Response = $this->createMock(ResponseInterface::class);
			$this->Dispatch = $this->createMock(DispatchInterface::class);
		} catch (Throwable $e) {
			$this->fail('Could not set up test; ' . $e->getMessage());
		}
	}
}
