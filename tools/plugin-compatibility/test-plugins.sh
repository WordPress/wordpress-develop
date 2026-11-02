#!/usr/bin/env bash
##
# Checks that plugins from the WordPress.org directory can be activated against a version of WordPress
# without fataling.
#
# Each plugin is installed on its own, activated, exercised over HTTP and through WP-CLI, and then the site is
# reset to the fresh install - files and database - before the next one is installed, so that one broken
# plugin cannot mask (or break) the next. A plugin that declares `Requires Plugins` has those dependencies
# installed and activated alongside it, because core refuses to activate it otherwise.
#
# What a real request does is what decides whether a plugin passed. The WP-CLI check runs last and only ever
# adds a note, because WP-CLI loads WordPress in a way a visitor never does.
#
# This is the same code the Plugin Compatibility Tests workflow runs. Running it here is how to reproduce a
# failure from a workflow run, or to check a plugin against a release candidate, without waiting on Actions.
#
# By default everything is provisioned in throwaway containers - a database, and a PHP with WP-CLI to run the
# checks in - so nothing but Docker is needed and nothing is left behind:
#
#     tools/plugin-compatibility/test-plugins.sh --wp-version=nightly woocommerce classic-editor
#
# Pass `--no-docker` to run the checks in the current environment instead, against a database that is already
# running. That is the path the workflow takes, where the runner already has PHP, WP-CLI and a database
# service.
#
# Usage: test-plugins.sh [options] [slug...]
#
# Run `test-plugins.sh --help` for the options.
##

# `set -e` is deliberately not used: a plugin that fatals must not stop the remaining plugins from being
# tested. Failures are checked where they matter instead.
set -uo pipefail

# Written for Bash 3.2 so that it runs on a stock macOS as well as on a runner.

SCRIPT_PATH="$( cd "$( dirname "${BASH_SOURCE[0]}" )" > /dev/null 2>&1 && pwd )/$( basename "${BASH_SOURCE[0]}" )"

# Defaults. Every one of these can be overridden with the matching option.
WP_VERSION="nightly"
PLUGIN_LIST=""
USE_DOCKER="yes"
# The official WP-CLI image carries PHP, WP-CLI, `mysqli` and curl, which is everything the checks need.
PHP_IMAGE="wordpress:cli-php8.3"
# Matches the database the workflow runs against by default.
DB_IMAGE="mysql:8.4"
WP_DIR=""
DB_HOST="127.0.0.1"
DB_NAME="test_db"
DB_USER="root"
DB_PASS="password"
SERVER_PORT="8889"
KEEP_INSTALL="no"
FIXTURES_DIR=""
EXPECT_FILE=""

# How many levels of `Requires Plugins` to follow. Chains in the directory are one level deep in practice, so
# the bound is only here to stop a circular declaration from looping forever.
MAX_DEPENDENCY_DEPTH=3

usage() {
	cat <<'USAGE_EOF'
Usage: test-plugins.sh [options] [slug...]

Checks that WordPress.org plugins can be activated against a version of WordPress without fataling.

Options:
  --plugins=<list>     Plugin slugs to test, separated by commas or spaces. Slugs can also be passed as
                       positional arguments.
  --wp-version=<ver>   Version of WordPress to test against. Accepts "latest", "nightly", or a version
                       number such as a beta or RC. Default: nightly.
  --php-image=<image>  Container image the checks run in. Default: wordpress:cli-php8.3.
  --db-image=<image>   Container image the database runs in. Default: mysql:8.4.
  --no-docker          Run the checks in the current environment rather than in a container. Requires PHP,
                       WP-CLI, curl and a database that is already running.
  --dir=<path>         Where to install WordPress. Only used with --no-docker. Default: a temporary
                       directory, which is removed again on exit.
  --db-host=<host>     Database host, with an optional port. Only used with --no-docker. Default: 127.0.0.1.
  --db-name=<name>     Database name. Only used with --no-docker. Default: test_db.
  --db-user=<user>     Database user. Only used with --no-docker. Default: root.
  --db-pass=<pass>     Database password. Only used with --no-docker. Default: password.
  --port=<port>        Port the PHP built-in server listens on. Default: 8889.
  --keep               Leave the WordPress install in place on exit rather than removing it. Only used with
                       --no-docker.
  --fixtures=<dir>     Install a plugin from <dir>/<slug> instead of WordPress.org when that directory exists.
  --expect=<file>      Check each plugin's result against the one listed in <file>, and exit 0 only when they
                       all match. Plugins are taken from the file when none are named.
  -h, --help           Print this message.

Examples:
  # Check two plugins against nightly, provisioning everything in containers.
  test-plugins.sh --wp-version=nightly woocommerce classic-editor

  # Reproduce a failure from a workflow run against the release it was seen on.
  test-plugins.sh --wp-version=7.1-RC1 eps-301-redirects

  # Run the checks here, against a database that is already running.
  test-plugins.sh --no-docker --db-host=127.0.0.1:3306 --db-pass=root hello-dolly

  # Check that plugins built to break are still caught.
  test-plugins.sh --fixtures=tools/plugin-compatibility/fixtures \
    --expect=tools/plugin-compatibility/fixtures/expected.txt

Exits 0 when no plugin fataled, and 1 when one did. A plugin that could not be downloaded, or that core
declined to activate because its declared requirements are not met, is reported as skipped and does not
fail the run.
USAGE_EOF
}

# error <message>
#
# Prints a message to stderr and stops.
error() {
	printf 'Error: %s\n' "${1}" >&2
	exit 1
}

# exit_on_signal
#
# Exits on an interrupt rather than carrying on, so that the EXIT trap cleans up once and nothing runs against
# the server or container it removed. A trap that only cleans up hands control back to whatever was running.
exit_on_signal() {
	trap 'exit 130' INT
	trap 'exit 143' TERM
}

