<?php

// require composer autoload
require_once __DIR__ . '/examples/bootstrap.php';

$defaultConfig = (new Mpdf\Config\ConfigVariables())->getDefaults();
$fontDirs = $defaultConfig['fontDir'];

$defaultFontConfig = (new Mpdf\Config\FontVariables())->getDefaults();
$fontData = $defaultFontConfig['fontdata'];

$path = __DIR__;

$mpdf = new \Mpdf\Mpdf(array(
	'fontDir' => array_merge($fontDirs, array($path)),
	'fontdata' => $fontData + 
		array(
			'lato' => array(
				'R' => 'fonts/lato-regular.ttf',
				'I' => 'fonts/lato-italic.ttf',
				'B' => 'fonts/lato-bold.ttf',
				'BI' => 'fonts/lato-bold-italic.ttf',
			),
			'juana' => array(
				'R' => 'fonts/juana-regular.ttf',
				'i' => 'fonts/juana-italic.ttf',
			),
			'silversouthscript' => array(
				'R' => 'fonts/silver-south-script.ttf',
			),
		),
));

ob_start();
?>
<html>
<head>
	<style>
		.lato {
			font-family: "lato";
		}
		.juana {
			font-family: "Juana";
		}
		.silversouthscript {
			font-family: "Silversouthscript";
		}
	</style>
</head>
<body>
	<h1>Using custom font in the document</h1>
	<p>version 1.0</p>
	
	<p>Example - Default text</p>
	<p style="font-family: Lato">Example - Lato</p>
	<p style="font-family: Lato, sans-serif">Example - Lato, sans-serif</p>
	<p style="font-family: 'Lato'">Example - 'Lato'</p>
	<p style='font-family: "Lato"'>Example - "Lato" <strong>Does not work</strong></p>
	<p class="lato">Example - class="lato"</p>
	
	<hr>
	
	<p style="font-family: Lato;">Lato Regular</p>
	<p style="font-family: Lato;"><strong>Lato Bold</strong></p>
	<p style="font-family: Lato;"><em>Lato Italic</em></p>
	<p style="font-family: Lato;"><strong><em>Lato Bold Italic</em></strong></p>
	
	<hr>
	
	<p style="font-family: juana;">Juana Regular</p>
	<p style="font-family: juana;"><em>Juana Italic</em></p>
	<p style="font-family: silversouthscript;">Silversouthscript</p>
	
</body>
</html>
<?php
$html = ob_get_clean();

$mpdf->WriteHtml($html);

$mpdf->Output();
die;
