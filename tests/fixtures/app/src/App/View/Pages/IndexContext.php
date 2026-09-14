<?php

namespace Zikan\Test\App\View\Pages;

readonly class IndexContext extends AbstractContext
{
	public function __construct(
		public bool $test
	) {
	}
}
