<?php
/*
 * e107 website system
 *
 * Copyright (C) 2008-2015 e107 Inc (e107.org)
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 * HugeRTE WYSIWYG editor plugin - admin settings.
 */
require_once(__DIR__.'/../../class2.php');
if (!getperms('P') || !e107::isInstalled('hugerte'))
{
	e107::redirect('admin');
	exit();
}

$result = e107::lan('hugerte', true);


	class hugerte_admin extends e_admin_dispatcher
	{

		protected $modes = array(
			'main'	=> array(
				'controller' 	=> 'hugerte_ui',
				'path' 			=> null,
				'ui' 			=> 'hugerte_ui_form',
				'uipath' 		=> null
			),
		);


		protected $adminMenu = array(

			'main/prefs' 		=> array('caption'=> LAN_PREFS, 'perm' => 'P'),
			 'main/preview'		=> array('caption'=> LAN_PREVIEW, 'perm' => 'P', 'icon'=>'fa-eye')
		);

		protected $adminMenuAliases = array(
			'main/edit'	=> 'main/list'
		);

		protected $menuTitle = 'HugeRTE';
	}



	class hugerte_ui extends e_admin_ui
	{

		protected $pluginTitle		= 'HugeRTE';
		protected $pluginName		= 'hugerte';




		protected $prefs = array(
			'paste_as_text'		    => array('title' => HUGERTE_ALAN_1, 'type'=>'boolean', 'data' => 'int','help'=> ''),
			'browser_spellcheck'    => array('title' => HUGERTE_ALAN_2, 'type'=>'boolean', 'data' => 'int','help'=> HUGERTE_ALAN_3),
			'visualblocks'          => array('title' => HUGERTE_ALAN_4, 'type'=>'boolean', 'data' => 'int','help'=> HUGERTE_ALAN_5),
			'use_theme_style'       => array('title' => HUGERTE_ALAN_7, 'type'=>'boolean', 'data' => 'int','help'=> HUGERTE_ALAN_8),
			'code_highlight_class'  => array('title' => HUGERTE_ALAN_6, 'type'=>'text', 'data' => 'str','help'=> ''),
		);


		function previewPage()
		{
			e107::wysiwyg(true);
			return e107::getForm()->bbarea('preview');

		}
	}


	class hugerte_ui_form extends e_admin_form_ui
	{

	}


new hugerte_admin();

require_once(e_ADMIN."auth.php");
e107::getAdminUI()->runPage();
require_once(e_ADMIN."footer.php");
