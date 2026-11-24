<?php
/*
 * e107 website system
 *
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 * HugeRTE WYSIWYG editor plugin - footer hook.
 *
 * Two contexts:
 *   1. Main page (newspost, cpage, …) – loads hugerte.min.js + wysiwyg config,
 *      wires up jQuery helpers (mailout buttons, tab-add, CSS override).
 *   2. Media-manager iframe (image.php?iframe=1) – overrides
 *      e107.mediaManager.eDialogSave so the Save button inserts into the
 *      parent TinyMCE editor instead of looking for a data-target textarea
 *      (which does not exist in WYSIWYG mode).
 */

if (!defined('e107_INIT')) { exit; }

$pref = e107::getPref();

// Gate: pass when hugerte is the active editor + user can post HTML,
// OR on the admin config page,
// OR when inside the media-manager iframe dialog (e_PAGE=image.php with iframe=1)
$isMediaDialog = (e_PAGE == 'image.php' && isset($_GET['iframe']) && check_class($pref['post_html']));

if ((e107::wysiwyg(null, true) === 'hugerte' && check_class($pref['post_html']))
    || strpos(e_SELF, "hugerte/admin_config.php")
    || $isMediaDialog)
{
    // ── Main page: load editor JS ────────────────────────────────────
    if (!$isMediaDialog)
    {
        // Local dist (upgraded by replacing the hugerte/ folder).
        e107::js('footer', e_PLUGIN_ABS.'hugerte/hugerte/hugerte.min.js');

        $mceScript = e_PLUGIN.'hugerte/wysiwyg.php';

        if (defined('e_TINYMCE_TEMPLATE'))
        {
            $mceScript .= '?config='.e_TINYMCE_TEMPLATE.'&other=';
        }

        e107::js('footer', $mceScript, 'jquery', 5);

        // Add to e107_config.php to view hidden content when the editor not saving correctly
        if (deftrue('e_HUGERTE_DEBUG'))
        {
            e107::js('footer-inline', '
                window.onload = function () {
                    $("textarea.e-wysiwyg").css("display","block");
                    $("textarea.e-wysiwyg").css("visibility","inherit");
                }
            ');
        }
    }

    // ── Admin mailout buttons (main page only) ───────────────────────
    if (ADMIN && !$isMediaDialog)
    {
        $insert = "$('#'+id).after('<div class=\"btn-group\" style=\"margin-top:5px\">";

        if (e_PAGE == 'mailout.php')
        {
            $insert .= "<a href=\"#\" class=\"btn btn-default btn-sm btn-mini tinyInsert\" data-value=\"|USERNAME|\" >".LAN_MAILOUT_16."<\/a>";
            $insert .= "<a href=\"#\" class=\"btn btn-default btn-sm btn-mini tinyInsert\"     data-value=\"|DISPLAYNAME|\" >".LAN_MAILOUT_14."<\/a>";
            $insert .= "<a href=\"#\" class=\"btn btn-default btn-sm btn-mini tinyInsert\"     data-value=\"|SIGNUP_LINK|\" >".LAN_MAILOUT_17."<\/a>";
            $insert .= "<a href=\"#\" class=\"btn btn-default btn-sm btn-mini tinyInsert\"     data-value=\"|USERID|\" >".LAN_MAILOUT_18."<\/a>";
        }

        $insert .= "</div>');";

        define("SWITCH_TO_BB", $insert);
    }
    else
    {
        define("SWITCH_TO_BB", "");
    }

    // ── Context-specific inline JS ───────────────────────────────────
    if ($isMediaDialog)
    {
        /*  Media-manager iframe context
        *
        *  The e107 dialog page (image.php) loads mediaManager.js which
        *  binds  $(context).on('click', '.e-dialog-save', function(){
        *      e107.mediaManager.eDialogSave(this);
        *  });
        *
        *  The native eDialogSave reads data-target from the button and
        *  tries to write into a textarea in the parent page. In WYSIWYG
        *  mode there is no textarea – the content lives in the TinyMCE
        *  editor. So we replace eDialogSave with a version that:
        *    1. reads #bbcode_holder (updated by eMediaSelectProcess)
        *    2. POSTs to parser.php (mode=tohtml)
        *    3. calls top.hugerte.activeEditor.execCommand('mceInsertRawHTML')
        *    4. closes the TinyMCE dialog
        */
        $parserUrl = e_PLUGIN_ABS.'hugerte/plugins/e107/parser.php';

        e107::js('footer-inline', "
(function () {
    /* Kill the body scrollbar inside the iframe – the page content is
       taller than the iframe viewport and the browser shows a vertical
       scrollbar on the right edge of the modal.  We hide it and let
       the inner content containers handle their own scrolling. */
    var st = document.createElement('style');
    st.textContent = 'html { height: 100% !important; overflow: hidden !important; } body { height: 100% !important; overflow: hidden !important; }';
    document.head.appendChild(st);

    /* Show the save/cancel buttons (hidden by default in image.php) */
    var btnBar = document.getElementById('media-manager-submit-buttons');
    if (btnBar) { btnBar.style.display = 'block'; }

    var origin = window.location.origin;

    /* ── Save button ────────────────────────────────────────────────
     * Override e107.mediaManager.eDialogSave. The native version looks
     * for a data-target textarea in the parent page – which doesn't
     * exist in WYSIWYG mode. We read #bbcode_holder (updated by
     * eMediaSelectProcess), convert via parser.php, then send the HTML
     * to the parent window via postMessage. The plugin.js message
     * handler inserts it into the correct editor instance.
     */
    e107.mediaManager.eDialogSave = function (that) {
        var holder = document.getElementById('bbcode_holder');
        var val    = holder ? holder.value : '';

        if (!val) {
            console.log('hugerte e_footer: bbcode_holder is empty');
            return;
        }

        console.log('hugerte e_footer: saving bbcode ->', val);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '$parserUrl', false);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('content=' + encodeURIComponent(val) + '&mode=tohtml');

        var html = xhr.responseText;
        if (!html) {
            console.warn('hugerte e_footer: parser returned empty response');
            return;
        }

        console.log('hugerte e_footer: posting html to parent ->', html);

        window.top.postMessage({
            type: 'hugerte-media-insert',
            html: html
        }, origin);
    };

    /* ── Cancel button ────────────────────────────────────────────── */
    document.addEventListener('click', function (e) {
        var el = e.target;
        while (el && el !== document) {
            if (el.classList && el.classList.contains('e-dialog-cancel')) {
                console.log('hugerte e_footer: cancel clicked, closing dialog');
                window.top.postMessage({
                    type: 'hugerte-media-close'
                }, origin);
                e.preventDefault();
                e.stopImmediatePropagation();
                return;
            }
            el = el.parentElement;
        }
    }, true);

})()
", 'jquery', 1);
    }
    else
    {
        /*  Main-page context
        *
        *  Loads the TinyMCE CSS override, wires up jQuery helpers for
        *  mailout insert buttons, tab-add, and the editor initialisation
        *  helpers that the old tinymce4 plugin used.
        */
        e107::js('footer-inline', "

<!---- HugeRTE Footer Inline Code -->

$(function() {

    // Override TinyMCE 6/7 dialog sizing (mirrors e_header.php CSS as a safety net):
    // native dialogs keep natural size, media-manager dialog is large + responsive.
    var st = document.createElement('style');
    st.textContent = '.tox .tox-dialog { max-width: min(500px, 95vw) !important; max-height: 90vh !important; } .tox .tox-dialog.hugerte-media-dialog { width: min(85vw, 1200px) !important; max-width: min(85vw, 1200px) !important; height: min(60vh, 900px) !important; max-height: min(60vh, 900px) !important; } .hugerte-media-dialog .tox-dialog__body { overflow: hidden; min-height: 0; } .hugerte-media-dialog .tox-dialog__body-content { max-height: none !important; overflow: hidden !important; padding: 0 !important; } .hugerte-media-dialog .tox-dialog__body-content > * { margin: 0 !important; flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; overflow: hidden; } .hugerte-media-dialog .tox-form__group { flex: 1 1 auto !important; min-height: 0; display: flex !important; flex-direction: column; overflow: hidden !important; } .hugerte-media-dialog .hugerte-media-host { flex: 1 1 auto; min-height: 0; width: 100%; display: flex; flex-direction: column; overflow: hidden; } .hugerte-media-dialog .hugerte-media-host iframe { flex: 1 1 auto; min-height: 0; width: 100%; display: block; border: 0; } @media (max-width: 768px) { .tox .tox-dialog.hugerte-media-dialog { width: 100vw !important; max-width: 100vw !important; height: 100vh !important; max-height: 100vh !important; } }';
    document.head.appendChild(st);

    // Italic fix: DIRECTLY override getContent() on each editor.
    // This wraps the actual method - no events, no TinyMCE internals.
    // Every call to getContent() (Source Code button, save(), autosave)
    // goes through this wrapper and gets <em> converted to <i>.
    (function () {
        function emToI(html) {
            if (typeof html !== 'string' || html.indexOf('<em') === -1) return html;
            return html.replace(/<em(\s[^>]*)?\/?>/gi, '<i\$1>').replace(/<\/em>/gi, '</i>');
        }
        function patchEditor(editor) {
            if (editor.__hugerteItalicPatched) return;
            var origGetContent = editor.getContent;
            editor.getContent = function (settings) {
                var content = origGetContent.call(this, settings);
                return emToI(content);
            };
            editor.__hugerteItalicPatched = true;
            console.log('[hugerte] getContent() patched for italic fix: ' + editor.id);
        }
        function initFix() {
            if (window.hugerte && hugerte.EditorManager && hugerte.EditorManager.editors.length) {
                hugerte.EditorManager.editors.forEach(patchEditor);
                hugerte.EditorManager.on('AddEditor', function (e) {
                    // Small delay to ensure the editor is fully initialized
                    setTimeout(function () { patchEditor(e.editor); }, 100);
                });
            } else { setTimeout(initFix, 200); }
        }
        initFix();
    })();

    // ULTIMATE safety net: form-level submit handler.
    // Runs in bubble phase (AFTER TinyMCE save() synced the textarea).
    // Directly converts <em> to <i> in all textarea values.
    // This bypasses TinyMCE's event system entirely.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.querySelectorAll) return;
        var tas = form.querySelectorAll('textarea');
        for (var i = 0; i < tas.length; i++) {
            var ta = tas[i];
            if (ta.value && ta.value.indexOf('<em') !== -1) {
                ta.value = ta.value.replace(/<em(\s[^>]*)?\/?>/gi, '<i$1>').replace(/<\/em>/gi, '</i>');
            }
        }
    }, false);

    $('.e-wysiwyg').each(function() {

        var id = $(this).attr('id'); // 'e-wysiwyg';
        ".SWITCH_TO_BB."
        $('#bbcode-panel-'+id+'--preview').hide();

    });


    $('#media-manager-submit-buttons').show();

    $('.tinyInsert').click(function() {

        var val = $(this).attr('data-value');
        top.hugerte.activeEditor.execCommand('mceInsertContent',0,val);
        return false;
    });


    // When new tab is added - convert textarea to editor.
    $('.e-tabs-add').on('click',function(){

        alert('New Page Added'); // added for delay - quick and dirty work-around. XXX fixme

        var idt = $(this).attr('data-target'); // eg. news-body
        var ct  = parseInt($('#e-tab-count').val());
        var id  = idt + '-' + ct;
        $('#bbcode-panel-'+id+'--preview').hide();
        ".SWITCH_TO_BB."
        top.hugerte.activeEditor.execCommand('mceAddControl', false, id);
    });


});

", 'jquery', 1);
    }

}
