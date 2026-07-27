<?php
/* ============================================================
 * Clarity Theme for ISPConfig — favicon.php  (the ICON reader)
 * ------------------------------------------------------------
 * Serves the operator's own favicon, or this design's shipped one when none is
 * set. Both shell templates link THIS file as their <link rel='icon'>, so the
 * tab, the bookmark and the history entry carry the host's brand instead of
 * whichever mark the design happens to ship. Companion to brand.php (colours +
 * logo, as CSS) and title.php (the tab title, as JS).
 *
 * Contract consumed (sys_ini, global row sysini_id = 1, section [branding]):
 *   favicon      -> the uploaded icon, a data URI (SVG, PNG or ICO)
 *   favicon_url  -> the icon by reference: a root-relative path or an https URL
 *
 * ONE precedence rule, the same one logo_url has always had over custom_logo:
 * the REFERENCE beats the upload. There is deliberately no light/dark pair here
 * — a browser paints the same icon whatever this design's header looks like.
 *
 * Resolution and both validators must stay identical to themes/classic/
 * favicon.php and to the customizer's lib/preview.inc.php. The readers cannot
 * include the module's code — they are pre-authentication endpoints that must
 * keep working with the extension uninstalled — so the rule is duplicated
 * deliberately, and all three copies change together.
 *
 * ---- WHY AN ENDPOINT AND NOT CSS ---------------------------------------
 * brand.php emits a stylesheet, and a favicon is a <link>, not a style: there
 * is no CSS property that sets it. Swapping the href from JavaScript would
 * flicker on every load, would lose to the browser's own icon cache, and would
 * do nothing at all with scripting disabled — on a pre-authentication page. So
 * the link points at a PHP endpoint that answers with the icon itself.
 *
 * ---- DESIGN CONSTRAINTS (identical to brand.php) ------------------------
 *   - Pre-auth safe: the login screen links this file, so it must work with no
 *     session. One side-effect-free, read-only query of one sys_ini row — no
 *     ISPConfig app bootstrap, no maintenance-mode redirect, no session start.
 *   - It must NEVER 404 and never emit a broken response. A missing favicon is
 *     a visible defect on every tab of the panel, so every failure path — DB
 *     down, nothing stored, a stored value that does not validate, an
 *     unreadable asset — ends in a valid image response.
 *   - Injection-safe: the stored value is attacker-controllable only by an
 *     administrator, and it is still validated before it reaches a header or
 *     the response body (anchored data-URI regex with a three-format type
 *     allowlist / the same anchored url allowlist the logo paths use, /D
 *     included / strict base64 decode).
 * ============================================================ */

/* ---- what this design ships, tried in order ----------------------------
 * Crispest first, then the universally supported container. These are the very
 * files the shell templates used to link directly, so with nothing stored the
 * panel looks exactly as it did before this endpoint existed. */
$fallbacks = array(
    __DIR__ . '/assets/favicon/favicon-32x32.png',
    __DIR__ . '/assets/favicon/favicon.ico',
    __DIR__ . '/assets/favicon/favicon-16x16.png',
);

/* ---- read the contract (direct, minimal, side-effect-free) ---- */
$branding = array();
$read_ok  = false; // true only when the sys_ini read actually succeeded

