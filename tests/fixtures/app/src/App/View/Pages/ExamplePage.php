<?php

namespace Zikan\Test\App\View\Pages;

use Zikan\Test\App\View\Layouts\Block;
use function Zikan\Test\App\View\Templates\default_template;

class ExamplePage extends AbstractPage
{
	protected string $var;

	public function set_var(string $var): void
	{
		$this->var = $var;
	}

	public function __invoke(?AbstractContext $vars = null): string
	{
		$title = $this->var === 'test' ? 'Test' : 'Example';

		$this->preload('Partials', 'Templates/default_template.php');

		return default_template(
			$this->HTMLContext,
			$title,
			(new Block())($title)
		);
	}
}