while [ "$#" -gt 0 ]; do
	case "${1}" in
		--plugins=* ) PLUGIN_LIST="${PLUGIN_LIST} ${1#*=}" ;;
		--wp-version=* ) WP_VERSION="${1#*=}" ;;
		--php-image=* ) PHP_IMAGE="${1#*=}" ;;
		--db-image=* ) DB_IMAGE="${1#*=}" ;;
		--no-docker ) USE_DOCKER="no" ;;
		--dir=* ) WP_DIR="${1#*=}" ;;
		--db-host=* ) DB_HOST="${1#*=}" ;;
		--db-name=* ) DB_NAME="${1#*=}" ;;
		--db-user=* ) DB_USER="${1#*=}" ;;
		--db-pass=* ) DB_PASS="${1#*=}" ;;
		--port=* ) SERVER_PORT="${1#*=}" ;;
		--keep ) KEEP_INSTALL="yes" ;;
		--fixtures=* ) FIXTURES_DIR="${1#*=}" ;;
		--expect=* ) EXPECT_FILE="${1#*=}" ;;
		-h | --help ) usage; exit 0 ;;
		-* ) usage >&2; error "Unknown option ${1}." ;;
		* ) PLUGIN_LIST="${PLUGIN_LIST} ${1}" ;;
	esac
	shift
done

# Both are resolved now, while relative paths still mean what they meant to whoever passed them.
if [ -n "${FIXTURES_DIR}" ]; then
	FIXTURES_DIR="$( cd "${FIXTURES_DIR}" > /dev/null 2>&1 && pwd )" || error "The fixtures directory does not exist."
fi

if [ -n "${EXPECT_FILE}" ]; then
	[ -f "${EXPECT_FILE}" ] || error "The expected results file does not exist."
	EXPECT_DIR="$( cd "$( dirname "${EXPECT_FILE}" )" > /dev/null 2>&1 && pwd )"
	EXPECT_FILE="${EXPECT_DIR%/}/$( basename "${EXPECT_FILE}" )"

	# The expected results double as the list of plugins to test, in the order they are listed.
	if [ -z "$( printf '%s' "${PLUGIN_LIST}" | tr -d '[:space:],' )" ]; then
		PLUGIN_LIST="$( awk '$1 !~ /^#/ && NF > 0 { print $1 }' "${EXPECT_FILE}" )"
	fi
fi

# Commas, spaces and newlines all separate slugs, so a list can be pasted in however it was written down.
PLUGIN_SLUGS="$( printf '%s' "${PLUGIN_LIST}" | tr ',[:space:]' '\n' | grep -v '^$' | awk '!seen[$0]++' )"

if [ -z "${PLUGIN_SLUGS}" ]; then
	usage >&2
	error "No plugins were named."
fi

# A value outside the character set the directory uses is a typo worth stopping for, rather than a plugin
# reported as impossible to download several minutes from now.
INVALID_SLUGS="$( printf '%s\n' "${PLUGIN_SLUGS}" | grep -Ev '^[a-z0-9-]+$' )"

if [ -n "${INVALID_SLUGS}" ]; then
	printf 'These values are not WordPress.org plugin slugs:\n%s\n' "${INVALID_SLUGS}" >&2
	exit 1
fi

##
# Provisions a database and a PHP, and runs this same script inside them.
#
# The checks themselves are identical either way: the container is handed this file and runs it with
# `--no-docker`, so there is only one copy of the logic that decides whether a plugin passed.
##
run_in_docker() {
	command -v docker > /dev/null 2>&1 || error "Docker is required, or pass --no-docker to run the checks here."

	# Everything is named after the process so that two runs at once do not collide.
	DOCKER_NETWORK="plugin-compatibility-$$"
	DB_CONTAINER="plugin-compatibility-db-$$"

	# Both are removed however this exits, an interrupt included. `--volumes` takes the anonymous volume the
	# database image keeps its data in along with the container, which would otherwise pile up run after run.
	trap 'docker rm --force --volumes "${DB_CONTAINER}" > /dev/null 2>&1; docker network rm "${DOCKER_NETWORK}" > /dev/null 2>&1' EXIT
	exit_on_signal

	printf 'Starting %s.\n' "${DB_IMAGE}"

	docker network create "${DOCKER_NETWORK}" > /dev/null || error "The Docker network could not be created."

	docker run --detach --name "${DB_CONTAINER}" --network "${DOCKER_NETWORK}" \
		--env MYSQL_ROOT_PASSWORD="${DB_PASS}" \
		--env MYSQL_DATABASE="${DB_NAME}" \
		"${DB_IMAGE}" > /dev/null || error "The database container could not be started."

	# A first run has to initialise the data directory, which takes appreciably longer than a later one.
	#
	# The image initialises it with a temporary server that answers on the socket and then restarts, so the
	# ping goes over TCP, which only the real server listens on. A socket ping can succeed in that window and
	# leave the next step to find the database gone.
	DB_READY="no"

	for _ in $( seq 1 90 ); do
		if docker exec "${DB_CONTAINER}" mysqladmin ping --silent --protocol=tcp --host=127.0.0.1 --user=root --password="${DB_PASS}" > /dev/null 2>&1; then
			DB_READY="yes"
			break
		fi
		sleep 2
	done

	if [ "${DB_READY}" != "yes" ]; then
		docker logs "${DB_CONTAINER}" 2>&1 | tail -n 20
		error "The database did not become available."
	fi

	printf 'The database is ready. Running the checks in %s.\n\n' "${PHP_IMAGE}"

	# Fixtures and expected results live on the host, so they are mounted read only and passed on by their
	# path inside the container.
	EXTRA_DOCKER_ARGS=()
	EXTRA_SCRIPT_ARGS=()

	if [ -n "${FIXTURES_DIR}" ]; then
		EXTRA_DOCKER_ARGS=( "${EXTRA_DOCKER_ARGS[@]+"${EXTRA_DOCKER_ARGS[@]}"}" --volume "${FIXTURES_DIR}:/fixtures:ro" )
		EXTRA_SCRIPT_ARGS=( "${EXTRA_SCRIPT_ARGS[@]+"${EXTRA_SCRIPT_ARGS[@]}"}" --fixtures=/fixtures )
	fi

	if [ -n "${EXPECT_FILE}" ]; then
		EXTRA_DOCKER_ARGS=( "${EXTRA_DOCKER_ARGS[@]+"${EXTRA_DOCKER_ARGS[@]}"}" --volume "${EXPECT_FILE}:/expected.txt:ro" )
		EXTRA_SCRIPT_ARGS=( "${EXTRA_SCRIPT_ARGS[@]+"${EXTRA_SCRIPT_ARGS[@]}"}" --expect=/expected.txt )
	fi

	# WordPress is installed inside the container rather than in a mounted directory, so a plugin cannot
	# leave anything behind on the host and file ownership never comes into it. `HOME` is pointed at a
	# writable directory because WP-CLI caches its downloads there.
	docker run --rm --network "${DOCKER_NETWORK}" \
		--volume "${SCRIPT_PATH}:/usr/local/bin/test-plugins.sh:ro" \
		${EXTRA_DOCKER_ARGS[@]+"${EXTRA_DOCKER_ARGS[@]}"} \
		--env HOME=/tmp \
		--entrypoint bash \
		"${PHP_IMAGE}" /usr/local/bin/test-plugins.sh \
		--no-docker \
		--dir=/tmp/wordpress \
		--wp-version="${WP_VERSION}" \
		--db-host="${DB_CONTAINER}" \
		--db-name="${DB_NAME}" \
		--db-user=root \
		--db-pass="${DB_PASS}" \
		--port="${SERVER_PORT}" \
		${EXTRA_SCRIPT_ARGS[@]+"${EXTRA_SCRIPT_ARGS[@]}"} \
		--plugins="$( printf '%s' "${PLUGIN_SLUGS}" | tr '\n' ' ' )"
}

