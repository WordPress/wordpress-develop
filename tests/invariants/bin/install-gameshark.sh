#!/usr/bin/env bash
#
# Builds the gameshark PHP extension and enables it for every PHP process in this container.
#
# Run it as root inside the PHP container of the local Docker environment, with `npm run env:gameshark`, or:
#
#     docker compose exec -u 0 php bash /var/www/tests/invariants/bin/install-gameshark.sh
#
# It builds the extension from .gameshark/src, which must be a checkout of
# https://github.com/dmsnell/php-ext-gameshark-extension at the commit in tests/invariants/GAMESHARK_REF.
# Rust and the build are kept in .gameshark/ so that later runs reuse them.
#
# The extension is only enabled until the container is recreated, as by `npm run env:restart`.
set -euo pipefail

root="$( cd "$( dirname "${BASH_SOURCE[0]}" )/../../.." && pwd )"
source_dir="$root/.gameshark/src"
export CARGO_HOME="$root/.gameshark/cargo"
export RUSTUP_HOME="$root/.gameshark/rustup"
export PATH="$CARGO_HOME/bin:$PATH"

if [[ ! -f "$source_dir/config.m4" ]]; then
	echo "There is no gameshark source in $source_dir. Check it out with:" >&2
	echo "  git clone https://github.com/dmsnell/php-ext-gameshark-extension.git .gameshark/src" >&2
	echo "  git -C .gameshark/src checkout \"\$( cat tests/invariants/GAMESHARK_REF )\"" >&2
	exit 1
fi

if ! command -v cargo > /dev/null; then
	echo 'Installing Rust...'
	curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y --profile minimal --no-modify-path
fi

cd "$source_dir"
RUN_TESTS=0 PHP_CONFIG="$( command -v php-config )" scripts/build-unix.sh

install -m 0644 modules/gameshark.so "$( php-config --extension-dir )/gameshark.so"
echo 'extension=gameshark.so' > "${PHP_INI_DIR:-/usr/local/etc/php}/conf.d/zz-gameshark.ini"
php --ri gameshark

# Whoever runs the tests, and the CI cache, can read and replace what was built here as root.
chmod -R a+rwX "$root/.gameshark"
