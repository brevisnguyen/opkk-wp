=== OPKK WP ===
Contributors: brevisnguyen
Donate link: https://github.com/brevisnguyen/opkk-wp
Tags: movie, cinema, crawl, ophim, kkphim
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

OPKK WP — CMS xem phim cho WordPress, tương thích dữ liệu oPhim và KKPhim.

== Description ==

OPKK WP là plugin core giúp xây dựng website xem phim trên WordPress.

**Tính năng chính**

* Custom post type / taxonomy / meta tương thích dữ liệu oPhim
* Crawl thủ công và tự động từ API Ophim
* CrawlKK: thu thập phim từ phimapi.com (KKPhim), map vào schema hiện có
* Quản lý episode / server phát
* Cập nhật plugin qua GitHub Releases

**Lưu ý cài đặt**

Thư mục plugin trên server phải tên `opkk-wp` (file chính `opkk-wp.php`) để cơ chế cập nhật hoạt động đúng.

== Installation ==

1. Tải ZIP release hoặc clone mã nguồn.
2. Upload vào `wp-content/plugins/opkk-wp/` (folder gốc phải là `opkk-wp`).
3. Kích hoạt plugin **OPKK WP** trong WordPress Admin.
4. Vào **Cài đặt → Đường dẫn tĩnh → Lưu**.
5. Dùng menu **Cài đặt OPKK** để cấu hình Crawl / CrawlKK.

= Migrate từ ophim-core =

Không chỉ đổi tên folder — cần thay cả bộ file plugin, giữ nguyên database.

1. Backup folder plugin cũ và database.
2. Deactivate plugin cũ.
3. Đổi tên/xóa folder cũ (giữ backup).
4. Upload bản OPKK WP vào `wp-content/plugins/opkk-wp/`.
5. Activate **OPKK WP**, lưu lại permalink.
6. Cập nhật crontab path sang `.../plugins/opkk-wp/` nếu dùng schedule.

Phim đã crawl giữ nguyên (`post_type=ophim` + meta/taxonomy cũ).

== Frequently Asked Questions ==

= Đổi tên plugin có mất phim đã crawl không? =

Không. Dữ liệu nằm trong database với post type `ophim` và meta `ophim_*`. Chỉ thay file plugin, không đổi schema.

= Cập nhật tự động hoạt động thế nào? =

Plugin dùng Plugin Update Checker, đọc GitHub Releases (release asset `.zip`). Site phải cài dưới folder `opkk-wp`.

= CrawlKK khác Crawl như thế nào? =

Crawl dùng API Ophim; CrawlKK dùng API phimapi.com (KKPhim). Cả hai map vào cùng schema hiện có.

== Changelog ==

= 1.0.1 =
* Fix URL xem tập khi tên tập đã chứa "Tập" (tránh /tap-tap-01 với dữ liệu KKPhim)
* Chuẩn hóa slug episode trong Episode.php

= 1.0.0 =
* Đổi tên plugin thành OPKK WP (slug `opkk-wp`), giữ schema oPhim
* Thêm CrawlKK (phimapi.com) với controller / CSS / JS / schedule riêng
* Tích hợp cập nhật qua GitHub Releases (Plugin Update Checker)
* Thêm script đóng gói release `bin/build-release.sh`

== Upgrade Notice ==

= 1.0.1 =
Sửa link xem tập bị thừa /tap- với phim crawl từ KKPhim. Khuyến nghị cập nhật.
