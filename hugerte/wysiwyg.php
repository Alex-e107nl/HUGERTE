<?php
/*
+ ----------------------------------------------------------------------------+
|     e107 website system - HugeRTE controller file.
+----------------------------------------------------------------------------+
*/
$_E107['no_online'] = true;
$_E107['no_forceuserupdate'] = true;
$_E107['no_menus'] = true;
$_E107['no_maintenance'] = true;
require_once(__DIR__.'/../../class2.php'); //TODO Prevent Theme loading.

require_once(__DIR__."/wysiwyg_class.php");
$wy = new wysiwyg();

$config = varset($_GET['config'],false); // e_QUERY;

$gen = $wy->renderConfig($config);

define('USE_GZIP', true);
$compression_browser_support = false;
$compression_server_support = false;

if(strpos(varset($_SERVER['HTTP_ACCEPT_ENCODING']), 'gzip') !== false)
{
	$compression_browser_support = true;
}

if(ini_get("zlib.output_compression")=='' && function_exists("gzencode"))
{
	$compression_server_support = true;
}


if((ADMIN || getperms('0')) && (e_QUERY == 'debug' || !empty($_GET['debug'])))
{
	define('e_IFRAME', true);
	require_once(HEADERF);

	echo "<table class='table'><tr>";
	echo "
	<td>
	".print_a($gen,true)."
	</td>
	</tr></table>";

	require_once(FOOTERF);

}
elseif((USE_GZIP === true) && $compression_browser_support && $compression_server_support)
{
	while (ob_get_length() !== false)  // clear out anything that may have been echoed from class2.php or theme
	{
        ob_end_clean();
	}
	header('Content-type: text/javascript;charset=UTF-8');
	header('Content-Encoding: gzip');

	$minified = e107::minify($gen);
	$gzipoutput = gzencode($minified,6);

	header('Content-Length: '.strlen($gzipoutput));
	echo $gzipoutput;
}
else
{
	while (ob_get_length() !== false)  // clear out anything that may have been echoed from class2.php or theme
	{
        ob_end_clean();
	}
	ob_start();
	ob_implicit_flush(0);
	header('Content-type: text/javascript', TRUE);
	echo $gen;
}

exit;
