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
assert.doesNotMatch(source, /'type' => 'DECIMAL'/);
const ast = new Parser.Engine({ ast: { withPositions: true } }).parseCode(source);
const names = [
  'manga_supported_image_files', 'manga_imported_source_files',
  'manga_get_chapter_image_urls', 'manga_set_cover_from_folder',
  'manga_new_source_stable', 'manga_audit_imported_sources',
  'manga_source_exclusion_key', 'manga_remember_deleted_import',
  'manga_sync_imported_chapter_images', 'manga_relink_renamed_chapter',
  'manga_normalize_chapter_text', 'manga_parse_chapter_title',
  'manga_chapter_numbers_for_post', 'manga_find_chapter_number_conflict',
  'manga_sort_chapter_posts',
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
function wp_parse_url($value, $part) { return parse_url($value, $part); }
function get_manga_base_url() { return 'https://example.test/manga/'; }
function manga_public_url($value) { return $value; }
function add_query_arg($key, $value, $url) { return $url . '?'. $key . '=' . $value; }
function manga_source_file_url($value) { return get_manga_base_url() . ltrim(substr($value, strlen('/test/')), '/'); }
function manga_resolve_source_folder($value, $chapter) { return is_dir($value) ? array('path' => $value) : new WP_Error('missing', 'missing'); }
$meta = array(); $transients = array(); $options = array(); $posts = array();
function get_post_meta($id, $key, $single = true) { global $meta; return $meta[$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { global $meta; $meta[$id][$key] = $value; }
function delete_post_meta($id, $key) { global $meta; unset($meta[$id][$key]); }
function get_transient($key) { global $transients; return $transients[$key] ?? false; }
function set_transient($key, $value, $expiry) { global $transients; $transients[$key] = $value; }
function delete_transient($key) { global $transients; unset($transients[$key]); }
function get_option($key, $default = false) { global $options; return $options[$key] ?? $default; }
function update_option($key, $value, $autoload = false) { global $options; $options[$key] = $value; }
function get_posts($args) { global $queryCandidates; return $queryCandidates ?? array(1); }
function get_post($id) { global $posts; return $posts[$id] ?? null; }
function get_post_status($id) { return 'publish'; }
function get_the_title($id) { global $titles; return $titles[$id] ?? 'Series - Ch 1'; }
function current_time($type) { return '2026-10-04 00:00:00'; }
function mark_chapter_as_imported($path) {}
${functions}
mkdir('/test/series/chapter', 0777, true);
$thai_number = manga_parse_chapter_title('ตอนที่1');
$fractional_numbers = array_map(function($label) {
    return manga_parse_chapter_title($label)['chapter'];
}, array('ตอนที่ 1', 'ตอนที่ 1.1', 'ตอนที่ 1.2', 'ตอนที่ 2', 'ตอนที่ 65.5', 'ตอนที่ 66', 'ตอนที่ 75.5', 'ตอนที่ 76'));
$titles = array(11 => 'ตอนที่ 1', 12 => 'ตอนที่ 1.1', 13 => 'ตอนที่ 1.2', 14 => 'ตอนที่ 2');
$sorted_fractional_posts = manga_sort_chapter_posts(array(
    (object) array('ID' => 12), (object) array('ID' => 14),
    (object) array('ID' => 11), (object) array('ID' => 13),
));
$sorted_fractional_ids = array_map(function($post) { return $post->ID; }, $sorted_fractional_posts);
$titles[21] = 'Series - ตอนที่ 65.5';
$titles[22] = 'Series - ตอนที่ 66';
$titles[23] = 'Series - ตอนที่ 75.5';
$titles[24] = 'Series - ตอนที่ 76';
$queryCandidates = array(21, 22, 23, 24);
$fractional_conflicts = array_map(function($number) {
    return manga_find_chapter_number_conflict(7, array('volume' => 0, 'chapter' => $number));
}, array(65.5, 66, 75.5, 76, 65.6));
$legacy_title_conflict = manga_find_chapter_number_conflict(7, array('volume' => 0, 'chapter' => 65.5), array(21));
$different_volume_conflict = manga_find_chapter_number_conflict(7, array('volume' => 1, 'chapter' => 65.5), array(21));
$queryCandidates = array(1);
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
$warning_type_empty = get_post_meta(1, '_manga_source_health');
file_put_contents('/test/series/chapter/01.jpg', 'restored');
$audit_restored = manga_audit_imported_sources();
$warning_cleared = get_post_meta(1, '_manga_source_health');
unlink('/test/series/chapter/01.jpg'); rmdir('/test/series/chapter');
mkdir('/test/series/ตอนที่1'); file_put_contents('/test/series/ตอนที่1/page01.png', 'renamed');
$audit_renamed = manga_audit_imported_sources();
$warning_type_renamed = get_post_meta(1, '_manga_source_health');
update_post_meta(1, 'image_links', 'https://example.test/manga/series/chapter/page01.png');
$relink_ambiguous = manga_relink_renamed_chapter('/test/series/ตอนที่1', '', array('volume' => 0, 'chapter' => 1), array(1, 2));
$relink_ambiguous_diagnostic = manga_relink_renamed_chapter('/test/series/ตอนที่1', '', array('volume' => 0, 'chapter' => 1), array(1, 2), true);
$saved_relink_links = get_post_meta(1, 'image_links');
update_post_meta(1, 'image_links', 'https://example.test/manga/series/chapter/other.png');
$relink_wrong_pages = manga_relink_renamed_chapter('/test/series/ตอนที่1', '', array('volume' => 0, 'chapter' => 1), array(1));
$relink_wrong_pages_diagnostic = manga_relink_renamed_chapter('/test/series/ตอนที่1', '', array('volume' => 0, 'chapter' => 1), array(1), true);
update_post_meta(1, 'image_links', $saved_relink_links);
$relink_waiting = manga_relink_renamed_chapter('/test/series/ตอนที่1', '', array('volume' => 0, 'chapter' => 1), array(1));
$relink_key = 'manga_new_source_' . md5('/test/series/ตอนที่1|');
$transients[$relink_key]['first_seen'] -= 301;
$relinked = manga_relink_renamed_chapter('/test/series/ตอนที่1', '', array('volume' => 0, 'chapter' => 1), array(1));
$relinked_path = get_post_meta(1, 'imported_from_path');
$relinked_links = get_post_meta(1, 'image_links');
$posts[4] = (object) array('post_type' => 'chapter');
update_post_meta(4, 'imported_from_path', '/test/series/chapter');
manga_remember_deleted_import(4);
$chapter_excluded = get_option(manga_source_exclusion_key('chapter', '/test/series/chapter')) > 0;
$posts[5] = (object) array('post_type' => 'manga');
update_post_meta(5, '_manga_source_path', '/test/series');
manga_remember_deleted_import(5);
$series_excluded = get_option(manga_source_exclusion_key('series', '/test/series')) > 0;
echo json_encode(compact('thai_number', 'fractional_numbers', 'sorted_fractional_ids', 'fractional_conflicts', 'legacy_title_conflict', 'different_volume_conflict', 'partial', 'complete', 'missing', 'no_cover', 'cover_first', 'cover_second', 'cover_removed', 'manual_cover', 'stable_first', 'stable_second', 'stable_after_wait', 'audit_missing', 'warning_count', 'warning_type_empty', 'audit_restored', 'warning_cleared', 'audit_renamed', 'warning_type_renamed', 'relink_ambiguous', 'relink_ambiguous_diagnostic', 'relink_wrong_pages', 'relink_wrong_pages_diagnostic', 'relink_waiting', 'relinked', 'relinked_path', 'relinked_links', 'chapter_excluded', 'series_excluded'));
`;
const result = await php.run({ code: fixture });
assert.equal(result.errors, '', result.errors);
const value = JSON.parse(result.text);
assert.equal(value.thai_number.chapter, 1);
assert.equal(value.thai_number.confidence, 'high');
assert.deepEqual(value.fractional_numbers, [1, 1.1, 1.2, 2, 65.5, 66, 75.5, 76]);
assert.deepEqual(value.sorted_fractional_ids, [14, 13, 12, 11]);
assert.deepEqual(value.fractional_conflicts, [21, 22, 23, 24, 0]);
assert.equal(value.legacy_title_conflict, 21);
assert.equal(value.different_volume_conflict, 0);
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
assert.equal(value.warning_type_empty, 'images_unavailable');
assert.equal(value.audit_restored.missing, 0);
assert.equal(value.warning_cleared, '');
assert.equal(value.audit_renamed.missing, 1);
assert.equal(value.warning_type_renamed, 'folder_unavailable');
assert.equal(value.relink_ambiguous, false);
assert.equal(value.relink_ambiguous_diagnostic.code, 'manga_relink_ambiguous');
assert.equal(value.relink_wrong_pages, false);
assert.equal(value.relink_wrong_pages_diagnostic.code, 'manga_relink_page_names');
assert.equal(value.relink_waiting.code, 'manga_relink_waiting');
assert.equal(value.relinked, true);
assert.equal(value.relinked_path, '/test/series/ตอนที่1');
assert.match(value.relinked_links, /ตอนที่1\/page01\.png/);
assert.equal(value.chapter_excluded, true);
assert.equal(value.series_excluded, true);
console.log('PASS: fractional chapter numbers, missing/partial images, cover changes, stable-source gate, Thai-folder relink safety and recovery, deletion exclusions');
