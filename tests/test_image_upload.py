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
            shutil.copy(ROOT / 'media.php', root / 'media.php')
            (root / 'index.php').write_text('<?php http_response_code(404); echo "CMS fallback";')
            (root / 'upload.php').write_text('''<?php
require __DIR__ . '/includes/functions.php';
// Model hosts whose temporary uploads are private to the PHP user.
chmod($_FILES['image']['tmp_name'], 0600);
$upload_error = null;
$result = upload_image($_FILES['image'], 'uploads/', $upload_error);
if (!$result) { http_response_code(400); exit($upload_error); }
echo version_image_url($result);
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
                from urllib.parse import parse_qs, urlparse
                self.assertTrue(url.startswith('/media.php?path='), url)
                image_path = parse_qs(urlparse(url).query)['path'][0]
                image = root / image_path
                self.assertEqual(stat.S_IMODE(image.stat().st_mode), 0o644)
                self.assertEqual(image.read_bytes(), PNG)
                connection.request('GET', url)
                response = connection.getresponse()
                self.assertEqual(response.status, 200)
                self.assertEqual(response.getheader('Content-Type'), 'image/png')
                self.assertEqual(response.getheader('X-Content-Type-Options'), 'nosniff')
                etag = response.getheader('ETag')
                self.assertEqual(response.read(), PNG)
                connection.request('HEAD', url)
                response = connection.getresponse()
                self.assertEqual(response.status, 200)
                self.assertEqual(int(response.getheader('Content-Length')), len(PNG))
                self.assertEqual(response.read(), b'')
                connection.request('GET', url, headers={'If-None-Match': etag})
                response = connection.getresponse()
                self.assertEqual(response.status, 304)
                self.assertEqual(response.read(), b'')
                # Traversal, non-images, remote URLs and symlinks must never
                # expose configuration, source code or files outside uploads.
                (root / 'assets/uploads/fake.jpg').write_text('<?php secret();')
                (root / 'secret.jpg').write_bytes(PNG)
                (root / 'assets/uploads/link.jpg').symlink_to(root / 'secret.jpg')
                from urllib.parse import urlencode
                for bad_path in ['../includes/config.php', 'assets/uploads/../../secret.jpg',
                                 'assets/uploads/fake.jpg', 'assets/uploads/link.jpg',
                                 'https://example.com/photo.jpg', 'assets/uploads/missing.jpg']:
                    connection.request('GET', '/media.php?' + urlencode({'path': bad_path}))
                    response = connection.getresponse()
                    self.assertEqual(response.status, 404, bad_path)
                    response.read()
                (root / 'assets/uploads/fake.jpg').unlink()
                (root / 'assets/uploads/link.jpg').unlink()
                # A renamed text file must not be saved as a featured image.
                invalid = body.replace(PNG, b'not an image')
                connection.request('POST', '/upload.php', invalid,
                                   {'Content-Type': f'multipart/form-data; boundary={boundary}'})
                response = connection.getresponse()
                self.assertEqual(response.status, 400)
                self.assertIn('file contents', response.read().decode())
                self.assertEqual(len(list((root / 'assets/uploads').iterdir())), 1)
                # A supported image with a mismatched filename is normalized,
                # not rejected just because PNG bytes were named .jpg.
                renamed = body.replace(b'filename="featured.png"', b'filename="featured.jpg"')
                connection.request('POST', '/upload.php', renamed,
                                   {'Content-Type': f'multipart/form-data; boundary={boundary}'})
                response = connection.getresponse()
                normalized_url = response.read().decode()
                self.assertEqual(response.status, 200, normalized_url)
                normalized_path = parse_qs(urlparse(normalized_url).query)['path'][0]
                self.assertTrue(normalized_path.endswith('.png'), normalized_path)
                self.assertEqual((root / normalized_path).read_bytes(), PNG)
                connection.close()
            finally:
                process.terminate()
                process.wait(timeout=5)


if __name__ == '__main__':
    unittest.main()
