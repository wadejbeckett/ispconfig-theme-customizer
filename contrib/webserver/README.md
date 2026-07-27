# Web-server snippets — deny theme metadata files

## What this fixes

ISPConfig refuses to load a third-party theme unless the theme directory
contains a version file matching the panel exactly:

```
interface/web/themes/<theme>/ispconfig_version   ->  e.g. 3.3.1p1
interface/web/themes/<theme>/ISPC_VERSION        ->  same
```

`install.sh` therefore has to create both — core reads those exact filenames and
there is no alternative location. But the theme directory lives inside the
panel's **web root**, so the web server serves them as ordinary static files:

```
$ curl -k https://panel.example.com:8080/themes/clarity/ispconfig_version
3.3.1p1
```

No session, no credentials, no unusual log entry. Anyone who can reach your
login page learns your exact ISPConfig version **and patch level**, which is
precisely the information needed to look up which known vulnerabilities apply to
your install.

**This is not stock behaviour.** ISPConfig's own `default` theme ships no
version file — the gate exists to validate *third-party* themes — so a stock
panel discloses nothing here. Installing any third-party theme, this one
included, introduces the exposure. Verified: on a stock panel
`/themes/default/ispconfig_version` returns 404 while
`/themes/clarity/ispconfig_version` returns 200.

It also undercuts this project's own **"hide the ISPConfig version"** toggle,
which hides the version in the Help page while the same value stays readable one
URL away. Applying the snippet below is what makes that toggle honest.

Two documentation files ride along for the same reason and are denied too:
`BUILT-AGAINST.txt` and `README.md`.

## Applying it

ISPConfig already denies files this way in its own panel vhost — dotfiles via
`location ~ /\.` and language files via `location ~* \.lng$`. These snippets
follow that existing idiom; they are not a new mechanism.

### nginx

Panel vhost, usually `/etc/nginx/sites-available/ispconfig.vhost`. Add inside
the `server { }` block, then `nginx -t && systemctl reload nginx`:

```nginx
location ~* /themes/[^/]+/(ispconfig_version|ISPC_VERSION|BUILT-AGAINST\.txt|README\.md)$ {
    deny all;
}
```

### Apache

Panel vhost, usually `/etc/apache2/sites-available/ispconfig.vhost`. Add inside
the `<VirtualHost>` block, then `apachectl configtest && systemctl reload apache2`:

```apache
<DirectoryMatch "^/usr/local/ispconfig/interface/web/themes/[^/]+/">
    <FilesMatch "^(ispconfig_version|ISPC_VERSION|BUILT-AGAINST\.txt|README\.md)$">
        Require all denied
    </FilesMatch>
</DirectoryMatch>
```

## Verifying

```bash
curl -sk -o /dev/null -w '%{http_code}\n' https://panel.example.com:8080/themes/clarity/ispconfig_version
```

`403` (or `404`) means the rule is live. `200` means it is not — check the
snippet is inside the panel vhost's server block and that the config was
reloaded.

**The panel must still work afterwards.** The deny rule only affects HTTP
requests; ISPConfig reads those files from disk, which is unaffected. Log out
and back in to confirm your theme is still applied — if it silently reverts to
the default theme, the version file itself is missing or stale, which is an
installer problem, not this snippet.

## A note on updates

The ISPConfig updater can regenerate the panel vhost when you let it
reconfigure services. If your theme suddenly starts disclosing its version
again, re-apply the snippet — it is worth re-checking after any panel upgrade,
alongside re-running `install.sh` to re-stamp the version gate.
