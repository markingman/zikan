<?php declare(strict_types=1);

namespace Zikan;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
	protected TestHTTPClient $client;

	protected function setUp(): void
	{
		$this->client = new TestHTTPClient;
	}

	public function testExample(): void
	{
		$response = $this->client->get('');
		$this->assertEquals(<<<__
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>
			Test
		</title>
		<link href="/css/main.css" rel="stylesheet">
	</head>
	<body>
		<main>
			hello, world!
		</main>
	</body>
</html>
<!--
Info: Document content looks like HTML5
No warnings or errors were found.
-->
__,
			$response['body']);
	}
}
