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
	   The skin already sets max-width:480px + width:95vw on .tox-dialog.
	   We only add a height cap so tall dialogs do not overflow the
	   viewport.  Do NOT override max-width here – that breaks the
	   built-in 480px compact size. */
	.tox .tox-dialog { max-height: 90vh !important; }

	/* ── Media-manager dialog: large, responsive, no scrollbars ─────
	   The .hugerte-media-dialog class is added by plugin.js (openMedia)
	   to the .tox-dialog element after the dialog renders (via the
	   .hugerte-media-host div + closest method). */
	.tox-dialog.hugerte-media-dialog {
		width: min(85vw, 1200px) !important;
		max-width: min(85vw, 1200px) !important;
		height: min(60vh, 900px) !important;
		max-height: min(60vh, 900px) !important;
	}
	.hugerte-media-dialog .tox-dialog__body {
		overflow: hidden;
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
		.tox-dialog.hugerte-media-dialog {
			width: 100vw !important;
			max-width: 100vw !important;
			height: 100vh !important;
			max-height: 100vh !important;
		}
	}
');
