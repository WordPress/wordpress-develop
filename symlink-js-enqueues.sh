#!/bin/bash
# See https://gist.github.com/westonruter/183bd7847539f5d74dc21f4b98b660ec

set -e
set -x

cd "$(dirname "$0")"
cd src/wp-includes/js

ln -sf ../../js/_enqueues/wp/emoji.js wp-emoji.js

ln -sf ../../js/_enqueues/vendor/twemoji.js twemoji.js

ln -sf ../../js/_enqueues/lib/emoji-loader.js wp-emoji-loader.js

ln -sf ../../js/_enqueues/wp/heartbeat.js heartbeat.js

ln -sf ../../js/_enqueues/lib/auth-check.js wp-auth-check.js

ln -sf ../../../js/_enqueues/deprecated/fakejshint.js codemirror/fakejshint.js

cd - > /dev/null
cd src/wp-admin/js

ln -sf ../../js/_enqueues/wp/code-editor.js code-editor.js
ln -sf ../../js/_enqueues/wp/theme-plugin-editor.js theme-plugin-editor.js

ln -sf ../../js/_enqueues/lib/nav-menu.js nav-menu.js

ln -sf ../../js/_enqueues/wp/customize/nav-menus.js customize-nav-menus.js