##
# Installs WordPress, starts a web server, and checks each plugin against it.
##
run_checks() {
	for REQUIRED_COMMAND in php curl; do
		command -v "${REQUIRED_COMMAND}" > /dev/null 2>&1 || error "${REQUIRED_COMMAND} is required to run the checks without Docker."
	done

	# `type -P` searches the path rather than resolving to the `wp` wrapper function defined below.
	WP_CLI_BIN="$( type -P wp )"

	[ -n "${WP_CLI_BIN}" ] || error "WP-CLI is required to run the checks without Docker."

	WORK_DIR="$( mktemp -d )"
	RESULTS="${WORK_DIR}/results.tsv"
	RESPONSE_BODY="${WORK_DIR}/response.html"
	SERVER_LOG="${WORK_DIR}/php-server.log"
	SERVER_PID=""
	: > "${RESULTS}"

	if [ -z "${WP_DIR}" ]; then
		WP_DIR="${WORK_DIR}/wordpress"
	fi

	mkdir -p "${WP_DIR}" || error "The WordPress directory could not be created."

	# The server is stopped and the temporary files are removed however this exits. The WordPress install is
	# only removed when this script created it, so that `--dir` never deletes a directory it was handed.
	cleanup_environment() {
		if [ -n "${SERVER_PID}" ]; then
			stop_server
		fi

		if [ "${KEEP_INSTALL}" = "yes" ]; then
			printf '\nThe WordPress install was left in %s.\n' "${WP_DIR}"
		else
			rm -rf "${WORK_DIR}"
		fi
	}

	# The built-in server forks its workers, and they keep answering on the port after the process that
	# started them is gone. The server runs in its own process group (see below) so that the whole group can
	# be stopped at once, and this waits for the last of them to exit so that a run which follows straight
	# after finds the port free.
	stop_server() {
		kill -TERM -- "-${SERVER_PID}" > /dev/null 2>&1

		for _ in $( seq 1 50 ); do
			kill -0 -- "-${SERVER_PID}" > /dev/null 2>&1 || break
			sleep 0.1
		done

		kill -KILL -- "-${SERVER_PID}" > /dev/null 2>&1
		wait "${SERVER_PID}" > /dev/null 2>&1
		SERVER_PID=""
	}

	trap cleanup_environment EXIT
	exit_on_signal

	cd "${WP_DIR}" || error "The WordPress directory could not be entered."

	# A relative `--dir` would be resolved against the wrong directory from here on.
	WP_DIR="$( pwd )"

	SITE_URL="http://127.0.0.1:${SERVER_PORT}"

	printf 'Downloading WordPress %s.\n' "${WP_VERSION}"
	wp core download --version="${WP_VERSION}" || error "WordPress ${WP_VERSION} could not be downloaded."

	wp config create --dbname="${DB_NAME}" --dbuser="${DB_USER}" --dbpass="${DB_PASS}" --dbhost="${DB_HOST}" \
		|| error "wp-config.php could not be written."

	# Errors need to reach `wp-content/debug.log` so that a white screen of death is still detectable.
	#
	# `WP_DEBUG_DISPLAY` is left off on purpose: this should behave the way a production site does, where a
	# fatal error is an empty page and an HTTP 500 rather than a printed stack trace.
	#
	# The fatal error handler is disabled so that a fatal is reported as a fatal instead of being swallowed
	# by recovery mode, which would also deactivate the plugin mid-test.
	#
	# WP-Cron is disabled because WordPress spawns it as a loopback request during a front end request. The
	# loopback lands back on the same PHP built-in server that is still busy serving the request that
	# spawned it, and the two deadlock until curl gives up.
	wp config set WP_DEBUG true --raw
	wp config set WP_DEBUG_LOG true --raw
	wp config set WP_DEBUG_DISPLAY false --raw
	wp config set WP_DISABLE_FATAL_ERROR_HANDLER true --raw
	wp config set DISABLE_WP_CRON true --raw

	wp core install --url="${SITE_URL}" --title="Plugin Compatibility Test" --admin_user=admin \
		--admin_password=password --admin_email=me@example.org --skip-email \
		|| error "WordPress could not be installed. Check that the database is reachable at ${DB_HOST}."

	rm -f "${WP_DIR}/wp-content/debug.log"
	snapshot_site || error "The freshly installed site could not be recorded for resetting between plugins."

	# The site needs to answer real requests so that fatals which only happen on a front end or admin page
	# load are caught. The built-in server is enough for that and needs nothing installed.
	#
	# `PHP_CLI_SERVER_WORKERS` is set because the built-in server is single threaded by default. Plenty of
	# plugins make a loopback request to the site they are running on, and a single threaded server cannot
	# answer one while it is still serving the request that made it.
	#
	# Job control is switched on just long enough to start the server, because that is what gives a
	# background job a process group of its own, whose ID is the PID of the server.
	set -m
	PHP_CLI_SERVER_WORKERS=4 php -S "127.0.0.1:${SERVER_PORT}" -t "${WP_DIR}" > "${SERVER_LOG}" 2>&1 &
	SERVER_PID=$!
	set +m

	# Any server already listening on the port would answer a request for a WordPress page just as well, and
	# every check after this would then be made against that site instead. So readiness means this server is
	# still running and is serving a file that only exists in this install.
	READY_FILE="plugin-compatibility-ready-$$-${RANDOM}.txt"
	READY_TOKEN="$$-${RANDOM}-$( date +%s )"
	printf '%s' "${READY_TOKEN}" > "${WP_DIR}/${READY_FILE}"

	SERVER_READY="no"

	for _ in $( seq 1 30 ); do
		kill -0 "${SERVER_PID}" > /dev/null 2>&1 || break

		if [ "$( curl -sf "${SITE_URL}/${READY_FILE}" 2>/dev/null )" = "${READY_TOKEN}" ]; then
			SERVER_READY="yes"
			break
		fi
		sleep 1
	done

	rm -f "${WP_DIR:?}/${READY_FILE}"

	if [ "${SERVER_READY}" != "yes" ]; then
		cat "${SERVER_LOG}"
		error "The PHP built-in server did not start on port ${SERVER_PORT}, or something else is already listening on it."
	fi

	printf 'The PHP built-in server is ready on %s.\n' "${SITE_URL}"

	test_plugins
	report_results
	REPORT_EXIT_CODE=$?

	# Plugins built to fail are expected to fail, so with a list of expected results it is whether they matched
	# that decides the exit code.
	if [ -n "${EXPECT_FILE}" ]; then
		check_expectations
		return
	fi

	return "${REPORT_EXIT_CODE}"
}

