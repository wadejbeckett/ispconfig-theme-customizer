<?php
/**
 * The Branding mockup's sample brand, run through the module's REAL renderers.
 *
 * mockup/branding/render_shipped.py needs two things it must not invent: the
 * markup the five preview slots really carry, and the response
 * customizer/preview.php really returns. Both come from
 * lib/preview.inc.php here, so the rendered page is the page the panel serves
 * rather than a hand-written imitation of it — which is the whole point of
 * shooting the shipped template instead of the mockup.
 *
 * Pure: no database, no session, no HTTP. Run it with
 *   php mockup/branding/sample_previews.php
 * and it prints one JSON object on stdout.
 *
 * The brand is invented (Karoo Hosting) and its colours are deliberately NOT
 * clarity's: a preview painted in the design's own palette proves nothing.
 */

require __DIR__ . '/../../interface/web/customizer/lib/preview.inc.php';

//* The three marks, written as readable SVG rather than pasted as base64 so a
//* reader can see what is being previewed. Byte-for-byte the artwork in
//* mockup/branding/branding.html.
$mark_on_light = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 178 40" width="178" height="40">
<rect x="0" y="4" width="32" height="32" rx="8" fill="#2E7D6B"/>
<path d="M7 27.5 L13.5 17 L18 22.5 L21.5 18.5 L26 27.5 Z" fill="#FFFFFF"/>
<circle cx="22.5" cy="12.5" r="2.6" fill="#FFFFFF" opacity="0.65"/>
<text x="42" y="26" font-family="Inter,\'Segoe UI\',Helvetica,Arial,sans-serif" font-size="18" font-weight="700" letter-spacing="-0.3" fill="#123A34">Karoo</text>
<text x="99" y="26" font-family="Inter,\'Segoe UI\',Helvetica,Arial,sans-serif" font-size="18" font-weight="400" letter-spacing="-0.3" fill="#4E6B64">Hosting</text>
</svg>';

$mark_on_dark = str_replace(array('fill="#123A34">Karoo', 'fill="#4E6B64">Hosting'),
                            array('fill="#FFFFFF">Karoo', 'fill="#A9C7BF">Hosting'),
                            $mark_on_light);

$icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="32" height="32">
<rect width="32" height="32" rx="7" fill="#2E7D6B"/>
<path d="M6 24 L13 11 L18 17.5 L21.5 13 L26 24 Z" fill="#FFFFFF"/>
</svg>';

$uri = function($svg) { return 'data:image/svg+xml;base64,' . base64_encode($svg); };

$custom_logo = $uri($mark_on_light);          // core's own column = the light-background slot
$branding = array(
    'logo_on_dark'     => $uri($mark_on_dark),
    'logo_url'         => '',
    'logo_url_on_dark' => '',
    'favicon'          => $uri($icon),
    'favicon_url'      => '',
    'accent_hex'       => '#2E7D6B',
    'rail_hex'         => '#123A34',
    'rail_hex_light'   => '#E6EEEC',
    'login_bg'         => '#0E2723',
);

/**
 * The module wordbook, read as TEXT and never executed — the rule
 * .github/scripts/lang_check.php follows, and the one mockup/build.py already
 * follows for the tform wordbook.
 *
 * The labels and texts below are what $app->lng() hands preview.php and
 * customizer_edit.php: they live in interface/web/customizer/lib/lang/en.lng,
 * not in the tform wordbook, because logo_upload.php redraws the same previews
 * with no tform at all. Reading them here rather than retyping them is what
 * keeps the rendered legend the SHIPPED wording.
 */
function sample_module_wordbook() {
    $src = file_get_contents(__DIR__ . '/../../interface/web/customizer/lib/lang/en.lng');
    $out = array();
    if(preg_match_all('/\$wb\[\s*\'([^\']+)\'\s*\]\s*=\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*;/', $src, $m, PREG_SET_ORDER)) {
        foreach($m as $hit) $out[$hit[1]] = str_replace(array("\\'", '\\\\'), array("'", '\\'), $hit[2]);
    }
    return $out;
}

$wb = sample_module_wordbook();
$lng = function($key) use ($wb) { return isset($wb[$key]) ? $wb[$key] : ''; };

//* ISPC_THEMES_PATH is what customizer_installed_designs() asks; pointing it at
//* the repository's own themes/ is how this harness gets the real answer (both
//* designs present) instead of a hand-written list. That is also the state the
//* mockup was drawn in, so the "Also used on" strip has something in it.
if(!defined('ISPC_THEMES_PATH')) define('ISPC_THEMES_PATH', __DIR__ . '/../../themes');
$designs = customizer_installed_designs('clarity');

//* preview.php:97 and :99-112, key for key.
$labels = array('nav' => $lng('surface_nav_txt'), 'login' => $lng('surface_login_txt'));
$texts  = array(
    'no_logo'              => $lng('no_logo_set_txt'),
    'fallback_from_dark'   => $lng('logo_fallback_from_dark_txt'),
    'fallback_from_light'  => $lng('logo_fallback_from_light_txt'),
    'no_favicon'           => $lng('no_favicon_set_txt'),
    'favicon_url_wins'     => $lng('favicon_url_wins_txt'),
    'summary_design'       => $lng('summary_design_txt'),
    'summary_marks_none'   => $lng('summary_marks_none_txt'),
    'summary_marks_one'    => $lng('summary_marks_one_txt'),
    'summary_marks'        => $lng('summary_marks_txt'),
    'summary_favicon'      => $lng('summary_favicon_txt'),
    'summary_favicon_none' => $lng('summary_favicon_none_txt'),
);

$payload = customizer_preview_payload($branding, $custom_logo, array(), $designs, $labels, $texts);

echo json_encode(array(
    //* What customizer/preview.php would answer, for the page's own script.
    'payload' => $payload,
    //* The fifteen "?" disclosures, from the ONE list publish_hint_labels()
    //* walks. Handed to render_shipped.py rather than retyped there, so the
    //* harness cannot name a different fifteen from the page.
    'hint_label_keys' => customizer_hint_label_keys(),
    //* What customizer_edit.php would put in the template, for the render. The
    //* five slots come from the payload rather than being rendered twice —
    //* they are the same call, which is the guarantee the module makes.
    'tpl' => array_merge($payload['previews'], array(
        'summary_fact_design'  => $payload['summary']['design'],
        'summary_fact_marks'   => $payload['summary']['marks'],
        'summary_fact_favicon' => $payload['summary']['favicon'],
        'company_name'         => 'Karoo Hosting',
        'accent_hex'           => $branding['accent_hex'],
        'rail_hex'             => $branding['rail_hex'],
        'rail_hex_light'       => $branding['rail_hex_light'],
        'login_bg'             => $branding['login_bg'],
        'logo_url'             => '',
        'logo_url_on_dark'     => '',
        'favicon_url'          => '',
        'custom_login_text'    => 'Support: help@karoo.example',
        'custom_login_link'    => 'https://karoo.example/support',
        'id'                   => '1',
        'field_errors_json'    => '{}',
    )),
), JSON_UNESCAPED_SLASHES);
echo "\n";
