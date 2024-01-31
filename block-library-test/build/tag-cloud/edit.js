"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _components = require("@wordpress/components");
var _data = require("@wordpress/data");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _serverSideRender = _interopRequireDefault(require("@wordpress/server-side-render"));
var _coreData = require("@wordpress/core-data");
/**
 * WordPress dependencies
 */

/**
 * Minimum number of tags a user can show using this block.
 *
 * @type {number}
 */
const MIN_TAGS = 1;

/**
 * Maximum number of tags a user can show using this block.
 *
 * @type {number}
 */
const MAX_TAGS = 100;
const MIN_FONT_SIZE = 0.1;
const MAX_FONT_SIZE = 100;
function TagCloudEdit({
  attributes,
  setAttributes
}) {
  const {
    taxonomy,
    showTagCounts,
    numberOfTags,
    smallestFontSize,
    largestFontSize
  } = attributes;
  const [availableUnits] = (0, _blockEditor.useSettings)('spacing.units');
  const units = (0, _components.__experimentalUseCustomUnits)({
    availableUnits: availableUnits || ['%', 'px', 'em', 'rem']
  });
  const taxonomies = (0, _data.useSelect)(select => select(_coreData.store).getTaxonomies({
    per_page: -1
  }), []);
  const getTaxonomyOptions = () => {
    const selectOption = {
      label: (0, _i18n.__)('- Select -'),
      value: '',
      disabled: true
    };
    const taxonomyOptions = (taxonomies !== null && taxonomies !== void 0 ? taxonomies : []).filter(tax => !!tax.show_cloud).map(item => {
      return {
        value: item.slug,
        label: item.name
      };
    });
    return [selectOption, ...taxonomyOptions];
  };
  const onFontSizeChange = (fontSizeLabel, newValue) => {
    // eslint-disable-next-line @wordpress/no-unused-vars-before-return
    const [quantity, newUnit] = (0, _components.__experimentalParseQuantityAndUnitFromRawValue)(newValue);
    if (!Number.isFinite(quantity)) {
      return;
    }
    const updateObj = {
      [fontSizeLabel]: newValue
    };
    // We need to keep in sync the `unit` changes to both `smallestFontSize`
    // and `largestFontSize` attributes.
    Object.entries({
      smallestFontSize,
      largestFontSize
    }).forEach(([attribute, currentValue]) => {
      const [currentQuantity, currentUnit] = (0, _components.__experimentalParseQuantityAndUnitFromRawValue)(currentValue);
      // Only add an update if the other font size attribute has a different unit.
      if (attribute !== fontSizeLabel && currentUnit !== newUnit) {
        updateObj[attribute] = `${currentQuantity}${newUnit}`;
      }
    });
    setAttributes(updateObj);
  };
  const inspectorControls = (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Taxonomy'),
    options: getTaxonomyOptions(),
    value: taxonomy,
    onChange: selectedTaxonomy => setAttributes({
      taxonomy: selectedTaxonomy
    })
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Show post counts'),
    checked: showTagCounts,
    onChange: () => setAttributes({
      showTagCounts: !showTagCounts
    })
  }), (0, _react.createElement)(_components.RangeControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: (0, _i18n.__)('Number of tags'),
    value: numberOfTags,
    onChange: value => setAttributes({
      numberOfTags: value
    }),
    min: MIN_TAGS,
    max: MAX_TAGS,
    required: true
  }), (0, _react.createElement)(_components.Flex, null, (0, _react.createElement)(_components.FlexItem, {
    isBlock: true
  }, (0, _react.createElement)(_components.__experimentalUnitControl, {
    label: (0, _i18n.__)('Smallest size'),
    value: smallestFontSize,
    onChange: value => {
      onFontSizeChange('smallestFontSize', value);
    },
    units: units,
    min: MIN_FONT_SIZE,
    max: MAX_FONT_SIZE
  })), (0, _react.createElement)(_components.FlexItem, {
    isBlock: true
  }, (0, _react.createElement)(_components.__experimentalUnitControl, {
    label: (0, _i18n.__)('Largest size'),
    value: largestFontSize,
    onChange: value => {
      onFontSizeChange('largestFontSize', value);
    },
    units: units,
    min: MIN_FONT_SIZE,
    max: MAX_FONT_SIZE
  })))));
  return (0, _react.createElement)(_react.Fragment, null, inspectorControls, (0, _react.createElement)("div", {
    ...(0, _blockEditor.useBlockProps)()
  }, (0, _react.createElement)(_components.Disabled, null, (0, _react.createElement)(_serverSideRender.default, {
    skipBlockSupportAttributes: true,
    block: "core/tag-cloud",
    attributes: attributes
  }))));
}
var _default = exports.default = TagCloudEdit;
//# sourceMappingURL=edit.js.map