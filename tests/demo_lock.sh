#!/usr/bin/env bash
# With DEMO_MODE=1: the shared demo farmer login must not be able to change
# its password. Logs in, tries a password change on profile.php, then checks
# that the old password still works and the attempted new one doesn't.
#
#   tests/demo_lock.sh http://localhost:8080
set -euo pipefail
B=${1:-http://localhost:8080}
EMAIL=farmer@agritrack.test
D=$(mktemp -d)
trap 'rm -rf "$D"' EXIT

token() { sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -n1; }
login() { # jar password -> HTTP status of the login POST (302 = success)
  local t
  t=$(curl -s -c "$D/$1" -b "$D/$1" "$B/login.php" | token)
  curl -s -o /dev/null -w '%{http_code}' -c "$D/$1" -b "$D/$1" \
    --data-urlencode "csrf_token=$t" --data-urlencode "email=$EMAIL" --data-urlencode "password=$2" "$B/login.php"
}

[ "$(login a 'Demo@1234')" = 302 ] || { echo "FAIL: demo login does not work"; exit 1; }

page=$(curl -s -c "$D/a" -b "$D/a" "$B/profile.php")
grep -q 'shared demo account' <<<"$page" || { echo "FAIL: no demo notice on profile.php"; exit 1; }

curl -s -o /dev/null -c "$D/a" -b "$D/a" --data-urlencode "csrf_token=$(token <<<"$page")" -d action=password \
  --data-urlencode current_password=Demo@1234 -d new_password=Hijacked123 -d confirm_password=Hijacked123 \
  "$B/profile.php"

old=$(login b 'Demo@1234')
new=$(login c 'Hijacked123')
echo "login with old password -> $old, with attempted new password -> $new"
[ "$old" = 302 ] && [ "$new" = 200 ] || { echo "FAIL: demo password was changed"; exit 1; }
echo "OK: demo password is locked"
