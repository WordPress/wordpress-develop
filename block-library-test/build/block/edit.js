"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = ReusableBlockEdit;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _data = require("@wordpress/data");
var _element = require("@wordpress/element");
var _coreData = require("@wordpress/core-data");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _blockEditor = require("@wordpress/block-editor");
var _patterns = require("@wordpress/patterns");
var _blocks = require("@wordpress/blocks");
var _lockUnlock = require("../lock-unlock");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const {
  useLayoutClasses
} = (0, _lockUnlock.unlock)(_blockEditor.privateApis);
const {
  PARTIAL_SYNCING_SUPPORTED_BLOCKS
} = (0, _lockUnlock.unlock)(_patterns.privateApis);
function isPartiallySynced(block) {
  return Object.keys(PARTIAL_SYNCING_SUPPORTED_BLOCKS).includes(block.name) && !!block.attributes.metadata?.bindings && Object.values(block.attributes.metadata.bindings).some(binding => binding.source === 'core/pattern-overrides');
}
function getPartiallySyncedAttributes(block) {
  return Object.entries(block.attributes.metadata.bindings).filter(([, binding]) => binding.source === 'core/pattern-overrides').map(([attributeKey]) => attributeKey);
}
const fullAlignments = ['full', 'wide', 'left', 'right'];
const useInferredLayout = (blocks, parentLayout) => {
  const initialInferredAlignmentRef = (0, _element.useRef)();
  return (0, _element.useMemo)(() => {
    // Exit early if the pattern's blocks haven't loaded yet.
    if (!blocks?.length) {
      return {};
    }
    let alignment = initialInferredAlignmentRef.current;

    // Only track the initial alignment so that temporarily removed
    // alignments can be reapplied.
    if (alignment === undefined) {
      const isConstrained = parentLayout?.type === 'constrained';
      const hasFullAlignment = blocks.some(block => fullAlignments.includes(block.attributes.align));
      alignment = isConstrained && hasFullAlignment ? 'full' : null;
      initialInferredAlignmentRef.current = alignment;
    }
    const layout = alignment ? parentLayout : undefined;
    return {
      alignment,
      layout
    };
  }, [blocks, parentLayout]);
};

/**
 * Enum for patch operations.
 * We use integers here to minimize the size of the serialized data.
 * This has to be deserialized accordingly on the server side.
 * See block-bindings/sources/pattern.php
 */
const PATCH_OPERATIONS = {
  /** @type {0} */
  Remove: 0,
  /** @type {1} */
  Replace: 1
  // Other operations are reserved for future use. (e.g. Add)
};

/**
 * @typedef {[typeof PATCH_OPERATIONS.Remove]} RemovePatch
 * @typedef {[typeof PATCH_OPERATIONS.Replace, unknown]} ReplacePatch
 * @typedef {RemovePatch | ReplacePatch} OverridePatch
 */