# wp <arguments>
#
# Runs WP-CLI with the memory limit lifted. Unpacking a WordPress zip needs more than the 128M a stock
# php.ini allows, and WP-CLI reads its own `WP_CLI_PHP_ARGS` only when it is installed as a wrapper script
# rather than as the phar most environments carry.
wp() {
	php -d memory_limit=-1 "${WP_CLI_BIN}" "$@"
}

# record <slug> <version> <status> <dependencies> <reason>
#
# Appends one tab separated row to the results file. The reason is flattened so that it cannot break the
# markdown table that is generated from these rows later on.
record() {
	SAFE_REASON="$( printf '%s' "${5:--}" | tr '\n\t|' '   ' )"
	printf '%s\t%s\t%s\t%s\t%s\n' "${1}" "${2:-unknown}" "${3}" "${4:--}" "${SAFE_REASON}" >> "${RESULTS}"
}

# check_url <path>
#
# Requests a path on the test site and prints a reason to stdout when the response looks broken. Prints
# nothing when the request looks healthy.
check_url() {
	CURL_EXIT_CODE=0
	# The exit code is captured separately rather than falling back to a literal inside the command
	# substitution, which would append to whatever curl had already written and produce a nonsense status
	# like "200000" when a request returned headers and then stalled.
	HTTP_CODE="$( curl -s -o "${RESPONSE_BODY}" -w '%{http_code}' --max-time 60 "${SITE_URL}${1}" )" || CURL_EXIT_CODE=$?

	if [ -z "${HTTP_CODE}" ] || [ "${HTTP_CODE}" = "000" ]; then
		printf 'The request to %s did not complete, curl exit code %s' "${1}" "${CURL_EXIT_CODE}"
		return
	fi

	if [ "${HTTP_CODE}" -ge 500 ]; then
		printf 'The request to %s returned HTTP %s' "${1}" "${HTTP_CODE}"
		return
	fi

	if [ "${CURL_EXIT_CODE}" -ne 0 ]; then
		printf 'The request to %s returned HTTP %s but the response did not finish, curl exit code %s' "${1}" "${HTTP_CODE}" "${CURL_EXIT_CODE}"
		return
	fi

	# Belt and braces. Errors are not displayed by default, but a plugin can turn display_errors back on for
	# itself.
	if grep -qiE '(Fatal|Parse) error' "${RESPONSE_BODY}"; then
		printf 'The response from %s contained a fatal error' "${1}"
	fi
}

# logged_fatals
#
# Prints the fatal and parse errors in the debug log, one per line, and returns non zero when there are none.
# A parse error in a file included after output has started ends the request just as surely as a fatal does,
# and still answers with a 200.
logged_fatals() {
	[ -f "${WP_DIR}/wp-content/debug.log" ] && grep -E 'PHP (Fatal|Parse) error' "${WP_DIR}/wp-content/debug.log"
}

# install_plugin <slug>
#
# Installs a plugin from the fixtures directory when there is one by that name, and from WordPress.org
# otherwise. Returns non zero when the plugin could not be installed.
install_plugin() {
	if [ -n "${FIXTURES_DIR}" ] && [ -d "${FIXTURES_DIR}/${1}" ]; then
		printf 'Installing %s from %s.\n' "${1}" "${FIXTURES_DIR}"
		cp -R "${FIXTURES_DIR}/${1}" "${WP_DIR}/wp-content/plugins/"
		return
	fi

	wp plugin install "${1}" --skip-plugins --skip-themes
}

