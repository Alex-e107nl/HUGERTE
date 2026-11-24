/**
 * e107 external plugin for HugeRTE (TinyMCE 6/7 API, global renamed to "hugerte").
 *
 * Registers:
 *   - e107-image   toolbar button  -> opens the e107 Media Manager (images)
 *   - e107-video   toolbar button  -> opens the e107 Media Manager (videos)
 *   - e107-glyph   toolbar button  -> opens the e107 Media Manager (glyphs)
 *   - e107-bbcode  toolbar button  -> inserts raw e107 BBcode (converted via parser.php)
 *
 * Media-manager insertion uses postMessage: the iframe (e107 image.php dialog)
 * sends the converted HTML back to this window, which inserts it into the
 * specific editor instance whose toolbar button was clicked.
 *
 * Content conversion (bbcode <-> html) is handled server-side in the e_parse.php
 * addon (toWYSIWYG/toDB), NOT here.
 *
 * ---------------------------------------------------------------------------
 * STRIKETHROUGH FIX (root cause):
 *
 * e107's cleanHtml() strips <s> tags (not in allowedTags) AND their text
 * content.  TinyMCE's built-in strikethrough format uses inline:'s', which
 * produces <s>text</s> in the content.
 *
 * Fix: re-register the 'strikethrough' format to use <span> with
 * text-decoration:line-through instead of <s>.  <span> IS in allowedTags,
 * so cleanHtml() preserves it.  The visual result is identical.
 *
 * Additionally:
 *   - BeforeSetContent converts legacy <s> tags to <span> on load, so
 *     existing DB content is normalised and the toolbar button's active
 *     state works correctly.
 *   - BeforeGetContent is a safety net: any <s> that slipped through
 *     (e.g. pasted content) is converted before the value reaches the
 *     server.
 * ---------------------------------------------------------------------------
 *
 * ---------------------------------------------------------------------------
 * GLYPH INSERTION (TinyMCE 6/7 specifics, verified via headless browser):
 *
 * Two root causes discovered in headless Edge testing (glyph_dom_test2/3):
 *
 *   1. TinyMCE 6/7 schema ALIASES <i> to <em> during DomParser.parse().
 *      Any <i class="fas fa-x"> silently becomes <em class="fas fa-x">.
 *
 *   2. The getContent() serializer STRIPS elements whose only text content
 *      is U+FEFF (zero-width no-break space).  The Ja() helper in the
 *      insertContent pipeline strips U+FEFF from the HTML string before
 *      parsing, and the DOMâ†’Node serializer drops text nodes that
 *      become empty after stripping.
 *
 * Fix (verified in glyph_dom_test3.html â€“ all 7 tests pass):
 *   - Convert <i> â†’ <span> in prepareHtml().  <span> is NOT aliased by
 *     the schema and FA CSS works identically with <span>.
 *   - Inject U+200B (zero-width space) into empty inline elements.
 *     Ja() only strips U+FEFF, NOT U+200B, so the element is
 *     "non-empty" to both the parser and the serializer and
 *     survives getContent().
 *   - U+200B is invisible (zero-width) so it has no visual impact.
 *
 * The insertion routine:
 *   - closes the dialog first (editor must regain focus),
 *   - ensures a valid editable selection inside the body,
 *   - calls insertContent() with the prepared HTML,
 *   - verifies via getContent() and falls back to direct DOM insertion
 *     (bypasses the model pipeline) if the element was still dropped.
 * ---------------------------------------------------------------------------
 */
