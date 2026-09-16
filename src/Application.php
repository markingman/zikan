<?php declare(strict_types=1);

namespace Zikan;

use Zikan\Exception\ApplicationError;
use Zikan\Exception\ApplicationException;
use Zikan\Http\RequestInterface;
use Zikan\Http\ResponseInterface;
use Zikan\Routing\DispatchInterface;
use Throwable;

class Application
{
	public function __construct(
		public readonly ConfigInterface $Config,
		public ContainerInterface $Container,
		public RequestInterface $Request,
		public ResponseInterface $Response,
		public DispatchInterface $Dispatch
	) {
	}

	public function run(?string $method = null, ?string $path = null): void
	{
		try {
			$this->Dispatch->call_controller(
				$method ?: $this->Request->get_method(),
				$path ?: $this->Request->get_path()
			);
		} catch (Throwable $e) {
			throw new ApplicationException(
				'Could not call controller',
				ApplicationError::CALL_ERR,
				$e->getCode() ?: 500,
				$e
			);
		}
	}
}

