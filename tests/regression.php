<?php
/** Run with: php tests/regression.php. No WordPress database is required. */
function add_action(...$args) {}
function add_filter(...$args) {}
function trailingslashit($path) { return rtrim($path, '/\\') . '/'; }
function wp_normalize_path($path) { return str_replace('\\', '/', $path); }
function wp_cache_delete(...$args) {}

$test_options = array();
$test_chapters = array();
$test_manga_posts = array();
$test_manga_meta = array();
$test_replace_lock_before_delete = null;
$test_chapter_queries = 0;
$test_latest_rows = array();
$test_chapter_posts = array();

function add_option($key, $value, ...$args) {
    global $test_options;
    if (array_key_exists($key, $test_options)) return false;
    $test_options[$key] = $value;
    return true;
}
function get_option($key, $default = false) {
    global $test_options;
    return $test_options[$key] ?? $default;
}
function get_posts($args) {
    global $test_chapters, $test_manga_posts, $test_chapter_queries, $test_chapter_posts;
    if (($args['post_type'] ?? '') === 'manga') return $test_manga_posts;
    if (($args['post_type'] ?? '') !== 'chapter') return array();
    if (isset($args['post__in'])) return array_values(array_intersect_key($test_chapter_posts, array_flip($args['post__in'])));
    $test_chapter_queries++;
    $ids = array();
    foreach ($test_chapters as $id => $meta) {
        if (isset($args['meta_value']) && ($meta['imported_from_path'] ?? '') === $args['meta_value']) $ids[] = $id;
        if (isset($args['meta_query'])) {
            $query = $args['meta_query'][0];
            if ($query['key'] === 'imported_from_path' &&
                in_array($meta['imported_from_path'] ?? '', (array) $query['value'], true)) {
                $ids[] = $id;
            } elseif ($query['key'] === 'connected_manga_id' &&
                in_array((int) ($meta['connected_manga_id'] ?? 0), (array) $query['value'], true)) {
                $ids[] = (object) array('ID' => $id);
            }
        }
    }
    return $ids;
}
function get_post_meta($id, $key, $single = true) {
    global $test_chapters, $test_manga_meta;
    return $test_chapters[$id][$key] ?? $test_manga_meta[$id][$key] ?? '';
}
function get_the_title($id) { global $test_chapter_posts; return $test_chapter_posts[$id]->post_title ?? ''; }
function get_the_date($format, $id) { global $test_chapter_posts; return $test_chapter_posts[$id]->post_date ?? ''; }
function update_post_meta($id, $key, $value) {
    global $test_chapters, $test_manga_meta;
    if (isset($test_chapters[$id])) $test_chapters[$id][$key] = $value;
    else $test_manga_meta[$id][$key] = $value;
}
function update_option($key, $value, ...$args) {
    global $test_options;
    $test_options[$key] = $value;
}
function current_time($format) { return gmdate('Y-m-d H:i:s'); }
class WP_Error {
    public $code;
    public $message;
    public function __construct($code, $message) { $this->code = $code; $this->message = $message; }
    public function get_error_message() { return $this->message; }
}

class FakeWpdb {
    public $options = 'wp_options';
    public $posts = 'wp_posts';
    public $postmeta = 'wp_postmeta';
    public function get_results($prepared) { global $test_latest_rows; return $test_latest_rows; }
    public function prepare($query, ...$args) { return array($query, $args); }
    public function query($prepared) {
        global $test_options, $test_replace_lock_before_delete;
        list($query, $args) = $prepared;
        list($key, $expected) = $args;
        if ($test_replace_lock_before_delete !== null) {
            $test_options[$key] = $test_replace_lock_before_delete;
            $test_replace_lock_before_delete = null;
        }
        if ((string) ($test_options[$key] ?? '') !== $expected) return 0;
        unset($test_options[$key]);
        return 1;
    }
}
$wpdb = new FakeWpdb();

require dirname(__DIR__) . '/functions.php';

function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}

$fresh = manga_acquire_import_lock('fresh');
check(is_string($fresh) && isset($test_options[$fresh]), 'fresh lock');
$stale_key = 'manga_import_lock_' . md5('stale');
$test_options[$stale_key] = time() - 301;
check(manga_acquire_import_lock('stale') === $stale_key, 'stale lock recovery');
$race_key = 'manga_import_lock_' . md5('race');
$test_options[$race_key] = time() - 301;
$test_replace_lock_before_delete = time();
check(manga_acquire_import_lock('race') === false, 'must not steal a renewed lock');
check($test_options[$race_key] > time() - 300, 'renewed lock must remain');

$chapters = array(
    array('info' => array('sort_key' => 1.1)),
    array('info' => array('sort_key' => 1.3)),
    array('info' => array('sort_key' => 1.2)),
);
manga_sort_chapter_info($chapters);
check(array_column(array_column($chapters, 'info'), 'sort_key') === array(1.3, 1.2, 1.1), 'fractional chapter order');

$page = manga_archive_page_slice(range(1, 45), 2, 20);
check($page['total_pages'] === 3 && count($page['items']) === 20 && $page['items'][0] === 21, 'archive second page');
$last = manga_archive_page_slice(range(1, 45), 99, 20);
check($last['page'] === 3 && $last['items'] === array(41, 42, 43, 44, 45), 'archive page bounds');

