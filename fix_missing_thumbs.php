<?php
/**
 * Kiểm tra toàn bộ ophim_thumb_url trong DB; nếu file ảnh không tồn tại thì tạo lại
 * bằng cách crop từ ophim_poster_url.
 *
 * Chạy CLI:
 *   php fix_missing_thumbs.php <secret_key> [--dry-run] [--limit=N]
 */
require_once __DIR__ . '/../../../wp-load.php';
require_once __DIR__ . '/../../../wp-admin/includes/image.php';
require_once __DIR__ . '/../../../wp-admin/includes/file.php';

set_time_limit(0);
require_once CRAWL_OPHIM_PATH . 'define.php';
require_once CRAWL_OPHIM_PATH . 'helpers/functions.php';
require_once CRAWL_OPHIM_PATH . 'crawl_movies.php';

if (php_sapi_name() !== 'cli') exit('CLI only');
// Secret key khác nhau theo từng site; chạy hàng loạt thì đặt OPKK_SKIP_KEY=1
if (getenv('OPKK_SKIP_KEY') !== '1' && (!isset($argv[1]) || $argv[1] != get_option(CRAWL_KK_OPTION_SECRET_KEY, 'secret_key'))) exit("Invalid secret key\n");

$dry_run = in_array('--dry-run', $argv, true);
$limit = 0;
foreach ($argv as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) $limit = (int)$m[1];
}

$settings = json_decode(get_option(CRAWL_KK_OPTION_SETTINGS, false));
$thumb_w = (int)oIsset($settings, 'crawl_resize_size_thumb_w', 0);
$thumb_h = (int)oIsset($settings, 'crawl_resize_size_thumb_h', 0);
$is_webp_default = oIsset($settings, 'crawl_convert_webp', 'off') == 'on';

// Kiểm tra song song nhiều URL ngoài bằng curl_multi; trả về [url => bool]
function fm_check_urls(array $urls, $concurrency = 30)
{
    $result = [];
    $urls = array_values(array_unique($urls));
    foreach (array_chunk($urls, $concurrency) as $chunk) {
        $mh = curl_multi_init();
        $handles = [];
        foreach ($chunk as $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_NOBODY => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$url] = $ch;
        }
        do {
            curl_multi_exec($mh, $running);
            if ($running) curl_multi_select($mh, 1);
        } while ($running);
        foreach ($handles as $url => $ch) {
            // 200 nhưng Content-Length = 0 là file rỗng (ảnh trắng), coi như hỏng
            $result[$url] = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) == 200
                && curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD) != 0;
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
    }
    return $result;
}

// Giá trị ảnh hợp lệ: đường dẫn local (/...) hoặc URL có host (loại bỏ "http://" rỗng, v.v.)
function fm_valid_image_source($v)
{
    if (!is_string($v) || trim($v) === '') return false;
    if (preg_match('#^https?://#i', $v)) {
        $host = parse_url($v, PHP_URL_HOST);
        return !empty($host) && strpos($host, '.') !== false;
    }
    return $v[0] === '/' && strpos($v, '..') === false;
}

// Lấy nội dung ảnh từ poster trong DB; nếu hỏng thì thử poster/thumb từ API (ophim_fetch_info_url)
function fm_load_source_image($post_id, $poster, &$used)
{
    $candidates = [];
    if (fm_valid_image_source($poster)) $candidates[] = $poster;
    $api = get_post_meta($post_id, 'ophim_fetch_info_url', true);
    if ($api && preg_match('#^https?://#i', $api)) {
        $res = wp_remote_get($api, ['timeout' => 20]);
        if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) == 200) {
            $json = json_decode(wp_remote_retrieve_body($res), true);
            foreach (['poster_url', 'thumb_url'] as $k) {
                $v = $json['movie'][$k] ?? null;
                if (fm_valid_image_source($v) && !in_array($v, $candidates, true)) $candidates[] = $v;
            }
        }
    }
    foreach ($candidates as $src) {
        $bin = fm_get_poster_binary($src);
        if ($bin) {
            $used = $src;
            return $bin;
        }
    }
    return null;
}

// Lấy nội dung ảnh poster (file local hoặc URL)
function fm_get_poster_binary($poster)
{
    if (!preg_match('#^https?://#i', $poster)) {
        $path = ABSPATH . ltrim($poster, '/');
        return file_exists($path) ? file_get_contents($path) : null;
    }
    // Link http:// trong DB thường chỉ mở được qua https:// -> thử https trước, rồi mới tới link gốc
    $urls = [];
    if (stripos($poster, 'http://') === 0) $urls[] = 'https://' . substr($poster, 7);
    $urls[] = $poster;
    foreach ($urls as $url) {
        $res = wp_remote_get($url, [
            'timeout' => 30,
            'redirection' => 5,
            'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
        ]);
        if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) == 200) {
            $body = wp_remote_retrieve_body($res);
            if ($body !== '') return $body;
        }
    }
    return null;
}

