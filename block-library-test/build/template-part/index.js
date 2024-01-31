"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.settings = exports.name = exports.metadata = exports.init = void 0;
var _changeCase = require("change-case");
var _coreData = require("@wordpress/core-data");
var _data = require("@wordpress/data");
var _icons = require("@wordpress/icons");
var _hooks = require("@wordpress/hooks");
var _htmlEntities = require("@wordpress/html-entities");
var _initBlock = _interopRequireDefault(require("../utils/init-block"));
var _edit = _interopRequireDefault(require("./edit"));
var _variations = require("./variations");
/**
 * External dependencies
 */
/**
 * WordPress dependencies
 */
/**
 * Internal dependencies
 */
const metadata = exports.metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/template-part",
  title: "Template Part",
  category: "theme",
  description: "Edit the different global regions of your site, like the header, footer, sidebar, or create your own.",
  textdomain: "default",
  attributes: {
    slug: {
      type: "string"
    },
    theme: {
      type: "string"
    },
    tagName: {
      type: "string"
    },
    area: {
      type: "string"
    }
  },
  supports: {
    align: true,
    html: false,
    reusable: false,
    renaming: false
  },
  editorStyle: "wp-block-template-part-editor"
};
const {
  name
} = metadata;
exports.name = name;
const settings = exports.settings = {
  icon: _icons.symbolFilled,
  __experimentalLabel: ({
    slug,
    theme
  }) => {
    // Attempt to find entity title if block is a template part.
    // Require slug to request, otherwise entity is uncreated and will throw 404.
    if (!slug) {
      return;
    }
    const {
      getCurrentTheme,
      getEntityRecord
    } = (0, _data.select)(_coreData.store);
    const entity = getEntityRecord('postType', 'wp_template_part', (theme || getCurrentTheme()?.stylesheet) + '//' + slug);
    if (!entity) {
      return;
    }
    return (0, _htmlEntities.decodeEntities)(entity.title?.rendered) || (0, _changeCase.capitalCase)(entity.slug);
  },
  edit: _edit.default
};
const init = () => {
  (0, _hooks.addFilter)('blocks.registerBlockType', 'core/template-part', _variations.enhanceTemplatePartVariations);

  // Prevent adding template parts inside post templates.
  const DISALLOWED_PARENTS = ['core/post-template', 'core/post-content'];
  (0, _hooks.addFilter)('blockEditor.__unstableCanInsertBlockType', 'core/block-library/removeTemplatePartsFromPostTemplates', (canInsert, blockType, rootClientId, {
    getBlock,
    getBlockParentsByBlockName
  }) => {
    if (blockType.name !== 'core/template-part') {
      return canInsert;
    }
    for (const disallowedParentType of DISALLOWED_PARENTS) {
      const hasDisallowedParent = getBlock(rootClientId)?.name === disallowedParentType || getBlockParentsByBlockName(rootClientId, disallowedParentType).length;
      if (hasDisallowedParent) {
        return false;
      }
    }
    return true;
  });
  return (0, _initBlock.default)({
    name,
    metadata,
    settings
  });
};
exports.init = init;
//# sourceMappingURL=index.js.map