# OPKK WP Plugin for WordPress

## I - Yêu cầu hệ thống
Chúng tôi khuyến nghị sử dụng Apache hoặc Nginx như các lựa chọn phù hợp nhất để chạy WordPress, nhưng không phải bắt buộc.
WordPress 6.5.5 trở lên

## II - Cài đặt
Cài đặt: Tải về và upload như WordPress plugin thông thường.
**Quan trọng:** thư mục plugin trên server phải tên `opkk-wp` (file chính `opkk-wp.php`) để cơ chế cập nhật hoạt động đúng.
Plugins khuyến nghị đi kèm: optimize, cache, SEO (tuỳ chọn).

### Migrate từ ophim-core (site đã cài bản cũ)

1. Deactivate plugin cũ
2. Đổi tên folder `wp-content/plugins/ophim-core` → `opkk-wp`
3. Đảm bảo file chính là `opkk-wp.php` (đổi/xoá `ophim-core.php` cũ)
4. Activate lại **OPKK WP**

Phim đã crawl giữ nguyên (`post_type=ophim` + meta/taxonomy cũ).

## III - Ghi chú
Các giao diện sẽ được tách riêng và đăng vào cùng post giao diện của cms (Có tag wordpress): https://forum.ophim.cc/t/wordpress-cms

## IV - Cập nhật qua GitHub Releases

Plugin dùng [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) để kiểm tra bản mới từ GitHub Releases (repo public).

### Cấu hình

Trong `define.php`:

- `OFIM_GITHUB_REPO` — URL repo GitHub (ví dụ `https://github.com/YOUR_ORG/opkk-core/`). **Phải đổi** từ placeholder trước khi phát hành.
- `OFIM_PLUGIN_SLUG` — `opkk-wp` (khớp tên thư mục cài trên WP; độc lập với tên repo).
- `OFIM_VERSION` — đồng bộ với header `Version:` trong `opkk-wp.php` (dùng nội bộ; PUC đọc version từ header plugin).

### Quy trình phát hành

1. Bump semver ở **cả hai chỗ**:
   - Header `Version:` trong `opkk-wp.php`
   - Constant `OFIM_VERSION` trong `define.php`
2. Commit & push nhánh chính.
3. Đóng gói ZIP (folder gốc phải là `opkk-wp/`):

```bash
chmod +x bin/build-release.sh
./bin/build-release.sh 1.1.0
# → dist/opkk-wp-1.1.0.zip
```

Script loại `.git`, `.cursor`, `cache/*`, `bin/`, v.v. và đặt `schedule.json` / `schedule_kk.json` về `{"enable":false}`.

4. Tạo GitHub Release:
   - Tag: `v1.1.0` (PUC lấy remote version từ tag, tự bỏ tiền tố `v`)
   - **Không** đánh dấu pre-release
   - Upload asset `dist/opkk-wp-1.1.0.zip`
   - Ghi changelog trong body Release (hiện ở “View version details” trên WP)

5. Kiểm tra trên site đang cài bản cũ hơn: **Dashboard → Updates** phải thấy OPKK WP → **Update Now**.

### Lưu ý khi update

- Options trong DB (`CRAWL_*`, settings) được giữ nguyên.
- File `schedule.json` / `schedule_kk.json` trong thư mục plugin bị ghi đè theo bản trong ZIP; bật lại lịch tự động trong admin nếu cần.
