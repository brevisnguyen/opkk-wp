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
if (!isset($argv[1]) || $argv[1] != get_option(CRAWL_KK_OPTION_SECRET_KEY, 'secret_key')) exit("Invalid secret key\n");

$dry_run = in_array('--dry-run', $argv, true);
$limit = 0;
foreach ($argv as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) $limit = (int)$m[1];
}

$settings = json_decode(get_option(CRAWL_KK_OPTION_SETTINGS, false));
$thumb_w = (int)oIsset($settings, 'crawl_resize_size_thumb_w', 0);
$thumb_h = (int)oIsset($settings, 'crawl_resize_size_thumb_h', 0);
$is_webp_default = oIsset($settings, 'crawl_convert_webp', 'off') == 'on';

// Kiểm tra URL/đường dẫn thumb có mở được không
function fm_thumb_exists($value)
{
    if (!$value) return false;
    if (preg_match('#^https?://#i', $value)) {
        $res = wp_remote_head($value, ['timeout' => 15, 'redirection' => 3]);
        return !is_wp_error($res) && wp_remote_retrieve_response_code($res) == 200;
    }
    return file_exists(ABSPATH . ltrim($value, '/'));
}

// Lấy nội dung ảnh poster (file local hoặc URL)
function fm_get_poster_binary($poster)
{
    if (!preg_match('#^https?://#i', $poster)) {
        $path = ABSPATH . ltrim($poster, '/');
        return file_exists($path) ? file_get_contents($path) : null;
    }
    $res = wp_remote_get($poster, [
        'timeout' => 30,
        'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
    ]);
    if (is_wp_error($res) || wp_remote_retrieve_response_code($res) != 200) return null;
    return wp_remote_retrieve_body($res);
}

// Crop căn giữa poster về tỉ lệ thumb, lưu vào $dest
function fm_build_thumb($binary, $dest, $w, $h)
{
    wp_mkdir_p(dirname($dest));
    $tmp = $dest . '.tmp';
    file_put_contents($tmp, $binary);
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

$ids = get_posts([
    'post_type' => 'ophim',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'meta_key' => 'ophim_thumb_url',
]);

$checked = $missing = $fixed = $failed = 0;
foreach ($ids as $post_id) {
    $thumb = get_post_meta($post_id, 'ophim_thumb_url', true);
    $poster = get_post_meta($post_id, 'ophim_poster_url', true);
    $checked++;

    if (fm_thumb_exists($thumb)) continue;
    $missing++;

    if ($limit && $missing > $limit) break;
    echo "[#$post_id] missing thumb: " . ($thumb ?: '(empty)') . "\n";

    if (!$poster) {
        echo "  -> no poster_url, skip\n";
        $failed++;
        continue;
    }
    if ($dry_run) continue;

    $binary = fm_get_poster_binary($poster);
    if (!$binary) {
        echo "  -> cannot load poster: $poster\n";
        $failed++;
        continue;
    }

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
