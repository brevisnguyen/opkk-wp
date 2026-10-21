<?php
/**
 * Kiểm tra toàn bộ ophim_thumb_url trong DB; nếu file ảnh không tồn tại thì tạo lại
 * bằng cách crop từ ophim_poster_url.
 *
 * Chạy CLI:
 *   php fix_missing_thumbs.php <secret_key> [--dry-run] [--limit=N]
 */
require_once __DIR__ . '/helpers/drop-root.php';
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

// Nhận diện ảnh thật qua magic bytes (JPEG/PNG/GIF/WEBP/AVIF); loại bỏ file rỗng, trang lỗi HTML/text
function fm_is_image_bytes($b)
{
    if (!is_string($b) || strlen($b) < 12) return false;
    if (substr($b, 0, 3) === "\xFF\xD8\xFF") return true;                 // JPEG
    if (substr($b, 0, 8) === "\x89PNG\r\n\x1a\n") return true;            // PNG
    if (substr($b, 0, 4) === 'GIF8') return true;                         // GIF
    if (substr($b, 0, 4) === 'RIFF' && substr($b, 8, 4) === 'WEBP') return true; // WEBP
    if (substr($b, 4, 4) === 'ftyp') return true;                         // AVIF/HEIF
    return false;
}

// Đường dẫn tương đối (/wp-content/...) nếu giá trị là đường dẫn local hoặc URL thuộc chính domain site; ngược lại null
function fm_local_rel($v)
{
    if (!is_string($v) || $v === '') return null;
    if (preg_match('#^https?://#i', $v)) {
        $host = parse_url($v, PHP_URL_HOST);
        if (!$host || strcasecmp($host, parse_url(home_url(), PHP_URL_HOST)) !== 0) return null;
        $v = parse_url($v, PHP_URL_PATH) ?: '';
    }
    return ($v !== '' && $v[0] === '/' && strpos($v, '..') === false) ? $v : null;
}

// File local có phải ảnh thật không (đọc 16 byte đầu)
function fm_local_image_ok($rel)
{
    $path = ABSPATH . ltrim($rel, '/');
    if (!is_file($path) || filesize($path) < 12) return false;
    $fh = @fopen($path, 'rb');
    if (!$fh) return false;
    $head = fread($fh, 16);
    fclose($fh);
    return fm_is_image_bytes($head);
}