function applyInitialOverrides(blocks, overrides = {}, defaultValues) {
  return blocks.map(block => {
    const innerBlocks = applyInitialOverrides(block.innerBlocks, overrides, defaultValues);
    const blockId = block.attributes.metadata?.id;
    if (!isPartiallySynced(block) || !blockId) return {
      ...block,
      innerBlocks
    };
    const attributes = getPartiallySyncedAttributes(block);
    const newAttributes = {
      ...block.attributes
    };
    for (const attributeKey of attributes) {
      var _defaultValues$blockI;
      (_defaultValues$blockI = defaultValues[blockId]) !== null && _defaultValues$blockI !== void 0 ? _defaultValues$blockI : defaultValues[blockId] = {};
      defaultValues[blockId][attributeKey] = block.attributes[attributeKey];
      /** @type {OverridePatch} */
      const overrideAttribute = overrides[blockId]?.[attributeKey];
      if (!overrideAttribute) {
        continue;
      }
      if (overrideAttribute[0] === PATCH_OPERATIONS.Remove) {
        delete newAttributes[attributeKey];
      } else if (overrideAttribute[0] === PATCH_OPERATIONS.Replace) {
        newAttributes[attributeKey] = overrideAttribute[1];
      }
    }
    return {
      ...block,
      attributes: newAttributes,
      innerBlocks
    };
  });
}
function getOverridesFromBlocks(blocks, defaultValues) {
  /** @type {Record<string, Record<string, OverridePatch>>} */
  const overrides = {};
  for (const block of blocks) {
    Object.assign(overrides, getOverridesFromBlocks(block.innerBlocks, defaultValues));
    /** @type {string} */
    const blockId = block.attributes.metadata?.id;
    if (!isPartiallySynced(block) || !blockId) continue;
    const attributes = getPartiallySyncedAttributes(block);
    for (const attributeKey of attributes) {
      if (block.attributes[attributeKey] !== defaultValues[blockId][attributeKey]) {
        var _overrides$blockId;
        (_overrides$blockId = overrides[blockId]) !== null && _overrides$blockId !== void 0 ? _overrides$blockId : overrides[blockId] = {};
        /**
         * Create a patch operation for the binding attribute.
         * We use a tuple here to minimize the size of the serialized data.
         * The first item is the operation type, the second item is the value if any.
         */
        if (block.attributes[attributeKey] === undefined) {
          /** @type {RemovePatch} */
          overrides[blockId][attributeKey] = [PATCH_OPERATIONS.Remove];
        } else {
          /** @type {ReplacePatch} */
          overrides[blockId][attributeKey] = [PATCH_OPERATIONS.Replace, block.attributes[attributeKey]];
        }
      }
    }
  }
  return Object.keys(overrides).length > 0 ? overrides : undefined;
}
function setBlockEditMode(setEditMode, blocks, mode) {
  blocks.forEach(block => {
    const editMode = mode || (isPartiallySynced(block) ? 'contentOnly' : 'disabled');
    setEditMode(block.clientId, editMode);
    setBlockEditMode(setEditMode, block.innerBlocks, mode);
  });
}
function getHasOverridableBlocks(blocks) {
  return blocks.some(block => {
    if (isPartiallySynced(block)) return true;
    return getHasOverridableBlocks(block.innerBlocks);
  });
}
function ReusableBlockEdit({
  name,
  attributes: {
    ref,
    overrides
  },
  __unstableParentLayout: parentLayout,
  clientId: patternClientId,
  setAttributes
}) {
  const registry = (0, _data.useRegistry)();
  const hasAlreadyRendered = (0, _blockEditor.useHasRecursion)(ref);
  const {
    record,
    editedRecord,
    hasResolved
  } = (0, _coreData.useEntityRecord)('postType', 'wp_block', ref);
  const isMissing = hasResolved && !record;
  const initialOverrides = (0, _element.useRef)(overrides);
  const defaultValuesRef = (0, _element.useRef)({});
  const {
    replaceInnerBlocks,
    __unstableMarkNextChangeAsNotPersistent,
    setBlockEditingMode
  } = (0, _data.useDispatch)(_blockEditor.store);
  const {
    syncDerivedUpdates
  } = (0, _lockUnlock.unlock)((0, _data.useDispatch)(_blockEditor.store));
  const {
    innerBlocks,
    userCanEdit,
    getBlockEditingMode,
    getPostLinkProps
  } = (0, _data.useSelect)(select => {
    const {
      canUser
    } = select(_coreData.store);
    const {
      getBlocks,
      getBlockEditingMode: editingMode,
      getSettings
    } = select(_blockEditor.store);
    const blocks = getBlocks(patternClientId);
    const canEdit = canUser('update', 'blocks', ref);

    // For editing link to the site editor if the theme and user permissions support it.
    return {
      innerBlocks: blocks,
      userCanEdit: canEdit,
      getBlockEditingMode: editingMode,
      getPostLinkProps: getSettings().getPostLinkProps
    };
  }, [patternClientId, ref]);
  const editOriginalProps = getPostLinkProps ? getPostLinkProps({
    postId: ref,
    postType: 'wp_block'
  }) : {};
  (0, _element.useEffect)(() => setBlockEditMode(setBlockEditingMode, innerBlocks), [innerBlocks, setBlockEditingMode]);
  const hasOverridableBlocks = (0, _element.useMemo)(() => getHasOverridableBlocks(innerBlocks), [innerBlocks]);
  const initialBlocks = (0, _element.useMemo)(() => {
    var _editedRecord$blocks$;
    return (// Clone the blocks to generate new client IDs.
      (_editedRecord$blocks$ = editedRecord.blocks?.map(block => (0, _blocks.cloneBlock)(block))) !== null && _editedRecord$blocks$ !== void 0 ? _editedRecord$blocks$ : editedRecord.content && typeof editedRecord.content !== 'function' ? (0, _blocks.parse)(editedRecord.content) : []
    );
  }, [editedRecord.blocks, editedRecord.content]);

  // Apply the initial overrides from the pattern block to the inner blocks.
  (0, _element.useEffect)(() => {
    defaultValuesRef.current = {};
    const editingMode = getBlockEditingMode(patternClientId);
    // Replace the contents of the blocks with the overrides.
    registry.batch(() => {
      setBlockEditingMode(patternClientId, 'default');
      syncDerivedUpdates(() => {
        replaceInnerBlocks(patternClientId, applyInitialOverrides(initialBlocks, initialOverrides.current, defaultValuesRef.current));
      });
      setBlockEditingMode(patternClientId, editingMode);
    });
  }, [__unstableMarkNextChangeAsNotPersistent, patternClientId, initialBlocks, replaceInnerBlocks, registry, getBlockEditingMode, setBlockEditingMode, syncDerivedUpdates]);
  const {
    alignment,
    layout
  } = useInferredLayout(innerBlocks, parentLayout);
  const layoutClasses = useLayoutClasses({
    layout
  }, name);
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)('block-library-block__reusable-block-container', layout && layoutClasses, {
      [`align${alignment}`]: alignment
    })
  });
  const innerBlocksProps = (0, _blockEditor.useInnerBlocksProps)(blockProps, {
    templateLock: 'all',
    layout,
    renderAppender: innerBlocks?.length ? undefined : _blockEditor.InnerBlocks.ButtonBlockAppender
  });

  // Sync the `overrides` attribute from the updated blocks to the pattern block.
  // `syncDerivedUpdates` is used here to avoid creating an additional undo level.
  (0, _element.useEffect)(() => {
    const {
      getBlocks
    } = registry.select(_blockEditor.store);
    let prevBlocks = getBlocks(patternClientId);
    return registry.subscribe(() => {
      const blocks = getBlocks(patternClientId);
      if (blocks !== prevBlocks) {
        prevBlocks = blocks;
        syncDerivedUpdates(() => {
          setAttributes({
            overrides: getOverridesFromBlocks(blocks, defaultValuesRef.current)
          });
        });
      }
    }, _blockEditor.store);
  }, [syncDerivedUpdates, patternClientId, registry, setAttributes]);
  const handleEditOriginal = event => {
    setBlockEditMode(setBlockEditingMode, innerBlocks, 'default');
    editOriginalProps.onClick(event);
  };
  const resetOverrides = () => {
    if (overrides) {
      replaceInnerBlocks(patternClientId, initialBlocks);
    }
  };
  let children = null;
  if (hasAlreadyRendered) {
    children = (0, _react.createElement)(_blockEditor.Warning, null, (0, _i18n.__)('Block cannot be rendered inside itself.'));
  }
  if (isMissing) {
    children = (0, _react.createElement)(_blockEditor.Warning, null, (0, _i18n.__)('Block has been deleted or is unavailable.'));
  }
  if (!hasResolved) {
    children = (0, _react.createElement)(_components.Placeholder, null, (0, _react.createElement)(_components.Spinner, null));
  }
  return (0, _react.createElement)(_blockEditor.RecursionProvider, {
    uniqueId: ref
  }, userCanEdit && editOriginalProps && (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_components.ToolbarGroup, null, (0, _react.createElement)(_components.ToolbarButton, {
    href: editOriginalProps.href,
    onClick: handleEditOriginal
  }, (0, _i18n.__)('Edit original')))), hasOverridableBlocks && (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_components.ToolbarGroup, null, (0, _react.createElement)(_components.ToolbarButton, {
    onClick: resetOverrides,
    disabled: !overrides,
    __experimentalIsFocusable: true
  }, (0, _i18n.__)('Reset')))), children === null ? (0, _react.createElement)("div", {
    ...innerBlocksProps
  }) : (0, _react.createElement)("div", {
    ...blockProps
  }, children));
}
//# sourceMappingURL=edit.js.map