// Locate interface/lib/config.inc.php WITHOUT trusting __DIR__ alone.
// install.sh's DEFAULT mode is a SYMLINK, and PHP resolves __DIR__ through
// symlinks — so on a symlinked design __DIR__ is the git clone, where
// ../../../lib/config.inc.php does not exist. This endpoint would then fail to
// find the panel, take its DB-failure path, and emit its empty/no-op response:
// branding silently doing nothing on the documented default install, with no
// error anywhere. SCRIPT_FILENAME is the path the web server actually
// requested and is NOT symlink-resolved, so try that first; __DIR__ still
// covers CLI use and copy installs, and DOCUMENT_ROOT is a last resort.
$config_inc = '';
$_cfg_candidates = array();
if (!empty($_SERVER['SCRIPT_FILENAME'])) {
    $_cfg_candidates[] = dirname($_SERVER['SCRIPT_FILENAME']) . '/../../../lib/config.inc.php';
}
$_cfg_candidates[] = __DIR__ . '/../../../lib/config.inc.php';
if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $_cfg_candidates[] = $_SERVER['DOCUMENT_ROOT'] . '/../lib/config.inc.php';
}
foreach ($_cfg_candidates as $_cand) {
    if (is_readable($_cand)) { $config_inc = $_cand; break; }
}
unset($_cfg_candidates, $_cand);
if (is_readable($config_inc)) {
    // config.inc.php only defines $conf + constants — but on a web request it also
    // emits `Content-Type: text/html`, which every send path below overrides.
    require $config_inc;
    if (isset($conf) && is_array($conf) && !empty($conf['db_host'])) {
        // PHP 8.1+ makes mysqli throw by default; keep the graceful errno idiom working.
        if (function_exists('mysqli_report')) {
            mysqli_report(MYSQLI_REPORT_OFF);
        }
        try {
            $port   = isset($conf['db_port']) ? (int)$conf['db_port'] : 3306;
            $mysqli = @new mysqli($conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_database'], $port);
            if ($mysqli && !$mysqli->connect_errno) {
                // Same guard as brand.php and bin/purge_branding.php: a raw mysqli
                // connection inherits mysqli.default_charset, which is not always
                // utf8mb4, and sys_ini.config is a utf8mb4 column. A narrower
                // connection charset substitutes '?' for what it cannot represent,
                // which would corrupt the INI blob as we parse it.
                @$mysqli->set_charset('utf8mb4');
                if ($res = @$mysqli->query('SELECT config FROM sys_ini WHERE sysini_id = 1')) {
                    $read_ok = true;
                    if ($row = $res->fetch_assoc()) {
                        $parsed   = favicon_parse_config((string)$row['config']);
                        $branding = (isset($parsed['branding']) && is_array($parsed['branding'])) ? $parsed['branding'] : array();
                    }
                }
                if ($mysqli instanceof mysqli) {
                    $mysqli->close();
                }
            }
        } catch (\Throwable $e) {
            // any DB fault -> serve the shipped icon; never leak an error on this
            // pre-auth route, and never let a tab go iconless over a hiccup
            $branding = array();
            $read_ok  = false;
        }
    }
}

/* ---- resolve: reference first, then upload, then what we ship ---- */
$ref  = isset($branding['favicon_url']) ? $branding['favicon_url'] : '';
$data = isset($branding['favicon'])     ? $branding['favicon']     : '';

if ($read_ok && favicon_ref_ok($ref)) {
    // A REFERENCE is answered with a redirect, not by fetching it here. Two
    // reasons, both load-bearing:
    //   - an https reference must not turn this endpoint into a fetcher of
    //     arbitrary URLs on the panel's behalf (that is an SSRF, reachable
    //     pre-auth, from a value stored in a database);
    //   - a root-relative reference is a WEB path, not a filesystem path.
    //     Resolving "/img/../../etc/passwd" against a directory here would be a
    //     file-disclosure primitive. The browser is the right thing to resolve
    //     it, exactly as it would for a hardcoded <link href> — which is all
    //     this reference is.
    // 302, never 301: branding changes, and a permanent redirect would be
    // cached by browsers long after the operator cleared the field.
    //
    // The value cannot split this header: the allowlist regex admits no
    // whitespace at all, so it cannot carry the CR or LF that would be needed —
    // which is exactly what the /D modifier guarantees, since without it PCRE's
    // "$" also matches before a trailing newline.
    header('Cache-Control: private, max-age=30');
    header('Location: ' . $ref, true, 302);
    exit;
}

if ($read_ok) {
    $decoded = favicon_decode_data($data);
    if ($decoded !== null) {
        favicon_send($decoded[1], $decoded[0], true);
    }
}

// Nothing stored, nothing valid, or the DB was unreachable: this design's own
// icon. On a DB fault it is sent uncacheable, so a transient outage cannot pin
// the shipped icon over the host's brand for the whole max-age window.
favicon_send_shipped($fallbacks, $read_ok);

/* ============================================================
 * Helpers — pure, dependency-free.
 * ============================================================ */

/**
 * Parse the whole sys_ini config blob with ISPConfig's own INI reader, so this
 * reader can never drift from the customizer's writer (which serialises with
 * the same framework class). ini_parser.inc.php is a pure, dependency-free
 * class — safe to require on this pre-auth path. Identical to brand.php's
 * brand_parse_config(), including the stripslashes: core escapes values on the
 * way in (tform_base::_encode() runs db->quote() before the blob is
 * serialised) and every core reader unquotes on the way out. Neither favicon
 * value can actually contain a backslash — the base64 alphabet has none and the
 * reference allowlist rejects them — but reading the blob differently from
 * every other reader is how the two brand endpoints on one login page start
 * disagreeing, and that has happened here before (see title.php).
 */
function favicon_parse_config($config)
{
    // Same reasoning as the config path resolved at top level: a bare __DIR__
    // walk lands in the git clone on a symlink install, so prefer the path the
    // web server actually requested. $config_inc is NOT usable here — this is a
    // function, and PHP has no implicit access to the enclosing file's scope;
    // referencing it emitted "Undefined variable" warnings straight into this
    // endpoint's CSS response, which corrupted the stylesheet.
    $parser_file = '';
    $_pf = array();
    if (!empty($_SERVER['SCRIPT_FILENAME'])) {
        $_pf[] = dirname($_SERVER['SCRIPT_FILENAME']) . '/../../../lib/classes/ini_parser.inc.php';
    }
    $_pf[] = __DIR__ . '/../../../lib/classes/ini_parser.inc.php';
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $_pf[] = $_SERVER['DOCUMENT_ROOT'] . '/../lib/classes/ini_parser.inc.php';
    }
    foreach ($_pf as $_c) {
        if (is_readable($_c)) { $parser_file = $_c; break; }
    }
    if (is_readable($parser_file)) {
        require_once $parser_file;
        if (class_exists('ini_parser')) {
            $p   = new ini_parser();
            $out = $p->parse_ini_string(stripslashes($config));
            return is_array($out) ? $out : array();
        }
    }
    return array();
}

