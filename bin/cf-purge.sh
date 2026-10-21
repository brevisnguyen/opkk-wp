#!/usr/bin/env bash
# Xoá toàn bộ cache Cloudflare (purge everything) cho danh sách domain.
#
# Usage:
#   export CF_API_TOKEN='...'            # token có quyền Zone:Cache Purge + Zone:Zone:Read
#   ./bin/cf-purge.sh domains.txt        # mỗi dòng một domain, bỏ qua dòng trống và dòng bắt đầu bằng #
#   ./bin/cf-purge.sh -d a.com b.com     # hoặc truyền trực tiếp domain
#   DRY_RUN=1 ./bin/cf-purge.sh domains.txt   # chỉ tìm zone, không purge
#
# Domain có thể là domain gốc (example.com) hoặc subdomain (www.example.com);
# script tự tìm zone chứa domain đó. Token KHÔNG được truyền qua tham số dòng lệnh.
# Cần: curl, jq.
set -euo pipefail

: "${CF_API_TOKEN:?Hãy export CF_API_TOKEN trước khi chạy}"
DRY_RUN="${DRY_RUN:-0}"
API="https://api.cloudflare.com/client/v4"

command -v jq >/dev/null || { echo "Cần cài jq (apt install jq / yum install jq)" >&2; exit 1; }

if [[ $# -lt 1 ]]; then
  echo "Usage: $0 <file> | -d <domain>..." >&2
  exit 1
fi

DOMAINS=()
if [[ "$1" == "-d" ]]; then
  shift
  DOMAINS=("$@")
else
  [[ -f "$1" ]] || { echo "Không thấy file: $1" >&2; exit 1; }
  while IFS= read -r line || [[ -n "$line" ]]; do
    line="$(echo "$line" | sed -E 's/#.*//; s#^https?://##; s#/.*$##; s/[[:space:]]//g')"
    [[ -n "$line" ]] && DOMAINS+=("$line")
  done < "$1"
fi

# Gọi API (header token đọc từ biến môi trường, không lộ trên dòng lệnh), tự thử lại khi bị 429
cf() {
  local method="$1" path="$2" body="${3:-}" out code attempt
  for attempt in 1 2 3 4; do
    if [[ -n "$body" ]]; then
      out="$(curl -sS -m 30 -w '\n%{http_code}' -X "$method" "$API$path" \
        -K <(printf 'header = "Authorization: Bearer %s"\n' "$CF_API_TOKEN") \
        -H 'Content-Type: application/json' --data "$body")"
    else
      out="$(curl -sS -m 30 -w '\n%{http_code}' -X "$method" "$API$path" \
        -K <(printf 'header = "Authorization: Bearer %s"\n' "$CF_API_TOKEN"))"
    fi
    code="${out##*$'\n'}"
    if [[ "$code" == "429" ]]; then sleep $((attempt * 5)); continue; fi
    printf '%s' "${out%$'\n'*}"
    return 0
  done
  echo '{"success":false,"errors":[{"message":"rate limited (429)"}]}'
}

# Tìm zone id: thử domain đầy đủ, rồi bỏ dần nhãn bên trái (a.b.com -> b.com)
find_zone() {
  local name="$1" res id
  while [[ "$name" == *.* ]]; do
    res="$(cf GET "/zones?name=$name&status=active")"
    id="$(jq -r '.result[0].id // empty' <<<"$res")"
    if [[ -n "$id" ]]; then echo "$id $name"; return 0; fi
    name="${name#*.}"
  done
  return 1
}

ok=0; fail=0
for domain in "${DOMAINS[@]}"; do
  if ! zone="$(find_zone "$domain")"; then
    echo "[FAIL] $domain: không tìm thấy zone (sai domain hoặc token thiếu quyền Zone:Read)"
    fail=$((fail + 1))
    continue
  fi
  zone_id="${zone%% *}"; zone_name="${zone#* }"

  if [[ "$DRY_RUN" == "1" ]]; then
    echo "[DRY ] $domain -> zone $zone_name ($zone_id)"
    continue
  fi

  res="$(cf POST "/zones/$zone_id/purge_cache" '{"purge_everything":true}')"
  if [[ "$(jq -r '.success' <<<"$res")" == "true" ]]; then
    echo "[ OK ] $domain (zone $zone_name): đã purge toàn bộ cache"
    ok=$((ok + 1))
  else
    echo "[FAIL] $domain (zone $zone_name): $(jq -r '[.errors[]?.message] | join("; ")' <<<"$res" 2>/dev/null || echo "$res")"
    fail=$((fail + 1))
  fi
  sleep 1
done

echo "Xong. ok=$ok fail=$fail"
[[ "$fail" -eq 0 ]]
