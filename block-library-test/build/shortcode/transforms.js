"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _autop = require("@wordpress/autop");
/**
 * WordPress dependencies
 */

const transforms = {
  from: [{
    type: 'shortcode',
    // Per "Shortcode names should be all lowercase and use all
    // letters, but numbers and underscores should work fine too.
    // Be wary of using hyphens (dashes), you'll be better off not
    // using them." in https://codex.wordpress.org/Shortcode_API
    // Require that the first character be a letter. This notably
    // prevents footnote markings ([1]) from being caught as
    // shortcodes.
    tag: '[a-z][a-z0-9_-]*',
    attributes: {
      text: {
        type: 'string',
        shortcode: (attrs, {
          content
        }) => {
          return (0, _autop.removep)((0, _autop.autop)(content));
        }
      }
    },
    priority: 20
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map