<?php
/*
 * e107 website system
 *
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 * HugeRTE emoticons database endpoint.
 *
 * Serves a JavaScript file consumed by the built-in HugeRTE "emoticons" plugin
 * (config: emoticons_database: "images"). The plugin loads this file through
 * hugerte.Resource.load() and expects:
 *
 *   hugerte.Resource.add("hugerte.plugins.emoticons", {
 *       "<Title>": { keywords: [...], char: "<img .../>", category: "user" },
 *       ...
 *   });
 *
 * The entry KEY is used by the plugin as the emoticon title, and each entry's
 * char <img> src is prefixed with the emoticons_images_url option. The editor
 * config sets emoticons_images_url to "" so the absolute src below is used as-is.
 *
 * The data source is the same as the old tinymce4 "extended_smileys": the emote
 * plugin config (e107::getConfig('emote')->getPref()) + the core 'emotepack'
 * pref. Because the live config is used, any change made by the emoticons
 * plugin (different emote pack, edited shortcuts) is picked up automatically.
 */

if (!defined('E_HUGERTE_DEBUG'))
{
	$_E107['no_online']         = true;
	$_E107['no_menus']          = true;
	$_E107['no_forceuserupdate'] = true;
	$_E107['no_maintenance']    = true;
	$_E107['minimal']           = true;

	require_once(__DIR__ . "/../../../../class2.php");
	require_once(__DIR__ . "/../../wysiwyg_class.php");

	if (!wysiwyg::isAllowed())
	{
		http_response_code(403);
		exit;
	}
}

$emotes = array();

if (e107::pref('core', 'smiley_activate', false))
{
	$emo  = e107::getConfig("emote")->getPref();
	$pack = e107::pref('core', 'emotepack');

	foreach ($emo as $path => $co)
	{
		$codes = explode(" ", $co);

		// Decode entities so the picker search matches the real shortcut
		// (e.g. stored "&amp;|" -> "&|").
		$keywords = array();
		foreach ($codes as $code)
		{
			$keywords[] = html_entity_decode($code, ENT_QUOTES, 'UTF-8');
		}

		$name  = str_replace("!", "", $path);
		$url   = e_IMAGE_ABS . "emotes/" . $pack . "/" . str_replace("!", ".", $path);
		$title = ucfirst($name);

		$emotes[$title] = array(
			'keywords' => $keywords,
			'char'     => '<img data-emoticon="true" style="width:1em;height:1em;vertical-align:-.1em" draggable="false" src="' . $url . '" alt="' . $title . '"/>',
			'category' => 'user',
		);
	}
}

header('Content-Type: text/javascript; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

echo "hugerte.Resource.add(\"hugerte.plugins.emoticons\"," . json_encode($emotes) . ");\n";
