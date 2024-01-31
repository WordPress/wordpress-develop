"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
exports.useCanEditEntity = useCanEditEntity;
var _data = require("@wordpress/data");
var _coreData = require("@wordpress/core-data");
/**
 * WordPress dependencies
 */

/**
 * Returns whether the current user can edit the given entity.
 *
 * @param {string} kind     Entity kind.
 * @param {string} name     Entity name.
 * @param {string} recordId Record's id.
 */
function useCanEditEntity(kind, name, recordId) {
  return (0, _data.useSelect)(select => select(_coreData.store).canUserEditEntityRecord(kind, name, recordId), [kind, name, recordId]);
}
var _default = exports.default = {
  useCanEditEntity
};
//# sourceMappingURL=hooks.js.map