# is_active <slug>
#
# Returns zero when core has recorded the plugin as active.
is_active() {
	wp plugin is-active "${1}" --skip-plugins --skip-themes > /dev/null 2>&1
}

# plugin_dependencies <slug>
#
# Prints the slugs named in the plugin's `Requires Plugins` header, one per line. WordPress' own header
# parser is used so that the same rules apply here as when core decides whether a plugin's dependencies are
# met.
plugin_dependencies() {
	PLUGIN_FILE="$( wp plugin path "${1}" --skip-plugins --skip-themes 2>/dev/null || printf '' )"

	[ -n "${PLUGIN_FILE}" ] || return 0

	# The single quotes are what keep the shell out of the PHP below. The path is handed over as an
	# environment variable rather than interpolated so that it never reaches PHP as source code.
	export PLUGIN_FILE

	# shellcheck disable=SC2016
	wp eval --skip-plugins --skip-themes '
		require_once ABSPATH . "wp-admin/includes/plugin.php";

		$plugin_data = get_plugin_data( getenv( "PLUGIN_FILE" ), false, false );

		foreach ( explode( ",", $plugin_data["RequiresPlugins"] ?? "" ) as $dependency ) {
			// Core only accepts a WordPress.org slug here, so anything else is ignored.
			if ( preg_match( "/^[a-z0-9-]+$/", trim( $dependency ) ) ) {
				echo trim( $dependency ), "\n";
			}
		}
	' 2>/dev/null
}

# Prefix of the copies of the freshly installed site's tables, which every plugin's changes are rolled back to.
BASELINE_TABLE_PREFIX="pcbaseline_"

# snapshot_site
#
# Records the freshly installed site so that it can be put back exactly after each plugin: the entries at the
# root of the install, `wp-config.php` and `wp-content` as an archive, and a copy of every database table.
snapshot_site() {
	BASELINE_DIR="${WORK_DIR}/baseline"
	mkdir -p "${BASELINE_DIR}" || return 1

	(
		shopt -s dotglob nullglob

		for ENTRY in "${WP_DIR}"/*; do
			printf '%s\n' "${ENTRY##*/}"
		done
	) > "${BASELINE_DIR}/root-entries" || return 1
	tar -C "${WP_DIR}" -cf "${BASELINE_DIR}/files.tar" wp-config.php wp-content || return 1

	# The table copies live in the same database, which saves depending on a database client being
	# installed that can talk to whatever server version the run is using. The prefix is handed over as an
	# environment variable so that it never reaches PHP as source code.
	export BASELINE_TABLE_PREFIX

	# shellcheck disable=SC2016
	wp eval --skip-plugins --skip-themes '
		global $wpdb;

		$prefix = getenv( "BASELINE_TABLE_PREFIX" );

		foreach ( $wpdb->get_col( "SHOW TABLES" ) as $table ) {
			// Left over from an earlier run against the same database.
			if ( 0 === strpos( $table, $prefix ) ) {
				continue;
			}

			$wpdb->query( "DROP TABLE IF EXISTS `{$prefix}{$table}`" );

			if ( false === $wpdb->query( "CREATE TABLE `{$prefix}{$table}` LIKE `{$table}`" )
				|| false === $wpdb->query( "INSERT INTO `{$prefix}{$table}` SELECT * FROM `{$table}`" ) ) {
				exit( 1 );
			}
		}
	'
}

# reset_site
#
# Puts the site back the way `snapshot_site` found it. Removing the plugin directories is not enough on its
# own: plenty of plugins write drop-ins such as `advanced-cache.php`, add constants to `wp-config.php`, or
# create tables and options, and a drop-in left loading files that are gone would break every plugin tested
# after it. The files are restored first so that no drop-in is around to be loaded while the database is put
# back.
reset_site() {
	(
		shopt -s dotglob nullglob

		for ENTRY in "${WP_DIR}"/*; do
			grep -qxF -- "${ENTRY##*/}" "${BASELINE_DIR}/root-entries" || rm -rf "${ENTRY}"
		done
	)

	rm -rf "${WP_DIR:?}/wp-content" || return 1
	tar -C "${WP_DIR}" -xf "${BASELINE_DIR}/files.tar" || return 1

	# shellcheck disable=SC2016
	wp eval --skip-plugins --skip-themes '
		global $wpdb;

		$prefix   = getenv( "BASELINE_TABLE_PREFIX" );
		$baseline = array();

		$wpdb->query( "SET FOREIGN_KEY_CHECKS = 0" );

		foreach ( $wpdb->get_results( "SHOW FULL TABLES", ARRAY_N ) as $row ) {
			list( $table, $type ) = $row;

			if ( 0 === strpos( $table, $prefix ) ) {
				$baseline[] = substr( $table, strlen( $prefix ) );
			} else {
				$wpdb->query( ( "VIEW" === $type ? "DROP VIEW" : "DROP TABLE" ) . " `{$table}`" );
			}
		}

		if ( ! $baseline ) {
			exit( 1 );
		}

		foreach ( $baseline as $table ) {
			if ( false === $wpdb->query( "CREATE TABLE `{$table}` LIKE `{$prefix}{$table}`" )
				|| false === $wpdb->query( "INSERT INTO `{$table}` SELECT * FROM `{$prefix}{$table}`" ) ) {
				exit( 1 );
			}
		}
	'
}

# group_start <name> / group_end
#
# Collapses a plugin's output in the workflow log. The markers mean nothing to a terminal, so they are only
# printed when this is running in Actions.
group_start() {
	if [ "${GITHUB_ACTIONS:-}" = "true" ]; then
		printf '::group::%s\n' "${1}"
	else
		printf '\n----- %s -----\n' "${1}"
	fi
}

