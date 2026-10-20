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

## Hosts that cannot deliver uploads as static files

Local `/assets/uploads/...` URLs now render through `media.php?path=assets%2Fuploads%2F...&v=...`. Stored database paths and existing files are unchanged. The endpoint runs as the PHP user, bypasses static-file handling, and requires no database connection or session. It only serves recognized raster images within the real uploads directory; traversal, symlinks escaping uploads, source files and remote URLs are rejected.

Deploy **`media.php`**, `includes/functions.php`, and `pages/home.php` together and clear page caches. The endpoint is a compatibility workaround, not a diagnosis of the host's static-file failure. If the image still fails, open the `media.php` URL directly and inspect its HTTP status. A 404 means PHP cannot locate/read the file or recognize it as a supported image; a 5xx needs the hosting PHP error log. Also check for WAF rules affecting the endpoint.

For the reported file, the direct compatibility URL is:

`https://expertwealthinfo.com/media.php?path=assets%2Fuploads%2Fb7e603771d59dc4e_1792485413.jpg`

## A .jpg upload is rejected

The upload handler reports the specific failure instead of one generic message. It distinguishes PHP upload limits, partial transfers, missing temporary directories, disk write failures, unrecognized image contents, destination permissions, failed moves and failed permission changes. The post stays unsaved on failure.

A recognized PNG, WebP or other supported image renamed to `.jpg` is now saved using its detected format's extension. This does not convert or re-encode image bytes. Unsupported or unrecognized contents still need to be exported as JPEG/PNG in an image editor. Deploy `includes/functions.php` and `admin/posts.php` together, retry, and use the precise error to resolve any remaining hosting issue. Do not make uploads world-writable (0777).

## Keep drafts when saving fails

The create/edit post forms submit in the background using `assets/js/post-editor.js`. On upload, server, session or network errors, the same modal remains open with the current text and selected file intact; the error appears inside the modal. Submission buttons are disabled while saving and re-enabled for retry. Only a confirmed success refreshes the registry. An uncertain network/server response warns the author to check the registry before retrying, because a save might already have completed.

For ordinary failed POSTs, received text fields are restored and the corresponding editor is reopened. Browsers cannot restore file selections after a full-page navigation, so this fallback asks the author to reselect the image. If PHP discards an oversized request entirely, only the enhanced background submission can retain the original fields.

Deploy `assets/js/post-editor.js` together with `admin/posts.php`. Run `npm run test:editor` for the editor unit regression checks.
