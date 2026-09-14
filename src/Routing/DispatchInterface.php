<?php

namespace Zikan\Routing;

interface DispatchInterface
{
	public function call_controller(?string $method = null, ?string $url = null): void;
}
