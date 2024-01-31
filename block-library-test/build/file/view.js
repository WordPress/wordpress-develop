"use strict";

var _interactivity = require("@wordpress/interactivity");
var _utils = require("./utils");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

(0, _interactivity.store)('core/file', {
  state: {
    get hasPdfPreview() {
      return (0, _utils.browserSupportsPdfs)();
    }
  }
});
//# sourceMappingURL=view.js.map