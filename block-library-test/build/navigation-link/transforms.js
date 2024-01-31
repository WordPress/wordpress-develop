"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _blocks = require("@wordpress/blocks");
/**
 * WordPress dependencies
 */

const transforms = {
  from: [{
    type: 'block',
    blocks: ['core/site-logo'],
    transform: () => {
      return (0, _blocks.createBlock)('core/navigation-link');
    }
  }, {
    type: 'block',
    blocks: ['core/spacer'],
    transform: () => {
      return (0, _blocks.createBlock)('core/navigation-link');
    }
  }, {
    type: 'block',
    blocks: ['core/home-link'],
    transform: () => {
      return (0, _blocks.createBlock)('core/navigation-link');
    }
  }, {
    type: 'block',
    blocks: ['core/social-links'],
    transform: () => {
      return (0, _blocks.createBlock)('core/navigation-link');
    }
  }, {
    type: 'block',
    blocks: ['core/search'],
    transform: () => {
      return (0, _blocks.createBlock)('core/navigation-link');
    }
  }, {
    type: 'block',
    blocks: ['core/page-list'],
    transform: () => {
      return (0, _blocks.createBlock)('core/navigation-link');
    }
  }, {
    type: 'block',
    blocks: ['core/buttons'],
    transform: () => {
      return (0, _blocks.createBlock)('core/navigation-link');
    }
  }],
  to: [{
    type: 'block',
    blocks: ['core/navigation-submenu'],
    transform: (attributes, innerBlocks) => (0, _blocks.createBlock)('core/navigation-submenu', attributes, innerBlocks)
  }, {
    type: 'block',
    blocks: ['core/spacer'],
    transform: () => {
      return (0, _blocks.createBlock)('core/spacer');
    }
  }, {
    type: 'block',
    blocks: ['core/site-logo'],
    transform: () => {
      return (0, _blocks.createBlock)('core/site-logo');
    }
  }, {
    type: 'block',
    blocks: ['core/home-link'],
    transform: () => {
      return (0, _blocks.createBlock)('core/home-link');
    }
  }, {
    type: 'block',
    blocks: ['core/social-links'],
    transform: () => {
      return (0, _blocks.createBlock)('core/social-links');
    }
  }, {
    type: 'block',
    blocks: ['core/search'],
    transform: () => {
      return (0, _blocks.createBlock)('core/search', {
        showLabel: false,
        buttonUseIcon: true,
        buttonPosition: 'button-inside'
      });
    }
  }, {
    type: 'block',
    blocks: ['core/page-list'],
    transform: () => {
      return (0, _blocks.createBlock)('core/page-list');
    }
  }, {
    type: 'block',
    blocks: ['core/buttons'],
    transform: ({
      label,
      url,
      rel,
      title,
      opensInNewTab
    }) => {
      return (0, _blocks.createBlock)('core/buttons', {}, [(0, _blocks.createBlock)('core/button', {
        text: label,
        url,
        rel,
        title,
        linkTarget: opensInNewTab ? '_blank' : undefined
      })]);
    }
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map