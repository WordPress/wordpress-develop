#!/bin/bash
#
# Smoke checks an installed WordPress single site over HTTP.
#
# Runs from the root of the installation, with WP-CLI available. Multisite is not supported because
# a network's domain lives in wp-config.php and the database, not just an option.

set -euo pipefail

: "${RUNNER_TEMP:?}" "${WP_ADMIN_USER:?}" "${WP_ADMIN_PASSWORD:?}"

site_port=8889
site_url="http://127.0.0.1:${site_port}"
server_log="${RUNNER_TEMP}/server.log"
jar="${RUNNER_TEMP}/cookies.txt"
: > "${jar}"
response="${RUNNER_TEMP}/response.txt"
code="${RUNNER_TEMP}/http-code.txt"
http_code=''

version="$(wp core version)"

# Nothing undoes these, so the site is only good for this check afterwards.
wp option update home "${site_url}"
wp option update siteurl "${site_url}"
# A fresh install shows posts here already, but the front page check below needs it.
wp option update show_on_front posts
# Both make requests no check asked for, and the update checks call api.wordpress.org.
wp config set DISABLE_WP_CRON true --raw
wp config set WP_HTTP_BLOCK_EXTERNAL true --raw

# opcache counts php -S as a web SAPI, so the runner's JIT runs here and segfaults.
php -d opcache.jit=disable -S "127.0.0.1:${site_port}" -t . > "${server_log}" 2>&1 &
server_pid=$!

stop_server() {
  local status=$?
  kill "${server_pid}" 2> /dev/null || true
  wait "${server_pid}" 2> /dev/null || true
  [ "${status}" -eq 0 ] || cat "${server_log}" || true
  exit "${status}"
}
trap stop_server EXIT

# No connection prints 000 and exits 7, which set -e would take as fatal.
deadline=$(( SECONDS + 30 ))
while :; do
  ready_code="$(curl --silent --max-time 5 --output /dev/null --write-out '%{http_code}' "${site_url}/" || true)"
  [ "${ready_code}" = '000' ] || break
  [ "${SECONDS}" -lt "${deadline}" ] || { echo '::error::the server never answered'; exit 1; }
  sleep 1
done

fail() {
  echo "::error::${1}"
  tail -n 20 "${response}"
  exit 1
}

# An HTTP error returns to the caller. No reply at all is fatal.
fetch() {
  local path="${1}" status=0
  shift
  : > "${response}"
  curl --silent --show-error --max-time 30 --output "${response}" \
    --write-out '%{http_code}' --cookie "${jar}" --cookie-jar "${jar}" \
    "$@" "${site_url}${path}" > "${code}" || status=$?
  http_code="$(cat "${code}")"
  [ "${status}" -eq 0 ] || [ "${status}" -eq 22 ] \
    || fail "${path} got no reply and curl exited ${status}"
  return "${status}"
}

check() {
  local path="${1}" marker status=0
  shift
  fetch "${path}" --fail-with-body || status=$?
  [ "${status}" -eq 0 ] || fail "${path} returned HTTP ${http_code}"
  for marker in "$@"; do
    grep -qF -e "${marker}" "${response}" || fail "${path} did not contain: ${marker}"
  done
  echo "ok  ${path}"
}

# A fatal mid-page still returns 200, so every check also asks for the closing tag.
check '/' "content=\"WordPress ${version}\"" 'Hello world!' '</html>'

fetch '/?rest_route=/' --fail-with-body || fail "/?rest_route=/ returned HTTP ${http_code}"
jq -e --arg url "${site_url}" '.url == $url and ( .namespaces | index( "wp/v2" ) )' "${response}" > /dev/null \
  || fail 'the REST index was not the expected JSON'
echo 'ok  /?rest_route=/'

# It prints this for an empty database too, which the checks above rule out.
check '/wp-admin/upgrade.php' 'No Update Required' '</html>'

fetch '/?p=99999999' || true
[ "${http_code}" = '404' ] || fail "a missing post returned ${http_code}, expected 404"
grep -qF '</html>' "${response}" || fail 'the 404 page was cut short'
echo 'ok  404 handling'

login_status=0
fetch '/wp-login.php' --fail-with-body \
  --data-urlencode "log=${WP_ADMIN_USER}" --data-urlencode "pwd=${WP_ADMIN_PASSWORD}" || login_status=$?
[ "${login_status}" -eq 0 ] || fail "logging in returned HTTP ${http_code}"
grep -qF 'wordpress_logged_in_' "${jar}" || fail 'logging in did not set an authentication cookie'
echo 'ok  login'

# The admin bar renders only for a logged-in user.
check '/wp-admin/' 'id="wpadminbar"' '</html>'

# Catches a fatal during shutdown. An empty log would let the grep pass silently.
[ -s "${server_log}" ] || { echo '::error::the server wrote no log'; exit 1; }
if grep -qE 'Fatal error|Uncaught|Segmentation fault' "${server_log}"; then
  echo '::error::the server logged a fatal error'
  exit 1
fi
echo 'ok  server log'
