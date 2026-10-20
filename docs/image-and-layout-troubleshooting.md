# Featured images and consistent page layout

## Deploying this update

Deploy `assets/css/site.css` together with the PHP changes. This committed stylesheet replaces Tailwind's browser-side CDN compiler in both public and admin headers. No Node.js installation is needed on the web host. When changing templates locally, run `npm ci` and `npm run build:css`, and deploy the rebuilt CSS too.

Keep the existing `.htaccess` when deploying to Apache. Nginx needs an equivalent `try_files $uri $uri/ /index.php?$query_string;` rule in its site configuration; `.htaccess` has no effect on Nginx. Do not replace an existing hosting configuration blindly.

Clear hosting/CDN page caches after deployment. Existing missing or invalid images are not recreated by an application update. Re-upload a valid supported image and check for a success or upload error message. The admin now refuses to save a post when its selected image upload fails, rather than silently saving an empty or stale image URL.

## If an existing image still fails

In the browser, copy the broken image's actual URL (not just the page URL). Open it directly or inspect its Network response:

- **404:** Compare the URL's path and exact filename/case with the actual file under the site's document root. Check that the deployment did not omit uploads and that the domain points at the correct document root.
- **403:** Check directory traversal permissions, file ownership, hotlink protection and host security rules. Files should normally be 0644 and public directories 0755; never use 0777.
- **200 with HTML:** The request is being routed into the CMS or to a hosting error page instead of serving the file. Check the rewrite/static-file rules.
- **200 with image bytes but no display:** Check the actual image format, file integrity, Content-Type and browser console for CSP/mixed-content blocking.
- **5xx or incomplete HTML:** Check PHP/web-server error logs for the matching request. A screenshot alone cannot identify a server timeout or fatal error.

The URL helper repairs relative `assets/...` paths and previously HTML-escaped query strings. It does not guess at missing filenames or rewrite external image domains.

For unresolved reports, provide the public page URL, broken image URL, response status/Content-Type, and server type. Do not provide admin credentials.

## Tests

- `npm run build:css`
- `npm run test:frontend` — PHP syntax parsing and checks for local CSS/critical media layout rules.
- `python3 -m unittest discover -s tests -p 'test_image_upload.py' -v` — requires PHP CLI; verifies a real multipart upload, file permissions, static image delivery with a query string, and invalid-file rejection in an isolated temporary installation.

These checks do not replace testing the deployed host's permissions, rewrites, CSP, and cache configuration.
