<?php

namespace Zikan\Test\App\Controller;

use Zikan\Config;
use Zikan\Http\Request;
use Zikan\Http\Response;
use Zikan\Routing\Dispatch;

readonly class ControllerContext
{
	public function __construct(
		public Config $Config,
		public Dispatch $Dispatch,
		public Request $Request,
		public Response $Response,
	) {
	}
}
