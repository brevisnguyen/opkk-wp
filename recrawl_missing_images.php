<?php
/**
 * Crawl lại thumb & poster từ API KKPhim cho các phim đang thiếu ophim_thumb_url hoặc ophim_poster_url
 * (không có meta, NULL hoặc rỗng). Chỉ làm mới ảnh, không đụng tới tập phim và các meta khác.
 *
 * Chạy CLI:
 *   php recrawl_missing_images.php <secret_key> [--dry-run] [--limit=N] [--sleep=MS]
 */
require_once __DIR__ . '/helpers/drop-root.php';
require_once __DIR__ . '/../../../wp-load.php';
require_once __DIR__ . '/../../../wp-admin/includes/image.php';
require_once __DIR__ . '/../../../wp-admin/includes/file.php';

set_time_limit(0);
require_once CRAWL_OPHIM_PATH . 'define.php';
require_once CRAWL_OPHIM_PATH . 'helpers/functions.php';
require_once CRAWL_OPHIM_PATH . 'crawl_movies.php';
require_once CRAWL_OPHIM_PATH . 'crawl_kk_movies.php';

if (php_sapi_name() !== 'cli') exit('CLI only');
// Secret key khác nhau theo từng site; chạy hàng loạt thì đặt OPKK_SKIP_KEY=1
if (getenv('OPKK_SKIP_KEY') !== '1' && (!isset($argv[1]) || $argv[1] != get_option(CRAWL_KK_OPTION_SECRET_KEY, 'secret_key'))) exit("Invalid secret key\n");

$dry_run = in_array('--dry-run', $argv, true);
$limit = 0;
$sleep_ms = 200;
foreach ($argv as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) $limit = (int)$m[1];
    if (preg_match('/^--sleep=(\d+)$/', $arg, $m)) $sleep_ms = (int)$m[1];
}

$crawl_settings = json_decode(get_option(CRAWL_KK_OPTION_SETTINGS, false));

global $wpdb;
$rows = $wpdb->get_results("
    SELECT p.ID AS id, p.post_name, t.meta_value AS thumb, po.meta_value AS poster,
           fu.meta_value AS fetch_url, fi.meta_value AS fetch_id
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} t  ON t.post_id  = p.ID AND t.meta_key  = 'ophim_thumb_url'
    LEFT JOIN {$wpdb->postmeta} po ON po.post_id = p.ID AND po.meta_key = 'ophim_poster_url'
    LEFT JOIN {$wpdb->postmeta} fu ON fu.post_id = p.ID AND fu.meta_key = 'ophim_fetch_info_url'
    LEFT JOIN {$wpdb->postmeta} fi ON fi.post_id = p.ID AND fi.meta_key = 'ophim_fetch_ophim_id'
    WHERE p.post_type = 'ophim' AND p.post_status <> 'auto-draft'
      AND (NULLIF(TRIM(t.meta_value), '') IS NULL OR NULLIF(TRIM(po.meta_value), '') IS NULL)
    ORDER BY p.ID DESC
");

$total = count($rows);
$done = $fixed = $still_missing = $failed = 0;
foreach ($rows as $row) {
    if ($limit && $done >= $limit) break;
    $done++;
    $post_id = (int)$row->id;

    // Slug lấy từ link API đã lưu; không có thì dùng slug bài viết
    $slug = $row->fetch_url ? basename(parse_url($row->fetch_url, PHP_URL_PATH) ?: '') : '';
    if ($slug === '') $slug = $row->post_name;
    $url = API_DOMAIN_KK . '/phim/' . $slug;

    echo "[#$post_id] thumb=" . ($row->thumb ?: '(null)') . " poster=" . ($row->poster ?: '(null)') . " <- $url\n";
    if ($dry_run) continue;

    $res = wp_remote_get($url, ['timeout' => 30]);
    if (is_wp_error($res) || wp_remote_retrieve_response_code($res) != 200) {
        echo "  -> lỗi gọi API\n";
        $failed++;
        continue;
    }
    $sourcePage = json_decode(wp_remote_retrieve_body($res), true);
    $movie = $sourcePage['movie'] ?? null;
    if (empty($movie['_id'])) {
        echo "  -> API không có phim với slug này\n";
        $failed++;
        continue;
    }
    // Tránh gán nhầm ảnh của phim khác trùng slug
    if ($row->fetch_id && $row->fetch_id !== $movie['_id']) {
        echo "  -> bỏ qua: _id API ({$movie['_id']}) khác ophim_fetch_ophim_id ({$row->fetch_id})\n";
        $failed++;
        continue;
    }

    kk_swap_thumb_poster($sourcePage);
    $data = create_data($sourcePage, $url, $movie['_id'], $movie['modified']['time'] ?? '');
    refresh_post_images($data, $post_id, $crawl_settings);

    wp_cache_delete($post_id, 'post_meta');
    $thumb = get_post_meta($post_id, 'ophim_thumb_url', true);
    $poster = get_post_meta($post_id, 'ophim_poster_url', true);
    if ($thumb && $poster) {
        echo "  -> fixed: thumb=$thumb poster=$poster\n";
        $fixed++;
    } else {
        echo "  -> API vẫn thiếu ảnh: thumb=" . ($thumb ?: '(null)') . " poster=" . ($poster ?: '(null)') . "\n";
        $still_missing++;
    }
    if ($sleep_ms) usleep($sleep_ms * 1000);
}

echo "Done. total=$total processed=$done fixed=$fixed still_missing=$still_missing failed=$failed" . ($dry_run ? ' (dry-run)' : '') . "\n";