// Kiểm tra song song nhiều URL ngoài bằng curl_multi (tải 16 byte đầu để kiểm tra magic bytes); trả về [url => bool]
function fm_check_urls(array $urls, $concurrency = 30)
{
    $result = [];
    $urls = array_values(array_unique($urls));
    foreach (array_chunk($urls, $concurrency) as $chunk) {
        $mh = curl_multi_init();
        $handles = [];
        $bufs = [];
        foreach ($chunk as $i => $url) {
            $bufs[$i] = '';
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RANGE => '0-15',
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_WRITEFUNCTION => function ($c, $data) use (&$bufs, $i) {
                    if (strlen($bufs[$i]) < 16) $bufs[$i] .= $data;
                    return strlen($data);
                },
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$i] = $ch;
        }
        do {
            curl_multi_exec($mh, $running);
            if ($running) curl_multi_select($mh, 1);
        } while ($running);
        foreach ($handles as $i => $ch) {
            $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $result[$chunk[$i]] = ($code == 200 || $code == 206) && fm_is_image_bytes(substr($bufs[$i], 0, 16));
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

// Danh sách nguồn ảnh: poster trong DB, rồi poster/thumb từ API (ophim_fetch_info_url)
function fm_source_candidates($post_id, $poster)
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
    return $candidates;
}

// Lấy nội dung ảnh (file local hoặc URL); chỉ trả về nếu đúng là dữ liệu ảnh
function fm_get_poster_binary($poster)
{
    $rel = fm_local_rel($poster);
    if ($rel !== null) {
        $path = ABSPATH . ltrim($rel, '/');
        $bin = is_file($path) ? file_get_contents($path) : null;
        if ($bin !== null && fm_is_image_bytes(substr($bin, 0, 16))) return $bin;
        if (!preg_match('#^https?://#i', $poster)) return null; // đường dẫn local hỏng: thử nguồn khác
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
            if (fm_is_image_bytes(substr($body, 0, 16))) return $body;
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
    if (@file_put_contents($tmp, $binary) === false) {
        echo "  -> không ghi được file tạm: $tmp\n";
        return false;
    }
    $editor = wp_get_image_editor($tmp);
    if (is_wp_error($editor)) {
        echo "  -> không mở được ảnh nguồn: " . $editor->get_error_message() . "\n";
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
    if (is_wp_error($saved)) {
        echo "  -> không lưu được ảnh: " . $saved->get_error_message() . "\n";
        return false;
    }
    return true;
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

// Pha 1: file local -> kiểm tra magic bytes (nhanh); URL ngoài gom lại kiểm tra song song
$todo = [];
$urls = [];
foreach ($rows as $row) {
    $thumb = $row->thumb;
    if (!$thumb) {
        continue; // thumb null trong DB là chủ đích, không tự tạo
    }
    $rel = fm_local_rel($thumb);
    if ($rel !== null) {
        if (!fm_local_image_ok($rel)) $todo[] = $row; // thiếu, rỗng hoặc không phải ảnh
    } elseif (preg_match('#^https?://#i', $thumb)) {
        $urls[] = $thumb;
    } else {
        $todo[] = $row;
    }
}
$url_ok = fm_check_urls($urls);
foreach ($rows as $row) {
    if ($row->thumb && fm_local_rel($row->thumb) === null && preg_match('#^https?://#i', $row->thumb) && empty($url_ok[$row->thumb])) $todo[] = $row;
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

    // Đích: giữ nguyên đường dẫn thumb cũ nếu thuộc site này; ngược lại tạo tên mới trong uploads
    $rel = fm_local_rel($thumb);
    $webp_ok = wp_image_editor_supports(['mime_type' => 'image/webp']);
    if ($rel !== null && ($webp_ok || strtolower(pathinfo($rel, PATHINFO_EXTENSION)) !== 'webp')) {
        $dest = ABSPATH . ltrim($rel, '/');
        $new_meta = $thumb;
    } else {
        $dirs = wp_upload_dir();
        $ext = ($is_webp_default && $webp_ok) ? 'webp' : 'jpg';
        $name = sanitize_title(get_the_title($post_id)) . "-$post_id-thumb.$ext";
        $dest = $dirs['path'] . '/' . $name;
        $new_meta = str_replace(get_site_url(), '', $dirs['url'] . '/' . $name);
    }

    // Thử lần lượt từng nguồn ảnh cho tới khi tạo được thumb
    $built = false;
    foreach (fm_source_candidates($post_id, $poster) as $src) {
        $binary = fm_get_poster_binary($src);
        if (!$binary) {
            echo "  -> nguồn không phải ảnh hợp lệ / không tải được: $src\n";
            continue;
        }
        if (fm_build_thumb($binary, $dest, $thumb_w, $thumb_h)) {
            if ($src !== $poster) echo "  -> dùng ảnh nguồn: $src\n";
            $built = true;
            break;
        }
    }
    if (!$built) {
        echo "  -> không tạo được thumb (poster: " . ($poster ?: '(empty)') . ")\n";
        $failed++;
        continue;
    }

    insert_attachment($dest, $post_id, true);
    if ($new_meta != $thumb) update_post_meta($post_id, 'ophim_thumb_url', $new_meta);
    echo "  -> fixed: $new_meta\n";
    $fixed++;
}

echo "Done. checked=$checked missing=$missing fixed=$fixed failed=$failed" . ($dry_run ? ' (dry-run)' : '') . "\n";
