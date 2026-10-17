#!/usr/bin/env bash
# End-to-end smoke test: registers a farmer and a buyer, then drives every
# page and form over HTTP (listing, editing, reminders, export, marketplace
# filters, inquiry thread, reply, completion, rating, access control, delete,
# logout) and fails on any PHP warning/error in the page or the server log.
#
#   php -d log_errors=1 -d error_log=/tmp/agritrack.log -S 127.0.0.1:8080 -t public &
#   SERVER_LOG=/tmp/agritrack.log tests/smoke.sh http://127.0.0.1:8080
B=${1:-http://127.0.0.1:8080}
D=$(mktemp -d)
FAIL=0
LOG=${SERVER_LOG:-/dev/null}
LOGSTART=$(wc -l < "$LOG" 2>/dev/null || echo 0)
RUN=$RANDOM

tok() { curl -s -b "$D/$1" -c "$D/$1" "$B/$2" | grep -o 'name="csrf_token" value="[^"]*' | head -1 | sed 's/.*value="//'; }
check() { # jar url [expect_code]
  local out code
  out=$(curl -s -b "$D/$1" -c "$D/$1" -w '\n%{http_code}' "$B/$2")
  code=${out##*$'\n'}
  if grep -qiE '<b>(warning|fatal error|notice|deprecated)</b>|Uncaught|Stack trace' <<<"$out"; then
    echo "PHP ERROR on $2:"; grep -iE -m3 '<b>(warning|fatal error|notice|deprecated)</b>|Uncaught' <<<"$out" | sed 's/<[^>]*>//g'; FAIL=1
  fi
  if [[ -n $3 && $code != "$3" ]]; then echo "HTTP $code (want $3) on $2"; FAIL=1; fi
  printf '%-45s %s\n' "$2" "$code"
}
post() { # jar url data... -> prints redirect target
  local jar=$1 url=$2; shift 2
  local out
  out=$(curl -s -b "$D/$jar" -c "$D/$jar" -w '\n%{http_code} %{redirect_url}' "$@" "$B/$url")
  if grep -qiE '<b>(warning|fatal error|notice|deprecated)</b>|Uncaught' <<<"$out"; then
    echo "PHP ERROR on POST $url:"; grep -iE -m3 '<b>(warning|fatal error|notice|deprecated)</b>|Uncaught' <<<"$out" | sed 's/<[^>]*>//g'; FAIL=1
  fi
  printf 'POST %-40s %s\n' "$url" "${out##*$'\n'}"
}

for who in farmer buyer; do
  T=$(tok $who register.php)
  post $who register.php -d "csrf_token=$T&role=$who&name=Smoke+$who&email=$who$RUN@smoke.test&phone=0712345678&county=32&password=secret123&confirm_password=secret123"
  T=$(tok $who login.php)
  post $who login.php -d "csrf_token=$T&email=$who$RUN@smoke.test&password=secret123"
done

# Farmer lists a product (with image)
printf 'GIF89a\x01\x00\x01\x00\x00\x00\x00;' > "$D/p.gif"
T=$(tok farmer add_crop.php)
post farmer add_crop.php -F csrf_token=$T -F name="Smoke Maize $RUN" -F category=1 -F price=45 -F unit=kg -F quantity=100 \
     -F status=available -F growth_stage=growing -F variety=H614 -F planting_date=2026-06-01 \
     -F expected_harvest=$(date -d '+3 days' +%F) -F notes="Dry, well sorted" -F "image=@$D/p.gif;type=image/gif"
CROP=$(curl -s -b "$D/farmer" "$B/dashboard.php" | grep -o 'edit_crop.php?id=[0-9]*' | head -1 | cut -d= -f2)
echo "crop id: $CROP"

for u in dashboard.php add_crop.php "edit_crop.php?id=$CROP" "qrcode.php?crop_id=$CROP" marketplace.php my_inquiries.php \
         activity_log.php profile.php export.php disease_check.php send_reminders.php price_trends.php "dashboard.php?lang=sw"; do
  check farmer "$u"
done
check farmer "dashboard.php?lang=en" >/dev/null
T=$(tok farmer "edit_crop.php?id=$CROP")
post farmer "edit_crop.php?id=$CROP" -F csrf_token=$T -F name="Smoke Maize $RUN" -F category=1 -F price=50 -F unit=kg -F quantity=90 \
     -F status=available -F growth_stage=growing -F variety=H614 -F planting_date=2026-06-01 -F expected_harvest=$(date -d '+3 days' +%F)
check farmer "export.php?format=csv" 200
pdf=$(curl -s -b "$D/farmer" -o "$D/report.pdf" -w '%{http_code} %{content_type}' "$B/export.php?format=pdf")
printf '%-45s %s\n' "export.php?format=pdf" "$pdf"
# Dompdf (composer install) streams a PDF; without vendor/ a printable HTML page is shown
if [[ $pdf == "200 application/pdf" ]]; then [[ $(head -c4 "$D/report.pdf") == "%PDF" ]] || { echo "PDF body is not a PDF"; FAIL=1; }
elif [[ $pdf != "200 text/html"* ]]; then echo "unexpected PDF export response: $pdf"; FAIL=1; fi
T=$(tok farmer send_reminders.php)
post farmer send_reminders.php -d "csrf_token=$T"

# Buyer browses and contacts the farmer
for u in dashboard.php marketplace.php "marketplace.php?q=Smoke&county=32&status=available&stage=harvested" "inquiry.php?crop_id=$CROP" \
         my_inquiries.php price_trends.php "view_crop.php?id=$CROP" profile.php activity_log.php; do
  check buyer "$u"
done
T=$(tok buyer "inquiry.php?crop_id=$CROP")
post buyer inquiry.php -d "csrf_token=$T&crop_id=$CROP&contact_phone=0722000000&message=I+would+like+200kg+delivered+to+Nakuru."
INQ=$(curl -s -b "$D/buyer" "$B/my_inquiries.php" | grep -o 'inquiry_thread.php?id=[0-9]*' | head -1 | cut -d= -f2)
echo "inquiry id: $INQ"
if [[ -n $INQ ]]; then
  check farmer "inquiry_thread.php?id=$INQ" 200
  T=$(tok farmer "inquiry_thread.php?id=$INQ")
  post farmer inquiry_thread.php -d "csrf_token=$T&id=$INQ&action=reply&body=Yes,+available.+KSh+48/kg+for+200kg."
  T=$(tok farmer "inquiry_thread.php?id=$INQ")
  post farmer inquiry_thread.php -d "csrf_token=$T&id=$INQ&action=complete"
  T=$(tok buyer "inquiry_thread.php?id=$INQ")
  post buyer inquiry_thread.php -d "csrf_token=$T&id=$INQ&action=rate&score=5&comment=Great+quality"
  check buyer "inquiry_thread.php?id=$INQ" 200
  check buyer "farmer.php?id=$(curl -s -b "$D/buyer" "$B/inquiry_thread.php?id=$INQ" | grep -o 'farmer.php?id=[0-9]*' | head -1 | cut -d= -f2)" 200
fi
check farmer my_inquiries.php 200
check farmer "dashboard.php" 200

# Access control: buyer must not edit farmer's crop
curl -s -b "$D/buyer" -o /dev/null -w "buyer edit_crop -> %{http_code} %{redirect_url}\n" "$B/edit_crop.php?id=$CROP"
T=$(tok farmer dashboard.php)
post farmer delete_crop.php -d "csrf_token=$T&crop_id=$CROP"
for who in farmer buyer; do T=$(tok $who dashboard.php); post $who logout.php -d "csrf_token=$T"; done
code=$(curl -s -b "$D/farmer" -o /dev/null -w '%{http_code}' "$B/dashboard.php"); [[ $code == 302 ]] || { echo "still logged in after logout ($code)"; FAIL=1; }

rm -rf "$D"
# Fatal errors and warnings go to the server log, not the page: check it too
NEW=$(tail -n +$((LOGSTART + 1)) "$LOG" 2>/dev/null | grep -E "PHP (Fatal|Warning|Notice|Deprecated)|Uncaught")
if [[ -n $NEW ]]; then echo "SERVER LOG ERRORS:"; echo "$NEW" | head -5; FAIL=1; fi
[[ $FAIL == 0 ]] && echo "SMOKE OK" || { echo "SMOKE FAILED"; exit 1; }