(function () {

	hugerte.PluginManager.add('e107', function (editor, url) {

		console.log('=== HUGERTE E107 PLUGIN LOADED ===', editor.id);

		// ------------------------------------------------------------------
		// ITALIC FIX: override getContent() to convert <em> to <i>
		// TinyMCE 6/7 schema aliases <i> to <em>. The ONLY reliable way
		// to get <i> in the output is to convert AFTER serialization.
		// This wraps editor.getContent() directly - every call goes through
		// this wrapper. No events, no timing issues.
		// ------------------------------------------------------------------
		(function () {
			function emToI(html) {
				if (typeof html !== 'string' || html.indexOf('<em') === -1) return html;
				return html.replace(/<em(\s[^>]*)?\/?>/gi, '<i$1>').replace(/<\/em>/gi, '</i>');
			}
			var origGetContent = editor.getContent.bind(editor);
			editor.getContent = function (settings) {
				var content = origGetContent(settings);
				return emToI(content);
			};
			console.log('[hugerte e107] getContent() patched for em-to-i conversion, editor=' + editor.id);
		})();


		// ------------------------------------------------------------------
		// STRIKETHROUGH FIX â€“ root cause
		//
		// Re-register the 'strikethrough' format to use <span> with
		// text-decoration:line-through instead of the built-in <s> tag.
		//
		// Why: e107 cleanHtml() removes <s> (not in allowedTags) and its
		// text content.  <span> IS in allowedTags, so the content survives.
		//
		// The toolbar button, keyboard shortcut (Ctrl+Shift+X), and the
		// StrikeThrough command all reference the format by name
		// ('strikethrough'), so they automatically pick up the new
		// definition.  No command override needed.
		// ------------------------------------------------------------------
		editor.on('init', function () {
			try {
				editor.formatter.register('strikethrough', {
					inline: 'span',
					styles: { 'text-decoration': 'line-through' }
				});
				console.log('hugerte e107: strikethrough format overridden to <span>');
			} catch (err) {
				console.warn('hugerte e107: failed to override strikethrough format', err);
			}
		});

		// ------------------------------------------------------------------
		// STRIKETHROUGH FIX â€“ normalise legacy <s> tags on load
		//
		// When the editor loads saved content that still contains <s> tags
		// (from before this fix), convert them to <span> so:
		//   - the editor displays them correctly (strikethrough)
		//   - the toolbar button's active state works (matches <span>)
		//   - on save, the content already has <span> (no conversion needed)
		// ------------------------------------------------------------------
		var sToSpanRe = /<s(\s[^>]*)?>([\s\S]*?)<\/s>/gi;

		function convertSToSpan(html) {
			if (typeof html !== 'string' || html.indexOf('<s') === -1) return html;
			return html.replace(sToSpanRe, '<span style="text-decoration:line-through">$2</span>');
		}

		editor.on('BeforeSetContent', function (e) {
			try {
				if ((e.format === 'html' || !e.format) &&
				    typeof e.content === 'string' &&
				    e.content.indexOf('<s') !== -1) {
					e.content = convertSToSpan(e.content);
				}
			} catch (err) {
				console.warn('hugerte e107: BeforeSetContent <s>â†’<span> failed', err);
			}
		});

		// ------------------------------------------------------------------
		// STRIKETHROUGH FIX â€“ safety net on save
		//
		// Convert any <s> tags that slipped through (e.g. pasted content,
		// programmatic insertion) before the content leaves the editor.
		// This covers autosave, the code dialog, preview, and form sync.
		// ------------------------------------------------------------------
		editor.on('BeforeGetContent', function (e) {
			try {
				if (e.format === 'html' || !e.format) {
					if (typeof e.content === 'string') {
						if (e.content.indexOf('<s') !== -1) {
							e.content = convertSToSpan(e.content);
						}
						// Convert <em> to <i> (user preference: italic = <i> not <em>)
						if (e.content.indexOf('<em') !== -1) {
							e.content = e.content.replace(/<em(\s[^>]*)?\/?>/gi, '<i$1>').replace(/<\/em>/gi, '</i>');
						}
					}
				}
			} catch (err) {
				console.warn('hugerte e107: BeforeGetContent conversion failed', err);
			}
		});


		// Synchronous same-origin AJAX for the bbcode insert dialog.
		// Uses raw XMLHttpRequest (no jQuery dependency).
		function toHtml(bbc) {
			try {
				var xhr = new XMLHttpRequest();
				xhr.open('POST', url + '/parser.php', false);
				xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
				xhr.send('content=' + encodeURIComponent(bbc) + '&mode=tohtml');
				if (xhr.status !== 200) {
					console.error('hugerte e107: toHtml - HTTP ' + xhr.status + ' ' + xhr.statusText);
					return '';
				}
				var resp = xhr.responseText;
				console.log('hugerte e107: toHtml response [' + resp.length + ' chars]:', resp.substring(0, 300));
				return resp;
			} catch (err) {
				console.error('hugerte e107: toHtml - XHR failed', err);
				return '';
			}
		}

		// ------------------------------------------------------------------
		// Custom icons (SVG paths adapted from trumbowyg editor)
		// ------------------------------------------------------------------
		editor.ui.registry.addIcon('e107-image',
			'<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M5,15.74 L8.27,12.47 C8.56,12.18 9.04,12.18 9.33,12.47 L11.9,15.04 L16,10.94 C16.3,10.64 16.77,10.64 17.06,10.94 L19,12.87 L19,5 L5,5 L5,15.74 Z M5,17.86 L5,19 L7.94,19 L10.84,16.1 L8.8,14.06 L5,17.86 Z M19,14.99 L16.53,12.53 L10.06,19 L19,19 L19,14.99 Z M4,3 L20,3 C20.55,3 21,3.45 21,4 L21,20 C21,20.55 20.55,21 20,21 L4,21 C3.45,21 3,20.55 3,20 L3,4 C3,3.45 3.45,3 4,3 Z M10,11 C11.1,11 12,10.1 12,9 C12,7.9 11.1,7 10,7 C8.9,7 8,7.9 8,9 C8,10.1 8.9,11 10,11 Z"/></svg>');
		editor.ui.registry.addIcon('e107-video',
			'<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M4,3 L20,3 C20.55,3 21,3.45 21,4 L21,20 C21,20.55 20.55,21 20,21 L4,21 C3.45,21 3,20.55 3,20 L3,4 C3,3.45 3.45,3 4,3 Z M5,5 L5,19 L19,19 L19,5 L5,5 Z M9.79,7.56 L15.43,11.59 C15.66,11.75 15.71,12.07 15.55,12.29 C15.51,12.34 15.48,12.37 15.43,12.41 L9.79,16.44 C9.57,16.6 9.25,16.54 9.09,16.32 C9.03,16.23 9,16.13 9,16.03 L9,7.97 C9,7.7 9.22,7.47 9.5,7.47 C9.6,7.47 9.71,7.5 9.79,7.56 Z"/></svg>');
		editor.ui.registry.addIcon('e107-glyph',
			'<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M15,18 L19,18 L20,16 L20,20 L14,20 L14,16.66 C14.51,16.44 14.98,16.14 15.41,15.77 C15.83,15.39 16.2,14.96 16.52,14.46 C16.83,13.97 17.07,13.43 17.23,12.84 C17.41,12.26 17.5,11.65 17.5,11 C17.5,10.17 17.35,9.39 17.06,8.66 C16.78,7.93 16.39,7.29 15.89,6.76 C15.39,6.22 14.81,5.8 14.14,5.48 C13.47,5.18 12.76,5.03 12,5.03 C11.24,5.03 10.53,5.18 9.86,5.48 C9.19,5.79 8.61,6.22 8.11,6.76 C7.61,7.29 7.22,7.93 6.94,8.66 C6.65,9.39 6.5,10.17 6.5,11 C6.5,11.65 6.59,12.26 6.77,12.84 C6.93,13.43 7.17,13.97 7.48,14.46 C7.8,14.96 8.17,15.39 8.59,15.77 C9.02,16.14 9.49,16.44 10,16.66 L10,20 L4,20 L4,16 L5,18 L9,18 L9,17.48 C8.27,17.22 7.6,16.88 6.98,16.45 C6.37,16.02 5.84,15.52 5.4,14.95 C4.96,14.39 4.61,13.78 4.38,13.11 C4.12,12.44 4,11.74 4,11 C4,10.03 4.21,9.13 4.63,8.28 C5.04,7.43 5.61,6.68 6.34,6.05 C7.06,5.41 7.91,4.91 8.89,4.55 C9.86,4.18 10.9,4 12,4 C13.1,4 14.14,4.18 15.11,4.55 C16.09,4.91 16.94,5.41 17.66,6.05 C18.39,6.68 18.96,7.43 19.38,8.28 C19.79,9.13 20,10.03 20,11 C20,11.74 19.88,12.44 19.63,13.11 C19.39,13.78 19.04,14.39 18.6,14.95 C18.16,15.52 17.63,16.02 17.02,16.45 C16.4,16.88 15.73,17.22 15,17.48 L15,18 Z"/></svg>');
		editor.ui.registry.addIcon('e107-bbcode',
			'<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M7 4H5v16h2v-2H6V6h1V4zm10 0h2v16h-2v-2h1V6h-1V4zM9 9h6v2H9V9zm0 4h6v2H9v-2z"/></svg>');

		// ------------------------------------------------------------------
		// Robust insertion of media HTML into the editor
		// ------------------------------------------------------------------
		function prepareHtml(html) {
			var out = String(html);
			// 1) Normalize: single-quoted attrs â†’ double-quoted,
			//    strip trailing whitespace before '>'.
			//    (e107 toGlyph() emits <i class='fab fa-x' ></i>)
			out = out.replace(/<(\w+)([^>]*)>/g, function (m, tag, attrs) {
				attrs = attrs.replace(/'([^']*)'/g, '"$1"').replace(/\s+$/, '');
				return '<' + tag + attrs + '>';
			});
			// 2) Convert <i> â†’ <span>.
			//    TinyMCE 6/7 schema aliases <i> to <em> during parse,
			//    so the FA glyph tag gets silently renamed.  <span> is
			//    not aliased and FA CSS works identically with it.
			out = out.replace(/<i(\s[^>]*)?\/?>/gi, '<span$1>');
			out = out.replace(/<\/i>/gi, '</span>');
			// 3) Make empty inline elements non-empty by putting a
			//    U+200B (zero-width space) inside them.
			//    Ja() in the insertContent pipeline strips U+FEFF but
			//    NOT U+200B, so the element survives getContent().
			//    U+200B is invisible (zero-width) â€“ no visual impact.
			out = out.replace(/<(\w+)(\s[^>]*)?>\s*<\/\1>/gi, function (m, tag, attrs) {
				if (/^(span|em|strong|b|u|sub|sup|a)$/i.test(tag)) {
					return '<' + tag + (attrs || '') + '>\u200B</' + tag + '>';
				}
				return m;
			});
			return out;
		}

		// Make sure the editor has a valid selection inside the body.
		// Returns 'existing' or 'created'.
		function ensureSelection(ed) {
			var body = ed.getBody();
			var doc = ed.getDoc();
			var rng = null;
			try { rng = ed.selection.getRng(); } catch (err) { rng = null; }

			if (!rng || !rng.startContainer || !body.contains(rng.startContainer)) {
				// No valid range: put the caret at the end of the last
				// block-level element (not at the end of <body> itself).
				var last = body.lastElementChild;
				while (last && !ed.dom.isBlock(last)) {
					last = last.previousElementSibling;
				}
				rng = doc.createRange();
				if (last) {
					rng.selectNodeContents(last);
				} else {
					rng.selectNodeContents(body);
				}
				rng.collapse(false);
				ed.selection.setRng(rng);
				return 'created';
			}
			return 'existing';
		}

		// Fallback: insert the HTML directly into the editor's iframe DOM.
		// Bypasses the model-based insertContent pipeline entirely.
		function domInsertFallback(ed, html) {
			var doc = ed.getDoc();
			var body = ed.getBody();

			var sel = doc.getSelection();
			var rng = (sel && sel.rangeCount > 0) ? sel.getRangeAt(0) : null;
			if (!rng || !rng.startContainer || !body.contains(rng.startContainer)) {
				var last = body.lastElementChild;
				while (last && !ed.dom.isBlock(last)) {
					last = last.previousElementSibling;
				}
				rng = doc.createRange();
				if (last) {
					rng.selectNodeContents(last);
				} else {
					rng.selectNodeContents(body);
				}
				rng.collapse(false);
			}
			// Caret sitting directly in <body> -> move into last block
			if (rng.startContainer === body) {
				var lastB = body.lastElementChild;
				while (lastB && !ed.dom.isBlock(lastB)) {
					lastB = lastB.previousElementSibling;
				}
				if (lastB) {
					rng = doc.createRange();
					rng.selectNodeContents(lastB);
					rng.collapse(false);
				}
			}

			var tmp = doc.createElement('div');
			tmp.innerHTML = html;
			var frag = doc.createDocumentFragment();
			var node;
			while ((node = tmp.firstChild)) frag.appendChild(node);
			rng.insertNode(frag);

			// Move caret to the end of the body so the user can keep typing
			var endRng = doc.createRange();
			endRng.selectNodeContents(body);
			endRng.collapse(false);
			sel = doc.getSelection();
			sel.removeAllRanges();
			sel.addRange(endRng);

			ed.nodeChanged();
			try { ed.fire('change'); } catch (err) {}
			try { ed.undoManager.add(); } catch (err) {}
		}

		function insertMediaHtml(ed, html) {
			var prepared = prepareHtml(html);
			var cls = (prepared.match(/class="([^"]+)"/) || [null, ''])[1];
			var selState = ensureSelection(ed);

			// --- Attempt 1: standard model-based insertContent ---
			// No need for {preserve_zwsp: true}: we use \u200B which
			// Ja() does NOT strip (it only strips \uFEFF).
			try {
				ed.insertContent(prepared);
			} catch (err) {
				console.warn('hugerte e107: insertContent threw, using DOM fallback', err);
			}

			var content = '';
			try { content = ed.getContent(); } catch (err) {}

			if (cls && content.indexOf(cls) !== -1) {
				console.log('hugerte e107: OK inserted via insertContent, editor=' + ed.id +
					', sel=' + selState + ', len=' + content.length);
				return;
			}

			// --- Attempt 2: direct DOM insertion (bypasses the model) ---
			console.log('hugerte e107: insertContent dropped content, DOM fallback. editor=' + ed.id +
				', sel=' + selState +
				', isEditable=' + (function () { try { return ed.selection.isEditable(); } catch (e) { return 'n/a'; } })() +
				', prepared=[' + prepared + ']');
			try {
				domInsertFallback(ed, prepared);
			} catch (err) {
				console.error('hugerte e107: DOM fallback failed', err);
				return;
			}
			try {
				content = ed.getContent();
				console.log('hugerte e107: FINAL verify, editor=' + ed.id +
					', hasClass=' + (cls ? content.indexOf(cls) !== -1 : 'n/a') +
					', len=' + content.length);
			} catch (err) {
				console.error('hugerte e107: final verify failed', err);
			}
		}

		// ------------------------------------------------------------------
		// postMessage listener: receives HTML from the media-manager iframe
		// and inserts it into THIS editor instance.
		// ------------------------------------------------------------------
		function onMediaMessage(e) {
			if (e.origin !== window.location.origin) return;
			if (!e.data || typeof e.data.type !== 'string') return;

			// Only the editor that opened the dialog handles the message.
			// Multiple editors on the page each register this listener,
			// so we gate on the editor ID stored when the dialog opened.
			if (window.__hugerte_media_editor !== editor.id) return;

			if (e.data.type === 'hugerte-media-insert') {
				console.log('hugerte e107: media insert -> editor=' + editor.id +
					', raw=[' + e.data.html + ']');

				// Close the dialog FIRST so the editor can regain focus.
				// (insertContent while the modal holds focus can no-op.)
				editor.windowManager.close();
				window.__hugerte_media_editor = null;

				setTimeout(function () {
					try { editor.focus(); } catch (err) {}
					insertMediaHtml(editor, e.data.html);
				}, 150);

			} else if (e.data.type === 'hugerte-media-close') {
				editor.windowManager.close();
				window.__hugerte_media_editor = null;
			}
		}
		window.addEventListener('message', onMediaMessage);

		// ------------------------------------------------------------------
		// Media Manager buttons
		// ------------------------------------------------------------------
		function openMedia(bbc) {
			window.__hugerte_media_editor = editor.id;

			// The 'width', 'height', and 'classes' options in
			// windowManager.open() are IGNORED by this version of
			// HugeRTE - sizing is handled entirely by the CSS in
			// e_header.php (.hugerte-media-dialog rules).
			var dlg = editor.windowManager.open({
				title: 'Media Manager',
				body: {
					type: 'panel',
					items: [{
						type: 'htmlpanel',
						// Host div is a flex column that fills the dialog body.
						// The iframe flexes to fill the host div.
						html: '<div class="hugerte-media-host"><iframe src="' + url + '/mediamanager.php?' + bbc +
							  '" style="width:100%;height:100%;border:none;display:block" frameborder="0"></iframe></div>'
					}]
				}
			});

			// Tag the dialog with our CSS class.
			// The 'classes' option is ignored, so we find the dialog
			// via the host div and add the class directly.
			// Dialog DOM renders asynchronously - wait a tick.
			setTimeout(function () {
				try {
					var host = document.querySelector('.hugerte-media-host');
					if (host) {
						var dlgEl = host.closest('.tox-dialog');
						if (dlgEl && !dlgEl.classList.contains('hugerte-media-dialog')) {
							dlgEl.classList.add('hugerte-media-dialog');
						}
					}
				} catch (err) { /* ignore */ }
			}, 100);
		}


		editor.ui.registry.addButton('e107-image', {
			icon: 'e107-image',
			tooltip: 'Insert Media-Manager Image',
			onAction: function () { openMedia('image'); }
		});

		editor.ui.registry.addButton('e107-video', {
			icon: 'e107-video',
			tooltip: 'Insert Media-Manager Video',
			onAction: function () { openMedia('video'); }
		});

		editor.ui.registry.addButton('e107-glyph', {
			icon: 'e107-glyph',
			tooltip: 'Insert Media-Manager Glyph',
			onAction: function () { openMedia('glyph'); }
		});

		// ------------------------------------------------------------------
		// e107 BBcode toggle button
		//
		// Click once:  hides the WYSIWYG editor, shows a plain BBCode
		//              editing panel (quick-insert buttons + textarea).
		// Click again: converts BBCode to HTML, restores the editor.
		//
		// The BBCode panel is a plain DOM element - NOT a TinyMCE dialog -
		// so native JavaScript event handling works reliably.
		// ------------------------------------------------------------------
		var bbPanel    = null;
		var bbTa       = null;
		var bbSelStart = 0;
		var bbSelEnd   = 0;

		function bbCacheSel() {
			if (!bbTa || !bbTa.isConnected) return;
			try { bbSelStart = bbTa.selectionStart; bbSelEnd = bbTa.selectionEnd; } catch (e) {}
		}

		function bbWrap(open, close) {
			if (!bbTa || !bbTa.isConnected) return;
			var s = bbSelStart, e = bbSelEnd;
			var sel = bbTa.value.substring(s, e);
			bbTa.value = bbTa.value.substring(0, s) + open + sel + close + bbTa.value.substring(e);
			bbTa.focus();
			bbTa.selectionStart = s + open.length;
			bbTa.selectionEnd   = s + open.length + sel.length;
			bbSelStart = bbTa.selectionStart;
			bbSelEnd   = bbTa.selectionEnd;
		}

		function bbInsert(text) {
			if (!bbTa || !bbTa.isConnected) return;
			var s = bbSelStart, e = bbSelEnd;
			bbTa.value = bbTa.value.substring(0, s) + text + bbTa.value.substring(e);
			bbTa.focus();
			bbTa.selectionStart = s + text.length;
			bbTa.selectionEnd   = s + text.length;
			bbSelStart = bbTa.selectionStart;
			bbSelEnd   = bbTa.selectionEnd;
		}

		function bbToBbcode(html) {
			var xhr = new XMLHttpRequest();
			xhr.open('POST', url + '/parser.php', false);
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
			xhr.send('mode=tobbcode&content=' + encodeURIComponent(html));
			return (xhr.status === 200) ? xhr.responseText : '';
		}

		function bbToHtml(bbcode) {
			var xhr = new XMLHttpRequest();
			xhr.open('POST', url + '/parser.php', false);
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
			xhr.send('mode=tohtml&content=' + encodeURIComponent(bbcode));
			return (xhr.status === 200) ? xhr.responseText : '';
		}

		function bbShow() {
			var html = editor.getContent();
			var bbcode;
			try {
				bbcode = bbToBbcode(html);
			} catch (err) {
				console.error('hugerte e107: BBcode switch - HTML to BBcode failed', err);
				return;
			}
			if (!bbcode) bbcode = html;

			var container = editor.getContainer();
			container.style.display = 'none';

			var panel = document.createElement('div');
			panel.className = 'e107-bbcode-panel';
			panel.style.cssText = 'border:1px solid #dee2e6;border-radius:6px;margin:8px 0;padding:10px;background:#f8f9fa;';

			var bar = document.createElement('div');
			bar.style.cssText = 'margin-bottom:8px;display:flex;flex-wrap:wrap;gap:4px;align-items:center;';

			var defs = [
				{ l: 'B',       t: 'Bold: [b]...[/b]',                  o: '[b]',            c: '[/b]' },
				{ l: 'I',       t: 'Italic: [i]...[/i]',                o: '[i]',            c: '[/i]' },
				{ l: 'U',       t: 'Underline: [u]...[/u]',             o: '[u]',            c: '[/u]' },
				{ l: 'Link',    t: 'Link: [link=url]text[/link]',       o: '[link=http://]', c: '[/link]' },
				{ l: 'Img',     t: 'Image: [img]url[/img]',             o: '[img]http://',   c: '[/img]' },
				{ l: 'Quote',   t: 'Quote: [quote]...[/quote]',         o: '[quote]',        c: '[/quote]' },
				{ l: 'List',    t: 'Bullet list: [list]...[/list]',     o: '[list]',         c: '[/list]' },
				{ l: 'Color',   t: 'Color: [color=red]...[/color]',     o: '[color=red]',    c: '[/color]' },
				{ l: 'Size',    t: 'Font size: [size=16]...[/size]',    o: '[size=16]',      c: '[/size]' },
				{ l: 'Glyph',   t: 'Glyph: [glyph]fab.fa-name[/glyph]', o: '[glyph]fab.',    c: '[/glyph]' },
				{ l: 'Code',    t: 'Code: [code]...[/code]',            o: '[code]',         c: '[/code]' },
				{ l: 'Spoiler', t: 'Spoiler: [spoiler]...[/spoiler]',   o: '[spoiler]',      c: '[/spoiler]' },
				{ l: 'Table',   t: 'Table: [table]...[/table]',         o: '[table]',        c: '[/table]' },
				{ l: 'BR',      t: 'Line break: [br]',                  i: '[br]' }
			];

			for (var i = 0; i < defs.length; i++) {
				(function (d) {
					var btn = document.createElement('button');
					btn.type = 'button';
					btn.textContent = d.l;
					btn.title = d.t;
					btn.className = 'btn btn-sm btn-secondary';
					btn.addEventListener('click', function (ev) {
						ev.preventDefault();
						ev.stopPropagation();
						bbCacheSel();
						if (d.i) { bbInsert(d.i); } else { bbWrap(d.o, d.c); }
					});
					bar.appendChild(btn);
				})(defs[i]);
			}

			panel.appendChild(bar);

			bbTa = document.createElement('textarea');
			bbTa.className = 'form-control';
			bbTa.rows = 20;
			bbTa.style.cssText = 'width:100%;font-family:Consolas,Monaco,monospace;font-size:14px;';
			bbTa.value = bbcode;
			bbTa.placeholder = 'Type or paste e107 BBCode here...';
			bbTa.addEventListener('keyup',   bbCacheSel);
			bbTa.addEventListener('mouseup', bbCacheSel);
			bbTa.addEventListener('select',  bbCacheSel);
			bbTa.addEventListener('click',   bbCacheSel);
			panel.appendChild(bbTa);

			var backBtn = document.createElement('button');
			backBtn.type = 'button';
			backBtn.textContent = '\u2190 Switch back to WYSIWYG';
			backBtn.className = 'btn btn-primary btn-sm';
			backBtn.style.cssText = 'margin-top:8px;';
			backBtn.addEventListener('click', function (ev) {
				ev.preventDefault();
				bbHide();
			});
			panel.appendChild(backBtn);

			container.parentNode.insertBefore(panel, container.nextSibling);
			bbPanel = panel;
			bbCacheSel();
			bbTa.focus();
		}

		function bbHide() {
			if (!bbTa || !bbPanel) return;

			var html;
			try {
				html = bbToHtml(bbTa.value);
			} catch (err) {
				console.error('hugerte e107: BBcode switch - BBcode to HTML failed', err);
				return;
			}
			if (!html || !html.trim()) html = '<p>&nbsp;</p>';

			bbPanel.remove();
			bbPanel = null;
			bbTa = null;

			var container = editor.getContainer();
			container.style.display = '';
			editor.setContent(html);
			editor.focus();
		}

		// Save correctly if form is submitted while BBcode panel is open,
		// and ALWAYS convert <em> to <i> (user preference: italic = <i>).
		// SaveContent fires AFTER getContent() and the modified e.content
		// is directly written to the form textarea - most reliable hook.
		editor.on('SaveContent', function (e) {
			try {
				if (bbPanel && bbPanel.isConnected && bbTa && bbTa.isConnected) {
					var html = bbToHtml(bbTa.value);
					if (html && html.trim()) {
						e.content = html;
					}
				}
				// Convert <em> to <i> in the final content
				if (typeof e.content === 'string' && e.content.indexOf('<em') !== -1) {
					e.content = e.content.replace(/<em(\s[^>]*)?\/?>/gi, '<i$1>').replace(/<\/em>/gi, '</i>');
				}
			} catch (err) {
				console.error('hugerte e107: SaveContent conversion failed', err);
			}
		});

		editor.ui.registry.addButton('e107-bbcode', {
			icon: 'e107-bbcode',
			tooltip: 'Edit as BBCode / Switch to WYSIWYG',
			onAction: function () {
				if (bbPanel && bbPanel.isConnected) {
					bbHide();
				} else {
					bbShow();
				}
			}
		});
	});

})();

