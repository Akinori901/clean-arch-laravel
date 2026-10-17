#!/usr/bin/env bash
# 規約が「守っているコードは通し、破ったコードは落とす」ことを確かめる。
#   cd examples && composer install && ./verify.sh
#
# 検査は 2 つ:
#   - deptrac（depfile.yaml）… レイヤー間の依存方向
#   - tools/no-service-to-service.php … Service 間の相互呼び出し
#     （deptrac は同じレイヤー内の依存を常に許可するため、別に見る必要がある）
#
# 1. app/（規約どおりのサンプル）で両方とも違反 0 件
# 2. violations/ の各ファイルを 1 つずつ app/ に入れ、どちらかが落ちること
#    （落ちないルールは、書いていないのと同じ）
set -euo pipefail
cd "$(dirname "$0")"

check() {
  ./vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress --no-cache \
    && php tools/no-service-to-service.php app/Services
}

echo "== 規約どおりのサンプル"
check

echo
echo "== 違反を 1 つずつ入れて、落ちることを確かめる"
fail=0
for v in violations/*.php; do
  name="$(basename "$v" .php)"
  expected="$(sed -n 's#^// 期待: ##p' "$v")"
  # 違反ファイルの namespace に合わせた場所に置く（Service 間検査は app/Services だけを見る）
  case "$(sed -n 's/^namespace \(.*\);/\1/p' "$v")" in
    App\\Services) target="app/Services/Violation_${name}.php" ;;
    *)             target="app/Violation_${name}.php" ;;
  esac
  cp "$v" "$target"
  if check >/dev/null 2>&1; then
    echo "FAIL $name が検知されなかった"
    fail=1
  else
    echo "ok   $name が検知された（$expected）"
  fi
  rm -f "$target"
done
exit "$fail"
