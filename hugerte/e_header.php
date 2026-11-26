<?php
/*
 * e107 website system
 *
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 * HugeRTE WYSIWYG editor plugin - header hook.
 */

if (!defined('e107_INIT')) { exit; }

e107::css('inline','
	a.e-wysiwyg-toggle { margin-top:5px }
	.mce-tinymce.mce-fullscreen {   z-index: 1050 !important;  }

	/* ── Native HugeRTE dialogs (link, image, table, …) ─────────────
	   Cap ALL dialogs (including width-md/width-lg variants used by
	   the table plugin) at a compact 500px.  The skin sets 480px on
	   the base rule, but width-md (800px) and width-lg (1200px) have
	   higher specificity and would override it without !important. 
	.tox .tox-dialog {
		max-width: min(500px, 95vw) !important;
		max-height: 90vh !important;
	}
	*/
	/* ── Media-manager dialog: larger, responsive, no scrollbars ────
	   The .hugerte-media-dialog class is added by plugin.js (openMedia)
	   to the .tox-dialog element after the dialog renders. */
	.tox .tox-dialog.hugerte-media-dialog {
		width: min(85vw, 1200px) !important;
		max-width: min(85vw, 1200px) !important;
		height: min(60vh, 900px) !important;
		max-height: min(60vh, 900px) !important;
	}
	.hugerte-media-dialog .tox-dialog__body {
		overflow: hidden;
		min-height: 0;
	}
	.hugerte-media-dialog .tox-dialog__body-content {
		max-height: none !important;
		overflow: hidden !important;
		padding: 0 !important;
	}
	.hugerte-media-dialog .tox-dialog__body-content > * {
		margin: 0 !important;
		flex: 1 1 auto;
		min-height: 0;
		display: flex;
		flex-direction: column;
		overflow: hidden;
	}
	.hugerte-media-dialog .tox-form__group {
		flex: 1 1 auto !important;
		min-height: 0;
		display: flex !important;
		flex-direction: column;
		overflow: hidden !important;
	}
	.hugerte-media-dialog .hugerte-media-host {
		flex: 1 1 auto;
		min-height: 0;
		width: 100%;
		display: flex;
		flex-direction: column;
		overflow: hidden;
	}
	.hugerte-media-dialog .hugerte-media-host iframe {
		flex: 1 1 auto;
		min-height: 0;
		width: 100%;
		display: block;
		border: 0;
	}

	/* Mobile: media dialog fills the screen */
	@media (max-width: 768px) {
		.tox .tox-dialog.hugerte-media-dialog {
			width: 100vw !important;
			max-width: 100vw !important;
			height: 100vh !important;
			max-height: 100vh !important;
		}
	}

	/* Dark mode: the hugerte skins (oxide and oxide-dark) both hardcode
	   the editor iframe to white - the dark skin only themes the toolbar
	   and dialogs, never the document area. This makes the iframe
	   background follow the e107 admin theme switch, i.e. the <html>
	   data-bs-theme attribute the admin theme sets. The text color and
	   body background inside the iframe are applied by the e_footer.php
	   JS via the .hugerte-dark class (styles: editor.css).
	   Colors: etabler admin dark theme (Tabler, slate base). */
	html[data-bs-theme="dark"] .tox .tox-edit-area__iframe {
		background-color: #1e293b !important;
	}
');
