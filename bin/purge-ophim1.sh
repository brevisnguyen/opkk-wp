#!/usr/bin/env bash
# Xoá toàn bộ phim (post_type=ophim) có ophim_fetch_info_url chứa "ophim1.com"
# trên MỌI database WordPress của server, dùng tài khoản root.
#
# Usage:
#   ./bin/purge-ophim1.sh                # dry-run: chỉ đếm số phim sẽ bị xoá
#   ./bin/purge-ophim1.sh --execute      # backup (mysqldump) rồi xoá thật
#
# Xác thực root: dùng socket (chạy bằng sudo) hoặc ~/.my.cnf [client] user/password.
# Không đưa mật khẩu vào dòng lệnh. Có thể truyền thêm tham số qua MYSQL_ARGS,
# ví dụ: MYSQL_ARGS="--defaults-file=/root/.my.cnf" ./bin/purge-ophim1.sh
set -euo pipefail

EXECUTE=0
[[ "${1:-}" == "--execute" ]] && EXECUTE=1

PATTERN="${PATTERN:-%ophim1.com%}"
BACKUP_DIR="${BACKUP_DIR:-./ophim1-backup-$(date +%Y%m%d-%H%M%S)}"
# shellcheck disable=SC2206
MYARGS=(${MYSQL_ARGS:-})
SKIP_DB="information_schema|performance_schema|mysql|sys"

q() { mysql "${MYARGS[@]}" -N -B "$@"; }

[[ $EXECUTE -eq 1 ]] && mkdir -p "$BACKUP_DIR"

# Danh sách (db, prefix) có đủ bảng posts + postmeta
mapfile -t TARGETS < <(q -e "
  SELECT m.table_schema, REPLACE(m.table_name, 'postmeta', '')
  FROM information_schema.tables m
  JOIN information_schema.tables p
    ON p.table_schema = m.table_schema
   AND p.table_name = CONCAT(REPLACE(m.table_name, 'postmeta', ''), 'posts')
  WHERE m.table_name LIKE '%\_postmeta'
    AND m.table_schema NOT REGEXP '^(${SKIP_DB})\$'
  ORDER BY 1, 2")

for line in "${TARGETS[@]}"; do
  DB="$(cut -f1 <<<"$line")"
  PFX="$(cut -f2 <<<"$line")"

  COUNT="$(q -e "
    SELECT COUNT(*) FROM \`$DB\`.\`${PFX}posts\` p
    JOIN \`$DB\`.\`${PFX}postmeta\` m ON m.post_id = p.ID AND m.meta_key = 'ophim_fetch_info_url'
    WHERE p.post_type = 'ophim' AND m.meta_value LIKE '$PATTERN'")"

  echo "[$DB] prefix=$PFX -> $COUNT phim"
  [[ "$COUNT" -eq 0 ]] && continue
  [[ $EXECUTE -eq 0 ]] && continue

  mysqldump "${MYARGS[@]}" --single-transaction "$DB" | gzip > "$BACKUP_DIR/$DB.sql.gz"

  mysql "${MYARGS[@]}" "$DB" <<SQL
START TRANSACTION;

CREATE TEMPORARY TABLE tmp_del_posts (ID BIGINT UNSIGNED PRIMARY KEY);
INSERT INTO tmp_del_posts
SELECT p.ID FROM \`${PFX}posts\` p
JOIN \`${PFX}postmeta\` m ON m.post_id = p.ID AND m.meta_key = 'ophim_fetch_info_url'
WHERE p.post_type = 'ophim' AND m.meta_value LIKE '$PATTERN';

CREATE TEMPORARY TABLE tmp_del_all (ID BIGINT UNSIGNED PRIMARY KEY);
INSERT INTO tmp_del_all SELECT ID FROM tmp_del_posts;
INSERT IGNORE INTO tmp_del_all
SELECT a.ID FROM \`${PFX}posts\` a JOIN tmp_del_posts d ON a.post_parent = d.ID
WHERE a.post_type = 'attachment';

DELETE cm FROM \`${PFX}commentmeta\` cm
JOIN \`${PFX}comments\` c ON c.comment_ID = cm.comment_id
JOIN tmp_del_all d ON d.ID = c.comment_post_ID;
DELETE c FROM \`${PFX}comments\` c JOIN tmp_del_all d ON d.ID = c.comment_post_ID;

DELETE tr FROM \`${PFX}term_relationships\` tr JOIN tmp_del_all d ON d.ID = tr.object_id;
DELETE pm FROM \`${PFX}postmeta\` pm JOIN tmp_del_all d ON d.ID = pm.post_id;
DELETE r FROM \`${PFX}posts\` r JOIN tmp_del_all d ON d.ID = r.post_parent
WHERE r.post_type IN ('revision', 'auto-draft');
DELETE p FROM \`${PFX}posts\` p JOIN tmp_del_all d ON d.ID = p.ID;

UPDATE \`${PFX}term_taxonomy\` tt
SET tt.count = (SELECT COUNT(*) FROM \`${PFX}term_relationships\` tr
                WHERE tr.term_taxonomy_id = tt.term_taxonomy_id);

DROP TEMPORARY TABLE tmp_del_posts;
DROP TEMPORARY TABLE tmp_del_all;
COMMIT;
SQL
  echo "[$DB] đã xoá $COUNT phim (backup: $BACKUP_DIR/$DB.sql.gz)"
done

[[ $EXECUTE -eq 0 ]] && echo "Dry-run xong. Thêm --execute để xoá thật."
