<?php
/*
 * e107 website system
 *
 * Copyright (C) e107 Inc (e107.org)
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 */

if(empty($_POST['content']) && empty($_GET['debug']) && !defined('TINYMCE_DEBUG') && !defined('TINYMCE_UNIT_TEST'))
{
	header('Content-Length: 0');
	exit;
}

if(!defined('TINYMCE_DEBUG') && !defined('TINYMCE_UNIT_TEST'))
{
	$_E107['no_online'] = true;
	$_E107['no_menus'] = true;
	$_E107['no_forceuserupdate'] = true;
	$_E107['no_maintenance'] = true;
	$_E107['minimal'] = true;

	require_once(__DIR__."/../../../../class2.php");
	require_once(__DIR__."/../../wysiwyg_class.php");

	if(!wysiwyg::isAllowed())
	{
		http_response_code(403);
		header('Content-Length: 0');
		exit;
	}
}

/**
 * BBCode <-> HTML bridge for the HugeRTE editor.
 *
 * Two modes (selected via the 'mode' POST parameter):
 *   - tohtml:   stored BBCode/HTML -> the raw HTML the editor edits.
 *               Used by the media-manager iframe insert and the BBCode toggle.
 *   - tobbcode: editor HTML -> stored BBCode ([html]...[/html] wrap).
 *               Used by the BBCode toggle on the way back to the editor.
 *
 * This endpoint is only reachable by users who may post HTML
 * (see wysiwyg::isAllowed()), so the HTML it echoes back is always
 * content the caller is already authorised to write.
 */
class e107HugerteParser
{
	protected $postHtmlClass;

	function __construct()
	{
		$this->postHtmlClass = (int) e107::getPref('post_html', e_UC_NOBODY);

		$mode = isset($_POST['mode']) ? $_POST['mode'] : 'tohtml';
		$_POST['content'] = isset($_POST['content']) ? $_POST['content'] : '';

		$html = '';

		if(!empty($_GET['debug']) && getperms('0'))
		{
			if(defined("TINYMCE_PARSER_DEBUG_TEXT"))
			{
				$text = TINYMCE_PARSER_DEBUG_TEXT;
				echo "<h1>Original</h1>";
				print_a($text);
				echo "<h1>toHtml</h1>";
			}
			else
			{

			$text = <<<TEMPL

	[html][code]Something goes here [b]bold print[/b][/code][/html]

TEMPL;
			}
			$_POST['content'] = $text;
		}

		if($mode === 'tohtml')
		{
			$html =  $this->toHTML($_POST['content']);
		}
		elseif($mode === 'tobbcode')
		{
			$html = $this->toDB($_POST['content']);
		}

		echo $html;

	}


	public function toHTML($content)
	{
		$tp = e107::getParser();
		$fa = e107::getTheme()->getFontAwesome(); // get the frontend theme's fontawesome version.
		$tp->setFontAwesome($fa);

		if(!defined('BOOTSTRAP') && ($bs = e107::getTheme()->getLibVersion('bootstrap')))
		{
			define('BOOTSTRAP', (int) $bs);
		}

		// No stripslashes(): the client sends URL-encoded form data (encodeURIComponent),
		// which PHP decodes once, so the value arrives raw. stripslashes() would
		// corrupt any legitimate backslash (code samples, Windows paths).

		e107::getBB()->setClass($this->getMediaCategory());

		if(check_class($this->postHtmlClass)) // raw HTML within [html] tags.
		{
			if(strpos($content,"[html]") === false) // BC - convert old BB code text to html.
			{
				e107::getBB()->clearClass();

				if($tp->isHtml($content) === false) // plain text or BBcode to HTML
				{
					$content =  nl2br($content, true);
				}

				$content = $tp->toHTML($content, true, 'WYSIWYG');
			}

			$content 		= str_replace("{e_BASE}",e_HTTP,$content); // We want {e_BASE} in the final data going to the DB, but not the editor.
			$srch 			= array("<!-- bbcode-html-start -->","<!-- bbcode-html-end -->","[html]","[/html]");
			$content 		= str_replace($srch,"",$content);
			$content 		= $tp->parseBBTags($content,true); // parse the <bbcode> tag so we see the HTML equivalent while editing!
			$content 		= e107::getBB()->parseBBCodes($content); 

			$text = $content;

		}
		else  // bbcode Mode.
		{
			$content = str_replace("{e_BASE}",e_HTTP, $content); // We want {e_BASE} in the final data going to the DB, but not the editor.
			$content = $tp->toHTML($content, true, 'WYSIWYG');
			$content = str_replace(e_MEDIA_IMAGE,"{e_MEDIA_IMAGE}",$content);

			$text = $content;
		}


		/*
		 * Convert [glyph] and [icon] media tags to Font-Awesome HTML.
		 * The core e107 parser does not handle these in WYSIWYG mode
		 * - they are normally resolved at frontend render time. In the
		 * editor we need the actual <i>/<span> markup so the icon is
		 * visible while editing.
		 */
		$text = preg_replace_callback('/\[(glyph|icon)\]([^\[\]]+)\[\/\1\]/', function ($m) use ($tp) {
			$glyphName = $m[2];
			if (substr($glyphName, -6) !== '.glyph') {
				$glyphName .= '.glyph';
			}
			$result = $tp->toGlyph($glyphName, '');
			return ($result !== false && $result !== '') ? $result : $m[0];
		}, $text);

		e107::getBB()->clearClass();
		return $text;

	}


	function toDB($content)
	{
		e107::getBB()->setClass($this->getMediaCategory());

		// No stripslashes() - see toHTML() for the rationale (value arrives raw).

		if(check_class($this->postHtmlClass)) // Plain HTML mode.
		{
			$content = trim($content);
			$content = e107::getBB()->imgToBBcode($content);

			if(strip_tags($content, '<i>') == '&nbsp;') // Avoid this: [html]<p>&nbsp;</p>[/html]
			{
				return '';
			}

			$content = html_entity_decode(strtr($content, array('&lt;' => '&amp;lt;', '&gt;' => '&amp;gt;', '&amp;' => '&amp;amp;', '&quot;' => '&amp;quot;')));
			$text = !empty($content) ? "[html]".$content."[/html]" : ''; // Add the tags before saving to DB.
		}
		else  // User doesn't have HTML access -  bbcode Mode.
		{
			$content = html_entity_decode($content);
			$text = e107::getBB()->htmltoBBcode($content);   // not reliable enough yet.
		}

		$text = str_replace('[html]<p></p>[/html]','',$text); // cleanup.

		e107::getBB()->clearClass();
		return $text;

	}

	/**
	 * @return mixed|null
	 */
	private function getMediaCategory()
	{
		return isset($_SESSION['media_category']) ? $_SESSION['media_category'] : null;
	}

}

$parser = new e107HugerteParser();


