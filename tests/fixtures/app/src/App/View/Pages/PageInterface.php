<?php

namespace Zikan\Test\App\View\Pages;

interface PageInterface
{
	public function __invoke(?AbstractContext $vars = null): string;
}
