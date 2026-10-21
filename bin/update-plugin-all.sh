#!/usr/bin/env bash
# Cập nhật plugin opkk-wp cho tất cả website mà KHÔNG làm đổi owner sang root.
# WP-CLI luôn chạy bằng đúng user sở hữu site (owner của wp-config.php), không dùng --allow-root.
#
# Usage:
#   sudo ./bin/update-plugin-all.sh /www/wwwroot
# Biến môi trường:
#   PLUGIN=opkk-wp   slug plugin (mặc định opkk-wp)
#   WP_BIN=wp        đường dẫn wp-cli
#   PHP_BIN=         đường dẫn php dùng cho wp-cli (vd /www/server/php/81/bin/php), để trống = php mặc định
set -uo pipefail

ROOT="${1:?Usage: $0 <thư mục chứa các site>}"
PLUGIN="${PLUGIN:-opkk-wp}"
WP_BIN="${WP_BIN:-wp}"
PHP_BIN="${PHP_BIN:-}"
CACHE_DIR="${WP_CLI_CACHE_DIR:-/tmp/wp-cli-cache}"
mkdir -p "$CACHE_DIR" && chmod 1777 "$CACHE_DIR"

owner_of() { stat -c %U "$1" 2>/dev/null || stat -f %Su "$1"; }

for cfg in "$ROOT"/*/wp-config.php; do
  [[ -f "$cfg" ]] || continue
  site_dir="$(dirname "$cfg")"
  user="$(owner_of "$cfg")"

  if [[ "$user" == "root" ]]; then
    echo "[SKIP] $site_dir: wp-config.php thuộc root, hãy chown về user PHP của site trước"
    continue
  fi

  run() {
    sudo -u "$user" -H env WP_CLI_CACHE_DIR="$CACHE_DIR" ${PHP_BIN:+WP_CLI_PHP="$PHP_BIN"} \
      "$WP_BIN" "$@" --path="$site_dir"
  }

  echo "== $site_dir (user: $user)"
  run transient delete update_plugins >/dev/null 2>&1
  run option delete "external_updates-$PLUGIN" >/dev/null 2>&1
  run plugin update "$PLUGIN"

  # Phòng hờ: nếu thư mục plugin đã lỡ thuộc root thì trả lại owner của site
  if [[ -d "$site_dir/wp-content/plugins/$PLUGIN" && "$(owner_of "$site_dir/wp-content/plugins/$PLUGIN")" == "root" ]]; then
    chown -R "$user":"$(stat -c %G "$cfg" 2>/dev/null || stat -f %Sg "$cfg")" "$site_dir/wp-content/plugins/$PLUGIN"
    echo "   đã chown lại plugin về $user"
  fi
done
