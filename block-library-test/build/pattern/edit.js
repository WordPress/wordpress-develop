"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _blocks = require("@wordpress/blocks");
var _data = require("@wordpress/data");
var _element = require("@wordpress/element");
var _blockEditor = require("@wordpress/block-editor");
var _coreData = require("@wordpress/core-data");
var _i18n = require("@wordpress/i18n");
var _recursionDetector = require("./recursion-detector");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const PatternEdit = ({
  attributes,
  clientId
}) => {
  const selectedPattern = (0, _data.useSelect)(select => select(_blockEditor.store).__experimentalGetParsedPattern(attributes.slug), [attributes.slug]);
  const currentThemeStylesheet = (0, _data.useSelect)(select => select(_coreData.store).getCurrentTheme()?.stylesheet, []);
  const {
    replaceBlocks,
    setBlockEditingMode,
    __unstableMarkNextChangeAsNotPersistent
  } = (0, _data.useDispatch)(_blockEditor.store);
  const {
    getBlockRootClientId,
    getBlockEditingMode
  } = (0, _data.useSelect)(_blockEditor.store);
  const [hasRecursionError, setHasRecursionError] = (0, _element.useState)(false);
  const parsePatternDependencies = (0, _recursionDetector.useParsePatternDependencies)();

  // Duplicated in packages/edit-site/src/components/start-template-options/index.js.
  function injectThemeAttributeInBlockTemplateContent(block) {
    if (block.innerBlocks.find(innerBlock => innerBlock.name === 'core/template-part')) {
      block.innerBlocks = block.innerBlocks.map(innerBlock => {
        if (innerBlock.name === 'core/template-part' && innerBlock.attributes.theme === undefined) {
          innerBlock.attributes.theme = currentThemeStylesheet;
        }
        return innerBlock;
      });
    }
    if (block.name === 'core/template-part' && block.attributes.theme === undefined) {
      block.attributes.theme = currentThemeStylesheet;
    }
    return block;
  }

  // Run this effect when the component loads.
  // This adds the Pattern's contents to the post.
  // This change won't be saved.
  // It will continue to pull from the pattern file unless changes are made to its respective template part.
  (0, _element.useEffect)(() => {
    if (!hasRecursionError && selectedPattern?.blocks) {
      try {
        parsePatternDependencies(selectedPattern);
      } catch (error) {
        setHasRecursionError(true);
        return;
      }

      // We batch updates to block list settings to avoid triggering cascading renders
      // for each container block included in a tree and optimize initial render.
      // Since the above uses microtasks, we need to use a microtask here as well,
      // because nested pattern blocks cannot be inserted if the parent block supports
      // inner blocks but doesn't have blockSettings in the state.
      window.queueMicrotask(() => {
        const rootClientId = getBlockRootClientId(clientId);
        // Clone blocks from the pattern before insertion to ensure they receive
        // distinct client ids. See https://github.com/WordPress/gutenberg/issues/50628.
        const clonedBlocks = selectedPattern.blocks.map(block => (0, _blocks.cloneBlock)(injectThemeAttributeInBlockTemplateContent(block)));
        const rootEditingMode = getBlockEditingMode(rootClientId);
        // Temporarily set the root block to default mode to allow replacing the pattern.
        // This could happen when the page is disabling edits of non-content blocks.
        __unstableMarkNextChangeAsNotPersistent();
        setBlockEditingMode(rootClientId, 'default');
        __unstableMarkNextChangeAsNotPersistent();
        replaceBlocks(clientId, clonedBlocks);
        // Restore the root block's original mode.
        __unstableMarkNextChangeAsNotPersistent();
        setBlockEditingMode(rootClientId, rootEditingMode);
      });
    }
  }, [clientId, hasRecursionError, selectedPattern, __unstableMarkNextChangeAsNotPersistent, replaceBlocks, getBlockEditingMode, setBlockEditingMode, getBlockRootClientId]);
  const props = (0, _blockEditor.useBlockProps)();
  if (hasRecursionError) {
    return (0, _react.createElement)("div", {
      ...props
    }, (0, _react.createElement)(_blockEditor.Warning, null, (0, _i18n.sprintf)(
    // translators: A warning in which %s is the name of a pattern.
    (0, _i18n.__)('Pattern "%s" cannot be rendered inside itself.'), selectedPattern?.name)));
  }
  return (0, _react.createElement)("div", {
    ...props
  });
};
var _default = exports.default = PatternEdit;
//# sourceMappingURL=edit.js.map