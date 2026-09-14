<?php

use Zikan\Test\App\Controller\IndexController;
use Zikan\Test\App\Controller\ExampleController;

return [

	'index' => [
		'path' => '/',
		'controller' => IndexController::class,
		'method' => 'GET',
	],

	'example' => [
		'path' => 'example',
		'controller' => ExampleController::class,
		'method' => 'GET'
	],

	'example/test' => [
		'path' => 'example/test',
		'controller' => ExampleController::class,
		'action' => 'test',
		'method' => 'GET'
	],

];
