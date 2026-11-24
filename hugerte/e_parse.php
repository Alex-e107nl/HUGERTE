<?php
/**
 * @file
 * Addon file for extending e_parser.
 *
 * HugeRTE WYSIWYG editor plugin - server-side content conversion.
 *
 * TinyMCE 4 (the old plugin) did its bbcode<->html bridging client-side via
 * AJAX (beforeSetContent / postProcess). TinyMCE 6/7 (HugeRTE) removed those
 * events, so the bridging now happens server-side here:
 *
 *  - toWYSIWYG(): DB -> editor.  Called by form_handler::bbarea() before the
 *    textarea is rendered. Converts stored [html]...[/html] / bbcode into the
 *    raw HTML the editor edits.
 *  - toDB():      editor -> DB.  Called by e_parse::toDB() when a model saves a
 *    string field. Wraps the editor's raw HTML in [html]...[/html] so the
 *    frontend bbcode system treats it as HTML.
 *
 * Only active for post_html users (the editor only renders for them) and only
 * when hugerte is the selected WYSIWYG editor, so it never interferes with the
 * bbcode fallback or any other editor.
 */

if (!defined('e107_INIT'))
{
	exit;
}

/**
 * Class hugerte_parse.
 */
class hugerte_parse
{

	/**
	 * Plugin preferences.
	 *
	 * @var array
	 */
	private $plugPrefs = array();

	/**
	 * Core preferences.
	 *
	 * @var array
	 */
	private $corePrefs = array();

	/**
	 * Constructor.
	 */
	function __construct()
	{
		$this->plugPrefs = e107::getPlugConfig('hugerte')->getPref();
		$this->corePrefs = e107::getPref();
	}

	/**
	 * Whether HugeRTE is the currently selected WYSIWYG editor.
	 *
	 * Stateful-free mirror of e107::wysiwyg() resolution: the site 'wysiwyg'
	 * pref must be on and the first key of the 'wysiwyg_list' pref (the active
	 * editor) must be 'hugerte'. Reading the pref directly (instead of
	 * e107::wysiwyg(null, true)) avoids the static-cache pitfall on fresh POST
	 * save requests, where e107::wysiwyg() may not yet have been called with a
	 * value.
	 *
	 * @return bool
	 */
	private function hugerteIsInUse()
	{
		if (e107::getPref('wysiwyg', false) != true)
		{
			return false;
		}

		$default = e107::isInstalled('tinymce4') ? array('tinymce4' => 'TinyMce4') : array();
		$availEditors = e107::getPref('wysiwyg_list', $default);

		if (empty($availEditors))
		{
			return false;
		}

		$active = reset(array_keys($availEditors));

		return ($active === 'hugerte');
	}

	/**
	 * Whether the current user may post raw HTML (the editor only renders then).
	 *
	 * @return bool
	 */
	private function userPostsHtml()
	{
		return check_class($this->corePrefs['post_html']);
	}

	/**
	 * Frontend display. The [html]...[/html] wrapper is handled by the core
	 * bbcode system, so there is nothing to do here.
	 *
	 * @param string $text
	 * @param string $context
	 * @return string
	 */
	function toHtml($text, $context = '')
	{
		return $text;
	}

	/**
	 * DB -> editor. Convert stored content into the HTML the editor edits.
	 *
	 * IMPORTANT: form_handler::bbarea() passes the value through
	 * asElementContent() BEFORE calling toWYSIWYG(). That method encodes
	 * &, < and > as &amp; &lt; &gt; so the value is safe inside a <textarea>.
	 * We must decode first to inspect the real content, then re-encode the
	 * result for safe textarea output (the browser decodes entities in
	 * textarea RCDATA, so the editor receives the original HTML).
	 *
	 * @param string $text   HTML-encoded stored field value.
	 * @param array  $param  'field' => field name.
	 * @return string  HTML-encoded content safe for textarea output.
	 */
	function toWYSIWYG($text, $param = array())
	{
		if (!$this->hugerteIsInUse() || !$this->userPostsHtml())
		{
			return $text;
		}

		$tp = e107::getParser();

		// Decode the asElementContent() encoding to inspect the real content.
		$content = htmlspecialchars_decode($text, ENT_QUOTES);

		// Content wrapped in [html]...[/html]: strip the wrapper, return inner HTML.
		if (strpos($content, '[html]') !== false)
		{
			$content = preg_replace('/\[\/?html\]/', '', $content);
			$content = str_replace('{e_BASE}', e_HTTP, $content);
			$content = preg_replace('/<!--(.*)-->/Uis', '', $content);
			if (empty($content))
			{
				$content = '&nbsp;';
			}
			return $this->encodeForTextarea($content);
		}

		// Content is already pure HTML (saved by the WYSIWYG editor).
		// Return it directly — do NOT run the BBCode-to-HTML pipeline,
		// which would mangle real HTML tags and insert <p>&nbsp;</p> / <br>
		// for every newline.
		if ($tp->isHtml($content) === true)
		{
			$content = str_replace('{e_BASE}', e_HTTP, $content);
			$content = preg_replace('/<!--(.*)-->/Uis', '', $content);
			if (empty($content))
			{
				$content = '&nbsp;';
			}
			return $this->encodeForTextarea($content);
		}

		// Content is BBCode / plain text — run the full conversion pipeline.
		$content = nl2br($content, true);
		$content = $tp->toHTML($content, true, 'WYSIWYG');
		$content = str_replace('{e_BASE}', e_HTTP, $content);
		$content = $tp->parseBBTags($content, true);
		$content = e107::getBB()->parseBBCodes($content);
		$content = htmlspecialchars_decode($content);
		$content = preg_replace('/<!--(.*)-->/Uis', '', $content);
		if (empty($content))
		{
			$content = '&nbsp;';
		}
		return $this->encodeForTextarea($content);
	}

	/**
	 * Encode content for safe output inside a <textarea> element.
	 * Mirrors form_handler::asElementContent() — the browser decodes these
	 * entities when reading the textarea RCDATA, so the WYSIWYG editor
	 * receives the original HTML.
	 *
	 * @param string $content
	 * @return string
	 */
	private function encodeForTextarea($content)
	{
		return str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), $content);
	}

	/**
	 * Editor -> DB. Wrap the editor's raw HTML in [html]...[/html].
	 *
	 * @param string $text   Sanitized HTML from the editor.
	 * @param array  $param  'type' => input type, 'field' => field name,
	 *                       'nostrip' => bool, 'noencode' => bool.
	 * @return string
	 */
	function toDB($text, $param = array())
	{
		if (!$this->hugerteIsInUse() || !$this->userPostsHtml())
		{
			return $text;
		}

		// No [html]...[/html] wrapping (see toWYSIWYG for rationale).
		//
		// Strip empty <p> variants that accumulate from TinyMCE 6/7's forced
		// root-block behaviour. Each save/reopen cycle adds another
		// <p>&nbsp;</p> because the editor ensures the body ends with a block
		// element for cursor placement. Without this cleanup the tags grow
		// unbounded (61+ observed after a handful of edits).
		$text = preg_replace('/<p[^>]*>\s*(?:&nbsp;|<br\s*\/?>)?\s*<\/p>\s*/i', '', $text);

		return $text;
	}

}
