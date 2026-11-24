<?php


class wysiwyg
{
	private $js;
	private $config;
	private $configName;
	private $bootstrapVersion;

	/**
	 * Whether this request may use the editor and the endpoints that serve it.
	 *
	 * post_html is the preference that governs who may write HTML, and so who the
	 * editor is offered to. getperms('P') additionally covers the plugin manager
	 * reaching e107_plugins/hugerte/admin_config.php, which loads the editor to
	 * preview it no matter what post_html says.
	 *
	 * @see e107_plugins/hugerte/admin_config.php the 'P' perm on its menu entries
	 * @return bool
	 */
	public static function isAllowed()
	{
		return check_class(e107::getPref('post_html', e_UC_NOBODY)) || getperms('P');
	}

	function renderConfig($config='')
	{
		$this->getConfig($config);
		$text = "\n /* HugeRTE Config: ".$this->configName." */\n\n";
		$text .= "window.__hugerte_dark = (document.documentElement.getAttribute('data-bs-theme') === 'dark') || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);\n\n";
		$text .= "hugerte.init({\n";

		$text .= $this->config;

		$text .= "\n});";

		return stripslashes($text);
	}



	function __construct($config=FALSE)
	{
		$this->bootstrapVersion = e107::getTheme()->getLibVersion('bootstrap');
	}

	function hugerte_lang()
	{
		$lang = e_LANGUAGE;

		// Languages shipped with the plugin (TinyMCE 6 community language packs,
		// flat format, global renamed to hugerte).
		$hugertelang = array(
			'Arabic'        => 'ar',
			'Armenian'      => 'hy',
			'Azerbaijani'   => 'az',
			'Basque'        => 'eu',
			'Belarusian'    => 'be',
			'Bengali'       => 'bn_BD',
			'Bosnian'       => 'bs',
			'Bulgarian'     => 'bg_BG',
			'Catalan'       => 'ca',
			'ChineseSimp'   => 'zh_CN',
			'ChineseTrad'   => 'zh_TW',
			'Croatian'      => 'hr',
			'Czech'         => 'cs',
			'Danish'        => 'da',
			'Dutch'         => 'nl',
			'English'       => 'en_GB', // Default language file.
			'Esperanto'     => 'eo',
			'Estonian'      => 'et',
			'Faroese'       => 'fo',
			'Finnish'       => 'fi',
			'French'        => 'fr_FR',
			'Gaelic'        => 'gd',
			'Gallegan'      => 'gl',
			'Georgian'      => 'ka_GE',
			'German'        => 'de',
			'Greek'         => 'el',
			'Hebrew'        => 'he_IL',
			'Hindi'         => 'hi_IN',
			'Hungarian'     => 'hu_HU',
			'Icelandic'     => 'is_IS',
			'Indonesian'    => 'id',
			'Irish'         => 'ga',
			'Italian'       => 'it',
			'Japanese'      => 'ja',
			'Kazakh'        => 'kk',
			'Khmer'         => 'km_KH',
			'Korean'        => 'ko',
			'Kurdish'       => 'ku',
			'Latvian'       => 'lv',
			'Lithuanian'    => 'lt',
			'Letzeburgesch' => 'lb',
			'Macedonian'    => 'mk_MK',
			'Malayalam'     => 'ml',
			'Mongolian'     => 'mn_MN',
			'Norwegian'     => 'nb_NO',
			'Persian'       => 'fa',
			'Polish'        => 'pl',
			'Portuguese'    => 'pt_PT',
			'Romanian'      => 'ro',
			'Russian'       => 'ru',
			'Serbian'       => 'sr',
			'Sinhala'       => 'si_LK',
			'Slovak'        => 'sk',
			'Slovenian'     => 'sl_SI',
			'Spanish'       => 'es',
			'Swedish'       => 'sv_SE',
			'Tajik'         => 'tg',
			'Tamil'         => 'ta',
			'Tatar'         => 'tt',
			'Thai'          => 'th_TH',
			'Turkish'       => 'tr',
			'Uighur'        => 'ug',
			'Ukrainian'     => 'uk',
			'Vietnamese'    => 'vi',
			'Welsh'         => 'cy',
		);

		if(!isset($hugertelang[$lang]))
		{
			$hugertelang[$lang] = "en_GB";
		}

		// If language file is not present, use default.
		$jsFile = e_PLUGIN . 'hugerte/langs/' . $hugertelang[$lang] . '.js';
		if($hugertelang[$lang] != 'en_GB' && !file_exists($jsFile))
		{
			$hugertelang[$lang] = "en_GB";
		}

		return $hugertelang[$lang];
	}



