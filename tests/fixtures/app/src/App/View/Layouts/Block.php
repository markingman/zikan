<?php

namespace Zikan\Test\App\View\Layouts;

use Zikan\Test\App\View\HTMLContext;
use function Zikan\Test\App\View\Partials\htmlentities;

class Block
{
	public function __invoke(string $title): string
	{
		HTMLContext::ob_start();
		?>

		<div class="example-block">
			<?= htmlentities($title) ?>
		</div>

		<?php
		return HTMLContext::ob_get_clean();
	}
}
