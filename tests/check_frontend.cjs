// Static regression checks, runnable without PHP or a browser.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const Engine = require('php-parser');
const parser = new Engine({parser: {php7: true}});
let count = 0;
for (const dir of ['includes', 'pages', 'admin', 'install']) {
  for (const file of fs.readdirSync(dir)) {
    if (!file.endsWith('.php')) continue;
    parser.parseCode(fs.readFileSync(path.join(dir, file), 'utf8'), file);
    count++;
  }
}
for (const file of ['includes/header.php', 'admin/header.php']) {
  const source = fs.readFileSync(file, 'utf8');
  assert(!source.includes('cdn.tailwindcss.com'), `${file} must not compile layout in the browser`);
  assert(source.includes('/assets/css/site.css'), `${file} must load the built stylesheet`);
}
const css = fs.readFileSync('assets/css/site.css', 'utf8');
for (const selector of ['.h-full', '.w-full', '.object-cover', '.absolute', '.relative', '.flex', '.min-h-\\[480px\\]', '.aspect-\\[4\\/3\\]']) {
  assert(css.includes(selector), `Missing critical layout selector: ${selector}`);
}
console.log(`Parsed ${count} PHP templates; local stylesheet and critical media layout checks passed.`);
