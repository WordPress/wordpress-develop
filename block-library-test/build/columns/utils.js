"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.getColumnWidths = getColumnWidths;
exports.getEffectiveColumnWidth = getEffectiveColumnWidth;
exports.getMappedColumnWidths = getMappedColumnWidths;
exports.getRedistributedColumnWidths = getRedistributedColumnWidths;
exports.getTotalColumnsWidth = getTotalColumnsWidth;
exports.getWidthWithUnit = getWidthWithUnit;
exports.getWidths = getWidths;
exports.hasExplicitPercentColumnWidths = hasExplicitPercentColumnWidths;
exports.isPercentageUnit = isPercentageUnit;
exports.toWidthPrecision = void 0;
/**
 * Returns a column width attribute value rounded to standard precision.
 * Returns `undefined` if the value is not a valid finite number.
 *
 * @param {?number} value Raw value.
 *
 * @return {number} Value rounded to standard precision.
 */
const toWidthPrecision = value => {
  const unitlessValue = parseFloat(value);
  return Number.isFinite(unitlessValue) ? parseFloat(unitlessValue.toFixed(2)) : undefined;
};
/**
 * Returns an effective width for a given block. An effective width is equal to
 * its attribute value if set, or a computed value assuming equal distribution.
 *
 * @param {WPBlock} block           Block object.
 * @param {number}  totalBlockCount Total number of blocks in Columns.
 *
 * @return {number} Effective column width.
 */
exports.toWidthPrecision = toWidthPrecision;
function getEffectiveColumnWidth(block, totalBlockCount) {
  const {
    width = 100 / totalBlockCount
  } = block.attributes;
  return toWidthPrecision(width);
}

/**
 * Returns the total width occupied by the given set of column blocks.
 *
 * @param {WPBlock[]} blocks          Block objects.
 * @param {?number}   totalBlockCount Total number of blocks in Columns.
 *                                    Defaults to number of blocks passed.
 *
 * @return {number} Total width occupied by blocks.
 */
function getTotalColumnsWidth(blocks, totalBlockCount = blocks.length) {
  return blocks.reduce((sum, block) => sum + getEffectiveColumnWidth(block, totalBlockCount), 0);
}

/**
 * Returns an object of `clientId` → `width` of effective column widths.
 *
 * @param {WPBlock[]} blocks          Block objects.
 * @param {?number}   totalBlockCount Total number of blocks in Columns.
 *                                    Defaults to number of blocks passed.
 *
 * @return {Object<string,number>} Column widths.
 */
function getColumnWidths(blocks, totalBlockCount = blocks.length) {
  return blocks.reduce((accumulator, block) => {
    const width = getEffectiveColumnWidth(block, totalBlockCount);
    return Object.assign(accumulator, {
      [block.clientId]: width
    });
  }, {});
}

/**
 * Returns an object of `clientId` → `width` of column widths as redistributed
 * proportional to their current widths, constrained or expanded to fit within
 * the given available width.
 *
 * @param {WPBlock[]} blocks          Block objects.
 * @param {number}    availableWidth  Maximum width to fit within.
 * @param {?number}   totalBlockCount Total number of blocks in Columns.
 *                                    Defaults to number of blocks passed.
 *
 * @return {Object<string,number>} Redistributed column widths.
 */
function getRedistributedColumnWidths(blocks, availableWidth, totalBlockCount = blocks.length) {
  const totalWidth = getTotalColumnsWidth(blocks, totalBlockCount);
  return Object.fromEntries(Object.entries(getColumnWidths(blocks, totalBlockCount)).map(([clientId, width]) => {
    const newWidth = availableWidth * width / totalWidth;
    return [clientId, toWidthPrecision(newWidth)];
  }));
}

/**
 * Returns true if column blocks within the provided set are assigned with
 * explicit widths, or false otherwise.
 *
 * @param {WPBlock[]} blocks Block objects.
 *
 * @return {boolean} Whether columns have explicit widths.
 */
function hasExplicitPercentColumnWidths(blocks) {
  return blocks.every(block => {
    const blockWidth = block.attributes.width;
    return Number.isFinite(blockWidth?.endsWith?.('%') ? parseFloat(blockWidth) : blockWidth);
  });
}

/**
 * Returns a copy of the given set of blocks with new widths assigned from the
 * provided object of redistributed column widths.
 *
 * @param {WPBlock[]}             blocks Block objects.
 * @param {Object<string,number>} widths Redistributed column widths.
 *
 * @return {WPBlock[]} blocks Mapped block objects.
 */
function getMappedColumnWidths(blocks, widths) {
  return blocks.map(block => ({
    ...block,
    attributes: {
      ...block.attributes,
      width: `${widths[block.clientId]}%`
    }
  }));
}

/**
 * Returns an array with columns widths values, parsed or no depends on `withParsing` flag.
 *
 * @param {WPBlock[]} blocks      Block objects.
 * @param {?boolean}  withParsing Whether value has to be parsed.
 *
 * @return {Array<number,string>} Column widths.
 */
function getWidths(blocks, withParsing = true) {
  return blocks.map(innerColumn => {
    const innerColumnWidth = innerColumn.attributes.width || 100 / blocks.length;
    return withParsing ? parseFloat(innerColumnWidth) : innerColumnWidth;
  });
}

/**
 * Returns a column width with unit.
 *
 * @param {string} width Column width.
 * @param {string} unit  Column width unit.
 *
 * @return {string} Column width with unit.
 */
function getWidthWithUnit(width, unit) {
  width = 0 > parseFloat(width) ? '0' : width;
  if (isPercentageUnit(unit)) {
    width = Math.min(width, 100);
  }
  return `${width}${unit}`;
}

/**
 * Returns a boolean whether passed unit is percentage
 *
 * @param {string} unit Column width unit.
 *
 * @return {boolean} 	Whether unit is '%'.
 */
function isPercentageUnit(unit) {
  return unit === '%';
}
//# sourceMappingURL=utils.js.map