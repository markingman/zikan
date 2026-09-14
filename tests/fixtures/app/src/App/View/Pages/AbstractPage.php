<?php

namespace Zikan\Test\App\View\Pages;

use Zikan\Test\App\View\HTMLContext;
use Zikan\Test\App\View\Preloader;

abstract class AbstractPage implements PageInterface
{
	public function __construct(
		protected Preloader $Preloader,
		protected HTMLContext $HTMLContext
	) {
	}

	public function preload(string ...$paths): void
	{
		$this->Preloader->preload($paths);
	}
}
