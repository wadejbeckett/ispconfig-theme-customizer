<?php
/**
 * ispconfig-customizer — standalone white-label branding for ISPConfig.
 * https://github.com/wadejbeckett/ispconfig-customizer
 * Copyright (c) 2026 Wade Beckett. MIT License — see LICENSE.
 *
 * Built for ISPConfig (ispconfig.org, BSD-3-Clause). Not affiliated with or
 * endorsed by the ISPConfig project.
 *
 * Logo upload target for the editor's own fetch() uploader (the button handler
 * in templates/customizer_edit.htm). Validates MIME + size, writes a data-URI
 * into sys_ini.custom_logo, and re-renders form.tpl.htm so the response body
 * carries #OKMsg/#errorMsg and a refreshed #used_logo for the caller to lift
 * out with DOMParser.
 *
 * ISPConfig's stock iframe uploader (ispconfig.js submitUploadForm) is
 * deliberately NOT used here — see the template for why — so this endpoint
 * neither expects nor returns a replacement CSRF pair. The token that
 * authorises the POST is minted by the GET branch below, at click time.
 */

require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';
require_once __DIR__ . '/lib/preview.inc.php';

//* admin-only
$app->auth->check_module_permissions('customizer');
$app->auth->check_security_permissions('admin_allow_system_config');
if(!$app->auth->is_admin()) die('Allowed for administrators only.');

//* Token minting for the fetch()-based uploader. ISPConfig's DB session store
//* has no locking (read = SELECT, write = REPLACE, last writer wins), so a
//* single-use CSRF token minted during the page render can be silently erased
//* by any concurrent session-writing request in the page-load burst (capp.php,
//* keepalive, login boot). The template therefore requests a FRESH pair here,
//* alone and immediately before the upload POST, shrinking the race window
//* from minutes to milliseconds. Same-origin JS only: the X-Requested-With
//* header cannot be attached cross-origin without a CORS preflight, and the
//* response is unreadable cross-origin anyway.
if($_SERVER['REQUEST_METHOD'] === 'GET') {
    if(!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
        die('Bad request.');
    }
    $csrf = $app->auth->csrf_token_get('customizer');
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(array('csrf_id' => $csrf['csrf_id'], 'csrf_key' => $csrf['csrf_key']));
    exit;
}

//* A POST body larger than post_max_size arrives with $_POST and $_FILES both
//* empty (only Content-Length survives). The CSRF token then can't be present,
//* so csrf_token_check() would die with a misleading "CSRF blocked" message —
//* detect the overflow first and report it as an oversize file instead.
$post_overflow = ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)
    && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0);

//* reject forged cross-site posts (halts on failure)
if(!$post_overflow) {
    $app->auth->csrf_token_check('POST');
}

$app->uses('tpl');
$app->tpl->newTemplate("form.tpl.htm");
$app->tpl->setInclude('content_tpl', 'templates/customizer_edit.htm');

//* sanitize the session language and fall back to English if that file isn't shipped
$lng = $app->functions->check_language($_SESSION['s']['language']);
$lng_path = '/web/customizer/lib/lang/' . $lng . '_customizer.lng';
if(!file_exists(ISPC_ROOT_PATH . $lng_path)) $lng_path = '/web/customizer/lib/lang/en_customizer.lng';
$app->load_language_file($lng_path);

$msg   = array();
$error = array();

$max_raw = 45000; // bytes of raw image; base64 (~x1.37) must fit the ~64 KB TEXT column
$allowed = array(
    'image/png'  => true,
    'image/jpeg' => true,
    'image/gif'  => true,
    'image/webp' => true,
    // image/svg+xml is accepted via customizer_svg_ok() below, not this map:
    // finfo mislabels prolog-less SVGs (text/xml, text/plain, even text/html),
    // so SVG detection is by strict XML validation instead of MIME string.
);