/**
 * Is $ref an icon reference we are willing to redirect to?
 *
 * The SAME anchored allowlist the two logo reference slots use, character for
 * character, in the module's tform validator, in both brand.php readers and in
 * lib/preview.inc.php: a root-relative path or an https URL, with (?!/)
 * rejecting protocol-relative "//host" (a remote URL in disguise).
 *
 * The /D modifier is not decoration. Without it PCRE's `$` also matches BEFORE
 * a final newline, so "/img/icon.png\n" would validate — and here that trailing
 * LF would be written straight into a Location: header, which is how one header
 * becomes two.
 */
function favicon_ref_ok($ref)
{
    return (is_string($ref) && $ref !== ''
        && preg_match('#^(https://[^\s"\'<>()\\\\]+|/(?!/)[^\s"\'<>()\\\\]+)$#D', $ref) === 1);
}

/**
 * Decode a stored favicon data URI into array(media type, raw bytes), or null
 * when the value is anything else.
 *
 * The type is an ALLOWLIST of the three formats the uploader accepts, because
 * it is echoed back as this response's Content-Type; image/vnd.microsoft.icon
 * is accepted alongside image/x-icon since they are one format under two
 * spellings (the uploader normalises to the latter). Anything outside the list
 * — a value from a future version, a hand-edited row — is treated as "not set"
 * and the shipped icon is served instead, which is the documented behaviour for
 * every invalid value on this endpoint.
 *
 * base64_decode() runs in STRICT mode: a value carrying anything outside the
 * base64 alphabet is a corrupt value, and half-decoding it would produce a
 * broken image response — the one thing this endpoint must never emit.
 */
function favicon_decode_data($uri)
{
    if (!is_string($uri) || $uri === '') {
        return null;
    }
    if (!preg_match('#^data:(image/(?:svg\+xml|png|x-icon|vnd\.microsoft\.icon));base64,([A-Za-z0-9+/=]+)$#D', $uri, $m)) {
        return null;
    }
    $bytes = base64_decode($m[2], true);
    if ($bytes === false || $bytes === '') {
        return null;
    }
    return array($m[1], $bytes);
}

/**
 * Send one icon and stop. $cacheable is false on a DB fault, where the response
 * is still a valid image but must not be cached — otherwise a momentary outage
 * would blank the host's branding for the whole max-age window even after the
 * database came back. Same rule, same reason, as brand.php.
 */
function favicon_send($bytes, $type, $cacheable)
{
    // Re-assert the MIME: config.inc.php sends text/html on web requests.
    header('Content-Type: ' . $type);
    header('X-Content-Type-Options: nosniff');
    // SVG is an active-content format served here from the panel's own origin,
    // so a direct navigation to this URL renders it as a same-origin DOCUMENT.
    // Uploads are screened by customizer_svg_ok() and carry no script — this is
    // the second lock on that door, and it costs one header: no scripts, no
    // plugins, no network of any kind, and `sandbox` drops the document out of
    // its origin entirely. img-src data: and inline styles stay allowed because
    // legitimate icons use both (an embedded raster, a <style> block).
    header("Content-Security-Policy: default-src 'none'; img-src data:; style-src 'unsafe-inline'; sandbox");

    if ($cacheable) {
        // Content-addressed, so it changes the moment the stored icon does, and
        // matches whether the bytes came from the database or from disk.
        $etag = '"' . md5($type . '|' . $bytes) . '"';
        header('ETag: ' . $etag);
        // Short + private, exactly like brand.php: long enough that this is not
        // a blocking round trip on every page load, short enough that a changed
        // icon appears without the operator being told to clear anything. (A
        // browser's own favicon cache is far stickier than this header, which is
        // why the upload confirmation mentions a hard refresh.)
        header('Cache-Control: private, max-age=30');
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            http_response_code(304);
            exit;
        }
    } else {
        header('Cache-Control: no-store');
    }

    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
    exit;
}

/**
 * Serve the first shipped icon that is actually readable.
 *
 * The last resort is a 1x1 transparent PNG rather than a 404: a design whose
 * icon files went missing (a partial deploy, a symlink into a directory the web
 * user cannot traverse) must still answer with a valid image, because the
 * alternative is a browser-drawn "broken" icon or a console full of 404s on
 * every page of the panel. It is sent uncacheable so the real icon appears as
 * soon as the install is repaired.
 */
function favicon_send_shipped($paths, $cacheable)
{
    $types = array('png' => 'image/png', 'ico' => 'image/x-icon', 'svg' => 'image/svg+xml', 'gif' => 'image/gif');

    foreach ($paths as $p) {
        if (!is_file($p) || !is_readable($p)) {
            continue;
        }
        $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
        if (!isset($types[$ext])) {
            continue;
        }
        $bytes = @file_get_contents($p);
        if ($bytes === false || $bytes === '') {
            continue;
        }
        favicon_send($bytes, $types[$ext], $cacheable);
    }

    favicon_send(base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    ), 'image/png', false);
}
