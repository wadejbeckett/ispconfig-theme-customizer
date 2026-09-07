<?php
/**
 * interface/web/customizer/preview.php — the endpoint's SHAPE.
 *
 * The endpoint's decisions all live in lib/preview.inc.php and are tested by
 * probe_module.php against real inputs. What cannot be tested that way is what
 * makes this file safe to add: that it authenticates the same way its three
 * siblings do, before anything else; that it writes nothing; and that it answers
 * as JSON that is never cached. Those are properties of the SOURCE, so they are
 * asserted against the token stream — tokenised rather than regexed because this
 * module's files carry "/*" inside string literals, which is how an earlier
 * comment-stripping regex in probe_classic.php swallowed the very code it was
 * looking for.
 *
 * Run through tests/brand/run.php. Nothing here executes the endpoint: it
 * requires ISPConfig's app bootstrap, a database and a session, none of which
 * exist in CI or on a developer's machine.
 */

require_once __DIR__ . '/harness.php';

$path = __DIR__ . '/../../interface/web/customizer/preview.php';
$src  = @file_get_contents($path);
t_ok('preview.php exists and is readable', $src !== false);
if ($src === false) t_done();

/**
 * The file with every comment removed.
 *
 * Comments must never satisfy an assertion below: half of them are about the
 * write verbs this endpoint must not contain, so a scan that read them would
 * fail on the documentation of the very rule it enforces.
 */
function p_code($src) {
    $code = '';
    foreach (token_get_all($src) as $tok) {
        if (is_array($tok)) {
            if ($tok[0] === T_COMMENT || $tok[0] === T_DOC_COMMENT) continue;
            $code .= $tok[1];
            continue;
        }
        $code .= $tok;
    }
    return $code;
}

$code = p_code($src);

/* ---- the three checks, in the documented order --------------------------- */
$checks = array(
    "check_module_permissions('customizer')",
    "check_security_permissions('admin_allow_system_config')",
    'is_admin()',
);
$at = -1;
foreach ($checks as $needle) {
    $pos = strpos($code, $needle);
    if (!t_ok("preview.php performs $needle", $pos !== false)) continue;
    t_ok("...after the check before it", $pos > $at, "at $pos, previous at $at");
    $at = $pos;
}
t_ok('the is_admin() guard dies rather than continuing',
    preg_match('/if\s*\(\s*!\s*\$app->auth->is_admin\(\)\s*\)\s*die\s*\(/', $code) === 1);

//* Nothing may run before the checks — not a query, not a read of $_POST.
$first_check = strpos($code, "check_module_permissions('customizer')");
$before = substr($code, 0, $first_check);
foreach (array('$_POST', '$_GET', 'queryOneRecord', 'get_global_config', 'echo') as $early) {
    t_ok("nothing reads or emits before the first check ($early)",
        strpos($before, $early) === false, $early);
}

/* ---- read-only, asserted rather than promised ---------------------------- */
foreach (array('datalogUpdate', 'datalogSave', 'datalogInsert', 'datalogDelete',
               'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'ALTER', 'CREATE',
               'file_put_contents', 'fwrite', 'unlink', 'mkdir', 'rename',
               'csrf_token_get', '->conf(') as $verb) {
    t_ok("preview.php contains no '$verb'", stripos($code, $verb) === false);
}
t_ok('the only SQL is a SELECT',
    preg_match_all('/\bSELECT\b/i', $code) >= 1 && stripos($code, 'sys_ini') !== false);

/**
 * ...and the shared model it includes adds no SQL of its own.
 *
 * The assertion above covers the endpoint's own text, which is where a write
 * would most obviously be added. But every line lib/preview.inc.php runs, this
 * request runs, so "read-only" is a property of the PAIR — a query added to a
 * helper is a query this endpoint issues. The set of SQL verbs across both files
 * must therefore be exactly {SELECT}: not "no writes among the verbs we thought
 * to list", but nothing else at all.
 */
