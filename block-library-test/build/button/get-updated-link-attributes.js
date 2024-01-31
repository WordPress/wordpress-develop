"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.getUpdatedLinkAttributes = getUpdatedLinkAttributes;
var _constants = require("./constants");
var _url = require("@wordpress/url");
/**
 * Internal dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Updates the link attributes.
 *
 * @param {Object}  attributes               The current block attributes.
 * @param {string}  attributes.rel           The current link rel attribute.
 * @param {string}  attributes.url           The current link url.
 * @param {boolean} attributes.opensInNewTab Whether the link should open in a new window.
 * @param {boolean} attributes.nofollow      Whether the link should be marked as nofollow.
 */
function getUpdatedLinkAttributes({
  rel = '',
  url = '',
  opensInNewTab,
  nofollow
}) {
  let newLinkTarget;
  // Since `rel` is editable attribute, we need to check for existing values and proceed accordingly.
  let updatedRel = rel;
  if (opensInNewTab) {
    newLinkTarget = _constants.NEW_TAB_TARGET;
    updatedRel = updatedRel?.includes(_constants.NEW_TAB_REL) ? updatedRel : updatedRel + ` ${_constants.NEW_TAB_REL}`;
  } else {
    const relRegex = new RegExp(`\\b${_constants.NEW_TAB_REL}\\s*`, 'g');
    updatedRel = updatedRel?.replace(relRegex, '').trim();
  }
  if (nofollow) {
    updatedRel = updatedRel?.includes(_constants.NOFOLLOW_REL) ? updatedRel : updatedRel + ` ${_constants.NOFOLLOW_REL}`;
  } else {
    const relRegex = new RegExp(`\\b${_constants.NOFOLLOW_REL}\\s*`, 'g');
    updatedRel = updatedRel?.replace(relRegex, '').trim();
  }
  return {
    url: (0, _url.prependHTTP)(url),
    linkTarget: newLinkTarget,
    rel: updatedRel || undefined
  };
}
//# sourceMappingURL=get-updated-link-attributes.js.map