# src/js/enqueues/vendor

In this directory you'll find vendor JavaScript packages that cannot be installed through NPM, but are included in WordPress. Below we've documented the sources for those packages.

## Folder dependencies

- codemirror: https://github.com/codemirror/CodeMirror
- crop: http://www.defusion.org.uk/code/javascript-image-cropper-ui-using-prototype-scriptaculous/download-zip/
- imgareaselect: https://github.com/odyniec/imgareaselect
- jcrop: https://github.com/tapmodo/Jcrop
- mediaelement: https://github.com/mediaelement/mediaelement
- plupload: https://github.com/moxiecode/plupload
- swfupload: https://github.com/WordPress/secure-swfupload
- thickbox: http://codylindley.com/thickbox/
- tinymce: https://github.com/tinymce/tinymce

## Single file dependencies

- colorpicker: http://www.mattkruse.com/javascript/colorpicker/
- deprecated/suggest: Patched by Mark Jaquith with Alexander Dick's "multiple items" patch to allow for auto-suggesting of more than one tag before submitting. See documentation in `suggest.js`.
- farbtastic: https://github.com/mattfarina/farbtastic
- iris: https://github.com/Automattic/Iris
- json2: https://github.com/douglascrockford/JSON-js
- jquery/jquery.color: https://github.com/jquery/jquery-color. Package is on NPM but not published by maintainer.
- jquery/jquery.hotkeys: https://github.com/tzuryby/jquery.hotkeys
- jquery/jquery.masonry: Old version for BC purposes, can't include two versions with NPM. The newer version is included through NPM and built to `wp-includes/js/masonry.min.js`
- jquery/jquery.query: https://github.com/blairmitchelmore/jquery.plugins/blob/master/jquery.query.js
- jquery/jquery.schedule: https://github.com/rse/jquery-schedule
- jquery/jquery.serializeobject: https://github.com/cowboy/jquery-misc/blob/master/jquery.ba-serializeobject.js
- jquery/jquery.table-hotkeys: WP version can be downloaded at https://code.google.com/archive/p/js-hotkeys/downloads?page=2. A newer version is available at https://github.com/jeresig/jquery.hotkeys.
- jquery/jquery.ui.touch-punch.js https://github.com/furf/jquery-ui-touch-punch/blob/master/jquery.ui.touch-punch.js
- swfobject: https://github.com/swfobject/swfobject
- tw-sack: https://github.com/abritinthebay/simpleajaxcodekit
- zxcvbn: https://github.com/dropbox/zxcvbn cannot automatically be installed as the frequency lists need to be manually ROT13 transformed.
