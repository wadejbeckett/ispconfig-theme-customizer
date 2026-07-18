<?php
/**
 * ispconfig-customizer — shared logo-preview renderer.
 * Copyright (c) 2026 Wade Beckett. MIT License — see ../../LICENSE.
 *
 * Both the settings page (customizer_edit.php) and the upload response
 * (logo_upload.php) show a thumbnail of the current logo. Keeping the markup
 * and the data-URI guard in one place means they can never drift apart.
 *
 * $logo         the stored value (a data:image/...;base64,... URI, or '')
 * $no_logo_text already-localised text to show when no valid logo is set
 */
function customizer_logo_preview_html($logo, $no_logo_text) {
    $logo = (string)$logo;
    //* only ever render a value that is a real image data-URI (defence-in-depth
    //* vs a tampered column) — the src is echoed unescaped into the page
    if($logo !== '' && preg_match('#^data:image/[a-z0-9.+-]+;base64,[A-Za-z0-9+/=]+$#i', $logo)) {
        return '<img src="' . $logo . '" alt="" style="max-height:48px;max-width:220px;background:#01243D;padding:6px 12px;border-radius:4px" />';
    }
    return '<em>' . $no_logo_text . '</em>';
}