	function getExternalPlugins($data)
	{
		if(empty($data))
		{
			return null;
		}

		$tmp = explode(" ",$data);

		$ext = array();

		foreach($tmp as $val)
		{
			// Cache-buster: use the file mtime so the browser always picks
			// up a freshly edited plugin.js without a manual hard refresh.
			$jsFile = __DIR__."/plugins/".$val."/plugin.js";
			$ver = is_file($jsFile) ? filemtime($jsFile) : 1;
			$ext[$val] = e_PLUGIN_ABS."hugerte/plugins/".$val."/plugin.js?v=".$ver;
		}

		return json_encode($ext);
	}



	function convertBoolean($string)
	{

		if(substr($string,0,1) == '{' || substr($string,0,1) == '[' || substr($string,0,9) == 'function(')
		{
			return $string;
		}

		if(is_numeric($string))
		{
			return $string;
		}

		if(is_string($string))
		{
			$string = trim($string);
			$string = str_replace("\n","",$string);
		}

		if($string === true)
		{
			return 'true';
		}

		if($string === false)
		{
			return 'false';
		}

		if($string === 'true' || $string === 'false' || $string[0] == '[')
		{
			return $string;
		}

		return '"'.$string.'"';
	}



	function getConfig($config=false)
	{
		$tp = e107::getParser();

		if($config !== false)
		{
			// 'file' maps everything outside [\w_.-] to '-', so no separator of any
			// kind survives. basename() would not do: it is not a separator on
			// Linux, so a Windows host would still resolve "..\..\somefile".
			$template = $tp->filter($config, 'file').".xml";
		}
		else
		{
			if(getperms('0'))
			{
				$template = "mainadmin.xml";
			}
			elseif(ADMIN)
			{
				$template = "admin.xml";
			}
			elseif(USER)
			{
				$template = "member.xml";
			}
			else
			{
				$template = "public.xml";
			}


		}

		// Compared case-insensitively, because the file system underneath may be.
		$requested = strtolower($template);

		if(($requested === 'mainadmin.xml' && !getperms('0')) || ($requested === 'admin.xml' && !ADMIN))
		{
			$template = 'public.xml';
		}

		$themeConfig = THEME."templates/hugerte/".$template;
		$pluginConfig = e_PLUGIN."hugerte/templates/".$template;

		if(is_readable($themeConfig))
		{
			$configPath = $themeConfig;
		}
		elseif(is_readable($pluginConfig))
		{
			$configPath = $pluginConfig;
		}
		else
		{
			$configPath = e_PLUGIN."hugerte/templates/public.xml";
		}

		$config 	= e107::getXml()->loadXMLfile($configPath, true);

		//TODO Cache!

		$this->configName = $config['@attributes']['name'];

		$hugerteLanguage    = $this->hugerte_lang();

		unset($config['@attributes']);

		$ret = array(
			'selector' 			=> '.e-wysiwyg',
			'base_url'          => e_PLUGIN_ABS."hugerte/hugerte",
			'skin'              => 'window.__hugerte_dark ? "oxide-dark" : "oxide"',
			'language'			=> $hugerteLanguage,
			'language_url'      => SITEURLBASE.e_PLUGIN_ABS."hugerte/langs/" . $hugerteLanguage . ".js",
		);

		// Loop thru XML parms.
		foreach($config as $k=>$xml)
		{
			if($k == 'plugins')
			{
				$ret[$k] = $this->filter_plugins($xml);
			}
			else
			{
				$ret[$k] = $xml;
			}

		}

		$tPref = e107::pref('hugerte');

		if(!empty($tPref['paste_as_text']))
		{
			$ret['paste_as_text']	= true;
		}


		if(!empty($tPref['browser_spellcheck']))
		{
			$ret['browser_spellcheck']	= true;
		}

		if(!empty($tPref['visualblocks']))
		{
			$ret['visualblocks_default_state']	= true;
		}

		$ret['autosave_ask_before_unload'] = true;
		$ret['autosave_retention']         = "30m";
		$ret['autosave_interval']          = "3s";
		$ret['autosave_prefix']            = "hugerte-autosave-{path}{query}-{id}-";

		// Emoticons: built-in emoticons plugin in "images" mode, fed by the e107
		// emote configuration (same source as the old tinymce4 smileys plugin, so
		// changes made by the emoticons plugin are picked up automatically).
		if(e107::pref('core','smiley_activate',false))
		{
			$ret['emoticons_database']     = 'images';
			$ret['emoticons_database_url'] = e_PLUGIN_ABS."hugerte/plugins/e107/emotes.php";
			// The plugin prefixes the <img> src with this value; use "" so the
			// absolute e107 emote URLs served by emotes.php are kept as-is.
			$ret['emoticons_images_url']   = '';
		}


		$codeHighlightClass = varset($tPref['code_highlight_class'], 'prettyprint linenums');

		$formats = "[
                {title: 'Headers', items: [
                    {title: 'Heading 1', block: 'h1'},
                    {title: 'Heading 2', block: 'h2'},
                    {title: 'Heading 3', block: 'h3'},
                    {title: 'Heading 4', block: 'h4'},
                    {title: 'Heading 5', block: 'h5'},
                    {title: 'Heading 6', block: 'h6'}
                ]},

                {title: 'Inline', items: [
                    {title: 'Bold', inline: 'b', icon: 'bold'},
                    {title: 'Italic', inline: 'em', icon: 'italic'},
                    {title: 'Underline', inline: 'span', styles : {textDecoration : 'underline'}, icon: 'underline'},
                    {title: 'Strikethrough', inline: 'span', styles : {textDecoration : 'line-through'}, icon: 'strikethrough'},
                    {title: 'Superscript', inline: 'sup', icon: 'superscript'},
                    {title: 'Subscript', inline: 'sub', icon: 'subscript'},
                    {title: 'Code', inline: 'code', icon: 'code'},
                    {title: 'Small', inline: 'small', icon: ''},
                ]},

                {title: 'Blocks', items: [
                    {title: 'Paragraph', block: 'p'},
                    {title: 'Blockquote', block: 'blockquote'},
                    {title: 'Blockquote alt.', block: 'blockquote', classes: 'blockquote-alt blockquote__alternative'},
                    {title: 'Div', block: 'div'},
                    {title: 'Pre', block: 'pre'},
                    {title: 'Code Highlighted', block: 'pre', classes: '".$codeHighlightClass."' }
                ]},

                  {title: 'Lists', items: [
                    {title: 'FontAwesome', selector: 'ul', classes: 'fa-ul' },
                    {title: 'FontAwesome List Icon', selector: 'i', classes: 'fa-li' },
                    {title: 'Bootstrap Listgroup', selector: 'ul', classes: 'list-group' },
                    {title: 'Bootstrap Listgroup Item', selector: 'li', classes: 'list-group-item' },
                ]},

                {title: 'Alignment', items: [
                    {title: 'Left', block: 'div', classes: 'text-left',  icon: 'alignleft'},
                    {title: 'Center', block: 'div',classes: 'text-center', icon: 'aligncenter'},
                    {title: 'Right', block: 'div', classes: 'text-right',  icon: 'alignright'},
                    {title: 'Justify', block: 'div', classes: 'text-justify', icon: 'alignjustify'},
                    {title: 'No Text-Wrap', block: 'div', classes: 'text-nowrap', icon: ''},
                    {title: 'Clear Float', block: 'div', classes: 'clearfix'},
                    {title: 'Image Left', selector: 'img', classes: 'bbcode-img-left',  icon: 'alignleft'},
                    {title: 'Image Right', selector: 'img', classes: 'bbcode-img-right', icon: 'alignright'}

                ]},

                {title: 'Glyphs', items: [
                    {title: 'Size 2x', selector: 'i', classes: 'fa-2x'},
                    {title: 'Size 3x', selector: 'i', classes: 'fa-3x'},
                    {title: 'Size 4x', selector: 'i', classes: 'fa-4x'},
                    {title: 'Size 5x', selector: 'i', classes: 'fa-5x'},
                    {title: 'Fixed Width', selector: 'i', classes: 'fa-fw'},
                     {title: 'Spin', selector: 'i', classes: 'fa-spin'}
                ]},

                {title: 'Bootstrap Inline', items: [
				 {title: 'Label (Default)', inline: 'span', classes: 'label label-default'},
				 {title: 'Label (Primary)', inline: 'span', classes: 'label label-primary'},
                 {title: 'Label (Success)', inline: 'span', classes: 'label label-success'},
                 {title: 'Label (Info)', inline: 'span', classes: 'label label-info'},
                 {title: 'Label (Warning)', inline: 'span', classes: 'label label-warning'},
                 {title: 'Label (Danger)', inline: 'span', classes: 'label label-danger'},
                 {title: 'Muted', inline: 'span', classes: 'text-muted'},
                ]},

                 {title: 'Bootstrap Blocks', items: [
                 {title: 'Alert (Success)', block: 'div', classes: 'alert alert-success'},
                 {title: 'Alert (Info)', block: 'div', classes: 'alert alert-info'},
                 {title: 'Alert (Warning)', block: 'div', classes: 'alert alert-warning'},
                 {title: 'Alert (Danger)', block: 'div', classes: 'alert alert-block alert-danger'},
                 {title: 'Lead', block: 'p', classes: 'lead'},
                 {title: 'Well', block: 'div', classes: 'well'},
				 {title: 'Row', block: 'div', classes: 'row'},
                 {title: '1/4 Width Block', block: 'div', classes: 'col-md-3 col-sm-12'},
                 {title: '3/4 Width Block', block: 'div', classes: 'col-md-9 col-sm-12'},
                 {title: '1/3 Width Block', block: 'div', classes: 'col-md-4 col-sm-12'},
                 {title: '2/3 Width Block', block: 'div', classes: 'col-md-8 col-sm-12'},
                 {title: '1/2 Width Block', block: 'div', classes: 'col-md-6 col-sm-12'},
                ]},

                 {title: 'Bootstrap Buttons', items: ".$this->bootstrapButtonStyles()."},

				 {title: 'Bootstrap Images', items: [
				 {title: 'Responsive (recommended)',  selector: 'img', classes: 'img-responsive img-fluid'},
				 {title: 'Rounded',  selector: 'img', classes: 'img-rounded rounded'},
				 {title: 'Circle', selector: 'img', classes: 'img-circle rounded-circle'},
                 {title: 'Thumbnail', selector: 'img', classes: 'img-thumbnail'},
                ]},

				 {title: 'Bootstrap Tables', items: [
				 {title: 'Bordered',  selector: 'table', classes: 'table-bordered'},
				 {title: 'Condensed', selector: 'table', classes: 'table-condensed'},
				 {title: 'Hover', selector: 'table', classes: 'table-hover'},
                 {title: 'Striped', selector: 'table', classes: 'table-striped'},
                ]},

                {title: 'Animate.css Style', items: [
					{title: 'bounce',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated bounce'},
					{title: 'flash',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated flash'},
					{title: 'pulse',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated pulse'},
					{title: 'rubberBand',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated rubberBand'},
					{title: 'shake',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated shake'},
					{title: 'headShake',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated headShake'},
					{title: 'swing',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated swing'},
					{title: 'tada',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated tada'},
					{title: 'wobble',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated wobble'},
					{title: 'jello',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated jello'},
					{title: 'bounceIn',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated bounceIn'},
					{title: 'bounceInDown',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated bounceInDown'},
					{title: 'bounceInLeft',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated bounceInLeft'},
					{title: 'bounceInRight',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated bounceInRight'},
					{title: 'bounceInUp',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated bounceInUp'},
					{title: 'fadeIn',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated fadeIn'},
					{title: 'fadeInDown',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated fadeInDown'},
					{title: 'fadeInDownBig',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated fadeInDownBig'},
					{title: 'fadeInLeft',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated fadeInLeft'},
					{title: 'fadeInLeftBig',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated fadeInLeftBig'},
					{title: 'fadeInRight',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated fadeInRight'},
					{title: 'fadeInRightBig',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated fadeInRightBig'},
					{title: 'fadeInUp',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated fadeInUp'},
					{title: 'fadeInUpBig',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated fadeInUpBig'},
					{title: 'fadeOut',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated fadeOut'},
					{title: 'flip',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated flip'},
					{title: 'flipInX',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated flipInX'},
					{title: 'flipInY',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated flipInY'},
					{title: 'lightSpeedIn',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated lightSpeedIn'},
					{title: 'rotateIn',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated rotateIn'},
					{title: 'rotateInDownLeft',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated rotateInDownLeft'},
					{title: 'rotateInDownRight',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated rotateInDownRight'},
					{title: 'rotateInUpLeft',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated rotateInUpLeft'},
					{title: 'rotateInUpRight',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated rotateInUpRight'},
					{title: 'hinge',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated hinge'},
					{title: 'jackInTheBox',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated jackInTheBox'},
					{title: 'rollIn',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated rollIn'},
					{title: 'rollOut',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated rollOut'},
					{title: 'zoomIn',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated zoomIn'},
					{title: 'zoomInDown',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated zoomInDown'},
					{title: 'zoomInLeft',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated zoomInLeft'},
					{title: 'zoomInRight',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated zoomInRight'},
					{title: 'zoomInUp',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated zoomInUp'},
					{title: 'zoomOut',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated zoomOut'},
					{title: 'zoomOutDown',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated zoomOutDown'},
					{title: 'zoomOutLeft',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated zoomOutLeft'},
					{title: 'zoomOutRight',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated zoomOutRight'},
					{title: 'zoomOutUp',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated zoomOutUp'},
					{title: 'slideInDown',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated slideInDown'},
					{title: 'slideInLeft',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated slideInLeft'},
					{title: 'slideInRight',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated slideInRight'},
					{title: 'slideInUp',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animated slideInUp'},
                ]},

			 {title: 'Animate.css Delay', items: [
				{title: '2 sec.',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animation-delay-2'},
				{title: '4 sec.',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animation-delay-4'},
				{title: '6 sec.',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animation-delay-6'},
				{title: '8 sec.',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animation-delay-8'},
				{title: '10 sec.',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animation-delay-10'},
				{title: '12 sec.',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animation-delay-12'},
				{title: '14 sec.',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animation-delay-14'},
				{title: '16 sec.',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animation-delay-16'},
				{title: '18 sec.',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animation-delay-18'},
				{title: '20 sec.',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animation-delay-20'},
				{title: '22 sec.',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animation-delay-22'},
				{title: '24 sec.',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animation-delay-24'},
				{title: '26 sec.',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animation-delay-26'},
				{title: '28 sec.',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animation-delay-28'},
				{title: '30 sec.',  selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'animation-delay-30'},
                ]},


            ]";

		$ret['style_formats']  = $formats;
		$ret['link_class_list'] = $this->getLinkClassList();

		// Override the built-in 'strikethrough' format to use <span> with
		// text-decoration:line-through instead of the default <s> tag.
		// e107 cleanHtml() strips <s> (not in allowedTags) AND its text
		// content.  <span> IS in allowedTags, so the content survives.
		// The toolbar button, keyboard shortcut (Ctrl+Shift+X), and the
		// StrikeThrough command all reference the format by name, so they
		// automatically pick up this definition.
		$ret['formats'] = "{ strikethrough: { inline: 'span', styles: { 'text-decoration': 'line-through' } } }";



		// Emoticon Support - data served by plugins/e107/emotes.php (e107 emote config).

		$ret['convert_fonts_to_spans']	= false;

		$editorCSS = $this->getEditorCSS();

		$ret['content_css']				= json_encode($editorCSS);
		$ret['content_style']           = "div.clearfix { border-top:1px solid red } ";
		$ret['relative_urls']			= false;  //Media Manager prefers it like this.
		$ret['preformatted']			= true;
		$ret['document_base_url']		= SITEURL;
		$ret['schema']                  = "html5";
		$ret['element_format']          = "html";
		$ret['pagebreak_separator']     = "[newpage]"; // emit e107's own pagination marker instead of the default <!-- pagebreak --> comment.


		if(!empty($ret['templates']))
		{
			$ret['templates']				 = $tp->replaceConstants($ret['templates'],'abs');
		}

		if(ADMIN)
		{
			$ret['templates'] = $this->getSnippets();
		}

		$text = array();
		foreach($ret as $k=>$v)
		{
			if($k == 'external_plugins')
			{
				$text[] = 'external_plugins: '. $this->getExternalPlugins($v);
				continue;
			}

			if($k == 'pagebreak_separator')
			{
				$text[] = $k.': '.json_encode($v); // quoted JS string; convertBoolean() would treat a [...] value as a JS array literal.
				continue;
			}

			if($k == 'skin')
			{
				// JS expression, not a literal string – output unquoted.
				$text[] = 'skin: ('.$v.')';
				continue;
			}

			$text[] = $k.': '.$this->convertBoolean($v);
		}

		$this->config = implode(",\n",$text);

	}


	private function bootstrapButtonStyles()
	{
		switch($this->bootstrapVersion)
		{
			case 4:
			case 5:
				$ret = "[
					{title: 'Button (Primary)', selector: 'a', classes: 'btn btn-primary'},
					{title: 'Button (Secondary)', selector: 'a', classes: 'btn btn-secondary'},
	                {title: 'Button (Success)', selector: 'a', classes: 'btn btn-success'},
	                {title: 'Button (Info)', selector: 'a', classes: 'btn btn-info'},
	                {title: 'Button (Warning)', selector: 'a', classes: 'btn btn-warning'},
	                {title: 'Button (Danger)', selector: 'a', classes: 'btn btn-danger'},

					{title: 'Button (Light)', selector: 'a', classes: 'btn btn-light'},
			        {title: 'Button (Dark)', selector: 'a', classes: 'btn btn-dark'},

			        {title: 'Button (Outline Primary)', selector: 'a', classes: 'btn btn-outline-primary'},
			        {title: 'Button (Outline Secondary)', selector: 'a', classes: 'btn btn-outline-secondary'},
			        {title: 'Button (Outline Success)', selector: 'a', classes: 'btn btn-outline-success'},
			        {title: 'Button (Outline Info)', selector: 'a', classes: 'btn btn-outline-info'},
			        {title: 'Button (Outline Warning)', selector: 'a', classes: 'btn btn-outline-warning'},
			        {title: 'Button (Outline Danger)', selector: 'a', classes: 'btn btn-outline-danger'},
			        {title: 'Button (Outline Light)', selector: 'a', classes: 'btn btn-outline-light'},
			        {title: 'Button (Outline Dark)', selector: 'a', classes: 'btn btn-outline-dark'},

	                ]";
			break;
			default:
				$ret = "[
	                 {title: 'Button (Default)', selector: 'a', classes: 'btn btn-default'},
					 {title: 'Button (Primary)', selector: 'a', classes: 'btn btn-primary'},
	                 {title: 'Button (Success)', selector: 'a', classes: 'btn btn-success'},
	                 {title: 'Button (Info)', selector: 'a', classes: 'btn btn-info'},
	                 {title: 'Button (Warning)', selector: 'a', classes: 'btn btn-warning'},
	                 {title: 'Button (Danger)', selector: 'a', classes: 'btn btn-danger'},
	                ]";

		}

		return $ret;
	}



	private function getLinkClassList()
	{


		switch($this->bootstrapVersion)
		{
			case 4:
			case 5:
				return "[
		        {title: 'None', value: ''},
		        {title: 'Link', value: 'btn btn-link'},
		        {title: 'Alert Link', value: 'alert-link'},
		        {title: 'Button (Primary)', value: 'btn btn-primary'},
		        {title: 'Button (Secondary)', value: 'btn btn-secondary'},
		        {title: 'Button (Success)', value: 'btn btn-success'},
		        {title: 'Button (Info)', value: 'btn btn-info'},
		        {title: 'Button (Warning)', value: 'btn btn-warning'},
		        {title: 'Button (Danger)', value: 'btn btn-danger'},
		        {title: 'Button (Light)', value: 'btn btn-light'},
		        {title: 'Button (Dark)', value: 'btn btn-dark'},

		        {title: 'Button (Outline Primary)', value: 'btn btn-outline-primary'},
		        {title: 'Button (Outline Secondary)', value: 'btn btn-outline-secondary'},
		        {title: 'Button (Outline Success)', value: 'btn btn-outline-success'},
		        {title: 'Button (Outline Info)', value: 'btn btn-outline-info'},
		        {title: 'Button (Outline Warning)', value: 'btn btn-outline-warning'},
		        {title: 'Button (Outline Danger)', value: 'btn btn-outline-danger'},
		        {title: 'Button (Outline Light)', value: 'btn btn-outline-light'},
		        {title: 'Button (Outline Dark)', value: 'btn btn-outline-dark'},

		        ]";
				break;

			default:
				return "[
		        {title: 'None', value: ''},
		        {title: 'Link', value: 'btn btn-link'},
		        {title: 'Alert Link', value: 'alert-link'},
		        {title: 'Button (Default)', value: 'btn btn-default'},
		        {title: 'Button (Primary)', value: 'btn btn-primary'},
		        {title: 'Button (Success)', value: 'btn btn-success'},
		        {title: 'Button (Info)', value: 'btn btn-info'},
		        {title: 'Button (Warning)', value: 'btn btn-warning'},
		        {title: 'Button (Danger)', value: 'btn btn-danger'}
		        ]";
		}
	}


	private function getSnippets()
	{

		$customPath = THEME."templates/hugerte/snippets";

		if(is_dir($customPath))
		{
			$path = $customPath;
			$base = THEME_ABS."templates/hugerte/snippets";
		}
		else
		{
			$path = e_PLUGIN."hugerte/snippets";
			$base = e_PLUGIN_ABS."hugerte/snippets";
		}


		$files = e107::getFile()->get_files($path);

		$ret = array();
		foreach($files as $f)
		{
			$content = file_get_contents($f['path'].$f['fname'], null, null, null, 140);

			preg_match('/<!--[^\w]*Title:[\s]([^\r\n]*)[\s]*Info: ?([^\r\n]*)/is', $content, $m);
			if(!empty($m[1]))
			{
				$url = $base."/".$f['fname'];
				$ret[] = array('title'=>trim($m[1]), 'url'=>$url, 'description'=>trim($m[2]));
			}
		}

		return json_encode($ret);

	}


	function filter_plugins($plugs)
	{

		$smile_pref = e107::getConfig()->getPref('smiley_activate');

		$admin_only = array("ibrowser");

		// TinyMCE 4-only plugins: gone in 6/7 (core features or removed).
		// print, preview, hr, contextmenu, paste, textcolor are core in 6/7.
		// preview is still a plugin in 6/7 and stays.
		$removed = array("print", "hr", "contextmenu", "paste", "textcolor", "smileys", "compat3x", "example");

		$plug_array = explode(" ",$plugs);

		$tinymce_plugins = array();

		foreach($plug_array as $val)
		{
			if(in_array($val,$admin_only) && !ADMIN)
			{
		    	continue;
			}

			if(in_array($val, $removed))
			{
				continue;
			}

			if(!$smile_pref && ($val=="emoticons"))
			{
		    	continue;
			}

			if(!empty($val))
			{
				$tinymce_plugins[$val] = trim($val);
			}
		}


		$tPref = e107::pref('hugerte');


		if(!empty($tPref['visualblocks']))
		{
			$tinymce_plugins['visualblocks'] = 'visualblocks';
		}

		return implode(" ",$tinymce_plugins);
	}

	/**
	 * @return array
	 */
	public function getEditorCSS($theme = 'front')
	{

		$tp = e107::getParser();

		$libraries = e107::getTheme($theme)->getThemeFiles('library', 'wysiwyg');

		$ret = [];

		foreach($libraries as $lib)
		{
			if(!empty($lib['css']))
			{
				foreach($lib['css'] as $path)
				{
					$ret[] = $tp->replaceConstants($path, 'abs');
				}
			}
		}

		$useThemeStyle = (bool) e107::pref('hugerte', 'use_theme_style', false);

		if($useThemeStyle)
		{
			$theme = e107::getTheme($theme)->getThemeFiles('css', 'wysiwyg');
			if(!empty($theme['css']))
			{
				foreach($theme['css'] as $path)
				{
					$ret[] = $tp->replaceConstants($path, 'abs');
				}
			}
		}

		$animate = e107::library('files', 'animate.css');
		$ret[] = $tp->replaceConstants($animate['css'][0], 'abs');

		// Font Awesome: the editor content iframe does NOT inherit the page CSS,
		// so without this a glyph inserted from the Media Manager (<i class="fas
		// fa-...">) renders as a zero-width, invisible element. Load the same FA
		// the theme uses (fontawesome6) so glyphs look identical to the frontend.
		$fa = e107::library('files', 'fontawesome6');
		if(!empty($fa['css'][0]))
		{
			$ret[] = $tp->replaceConstants($fa['css'][0], 'abs');
		}

		$ret[] = e_PLUGIN_ABS . 'hugerte/editor.css';

		return $ret;

	}


}