group_end() {
	if [ "${GITHUB_ACTIONS:-}" = "true" ]; then
		printf '::endgroup::\n'
	fi
}

# reset_failed <slug>
#
# Stops the run when the site could not be put back after a plugin. Every result after that point would be
# measured against whatever the plugin left behind, so the results so far are reported and nothing more is
# tested.
reset_failed() {
	printf '\nThe site could not be reset after %s, so no further plugins were tested.\n' "${1}" >&2
	report_results
	exit 1
}

##
# Tests each plugin in turn, writing a row per plugin to the results file.
##
test_plugins() {
	while IFS= read -r SLUG; do
		[ -n "${SLUG}" ] || continue

		group_start "${SLUG}"

		STATUS="PASS"
		REASON="-"
		VERSION="unknown"
		DEPENDENCIES=()

		# Start every plugin with an empty log so that anything found in it belongs to this plugin.
		rm -f "${WP_DIR}/wp-content/debug.log"

		# Step 1: download the plugin. A download failure is recorded as skipped rather than failed, since
		# it generally means a network flake or a plugin that is no longer in the directory.
		if ! install_plugin "${SLUG}"; then
			record "${SLUG}" "unknown" "SKIPPED" "-" "The plugin could not be downloaded from WordPress.org"
			reset_site || reset_failed "${SLUG}"
			group_end
			continue
		fi

		VERSION="$( wp plugin get "${SLUG}" --field=version --skip-plugins --skip-themes 2>/dev/null || printf 'unknown' )"

		# Step 2: install whatever the plugin names in `Requires Plugins`. Core refuses to activate a plugin
		# whose dependencies are missing, so without this every WooCommerce extension - a large slice of the
		# most popular plugins - would go untested.
		#
		# Dependencies are resolved a level at a time so that a dependency declaring its own is installed
		# too. Each one is resolved only once, which covers a plugin named twice in the same tree as well as a
		# declaration pointing back at the plugin under test. A dependency that is already installed, such as
		# the Akismet that ships with core, is not downloaded again but is still activated and followed.
		PENDING=()
		RESOLVED=$'\n'"${SLUG}"$'\n'

		while IFS= read -r DEPENDENCY; do
			[ -n "${DEPENDENCY}" ] && PENDING[${#PENDING[@]}]="${DEPENDENCY}"
		done < <( plugin_dependencies "${SLUG}" )

		DEPTH=0

		while [ "${#PENDING[@]}" -gt 0 ] && [ "${DEPTH}" -lt "${MAX_DEPENDENCY_DEPTH}" ]; do
			NEXT=()

			for DEPENDENCY in "${PENDING[@]}"; do
				case "${RESOLVED}" in
					*$'\n'"${DEPENDENCY}"$'\n'* ) continue ;;
				esac

				RESOLVED="${RESOLVED}${DEPENDENCY}"$'\n'

				if ! wp plugin is-installed "${DEPENDENCY}" --skip-plugins --skip-themes > /dev/null 2>&1; then
					printf 'Installing %s, which %s requires.\n' "${DEPENDENCY}" "${SLUG}"

					if ! install_plugin "${DEPENDENCY}"; then
						STATUS="SKIPPED"
						REASON="The required plugin ${DEPENDENCY} is not available from WordPress.org"
						break 2
					fi
				fi

				DEPENDENCIES[${#DEPENDENCIES[@]}]="${DEPENDENCY}"

				while IFS= read -r NESTED_DEPENDENCY; do
					[ -n "${NESTED_DEPENDENCY}" ] && NEXT[${#NEXT[@]}]="${NESTED_DEPENDENCY}"
				done < <( plugin_dependencies "${DEPENDENCY}" )
			done

			PENDING=( ${NEXT[@]+"${NEXT[@]}"} )
			DEPTH=$(( DEPTH + 1 ))
		done

		# Step 3: activate the dependencies. Core will not activate a plugin ahead of its own requirements,
		# and the order they were discovered in says nothing about the order they depend on each other in -
		# a plugin can require `base, extension` where `extension` requires `base` as well. So they are
		# activated in passes: each pass activates whatever it can, and whatever core declined is tried
		# again on the next. A pass that activates nothing means the rest can never be activated, whether
		# because one of them is broken or because they require each other.
		#
		# A successful exit is not taken as proof of activation. A plugin that calls `exit` while activating
		# ends WP-CLI with a zero status before core has recorded it as active.
		if [ "${STATUS}" = "PASS" ] && [ "${#DEPENDENCIES[@]}" -gt 0 ]; then
			INACTIVE=( "${DEPENDENCIES[@]}" )

			while [ "${#INACTIVE[@]}" -gt 0 ]; do
				STILL_INACTIVE=()

				for DEPENDENCY in "${INACTIVE[@]}"; do
					DEPENDENCY_OUTPUT="$( wp plugin activate "${DEPENDENCY}" 2>&1 )"
					printf '%s\n' "${DEPENDENCY_OUTPUT}"

					if ! is_active "${DEPENDENCY}"; then
						STILL_INACTIVE[${#STILL_INACTIVE[@]}]="${DEPENDENCY}"
					fi
				done

				if [ "${#STILL_INACTIVE[@]}" -eq "${#INACTIVE[@]}" ]; then
					STATUS="SKIPPED"
					REASON="The required plugin ${STILL_INACTIVE[0]} could not be activated"
					break
				fi

				INACTIVE=( ${STILL_INACTIVE[@]+"${STILL_INACTIVE[@]}"} )
			done
		fi

		# Step 4: with dependencies active the plugin under test is no longer alone on the site, so the
		# baseline is checked before it is activated. A site that is already broken says something about the
		# dependency rather than about the plugin being tested, and blaming the plugin for it would be the
		# masking these checks are built to avoid.
		if [ "${STATUS}" = "PASS" ] && [ "${#DEPENDENCIES[@]}" -gt 0 ]; then
			for URL_PATH in "/" "/wp-login.php"; do
				BASELINE_REASON="$( check_url "${URL_PATH}" )"

				if [ -n "${BASELINE_REASON}" ]; then
					STATUS="SKIPPED"
					REASON="The required plugins are not healthy on their own: ${BASELINE_REASON}"
					break
				fi
			done

			# A dependency can fatal after the page has started to render, which still answers with a 200.
			# The fatal only shows up in the log, and it would show up again once the plugin under test is
			# active and be blamed on that plugin, so the log is checked before it is cleared.
			if [ "${STATUS}" = "PASS" ] && LOGGED_FATALS="$( logged_fatals )"; then
				printf '%s\n' "${LOGGED_FATALS}"
				STATUS="SKIPPED"
				REASON="The required plugins are not healthy on their own: $( printf '%s\n' "${LOGGED_FATALS}" | head -n 1 | cut -c 1-200 )"
			fi

			# Anything else the dependencies logged on their way up is not the responsibility of the plugin
			# under test, so the log starts empty again here.
			rm -f "${WP_DIR}/wp-content/debug.log"
		fi

		# Step 5: activation. Activation runs the plugin's activation hooks and loads its main file.
		if [ "${STATUS}" = "PASS" ]; then
			ACTIVATE_EXIT_CODE=0
			ACTIVATE_OUTPUT="$( wp plugin activate "${SLUG}" 2>&1 )" || ACTIVATE_EXIT_CODE=$?
			printf '%s\n' "${ACTIVATE_OUTPUT}"

			if [ "${ACTIVATE_EXIT_CODE}" -ne 0 ]; then
				case "${ACTIVATE_OUTPUT}" in
					# Core refuses to activate a plugin whose declared requirements are not met.
					# Dependencies are installed above, so what is left here is a plugin asking for a
					# version of PHP or WordPress this run is not using, or for a dependency that is not on
					# WordPress.org. That is core working as designed rather than a fatal, so it is recorded
					# as skipped.
					*"to be installed and activated"* | *"does not meet minimum requirements"* )
						STATUS="SKIPPED"
						REASON="Core declined to activate the plugin because its declared requirements are not met"
						;;
					* )
						STATUS="FAIL"
						REASON="The plugin could not be activated"
						;;
				esac
			elif ! is_active "${SLUG}"; then
				# Checking the site now would be checking it without the plugin, and passing it for that.
				STATUS="SKIPPED"
				REASON="WP-CLI reported success, but the plugin was not active afterwards, so its activation stopped early"
			fi
		fi

		# Step 6: request the front page and the login screen through the PHP built-in server. A real request
		# is what decides whether a plugin passed, because it is what a visitor gets.
		if [ "${STATUS}" = "PASS" ]; then
			for URL_PATH in "/" "/wp-login.php"; do
				HTTP_REASON="$( check_url "${URL_PATH}" )"

				if [ -n "${HTTP_REASON}" ]; then
					STATUS="FAIL"
					REASON="${HTTP_REASON}"
					break
				fi
			done
		fi

		# Step 7: a fatal or parse error can be logged without changing the HTTP status, for example during
		# a shutdown hook, so the debug log is checked separately.
		if [ "${STATUS}" = "PASS" ] && LOGGED_FATALS="$( logged_fatals )"; then
			printf '%s\n' "${LOGGED_FATALS}"
			STATUS="FAIL"
			REASON="A fatal error was logged: $( printf '%s\n' "${LOGGED_FATALS}" | head -n 1 | cut -c 1-200 )"
		fi

		# Step 8: boot all of core plus the active plugin through WP-CLI. This runs last and cannot fail a
		# plugin on its own.
		#
		# WP-CLI requires `wp-settings.php` from inside a method, so a plugin that assigns a variable at file
		# scope and reads it back with `global` later finds nothing there. Plenty of plugins do exactly that,
		# and the resulting fatal happens only under WP-CLI - a visitor never sees it. Failing a plugin for
		# it would report breakage that does not exist on a real site, so it is recorded as a note against a
		# plugin that is otherwise healthy. Anything that genuinely fatals on load fails the HTTP checks
		# above.
		#
		# The fatal is written to the debug log too, which is why this comes after the log has been checked.
		if [ "${STATUS}" = "PASS" ]; then
			EVAL_OUTPUT="$( wp eval 'echo "loaded-ok";' 2>&1 )"

			if [ "${EVAL_OUTPUT#*loaded-ok}" = "${EVAL_OUTPUT}" ]; then
				printf '%s\n' "${EVAL_OUTPUT}"

				case "${EVAL_OUTPUT}" in
					*"Fatal error"* | *"Parse error"* | *"Uncaught"* )
						REASON="The site is healthy over HTTP, but WP-CLI fatals when it loads WordPress with the plugin active"
						;;
					# Some plugins redirect or exit while loading, which stops WP-CLI without anything being
					# broken.
					* )
						printf 'WP-CLI did not finish loading WordPress, but no fatal error was reported.\n'
						;;
				esac
			fi
		fi

		# Step 9: record the outcome and put the site back the way it was found.
		DEPENDENCY_LIST="-"

		if [ "${#DEPENDENCIES[@]}" -gt 0 ]; then
			DEPENDENCY_LIST="$( printf '%s, ' "${DEPENDENCIES[@]}" )"
			DEPENDENCY_LIST="${DEPENDENCY_LIST%, }"
		fi

		record "${SLUG}" "${VERSION}" "${STATUS}" "${DEPENDENCY_LIST}" "${REASON}"
		reset_site || reset_failed "${SLUG}"

		printf '%s: %s\n' "${SLUG}" "${STATUS}"
		group_end
	done <<PLUGIN_LIST_EOF
${PLUGIN_SLUGS}
PLUGIN_LIST_EOF
}

##
# Prints the results, and exits non zero when a plugin fataled.
#
# In Actions the same rows are written to the workflow summary as a markdown table, where they are easier to
# read than the log.
##
report_results() {
	PASS_COUNT="$( awk -F '\t' '$3 == "PASS" { count++ } END { print count + 0 }' "${RESULTS}" )"
	FAIL_COUNT="$( awk -F '\t' '$3 == "FAIL" { count++ } END { print count + 0 }' "${RESULTS}" )"
	SKIP_COUNT="$( awk -F '\t' '$3 == "SKIPPED" { count++ } END { print count + 0 }' "${RESULTS}" )"
	PHP_VERSION="$( php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' )"

	printf '\nWordPress %s / PHP %s: %s passed, %s failed, %s skipped.\n\n' \
		"${WP_VERSION}" "${PHP_VERSION}" "${PASS_COUNT}" "${FAIL_COUNT}" "${SKIP_COUNT}"

	awk -F '\t' 'BEGIN { printf "%-34s %-10s %-8s %-24s %s\n", "PLUGIN", "VERSION", "RESULT", "ALSO ACTIVE", "DETAILS" }
		{ printf "%-34s %-10s %-8s %-24s %s\n", $1, $2, $3, ( $4 == "-" ? "" : $4 ), ( $5 == "-" ? "" : $5 ) }' "${RESULTS}"

	if [ -n "${GITHUB_STEP_SUMMARY:-}" ]; then
		{
			printf '### WordPress %s / PHP %s\n\n' "${WP_VERSION}" "${PHP_VERSION}"
			printf '%s passed, %s failed, %s skipped.\n\n' "${PASS_COUNT}" "${FAIL_COUNT}" "${SKIP_COUNT}"
			printf '| Plugin | Version | Result | Also active | Details |\n'
			printf '| --- | --- | --- | --- | --- |\n'

			while IFS=$'\t' read -r ROW_SLUG ROW_VERSION ROW_STATUS ROW_DEPENDENCIES ROW_REASON; do
				case "${ROW_STATUS}" in
					PASS ) ICON=':white_check_mark:' ;;
					FAIL ) ICON=':x:' ;;
					* ) ICON=':warning:' ;;
				esac

				printf '| [%s](https://wordpress.org/plugins/%s/) | %s | %s %s | %s | %s |\n' \
					"${ROW_SLUG}" "${ROW_SLUG}" "${ROW_VERSION}" "${ICON}" "${ROW_STATUS}" "${ROW_DEPENDENCIES}" "${ROW_REASON}"
			done < "${RESULTS}"

			printf '\n'
		} >> "${GITHUB_STEP_SUMMARY}"
	fi

	# Plugins that could not be downloaded are reported but do not fail the run.
	if [ "${FAIL_COUNT}" -gt 0 ]; then
		printf '\nThe following plugins failed against WordPress %s:\n' "${WP_VERSION}"
		awk -F '\t' '$3 == "FAIL" { print "- " $1 " (" $2 "): " $5 }' "${RESULTS}"
		return 1
	fi

	printf '\nNo plugins fataled against WordPress %s.\n' "${WP_VERSION}"
}