$model_path = __DIR__ . '/../../interface/web/customizer/lib/preview.inc.php';
$model_src  = @file_get_contents($model_path);
if (t_ok('lib/preview.inc.php exists and is readable', $model_src !== false)) {
    $pair = $code . "\n" . p_code($model_src);
    preg_match_all('/\b(SELECT|INSERT|UPDATE|DELETE|REPLACE|MERGE|UPSERT|ALTER|CREATE|DROP|TRUNCATE|RENAME|GRANT|REVOKE|LOAD)\b/i',
        $pair, $m);
    $verbs = array_values(array_unique(array_map('strtoupper', $m[0])));
    sort($verbs);
    t_eq('preview.php + lib/preview.inc.php use exactly one SQL verb, SELECT',
        $verbs, array('SELECT'));
}

/**
 * ...and it leaves the SESSION alone, which is the write nobody sees.
 *
 * The write-verb scan above cannot catch this one, because the write is core's,
 * not ours: ISPConfig's session handler (interface/lib/classes/session.inc.php,
 * write() at line 86) rewrites the whole session row whenever the session data
 * differs from what it read, and only touches last_updated when it does not.
 * Minting a token or assigning into $_SESSION is therefore a whole-row REPLACE
 * per keystroke on a debounced endpoint — the exact race the uploader's
 * click-time mint exists to shrink. READING $_SESSION is fine and this endpoint
 * does it (the active design), so the assertion is about assignment.
 */
t_ok('nothing is assigned into $_SESSION',
    preg_match('/\$_SESSION\s*(\[[^\]]*\])+\s*=(?!=)/', $code) !== 1,
    'an assignment makes core rewrite the session row on every keystroke');
t_ok('no CSRF token is minted or checked',
    stripos($code, 'csrf_token_') === false, 'minting is a session write');

/* ---- the gates ----------------------------------------------------------- */
t_ok('only POST is answered', strpos($code, "REQUEST_METHOD") !== false
    && strpos($code, "'POST'") !== false);
t_ok('a non-POST is refused as 405, not as a 200 the caller cannot read',
    strpos($code, 'http_response_code(405)') !== false);
t_ok('the same-origin header gate the uploader uses is applied',
    strpos($code, 'HTTP_X_REQUESTED_WITH') !== false && strpos($code, 'XMLHttpRequest') !== false);
//* Both refusals have to carry a status, or the page's fetch() sees a 200 whose
//* text/html body fails JSON.parse and cannot tell a refusal from a bug.
$hdr_gate = strpos($code, 'HTTP_X_REQUESTED_WITH');
$bad_req  = strpos($code, 'http_response_code(400)');
t_ok('the header gate refuses with 400', $bad_req !== false);
t_ok('...and does so in the header gate, not before it',
    $bad_req !== false && $hdr_gate !== false && $bad_req > $hdr_gate,
    "400 at $bad_req, gate at $hdr_gate");

/* ---- the answer ---------------------------------------------------------- */
t_ok('the response is declared JSON', strpos($code, 'application/json') !== false);
t_ok('the response is never cached', strpos($code, 'no-store') !== false);
t_ok('the payload is built by the shared function, not here',
    strpos($code, 'customizer_preview_payload(') !== false);
t_ok('json_encode escapes for an HTML-hostile reader',
    strpos($code, 'JSON_HEX_TAG') !== false && strpos($code, 'JSON_HEX_AMP') !== false
    && strpos($code, 'JSON_HEX_APOS') !== false && strpos($code, 'JSON_HEX_QUOT') !== false);

/* ---- no decision of its own ---------------------------------------------
 * Every rule this endpoint applies has to be one of the shared resolvers, or
 * the three-copies-agree guarantee stops covering what the page shows.
 */
t_ok('the endpoint declares no function of its own',
    preg_match('/\bfunction\s+[a-z_]/i', $code) !== 1, 'a helper here would be a fourth copy');
t_ok('it includes the shared model', strpos($code, "lib/preview.inc.php") !== false);

t_done();
