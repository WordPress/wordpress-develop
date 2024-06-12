# src/js/enqueues/vendor

In this directory you'll find vendor JavaScript packages that cannot be installed through npm, but are included in WordPress. Below we've documented the sources for those packages.

## Folder dependencies

- codemirror: https://github.com/codemirror/CodeMirror
- crop: http://www.defusion.org.uk/code/javascript-image-cropper-ui-using-prototype-scriptaculous/download-zip/
- imgareaselect: https://github.com/odyniec/imgareaselect
- jcrop: https://github.com/tapmodo/Jcrop
- mediaelement: https://github.com/mediaelement/mediaelement
- plupload: https://github.com/moxiecode/plupload
- swfupload: https://github.com/WordPress/secure-swfupload
- thickbox: https://codylindley.com/thickbox/
- tinymce: https://www.tiny.cloud/get-tiny/self-hosted/
  - Download "TinyMCE Dev Package". This package is needed because it includes
    the `compat3x` plugin.
  - Open the package and go to `js/tinymce`.
  - Replace all the following files and folders:
    * license.txt
    * plugins
      * charmap
      * colorpicker
      * compat3x
      * directionality
      * fullscreen
      * hr
      * image
      * link
      * lists
      * media
      * paste
      * tabfocus
      * textcolor
    * skins
      * lightgray
    * themes
      * inlite
      * modern
    * tinymce.js
    * tinymce.min.js
  - Go to the `compat3x` plugin folder and move `tiny_mce_popup.js` and `utils`
    to the root directory. Delete the `img` folder. Revert `css/dialog.css`.
  - Go to the `lightgray` skin folder and delete `content.mobile.min.css`,
    `fonts/tinymce-mobile.woff`, `skin.min.css.map`, `skin.mobile.min.css` and
    `skin.mobile.min.css.map`.
  - After all these steps, there should normally not be any file additions or
    deletions when you run `svn status`, only file modifications. If there are,
    make sure it's intentional.
  - Update the TinyMCE version in `src/wp-includes/version.php`. Use the
    following format:
    - Major version number.
    - Minor version number.
    - Patch version number, holding 2 places.
    - A dash "-".
    - The date: YYYYMMDD.

## Single file dependencies

- colorpicker: http://www.mattkruse.com/javascript/colorpicker/
- deprecated/suggest: Patched by Mark Jaquith with Alexander Dick's "multiple items" patch to allow for auto-suggesting of more than one tag before submitting. See documentation in `suggest.js`.
- farbtastic: https://github.com/mattfarina/farbtastic
- iris: https://github.com/Automattic/Iris
- json2: https://github.com/douglascrockford/JSON-js
- jquery/jquery.color: https://github.com/jquery/jquery-color. Package is on npm but not published by maintainer.
- jquery/jquery.hotkeys: https://github.com/tzuryby/jquery.hotkeys
- jquery/jquery.masonry: Old version for BC purposes, can't include two versions with npm. The newer version is included through npm and built to `wp-includes/js/masonry.min.js`
- jquery/jquery.query: https://github.com/blairmitchelmore/jquery.plugins/blob/master/jquery.query.js
- jquery/jquery.schedule: https://github.com/rse/jquery-schedule
- jquery/jquery.serializeobject: https://github.com/cowboy/jquery-misc/blob/master/jquery.ba-serializeobject.js
- jquery/jquery.table-hotkeys: WP version can be downloaded at https://code.google.com/archive/p/js-hotkeys/downloads?page=2. A newer version is available at https://github.com/jeresig/jquery.hotkeys.
- jquery/jquery.ui.touch-punch.js https://github.com/furf/jquery-ui-touch-punch/blob/master/jquery.ui.touch-punch.js
- swfobject: https://github.com/swfobject/swfobject
- tw-sack: https://github.com/abritinthebay/simpleajaxcodekit
- zxcvbn: https://github.com/dropbox/zxcvbn cannot automatically be installed as the frequency lists need to be manually ROT13 transformed.