$series = sys_get_temp_dir() . '/manga-regression-' . getmypid();
$volume_one = $series . '/Vol 1';
$volume_two = $series . '/Vol 2';
mkdir($series);
mkdir($volume_one);
mkdir($volume_two);
$files = array('Vol 1/c11_001.jpg', 'Vol 1/c11_002.jpg', 'Vol 1/c12_001.jpg', 'Vol 1/c12_002.jpg', 'Vol 2/001.jpg', 'Vol 2/002.jpg');
try {
    foreach ($files as $file) file_put_contents($series . '/' . $file, 'image');
    $sources = manga_scan_auto_chapter_sources($series);
    check(count($sources) === 3, 'two chapter groups and one volume source');
    $targeted = manga_scan_auto_chapter_sources($series, $volume_one);
    check(count($targeted) === 2 && array_column($targeted, 'source_group') === array('c11', 'c12'),
        'event scan reads only the changed folder');
    check(manga_new_source_stable($volume_one, 'c11', array(), true), 'signed watcher bypasses delayed second scan');
    check(array_column($sources, 'source_group') === array('c11', 'c12', 'volume'), 'source group detection');
    check(manga_find_auto_source($series, realpath($volume_one), 'c12')['name'] === 'Vol. 1 Ch. 12',
        'manual import selects the requested group');
    check(manga_find_auto_source($series, realpath($volume_two), 'volume')['name'] === 'Vol. 2',
        'manual import selects a whole volume');
    check(manga_find_auto_source($series, realpath($volume_one), 'c99') === null,
        'manual import rejects an unknown group');
    check(count(manga_manual_import_sources($series)) === 3, 'manual importer lists every source');
    $test_chapters[1] = array('imported_from_path' => realpath($volume_one), '_manga_source_group' => 'c11');
    $test_chapter_queries = 0;
$test_latest_rows = array();
$test_chapter_posts = array();
    $remaining = manga_manual_import_sources($series);
    check(count($remaining) === 2 && $remaining[0]['source_group'] === 'c12', 'imported group does not hide sibling');
    check($test_chapter_queries === 1, 'manual source scan uses one chapter query');
    check(count(manga_imported_source_files($volume_one, 'c12')) === 2, 'grouped source files');
    check(manga_parse_chapter_title('Vol. 2')['volume'] === 2.0, 'volume-only number');
    check(manga_relink_numbers_match(array('volume' => 11, 'chapter' => 53), array('volume' => 0, 'chapter' => 53)),
        'flat chapter folder can match a moved volume chapter');
    check(!manga_relink_numbers_match(array('volume' => 11, 'chapter' => 53), array('volume' => 0, 'chapter' => 54)),
        'moved volume relink requires the exact chapter number');
    check(!manga_relink_numbers_match(array('volume' => 11, 'chapter' => 53), array('volume' => 10, 'chapter' => 53)),
        'different explicit volume remains a conflict');
    check(!manga_relink_numbers_match(array('volume' => 0, 'chapter' => 53), array('volume' => 11, 'chapter' => 53)),
        'relink does not invent a volume for flat sources');

    $test_manga_posts = array(
        (object) array('ID' => 10, 'post_title' => 'Series', 'post_content' => ''),
        (object) array('ID' => 20, 'post_title' => 'Series', 'post_content' => ''),
    );
    $test_manga_meta = array(10 => array('_manga_source_path' => $series),
        20 => array('_manga_source_path' => $series));
    $test_chapters = array(
        101 => array('connected_manga_id' => 10, 'imported_from_path' => realpath($volume_one), '_manga_source_group' => 'c11'),
        102 => array('connected_manga_id' => 20, 'imported_from_path' => realpath($volume_one), '_manga_source_group' => 'c12'),
    );
    $resolved = array('series_path' => $series, 'series_name' => 'Series');
    check(manga_reconcile_imported_series($resolved, 10) === true, 'distinct groups may share a folder');
    check($test_chapters[102]['connected_manga_id'] === 10 &&
        $test_manga_meta[20]['_manga_duplicate_of'] === 10, 'duplicate series links repaired');
    $test_options = array();
    unset($test_manga_meta[20]['_manga_duplicate_of']);
    $test_chapters[102]['connected_manga_id'] = 20;
    $test_chapters[102]['_manga_source_group'] = 'c11';
    $conflict = manga_reconcile_imported_series($resolved, 10);
    check($conflict instanceof WP_Error && $conflict->code === 'manga_repair_conflict',
        'same folder and group remains a conflict');
    $test_manga_posts = array(
        (object) array('ID' => 10, 'post_title' => 'Vol 1', 'post_content' => ''),
        (object) array('ID' => 20, 'post_title' => 'Vol 2', 'post_content' => 'Imported from folder: Vol 2'),
    );
    $test_manga_meta = array(10 => array('_manga_source_path' => wp_normalize_path($volume_one)));
    $test_chapters = array(
        301 => array('chapter_number' => 13, 'volume_number' => 0),
        302 => array('chapter_number' => 0, 'volume_number' => 2),
    );
    $test_latest_rows = array(
        (object) array('manga_id' => 10, 'chapter_id' => 301),
        (object) array('manga_id' => 20, 'chapter_id' => 302),
    );
    $test_chapter_posts = array(
        301 => (object) array('ID' => 301, 'post_title' => 'Chapter 13', 'post_date' => '2026-10-10 09:00'),
        302 => (object) array('ID' => 302, 'post_title' => 'Vol. 2', 'post_date' => '2026-10-09 21:00'),
    );
    $latest = manga_folder_import_latest_chapters(array('Vol 1', 'Vol 2', 'Unpublished'), $series);
    check($latest['Vol 1']['label'] === 'Ch. 13' && $latest['Vol 1']['date'] === '2026-10-10 09:00',
        'admin list shows the latest published chapter and date');
    check($latest['Vol 2']['label'] === 'Vol. 2' && !isset($latest['Unpublished']),
        'admin list supports legacy folder identity and unpublished folders');

} finally {
    foreach ($files as $file) unlink($series . '/' . $file);
    rmdir($volume_one);
    rmdir($volume_two);
    rmdir($series);
}

echo "Regression checks passed.\n";
