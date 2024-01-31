"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.PX_WIDTH_DEFAULT = exports.PC_WIDTH_DEFAULT = exports.MIN_WIDTH = void 0;
exports.isPercentageUnit = isPercentageUnit;
/**
 * Constants
 */
const PC_WIDTH_DEFAULT = exports.PC_WIDTH_DEFAULT = 50;
const PX_WIDTH_DEFAULT = exports.PX_WIDTH_DEFAULT = 350;
const MIN_WIDTH = exports.MIN_WIDTH = 220;

/**
 * Returns a boolean whether passed unit is percentage
 *
 * @param {string} unit Block width unit.
 *
 * @return {boolean} 	Whether unit is '%'.
 */
function isPercentageUnit(unit) {
  return unit === '%';
}
//# sourceMappingURL=utils.js.map