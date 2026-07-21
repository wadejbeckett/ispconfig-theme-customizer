# Security Policy

## Reporting a vulnerability

Please report security issues **privately** — use GitHub's private vulnerability
reporting on this repository (**Security → Report a vulnerability**), not a
public issue or pull request. You'll get an acknowledgement and a fix or
assessment. If a report is actually an ISPConfig **core** issue, it will be
redirected upstream with credit.

## Supported versions

The latest tagged release receives fixes. Developed and verified against
**ISPConfig 3.3.1p1** (3.2 / 3.3 supported).

## Design posture

- **No ISPConfig core file is modified.** The theme lives entirely under
  `themes/clarity/` and overrides templates and assets through ISPConfig's own
  theme loader. It borrows the stock theme's vendor CSS/JS by reference and
  never edits it.
- **The two pre-auth endpoints are the sharp end, and are written for it.**
  `brand.php` and `title.php` are linked from the **login** page, so they run
  with no session and must be safe for anonymous requests. Each does a single
  read-only query of one `sys_ini` row (no app bootstrap, no session start, no
  maintenance-mode side effects), always returns HTTP 200 with valid output, and
  **validates every value before it reaches output**: colours via anchored hex
  regexes, the logo reference via an anchored URL/path allowlist, the panel-name
  text wordmark via character-stripping (removes quotes, backslashes, CR/LF and
  angle brackets) plus a length cap, and booleans as strict `0|1`. Values reach
  a CSS `url()`/string context or a JS string only after passing these checks;
  the JS path additionally goes through `json_encode` with the HEX flags.
- **Fails safe.** On any database fault the endpoints emit empty (no-op) output
  with `Cache-Control: no-store`, so a transient outage can never blank a host's
  branding for a cache window or leak an error on the anonymous route.
- **No code execution surface.** Nothing user-controlled is `eval`'d or
  `include`'d; the endpoints only read and emit validated scalars.

## Scope

In scope: injection into the rendered panel or login page (CSS/JS/HTML) via any
value the theme reads, information disclosure on the pre-auth endpoints, and any
way the theme could execute attacker-controlled input. Out of scope: pre-existing
ISPConfig core behaviour (reported upstream instead).
