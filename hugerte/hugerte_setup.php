<?php
/*
* e107 website system
*
* Copyright (c) 2008-2009 e107 Inc (e107.org)
* Released under the terms and conditions of the
* GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
*
* HugeRTE WYSIWYG editor plugin - install/uninstall/update routines.
*
* The e_parse addon is not part of the auto-rebuilt addon lists (e_parse is not
* in e_plugin::$_addon_types), so it must be registered in the core 'e_parse_list'
* pref explicitly on install and removed on uninstall.
*
* hugerte is appended AFTER any existing entries (e.g. trumbowyg) on purpose:
* another editor's e_parse::toDB may wrap the content in [html]...[/html] first,
* and hugerte's toDB() is idempotent (it skips already-wrapped content), so the
* final value is always wrapped exactly once.
**
*/

if (!defined('e107_INIT')) { exit; }


class hugerte_setup
{
	/**
	 * No legacy data to migrate for a fresh plugin.
	 *
	 * @return bool
	 */
	function upgrade_required()
	{
		return false;
	}

	/**
	 * Register the e_parse addon in the core 'e_parse_list' pref.
	 *
	 * @param mixed $plug The e107plugin object (unused, present for API parity).
	 * @return bool
	 */
	function install_post($plug = null)
	{
		$core = e107::getConfig('core');
		$list = $core->getPref('e_parse_list', array());

		if (!isset($list['hugerte']))
		{
			$list['hugerte'] = 'hugerte'; // append after existing entries.
			$core->setPref('e_parse_list', $list);
			$core->save();
		}

		return true;
	}

	/**
	 * Remove the e_parse addon from the core 'e_parse_list' pref.
	 *
	 * @param mixed $plug The e107plugin object (unused, present for API parity).
	 * @return bool
	 */
	function uninstall_post($plug = null)
	{
		$core = e107::getConfig('core');
		$list = $core->getPref('e_parse_list', array());

		if (isset($list['hugerte']))
		{
			unset($list['hugerte']);
			$core->setPref('e_parse_list', $list);
			$core->save();
		}

		return true;
	}
}
