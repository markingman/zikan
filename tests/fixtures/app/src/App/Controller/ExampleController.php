<?php

namespace Zikan\Test\App\Controller;

use Zikan\Test\App\View\Pages\ExamplePage;

class ExampleController extends AbstractController
{
	public function action_default(ExamplePage $ExamplePage): void
	{
		$ExamplePage->set_var('test');
		$this->Response->html($ExamplePage());
	}

	public function action_test(ExamplePage $ExamplePage): void
	{
		$ExamplePage->set_var('example');
		$this->Response->html($ExamplePage());
	}
}