//* --- SVG screening -------------------------------------------------------
//*
//* An SVG is XML with executable affordances (script elements, event-handler
//* attributes, SMIL animation, foreignObject that embeds arbitrary HTML), so
//* unlike a PNG it cannot simply be believed. The obvious implementation — a
//* regex blocklist over the raw upload bytes — does not actually hold, because
//* the raw bytes are not the document:
//*
//*   - XML identity is (namespace, local name), not spelling. With s bound to
//*     the SVG namespace, <s:script> IS the SVG script element, and /<script/
//*     never sees it. Same for <s:foreignObject>.
//*   - Character references are resolved by the parser, so &#106;avascript:
//*     is a javascript: URL that no raw-byte scan for "javascript:" matches.
//*   - CDATA sections and comments let text masquerade as markup and back.
//*   - An event handler can hide in an attribute VALUE rather than a name,
//*     e.g. SMIL <set attributeName="onload" to="...">, which a
//*     "whitespace then on…=" pattern cannot see.
//*
//* Everything below therefore runs against the PARSED document, where those
//* encodings have already collapsed, and screens on the local name so a
//* namespace prefix is irrelevant. Names are lower-cased before every lookup
//* so case variants cannot change a verdict either.
//*
//* The element rule is an ALLOWLIST: a logo needs shapes, gradients, text and
//* filters, and anything outside that vocabulary is rejected by omission
//* rather than by having to be enumerated. Foreign-namespace elements are
//* tolerated because real editor output is full of them (sodipodi:namedview,
//* inkscape:*, rdf:RDF, dc:*, Adobe XMP) and SVG renderers ignore unknown
//* namespaces — but they still go through the attribute screen, and a second
//* hard denylist rejects executable/embedding local names in ANY namespace.
//*
//* This endpoint is admin-only and every render site is an image context (an
//* <img> tag, or CSS content:url()), where browsers apply SVG secure-static
//* mode, so nothing here is the last line of defence. It is written to be
//* exactly as strict as SECURITY.md says it is.

//* A URL is acceptable only if it stays inside the document or carries its own
//* raster payload. Browsers strip whitespace and C0 controls before deciding a
//* URL's scheme, so normalise the same way before looking at it.
function customizer_svg_url_ok($value) {
    $raw_value = (string)$value;
    $v = html_entity_decode($raw_value, ENT_QUOTES, 'UTF-8');
    //* html_entity_decode hands back an empty string when the input is not
    //* valid UTF-8. Letting that fall through would read as "empty URL, fine",
    //* so treat it as suspicious instead.
    if($v === '' && $raw_value !== '') return false;
    $v = strtolower(preg_replace('/[\x00-\x20\x7f]+/', '', $v));
    if($v === '') return true;
    if($v[0] === '#') return true; // same-document reference, e.g. url(#grad1)
    return (bool)preg_match('~^data:image/(png|jpeg|gif|webp);base64,~', $v);
}

//* CSS can fetch (@import, url()) and, on old engines, execute (expression(),
//* -moz-binding, behavior:). It also has two obfuscation layers of its own —
//* comments can split a token, and backslash escapes can spell any character
//* — so both are unwound before matching. Run over <style> element text and
//* over every attribute value, since funcIRI attributes such as fill="url(…)"
//* and filter="url(…)" are CSS-shaped too.
function customizer_svg_css_ok($css) {
    $c = preg_replace('~/\*.*?\*/~s', '', (string)$css);
    if($c === null) return false; // PCRE backtrack/recursion limit — fail closed
    $decoded = html_entity_decode($c, ENT_QUOTES, 'UTF-8');
    if($decoded === '' && $c !== '') return false; // not valid UTF-8 — fail closed
    $c = $decoded;
    //* \6a\61… -> "ja…"; the optional trailing space is the escape terminator
    $c = preg_replace_callback('/\\\\([0-9a-fA-F]{1,6})[ \t\r\n\f]?/', function($m) {
        return chr(hexdec($m[1]) & 0xff);
    }, $c);
    if($c === null) return false;
    $c = str_replace('\\', '', $c); // java\script: -> javascript:
    $c = strtolower(preg_replace('/[\x00-\x20\x7f]+/', '', $c));
    if($c === '') return true;

    foreach(array('@import', 'expression(', 'javascript:', 'vbscript:', '-moz-binding', 'behavior:') as $bad) {
        if(strpos($c, $bad) !== false) return false;
    }

    $found = preg_match_all('/url\(([^)]*)\)/', $c, $m);
    //* an unterminated url( never matches the pattern, so compare against the
    //* raw occurrence count and fail closed when the two disagree
    if($found === false || $found !== substr_count($c, 'url(')) return false;
    foreach($m[1] as $target) {
        if(!customizer_svg_url_ok(trim($target, '\'"'))) return false;
    }
    return true;
}

