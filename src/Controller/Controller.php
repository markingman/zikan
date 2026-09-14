<?php

namespace Zikan\Controller;

use Zikan\Config;
use Zikan\Http\Request;
use Zikan\Http\Response;
use Zikan\Routing\Dispatch;

class Controller
{
	public function __construct(
		protected Config $Config,
		protected Dispatch $Dispatch,
		protected Request $Request,
		protected Response $Response
	) {
	}
}
