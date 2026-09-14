<?php

namespace Zikan\Test\App\Controller;

use Zikan\Test\App\Model\ExampleModel;
use Zikan\Test\App\View\Pages\IndexContext;
use Zikan\Test\App\View\Pages\IndexPage;

class IndexController extends AbstractController
{
	public function __construct(
		ControllerContext $ControllerContext,
		protected ExampleModel $ExampleModel
	) {
		parent::__construct($ControllerContext);
	}

	public function action_default(IndexPage $IndexPage): void
	{
		$this->Response->html($IndexPage(new IndexContext(
			test: $this->ExampleModel->test()
		)));
	}
}
