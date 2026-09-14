<?php

namespace Zikan\Test\App\View\Pages;

use InvalidArgumentException;
use function Zikan\Test\App\View\Templates\default_template;

class IndexPage extends AbstractPage
{
	public function __invoke(?AbstractContext $vars = null): string
	{
		$vars instanceof IndexContext || throw new InvalidArgumentException('Excepted ' . IndexContext::class);

		$this->preload('Partials', 'Templates/default_template.php');

		return default_template(
			$this->HTMLContext,
			'Test',
			$vars->test ? 'hello, world!' : 'goodbye, world!'
		);
	}
}
