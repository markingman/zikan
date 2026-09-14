<?php

namespace Zikan\Test\App\View\Templates;

use Zikan\Test\App\View\HTMLContext;
use function Zikan\Test\App\View\Partials\htmlspecialchars;

function default_template(
	HTMLContext $HTML,
	string $title,
	string $content_html
): string {

	HTMLContext::ob_start();
	?>
	<!DOCTYPE html>
	<html lang="en">
	<head>
		<title><?= htmlspecialchars($title) ?></title>
		<link href="<?= $HTML->path_css('main.css') ?>" rel="stylesheet">
	</head>
	<body>
	<main>
		<?= $content_html ?>
	</main>
	</body>
	</html>
	<?php

	return HTMLContext::ob_get_clean();
}