##
# Compares each plugin's result with the one the expected results file lists for it, and exits non zero when
# any of them differ.
#
# Each line of the file is a slug, the expected result, and optionally some text that the "Also active" or
# "Details" column has to contain. That last part is what tells a plugin that failed for the reason it was
# built to fail apart from one that failed for some other reason.
##
check_expectations() {
	MISMATCH_COUNT=0
	MATCH_COUNT=0

	printf '\nChecking the results against %s.\n\n' "${EXPECT_FILE}"

	while read -r EXPECTED_SLUG EXPECTED_STATUS EXPECTED_TEXT; do
		case "${EXPECTED_SLUG}" in
			'' | '#'* ) continue ;;
		esac

		ROW="$( awk -F '\t' -v slug="${EXPECTED_SLUG}" '$1 == slug { print; exit }' "${RESULTS}" )"
		ACTUAL_STATUS="$( printf '%s' "${ROW}" | cut -f 3 )"
		ACTUAL_TEXT="$( printf '%s' "${ROW}" | cut -f 4- | tr '\t' ' ' )"
		MATCHED="yes"

		if [ -z "${ROW}" ] || [ "${ACTUAL_STATUS}" != "${EXPECTED_STATUS}" ]; then
			MATCHED="no"
		elif [ -n "${EXPECTED_TEXT}" ]; then
			case "${ACTUAL_TEXT}" in
				*"${EXPECTED_TEXT}"* ) ;;
				* ) MATCHED="no" ;;
			esac
		fi

		if [ "${MATCHED}" = "yes" ]; then
			MATCH_COUNT=$(( MATCH_COUNT + 1 ))
			printf 'ok        %s: %s\n' "${EXPECTED_SLUG}" "${EXPECTED_STATUS}"
		else
			MISMATCH_COUNT=$(( MISMATCH_COUNT + 1 ))
			printf 'MISMATCH  %s: expected %s %s\n          got %s %s\n' "${EXPECTED_SLUG}" "${EXPECTED_STATUS}" "${EXPECTED_TEXT}" "${ACTUAL_STATUS:-no result}" "${ACTUAL_TEXT}"
		fi
	done < "${EXPECT_FILE}"

	if [ -n "${GITHUB_STEP_SUMMARY:-}" ]; then
		printf '%s of %s results matched %s.\n\n' "${MATCH_COUNT}" "$(( MATCH_COUNT + MISMATCH_COUNT ))" "$( basename "${EXPECT_FILE}" )" >> "${GITHUB_STEP_SUMMARY}"
	fi

	if [ "${MISMATCH_COUNT}" -gt 0 ]; then
		printf '\n%s result(s) did not match what was expected.\n' "${MISMATCH_COUNT}"
		return 1
	fi

	printf '\nEvery result matched what was expected.\n'
}

if [ "${USE_DOCKER}" = "yes" ]; then
	run_in_docker
	exit $?
fi

run_checks