function customizer_svg_ok($raw) {
    //* The SVG vocabulary a logo can legitimately need. Everything absent from
    //* this list is refused: script, foreignObject, <a>, SMIL <set>/<animate>,
    //* <iframe>, <video>, … none of them have to be named to be blocked.
    static $allowed_elements = array(
        'svg' => 1, 'g' => 1, 'defs' => 1, 'symbol' => 1, 'use' => 1, 'switch' => 1,
        'title' => 1, 'desc' => 1, 'metadata' => 1,
        'path' => 1, 'rect' => 1, 'circle' => 1, 'ellipse' => 1, 'line' => 1,
        'polyline' => 1, 'polygon' => 1, 'image' => 1,
        'text' => 1, 'tspan' => 1, 'textpath' => 1,
        'lineargradient' => 1, 'radialgradient' => 1, 'stop' => 1, 'pattern' => 1,
        'clippath' => 1, 'mask' => 1, 'marker' => 1, 'style' => 1,
        'filter' => 1, 'feblend' => 1, 'fecolormatrix' => 1, 'fecomponenttransfer' => 1,
        'fecomposite' => 1, 'feconvolvematrix' => 1, 'fediffuselighting' => 1,
        'fedisplacementmap' => 1, 'fedistantlight' => 1, 'fedropshadow' => 1,
        'feflood' => 1, 'fefunca' => 1, 'fefuncb' => 1, 'fefuncg' => 1, 'fefuncr' => 1,
        'fegaussianblur' => 1, 'feimage' => 1, 'femerge' => 1, 'femergenode' => 1,
        'femorphology' => 1, 'feoffset' => 1, 'fepointlight' => 1,
        'fespecularlighting' => 1, 'fespotlight' => 1, 'fetile' => 1, 'feturbulence' => 1,
        'view' => 1, 'solidcolor' => 1, 'color-profile' => 1,
        //* SVG fonts: an old Illustrator wordmark export embeds its glyph
        //* outlines this way rather than converting them to paths.
        'font' => 1, 'font-face' => 1, 'font-face-src' => 1, 'font-face-name' => 1,
        'font-face-uri' => 1, 'font-face-format' => 1, 'glyph' => 1,
        'missing-glyph' => 1, 'hkern' => 1, 'vkern' => 1,
        //* Inkscape's SVG 1.2 flowed text — browsers ignore it, but it is all
        //* over real .svg files and there is nothing executable about it.
        'flowroot' => 1, 'flowregion' => 1, 'flowpara' => 1, 'flowspan' => 1,
        'flowdiv' => 1, 'flowline' => 1,
    );
    //* Local names that must never appear, whatever namespace they are in.
    //* The allowlist above already covers the SVG namespace; this catches the
    //* same names smuggled in under a foreign prefix (<html:script>) in case a
    //* renderer is less disciplined about unknown namespaces than the spec.
    static $denied_names = array(
        'script' => 1, 'foreignobject' => 1, 'handler' => 1, 'iframe' => 1,
        'embed' => 1, 'object' => 1, 'applet' => 1, 'frame' => 1, 'frameset' => 1,
        'video' => 1, 'audio' => 1, 'source' => 1, 'track' => 1, 'canvas' => 1,
        'link' => 1, 'meta' => 1, 'base' => 1, 'form' => 1, 'input' => 1,
        'button' => 1, 'a' => 1, 'animate' => 1, 'animatecolor' => 1,
        'animatemotion' => 1, 'animatetransform' => 1, 'set' => 1, 'discard' => 1,
    );

    if(stripos($raw, '<svg') === false) return false;

    //* Entity declarations are the one thing that has to die before the parser
    //* runs: an internal subset can define entities that expand exponentially
    //* (billion laughs) or reference an external file. '<!ENTITY' is a single
    //* XML token that cannot be split by whitespace, so a case-insensitive
    //* byte scan really is sufficient here. A bare DOCTYPE stays allowed —
    //* Inkscape and Illustrator both emit one.
    if(preg_match('/<!ENTITY/i', $raw)) return false;

    //* ext/dom is what makes namespace-aware screening possible. It ships in
    //* the same distro package as the ext/simplexml that ISPConfig core itself
    //* requires, so it is effectively always present; if it somehow is not, we
    //* refuse SVG rather than fall back to a weaker check. PNG/JPEG/GIF/WebP
    //* are unaffected.
    if(!class_exists('DOMDocument') || !class_exists('DOMXPath')) return false;

    $prev = libxml_use_internal_errors(true);
    $doc  = new DOMDocument();
    //* LIBXML_NONET blocks any network retrieval during the parse. LIBXML_NOENT
    //* is deliberately NOT set: we never want entity substitution to run.
    $parsed = $doc->loadXML($raw, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if($parsed === false || !($doc->documentElement instanceof DOMElement)) return false;

    //* Root must be <svg>, either in the SVG namespace or in none at all
    //* (which is what a hand-written file with no xmlns parses as).
    $root_ns = (string)$doc->documentElement->namespaceURI;
    if(strtolower((string)$doc->documentElement->localName) !== 'svg') return false;
    if($root_ns !== '' && $root_ns !== 'http://www.w3.org/2000/svg') return false;

    $xpath = new DOMXPath($doc);

    //* An xml-stylesheet processing instruction makes the renderer pull a
    //* remote stylesheet, and it can sit outside the root element where an
    //* element walk would never reach it. No processing instruction is allowed.
    //* (Not written out literally here: a "?" ">" pair inside a // comment
    //* closes PHP mode and would spill the rest of the file as HTML.)
    $pis = $xpath->query('//processing-instruction()');
    if($pis === false || $pis->length > 0) return false;

    //* '*' is a namespace-agnostic name test, so this reaches prefixed elements
    //* without us having to register a single prefix — which is exactly the
    //* property the old raw-bytes blocklist lacked.
    $nodes = $xpath->query('//*');
    if($nodes === false) return false;

    foreach($nodes as $el) {
        $name = strtolower((string)$el->localName);
        if(isset($denied_names[$name])) return false;
        $ns = (string)$el->namespaceURI;
        if(($ns === '' || $ns === 'http://www.w3.org/2000/svg') && !isset($allowed_elements[$name])) return false;

        if($el->attributes !== null) {
            foreach($el->attributes as $attr) {
                $an = strtolower((string)$attr->localName);
                $av = (string)$attr->nodeValue;
                //* onload=, onclick=, … in any namespace. The parser has already
                //* resolved character references, so &#111;nload cannot hide here.
                if(strncmp($an, 'on', 2) === 0) return false;
                if(($an === 'href' || $an === 'src') && !customizer_svg_url_ok($av)) return false;
                //* covers style="" and every funcIRI attribute in one pass
                if(!customizer_svg_css_ok($av)) return false;
            }
        }

        //* <style> content is CSS wherever it lives, CDATA-wrapped or not —
        //* textContent hands us the resolved text either way.
        if($name === 'style' && !customizer_svg_css_ok($el->textContent)) return false;
    }

    return true;
}

$data_uri  = null; // set to the stored value on a successful upload
$upload_ok = false;

if($post_overflow) {
    $error[] = $app->lng('logo_too_large_txt');
} else {
    //* map PHP's own upload error before touching the file
    $err = isset($_FILES['file']['error']) ? $_FILES['file']['error'] : UPLOAD_ERR_NO_FILE;

    if($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        $error[] = $app->lng('logo_too_large_txt');
    } elseif($err === UPLOAD_ERR_NO_FILE || !isset($_FILES['file']['name']) || $_FILES['file']['name'] === '') {
        $error[] = $app->lng('no_file_uploaded_error');
    } elseif($err !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        $error[] = $app->lng('upload_failed_txt');
    } else {
        $data = file_get_contents($_FILES['file']['tmp_name']);
        $size = strlen($data);

        $mime = '';
        if(function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if($finfo) {
                $mime = (string)finfo_file($finfo, $_FILES['file']['tmp_name']);
                finfo_close($finfo);
            }
        }

        //* SVG lane: finfo's label is unreliable for SVG, so any XML-ish/texty
        //* verdict gets one chance to prove itself against the strict validator
        if(!isset($allowed[$mime])
            && in_array($mime, array('image/svg+xml', 'text/xml', 'application/xml', 'text/plain', 'text/html'), true)
            && customizer_svg_ok($data)) {
            $mime = 'image/svg+xml';
            $allowed[$mime] = true;
        }

        if(!isset($allowed[$mime])) {
            $error[] = $app->lng('logo_bad_type_txt');
        } elseif($size <= 0 || $size > $max_raw) {
            $error[] = $app->lng('logo_too_large_txt');
        } elseif($conf['demo_mode'] == true) {
            $error[] = $app->lng('demo_mode_txt');
        } else {
            $data_uri = 'data:' . $mime . ';base64,' . base64_encode($data);
            //* direct UPDATE (not datalogUpdate) — a 48 KB blob has no place in sys_datalog
            $app->db->query("UPDATE sys_ini SET custom_logo = ? WHERE sysini_id = 1", $data_uri);
            $upload_ok = true;
        }
    }
}

//* Preview: on success use the value we just wrote (no redundant re-read);
//* otherwise reflect whatever is currently stored.
if($data_uri !== null) {
    $current_logo = $data_uri;
} else {
    $row = $app->db->queryOneRecord("SELECT custom_logo FROM sys_ini WHERE sysini_id = 1");
    $current_logo = (is_array($row) && isset($row['custom_logo'])) ? (string)$row['custom_logo'] : '';
}
$preview = customizer_logo_preview_html($current_logo, $app->lng('no_logo_set_txt'));
$app->tpl->setVar('used_logo', $preview);

//* The caller relocates the banner into the message slot at the top of the
//* editor while #used_logo stays down in the form, so the confirmation embeds
//* the new thumbnail too — otherwise the admin has to scroll to see the result.
if($upload_ok) {
    $msg[] = $app->lng('logo_uploaded_txt') . '<br />' . $preview;
}

//* upload_msg/upload_error, NOT msg/error: the content template keys its
//* #OKMsg/#errorMsg blocks on these so the tabbed_form wrapper (which renders
//* msg/error itself) can't double-display banners on the interactive page
$app->tpl->setVar('upload_msg',   count($msg)   > 0 ? implode('<br />', $msg)   : '');
$app->tpl->setVar('upload_error', count($error) > 0 ? implode('<br />', $error) : '');

//* No CSRF pair is minted for the response. form.tpl.htm carries hidden
//* _csrf_id/_csrf_key inputs, but nothing consumes them here: the caller reads
//* only #OKMsg/#errorMsg/#used_logo out of this body, and the editor page's own
//* token — the one Save and Remove submit — is untouched by this request.
//* Minting one anyway used to leave a dead entry in $_SESSION['_csrf'] after
//* every upload, lingering until csrf_token_check()'s hourly expiry sweep.
//* Unset template vars render empty, which is correct for a fragment nobody
//* submits.

$app->tpl_defaults();
$app->tpl->pparse();
