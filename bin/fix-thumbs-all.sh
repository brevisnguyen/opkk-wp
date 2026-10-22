#!/usr/bin/env bash
# Chạy fix_missing_thumbs.php cho tất cả website WordPress có cài plugin opkk-wp.
#
# Usage:
#   ./bin/fix-thumbs-all.sh /var/www                 # dry-run
#   ./bin/fix-thumbs-all.sh /var/www --execute       # sửa thật
# Biến môi trường:
#   PARALLEL=4     số site chạy song song (mặc định 4)
#   RUN_AS=user    user chạy PHP (cần chạy bằng sudo). Mặc định: tự lấy owner của từng site
#                  (aaPanel thường là 'www', Ubuntu/Debian thường là 'www-data')
#   PHP_BIN=php
set -euo pipefail

ROOT="${1:?Usage: $0 <thư mục chứa các site> [--execute]}"
MODE="${2:---dry-run}"
PARALLEL="${PARALLEL:-4}"
PHP_BIN="${PHP_BIN:-php}"
RUN_AS="${RUN_AS:-}"
LOG_DIR="${LOG_DIR:-./fix-thumbs-logs-$(date +%Y%m%d-%H%M%S)}"
mkdir -p "$LOG_DIR"

export MODE PHP_BIN RUN_AS LOG_DIR OPKK_SKIP_KEY=1

run_one() {
  script="$1"
  site="$(echo "$script" | sed -E 's#/wp-content/plugins/opkk-wp/fix_missing_thumbs.php$##; s#^.*/##')"
  log="$LOG_DIR/$site.log"
  cmd=("$PHP_BIN" "$script" skip "$MODE")
  user="$RUN_AS"
  if [[ -z "$user" ]]; then
    user="$(stat -c %U "$script" 2>/dev/null || stat -f %Su "$script")"
  fi
  if [[ "$(id -un)" != "$user" ]]; then
    sudo -u "$user" env OPKK_SKIP_KEY=1 "${cmd[@]}" >"$log" 2>&1 || true
  else
    "${cmd[@]}" >"$log" 2>&1 || true
  fi
  echo "[$site] $(tail -n 1 "$log")"
}
export -f run_one

find "$ROOT" -maxdepth 6 -path '*/wp-content/plugins/opkk-wp/fix_missing_thumbs.php' -print0 \
  | xargs -0 -n1 -P "$PARALLEL" bash -c 'run_one "$0"'

echo "Log chi tiết: $LOG_DIR"
