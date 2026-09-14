<?php

namespace Zikan\Test\App\Controller;

use Zikan\Controller\Controller;

abstract class AbstractController extends Controller
{
	public function __construct(
		ControllerContext $ControllerContext
	) {
		parent::__construct(
			$ControllerContext->Config,
			$ControllerContext->Dispatch,
			$ControllerContext->Request,
			$ControllerContext->Response,
		);
	}
}
