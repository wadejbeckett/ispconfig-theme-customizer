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
 * $logo_url     the [branding] logo_url override, if any ('' when unset)
 *
 * PRECEDENCE MUST MIRROR THE THEME'S READER (themes/clarity/brand.php:198-205):
 * a valid logo_url wins over an uploaded custom_logo, and an INVALID logo_url
 * falls through to custom_logo. Previewing only custom_logo made this page lie —
 * an admin who set logo_url saw "No custom logo set. The theme shows its own
 * default." while the panel was in fact displaying their referenced image. The
 * two must be resolved the same way or the preview actively misinforms.
 */
function customizer_logo_preview_html($logo, $no_logo_text, $logo_url = '') {
    $logo     = (string)$logo;
    $logo_url = (string)$logo_url;

    //* Same anchored pattern the reader uses — root-relative path or https URL,
    //* with (?!/) rejecting protocol-relative "//host" (a remote URL in disguise).
    //* Reader/writer/preview parity is the point: three copies that agree.
    //* /D matters: without it PCRE's `$` also matches before a final newline, so
    //* "/img/logo.png\n" would validate here while the theme's reader (which does
    //* carry /D) rejects it — the preview would then show an image the panel does
    //* not render, which is the exact class of contradiction this function exists
    //* to remove. All three copies of this pattern must agree.
    if($logo_url !== '' && preg_match('#^(https://[^\s"\'<>()\\\\]+|/(?!/)[^\s"\'<>()\\\\]+)$#D', $logo_url)) {
        return '<img src="' . htmlspecialchars($logo_url, ENT_QUOTES) . '" alt="" style="max-height:48px;max-width:220px;background:#01243D;padding:6px 12px;border-radius:4px" />';
    }
    //* only ever render a value that is a real image data-URI (defence-in-depth
    //* vs a tampered column) — the src is echoed unescaped into the page
    if($logo !== '' && preg_match('#^data:image/[a-z0-9.+-]+;base64,[A-Za-z0-9+/=]+$#i', $logo)) {
        return '<img src="' . $logo . '" alt="" style="max-height:48px;max-width:220px;background:#01243D;padding:6px 12px;border-radius:4px" />';
    }
    return '<em>' . $no_logo_text . '</em>';
}
