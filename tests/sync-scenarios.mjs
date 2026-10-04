// Isolated PHP behavior checks. Usage: node tests/sync-scenarios.mjs <directory-with-php-parser-and-@php-wasm>
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';

const dependencyDir = process.argv[2];
if (!dependencyDir) throw new Error('Pass the directory containing node_modules.');
const require = createRequire(path.join(path.resolve(dependencyDir), 'package.json'));
const Parser = require('php-parser');
const modules = path.join(path.resolve(dependencyDir), 'node_modules');
const { PHP } = await import(pathToFileURL(path.join(modules, '@php-wasm/universal/index.js')));
const { loadNodeRuntime } = await import(pathToFileURL(path.join(modules, '@php-wasm/node/index.js')));
const php = new PHP(await loadNodeRuntime('8.3', { emscriptenOptions: { processId: 1 } }));

const themeDir = new URL('..', import.meta.url);
for (const filename of fs.readdirSync(themeDir).filter((name) => name.endsWith('.php'))) {
  const contents = fs.readFileSync(new URL(filename, themeDir));
  const parsed = await php.run({ code: `<?php token_get_all(base64_decode('${contents.toString('base64')}'), TOKEN_PARSE); echo 'OK';` });
  assert.equal(parsed.text, 'OK', `PHP 8.3 parse failed: ${filename}`);
}
console.log('PASS: PHP 8.3 syntax for all top-level theme files');

const source = fs.readFileSync(new URL('../functions.php', import.meta.url), 'utf8');
const ast = new Parser.Engine({ ast: { withPositions: true } }).parseCode(source);
const names = [
  'manga_supported_image_files', 'manga_imported_source_files',
  'manga_get_chapter_image_urls', 'manga_set_cover_from_folder',
  'manga_new_source_stable', 'manga_audit_imported_sources',
];
const functions = names.map((name) => {
  const node = ast.children.find((item) => item.kind === 'function' && item.name.name === name);
  assert.ok(node, `Missing function ${name}`);
  return source.slice(node.loc.start.offset, node.loc.end.offset);
}).join('\n');

const fixture = `<?php
define('DAY_IN_SECONDS', 86400);
class WP_Error { public function __construct(public $code, public $message) {} public function get_error_message() { return $this->message; } }
function is_wp_error($value) { return $value instanceof WP_Error; }
function absint($value) { return abs((int) $value); }
function trailingslashit($value) { return rtrim($value, '/') . '/'; }
function wp_normalize_path($value) { return str_replace('\\\\', '/', $value); }
function wp_json_encode($value) { return json_encode($value); }
function get_manga_base_url() { return 'https://example.test/manga/'; }
function manga_public_url($value) { return $value; }
function add_query_arg($key, $value, $url) { return $url . '?'. $key . '=' . $value; }
function manga_source_file_url($value) { return get_manga_base_url() . basename($value); }
function manga_resolve_source_folder($value, $chapter) { return is_dir($value) ? array('path' => $value) : new WP_Error('missing', 'missing'); }
$meta = array(); $transients = array(); $options = array();
function get_post_meta($id, $key, $single = true) { global $meta; return $meta[$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { global $meta; $meta[$id][$key] = $value; }
function delete_post_meta($id, $key) { global $meta; unset($meta[$id][$key]); }
function get_transient($key) { global $transients; return $transients[$key] ?? false; }
function set_transient($key, $value, $expiry) { global $transients; $transients[$key] = $value; }
function get_option($key, $default = false) { global $options; return $options[$key] ?? $default; }
function update_option($key, $value, $autoload = false) { global $options; $options[$key] = $value; }
function get_posts($args) { return array(1); }
${functions}
mkdir('/test/series/chapter', 0777, true);
update_post_meta(1, 'imported_from_path', '/test/series/chapter');
update_post_meta(1, 'image_links', "https://example.test/manga/01.jpg\\nhttps://example.test/manga/02.jpg");
file_put_contents('/test/series/chapter/01.jpg', 'one');
$partial = manga_get_chapter_image_urls(1);
file_put_contents('/test/series/chapter/02.jpg', 'two');
$complete = manga_get_chapter_image_urls(1);
unlink('/test/series/chapter/01.jpg'); unlink('/test/series/chapter/02.jpg');
$missing = manga_get_chapter_image_urls(1);
$no_cover = manga_set_cover_from_folder(2, '/test/series');
file_put_contents('/test/series/cover.jpg', 'first');
manga_set_cover_from_folder(2, '/test/series');
$cover_first = get_post_meta(2, '_manga_cover_url');
file_put_contents('/test/series/cover.jpg', 'second');
manga_set_cover_from_folder(2, '/test/series');
$cover_second = get_post_meta(2, '_manga_cover_url');
unlink('/test/series/cover.jpg'); manga_set_cover_from_folder(2, '/test/series');
$cover_removed = get_post_meta(2, '_manga_cover_url');
update_post_meta(3, '_manga_cover_url', 'https://manual.test/cover.jpg');
file_put_contents('/test/series/cover.jpg', 'third'); manga_set_cover_from_folder(3, '/test/series');
$manual_cover = get_post_meta(3, '_manga_cover_url');
$files = array('/test/series/cover.jpg');
$stable_first = manga_new_source_stable('/test/series', '', $files);
$stable_second = manga_new_source_stable('/test/series', '', $files);
$key = 'manga_new_source_' . md5('/test/series|');
$transients[$key]['first_seen'] -= 301;
$stable_after_wait = manga_new_source_stable('/test/series', '', $files);
$audit_missing = manga_audit_imported_sources();
$warning_count = get_post_meta(1, '_manga_source_missing_checks');
file_put_contents('/test/series/chapter/01.jpg', 'restored');
$audit_restored = manga_audit_imported_sources();
$warning_cleared = get_post_meta(1, '_manga_source_health');
echo json_encode(compact('partial', 'complete', 'missing', 'no_cover', 'cover_first', 'cover_second', 'cover_removed', 'manual_cover', 'stable_first', 'stable_second', 'stable_after_wait', 'audit_missing', 'warning_count', 'audit_restored', 'warning_cleared'));
`;
const result = await php.run({ code: fixture });
assert.equal(result.errors, '', result.errors);
const value = JSON.parse(result.text);
assert.deepEqual(value.partial, []);
assert.equal(value.complete.length, 2);
assert.deepEqual(value.missing, []);
assert.equal(value.no_cover, false);
assert.notEqual(value.cover_first, value.cover_second);
assert.equal(value.cover_removed, '');
assert.equal(value.manual_cover, 'https://manual.test/cover.jpg');
assert.equal(value.stable_first, false);
assert.equal(value.stable_second, false);
assert.equal(value.stable_after_wait, true);
assert.equal(value.audit_missing.missing, 1);
assert.equal(value.warning_count, 1);
assert.equal(value.audit_restored.missing, 0);
assert.equal(value.warning_cleared, '');
console.log('PASS: missing/partial images, late/replaced/removed/manual covers, stable-source gate, missing-source warning and recovery');