// Crop căn giữa poster về tỉ lệ thumb, lưu vào $dest
function fm_build_thumb($binary, $dest, $w, $h)
{
    wp_mkdir_p(dirname($dest));
    if (!is_dir(dirname($dest)) || !is_writable(dirname($dest))) {
        echo "  -> thư mục không ghi được (sai owner/quyền?): " . dirname($dest) . "\n";
        return false;
    }
    // File tạm đặt trong thư mục tạm của hệ thống, giữ đuôi ảnh để WP nhận đúng định dạng
    $tmp = wp_tempnam('opkk-thumb') . '.' . strtolower(pathinfo($dest, PATHINFO_EXTENSION));
    if (@file_put_contents($tmp, $binary) === false) return false;
    $editor = wp_get_image_editor($tmp);
    if (is_wp_error($editor)) {
        @unlink($tmp);
        return false;
    }
    $size = $editor->get_size();
    // Không cấu hình kích thước thì dùng tỉ lệ 2:3 (dọc)
    $target = ($w > 0 && $h > 0) ? $w / $h : 2 / 3;
    if ($size['width'] / $size['height'] > $target) {
        $cw = (int)round($size['height'] * $target);
        $editor->crop((int)(($size['width'] - $cw) / 2), 0, $cw, $size['height']);
    } else {
        $ch = (int)round($size['width'] / $target);
        $editor->crop(0, (int)(($size['height'] - $ch) / 2), $size['width'], $ch);
    }
    if ($w > 0 && $h > 0) $editor->resize($w, $h, false);
    $mime = strtolower(pathinfo($dest, PATHINFO_EXTENSION)) === 'webp' ? 'image/webp' : 'image/jpeg';
    $saved = $editor->save($dest, $mime);
    @unlink($tmp);
    return !is_wp_error($saved);
}

// Một query lấy toàn bộ id + thumb + poster (thay vì get_post_meta từng bài)
global $wpdb;
$rows = $wpdb->get_results("
    SELECT p.ID AS id, t.meta_value AS thumb, po.meta_value AS poster
    FROM {$wpdb->posts} p
    JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = 'ophim_thumb_url'
    LEFT JOIN {$wpdb->postmeta} po ON po.post_id = p.ID AND po.meta_key = 'ophim_poster_url'
    WHERE p.post_type = 'ophim' AND p.post_status <> 'auto-draft'
");

// Pha 1: file local -> file_exists (nhanh); URL ngoài gom lại kiểm tra song song
$todo = [];
$urls = [];
foreach ($rows as $row) {
    $thumb = $row->thumb;
    if (!$thumb) {
        continue; // thumb null trong DB là chủ đích, không tự tạo
    } elseif (preg_match('#^https?://#i', $thumb)) {
        $urls[] = $thumb;
    } elseif (!file_exists(ABSPATH . ltrim($thumb, '/')) || filesize(ABSPATH . ltrim($thumb, '/')) === 0) {
        $todo[] = $row;
    }
}
$url_ok = fm_check_urls($urls);
foreach ($rows as $row) {
    if ($row->thumb && preg_match('#^https?://#i', $row->thumb) && empty($url_ok[$row->thumb])) $todo[] = $row;
}

// Pha 2: chỉ xử lý các bài thiếu thumb
$checked = count($rows);
$missing = $fixed = $failed = 0;
foreach ($todo as $row) {
    $post_id = (int)$row->id;
    $thumb = $row->thumb;
    $poster = $row->poster;
    $missing++;

    if ($limit && $missing > $limit) break;
    echo "[#$post_id] missing thumb: " . ($thumb ?: '(empty)') . "\n";

    if ($dry_run) {
        if (!fm_valid_image_source($poster)) echo "  -> poster không hợp lệ: " . ($poster ?: '(empty)') . " (sẽ thử lấy từ API)\n";
        continue;
    }

    $used = null;
    $binary = fm_load_source_image($post_id, $poster, $used);
    if (!$binary) {
        echo "  -> cannot load any source image (poster: " . ($poster ?: '(empty)') . ")\n";
        $failed++;
        continue;
    }
    if ($used !== $poster) echo "  -> dùng ảnh nguồn từ API: $used\n";

    // Giữ nguyên đường dẫn thumb cũ nếu là file local; ngược lại tạo tên mới trong uploads
    if ($thumb && !preg_match('#^https?://#i', $thumb) && strpos($thumb, '..') === false) {
        $dest = ABSPATH . ltrim($thumb, '/');
        $new_meta = $thumb;
    } else {
        $dirs = wp_upload_dir();
        $ext = $is_webp_default ? 'webp' : 'jpg';
        $name = sanitize_title(get_the_title($post_id)) . "-$post_id-thumb.$ext";
        $dest = $dirs['path'] . '/' . $name;
        $new_meta = str_replace(get_site_url(), '', $dirs['url'] . '/' . $name);
    }

    if (!fm_build_thumb($binary, $dest, $thumb_w, $thumb_h)) {
        echo "  -> crop failed\n";
        $failed++;
        continue;
    }

    insert_attachment($dest, $post_id, true);
    if ($new_meta != $thumb) update_post_meta($post_id, 'ophim_thumb_url', $new_meta);
    echo "  -> fixed: $new_meta\n";
    $fixed++;
}

echo "Done. checked=$checked missing=$missing fixed=$fixed failed=$failed" . ($dry_run ? ' (dry-run)' : '') . "\n";
