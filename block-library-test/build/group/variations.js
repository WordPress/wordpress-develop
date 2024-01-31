"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
/**
 * WordPress dependencies
 */

const variations = [{
  name: 'group',
  title: (0, _i18n.__)('Group'),
  description: (0, _i18n.__)('Gather blocks in a container.'),
  attributes: {
    layout: {
      type: 'constrained'
    }
  },
  isDefault: true,
  scope: ['block', 'inserter', 'transform'],
  isActive: blockAttributes => !blockAttributes.layout || !blockAttributes.layout?.type || blockAttributes.layout?.type === 'default' || blockAttributes.layout?.type === 'constrained',
  icon: _icons.group
}, {
  name: 'group-row',
  title: (0, _i18n._x)('Row', 'single horizontal line'),
  description: (0, _i18n.__)('Arrange blocks horizontally.'),
  attributes: {
    layout: {
      type: 'flex',
      flexWrap: 'nowrap'
    }
  },
  scope: ['block', 'inserter', 'transform'],
  isActive: blockAttributes => blockAttributes.layout?.type === 'flex' && (!blockAttributes.layout?.orientation || blockAttributes.layout?.orientation === 'horizontal'),
  icon: _icons.row
}, {
  name: 'group-stack',
  title: (0, _i18n.__)('Stack'),
  description: (0, _i18n.__)('Arrange blocks vertically.'),
  attributes: {
    layout: {
      type: 'flex',
      orientation: 'vertical'
    }
  },
  scope: ['block', 'inserter', 'transform'],
  isActive: blockAttributes => blockAttributes.layout?.type === 'flex' && blockAttributes.layout?.orientation === 'vertical',
  icon: _icons.stack
}];
if (window?.__experimentalEnableGroupGridVariation) {
  variations.push({
    name: 'group-grid',
    title: (0, _i18n.__)('Grid'),
    description: (0, _i18n.__)('Arrange blocks in a grid.'),
    attributes: {
      layout: {
        type: 'grid'
      }
    },
    scope: ['block', 'inserter', 'transform'],
    isActive: blockAttributes => blockAttributes.layout?.type === 'grid',
    icon: _icons.grid
  });
}
var _default = exports.default = variations;
//# sourceMappingURL=variations.js.map