"""Real multipart upload/static delivery regression test. Run with python3 -m unittest discover -s tests -p 'test_image_upload.py'. Requires PHP CLI."""
import base64
import http.client
from pathlib import Path
import shutil
import socket
import stat
import subprocess
import tempfile
import time
import unittest

ROOT = Path(__file__).resolve().parents[1]
PNG = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')


@unittest.skipUnless(shutil.which('php'), 'PHP CLI is required')
class ImageUploadTest(unittest.TestCase):
    def test_uploaded_image_is_publicly_readable_and_served_as_image(self):
        # Isolate all uploads and configuration from the real installation.
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            shutil.copytree(ROOT / 'includes', root / 'includes')
            shutil.copy(ROOT / 'router.php', root / 'router.php')
            (root / 'index.php').write_text('<?php http_response_code(404); echo "CMS fallback";')
            (root / 'upload.php').write_text('''<?php
require __DIR__ . '/includes/functions.php';
// Model hosts whose temporary uploads are private to the PHP user.
chmod($_FILES['image']['tmp_name'], 0600);
$result = upload_image($_FILES['image']);
if (!$result) { http_response_code(400); exit('Upload failed'); }
echo $result;
''')
            with socket.socket() as sock:
                sock.bind(('127.0.0.1', 0))
                port = sock.getsockname()[1]
            process = subprocess.Popen(
                ['php', '-S', f'127.0.0.1:{port}', 'router.php'], cwd=root,
                stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            try:
                for _ in range(100):
                    try:
                        with socket.create_connection(('127.0.0.1', port), timeout=0.1):
                            break
                    except OSError:
                        if process.poll() is not None:
                            self.fail('PHP server exited before startup')
                        time.sleep(0.05)
                boundary = 'ImageRegressionBoundary'
                body = (f'--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="featured.png"\r\nContent-Type: image/png\r\n\r\n'.encode()
                        + PNG + f'\r\n--{boundary}--\r\n'.encode())
                connection = http.client.HTTPConnection('127.0.0.1', port, timeout=5)
                connection.request('POST', '/upload.php', body,
                                   {'Content-Type': f'multipart/form-data; boundary={boundary}'})
                response = connection.getresponse()
                url = response.read().decode()
                self.assertEqual(response.status, 200, url)
                self.assertTrue(url.startswith('/assets/uploads/'), url)
                image = root / url.lstrip('/')
                self.assertEqual(stat.S_IMODE(image.stat().st_mode), 0o644)
                self.assertEqual(image.read_bytes(), PNG)
                # Cache-busting query strings must not route images into the CMS.
                connection.request('GET', url + '?v=123')
                response = connection.getresponse()
                self.assertEqual(response.status, 200)
                self.assertEqual(response.getheader('Content-Type'), 'image/png')
                self.assertEqual(response.read(), PNG)
                # A renamed text file must not be saved as a featured image.
                invalid = body.replace(PNG, b'not an image')
                connection.request('POST', '/upload.php', invalid,
                                   {'Content-Type': f'multipart/form-data; boundary={boundary}'})
                response = connection.getresponse()
                self.assertEqual(response.status, 400)
                response.read()
                self.assertEqual(len(list((root / 'assets/uploads').iterdir())), 1)
                connection.close()
            finally:
                process.terminate()
                process.wait(timeout=5)


if __name__ == '__main__':
    unittest.main()
