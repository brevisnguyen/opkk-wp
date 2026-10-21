<?php
/**
 * Khi script CLI (cron, fix_missing_thumbs.php) chạy bằng root, hạ quyền xuống đúng owner của wp-content
 * trước khi nạp WordPress, để file/thư mục tạo ra (uploads, logs, cache) không bị owner root.
 * Phải được require TRƯỚC wp-load.php.
 */
if (php_sapi_name() === 'cli' && function_exists('posix_geteuid') && posix_geteuid() === 0) {
    $ref = dirname(__DIR__, 2); // .../wp-content
    $st = @stat($ref);
    if ($st && $st['uid'] > 0) {
        if (function_exists('posix_getpwuid') && ($pw = posix_getpwuid($st['uid']))) {
            @posix_initgroups($pw['name'], $st['gid']);
        }
        if (!posix_setgid($st['gid']) || !posix_setuid($st['uid'])) {
            fwrite(STDERR, "Không hạ được quyền root xuống uid {$st['uid']}\n");
            exit(1);
        }
    } else {
        fwrite(STDERR, "Cảnh báo: chạy bằng root và wp-content cũng thuộc root, file tạo ra sẽ thuộc root.\n");
    }
}
