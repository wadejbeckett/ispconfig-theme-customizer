#!/usr/bin/env python3
"""Exercise real module entry points over HTTP without an ISPConfig database.

The synthetic core stops at the first authorization boundary. This proves path
resolution and config/app loading, not authenticated page or database behavior.
Run with Python 3 and PHP CLI: python3 tests/bootstrap/run.py
"""
from contextlib import contextmanager
from pathlib import Path
import os
import json
import shutil
import socket
import subprocess
import tempfile
import time
import unittest
import urllib.error
import urllib.request

REPO = Path(__file__).resolve().parents[2]
ENDPOINTS = ('customizer_edit.php', 'preview.php', 'logo_upload.php', 'logo_delete.php')
PHP = os.environ.get('PHP_BINARY', 'php')
CONFIG = "<?php $conf = array('bootstrap_probe' => 'panel');\n"
APP = """<?php
if (!isset($conf['bootstrap_probe']) || $conf['bootstrap_probe'] !== 'panel') {
    http_response_code(500); exit('Config not in application scope');
}
$app = new stdClass;
$app->auth = new class {
    function check_module_permissions($module) {
        if ($module !== 'customizer') { http_response_code(500); exit('Wrong module'); }
        http_response_code(403);
        exit('AUTH_BOUNDARY');
    }
};
"""


@contextmanager
def serve(document_root):
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    with tempfile.TemporaryFile(mode='w+') as log:
        process = subprocess.Popen(
            [PHP, '-d', 'display_errors=0', '-d', 'log_errors=1',
             '-S', f'127.0.0.1:{port}', '-t', str(document_root)],
            stdout=log, stderr=log)
        try:
            for _ in range(100):
                if process.poll() is not None:
                    raise RuntimeError('PHP HTTP server exited during startup')
                try:
                    with socket.create_connection(('127.0.0.1', port), timeout=0.1):
                        break
                except OSError:
                    time.sleep(0.02)
            else:
                raise RuntimeError('PHP HTTP server did not start')
            yield f'http://127.0.0.1:{port}', log
        finally:
            process.terminate()
            process.wait(timeout=5)


def request(url):
    try:
        response = urllib.request.urlopen(url, timeout=5)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        return response.status, response.read().decode()


class BootstrapTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix='customizer-bootstrap-')
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        self.interface = self.root / 'panel/interface'
        self.web = self.interface / 'web'
        self.web.mkdir(parents=True)
        (self.interface / 'lib').mkdir()
        (self.interface / 'lib/config.inc.php').write_text(CONFIG)
        (self.interface / 'lib/app.inc.php').write_text(APP)
        self.module = self.root / 'external-clone/interface/web/customizer'
        shutil.copytree(REPO / 'interface/web/customizer', self.module)

    def install(self, mode):
        target = self.web / 'customizer'
        if mode == 'copy':
            shutil.copytree(self.module, target)
        else:
            target.symlink_to(self.module)

    def assert_authorization_reached(self, document_root):
        with serve(document_root) as (url, log):
            for endpoint in ENDPOINTS:
                with self.subTest(endpoint=endpoint):
                    self.assertEqual(request(f'{url}/customizer/{endpoint}'),
                                     (403, 'AUTH_BOUNDARY'))
            log.seek(0)
            self.assertNotIn('PHP Fatal', log.read())

    def test_copied_module_loads_core_before_authorization(self):
        self.install('copy')
        self.assert_authorization_reached(self.web)

    def test_symlinked_module_loads_panel_core_not_clone(self):
        self.install('symlink')
        # A clone-adjacent config must not take precedence over the panel.
        clone_lib = self.root / 'external-clone/interface/lib'
        clone_lib.mkdir()
        (clone_lib / 'config.inc.php').write_text("<?php exit('WRONG_CLONE_CONFIG');")
        (clone_lib / 'app.inc.php').write_text("<?php exit('WRONG_CLONE_APP');")
        self.assert_authorization_reached(self.web)

    def test_symlinked_document_root_and_module(self):
        self.install('symlink')
        alias = self.root / 'www/ispconfig'
        alias.parent.mkdir()
        alias.symlink_to(self.web)
        self.assert_authorization_reached(alias)

    def test_missing_core_fails_without_exposing_filesystem(self):
        self.install('symlink')
        (self.interface / 'lib/app.inc.php').unlink()
        # Do not fall through from an incomplete panel to a clone-side config.
        clone_lib = self.root / 'external-clone/interface/lib'
        clone_lib.mkdir()
        (clone_lib / 'config.inc.php').write_text("<?php exit('WRONG_CLONE_CONFIG');")
        (clone_lib / 'app.inc.php').write_text("<?php exit('WRONG_CLONE_APP');")
        with serve(self.web) as (url, log):
            for endpoint in ENDPOINTS:
                with self.subTest(endpoint=endpoint):
                    status, body = request(f'{url}/customizer/{endpoint}')
                    self.assertEqual(status, 500)
                    self.assertIn('ISPConfig', body)
                    self.assertNotIn(str(self.root), body)
            log.seek(0)
            self.assertIn('bootstrap', log.read().lower())

    def assert_cli_authorization_reached(self, document_root=None, script_path=True):
        for endpoint in ENDPOINTS:
            with self.subTest(endpoint=endpoint):
                path = self.web / 'customizer' / endpoint
                server = {}
                if document_root is not None:
                    server['DOCUMENT_ROOT'] = str(document_root)
                if script_path:
                    server['SCRIPT_FILENAME'] = str(path)
                result = subprocess.run(
                    [PHP, '-r', '$_SERVER=json_decode($argv[1],true); '
                     'chdir($argv[2]); require $argv[3];',
                     json.dumps(server), str(path.parent.resolve()), str(path)],
                    capture_output=True, text=True)
                self.assertEqual(result.returncode, 0, result.stderr)
                self.assertEqual(result.stdout, 'AUTH_BOUNDARY')
                self.assertEqual(result.stderr, '')

    def test_script_path_fallback_without_document_root(self):
        self.install('symlink')
        self.assert_cli_authorization_reached()

    def test_unrelated_document_root_cannot_select_another_panel(self):
        self.install('symlink')
        other = self.root / 'other/interface'
        (other / 'web/customizer').mkdir(parents=True)
        (other / 'lib').mkdir()
        (other / 'lib/config.inc.php').write_text("<?php exit('WRONG_PANEL');")
        (other / 'lib/app.inc.php').write_text("<?php exit('WRONG_PANEL');")
        self.assert_cli_authorization_reached(other / 'web')

    def test_copy_fallback_without_server_paths(self):
        self.install('copy')
        self.assert_cli_authorization_reached(script_path=False)


if __name__ == '__main__':
    unittest.main(verbosity=2)
