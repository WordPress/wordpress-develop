"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.getLevelFromHeadingNodeName = getLevelFromHeadingNodeName;
/**
 * Given a node name string for a heading node, returns its numeric level.
 *
 * @param {string} nodeName Heading node name.
 *
 * @return {number} Heading level.
 */
function getLevelFromHeadingNodeName(nodeName) {
  return Number(nodeName.substr(1));
}
//# sourceMappingURL=shared.js.map