"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _blob = require("@wordpress/blob");
var _blocks = require("@wordpress/blocks");
var _data = require("@wordpress/data");
var _coreData = require("@wordpress/core-data");
var _url = require("@wordpress/url");
/**
 * WordPress dependencies
 */

const transforms = {
  from: [{
    type: 'files',
    isMatch(files) {
      return files.length > 0;
    },
    // We define a lower priorty (higher number) than the default of 10. This
    // ensures that the File block is only created as a fallback.
    priority: 15,
    transform: files => {
      const blocks = [];
      files.forEach(file => {
        const blobURL = (0, _blob.createBlobURL)(file);

        // File will be uploaded in componentDidMount()
        blocks.push((0, _blocks.createBlock)('core/file', {
          href: blobURL,
          fileName: file.name,
          textLinkHref: blobURL
        }));
      });
      return blocks;
    }
  }, {
    type: 'block',
    blocks: ['core/audio'],
    transform: attributes => {
      return (0, _blocks.createBlock)('core/file', {
        href: attributes.src,
        fileName: attributes.caption,
        textLinkHref: attributes.src,
        id: attributes.id,
        anchor: attributes.anchor
      });
    }
  }, {
    type: 'block',
    blocks: ['core/video'],
    transform: attributes => {
      return (0, _blocks.createBlock)('core/file', {
        href: attributes.src,
        fileName: attributes.caption,
        textLinkHref: attributes.src,
        id: attributes.id,
        anchor: attributes.anchor
      });
    }
  }, {
    type: 'block',
    blocks: ['core/image'],
    transform: attributes => {
      return (0, _blocks.createBlock)('core/file', {
        href: attributes.url,
        fileName: attributes.caption || (0, _url.getFilename)(attributes.url),
        textLinkHref: attributes.url,
        id: attributes.id,
        anchor: attributes.anchor
      });
    }
  }],
  to: [{
    type: 'block',
    blocks: ['core/audio'],
    isMatch: ({
      id
    }) => {
      if (!id) {
        return false;
      }
      const {
        getMedia
      } = (0, _data.select)(_coreData.store);
      const media = getMedia(id);
      return !!media && media.mime_type.includes('audio');
    },
    transform: attributes => {
      return (0, _blocks.createBlock)('core/audio', {
        src: attributes.href,
        caption: attributes.fileName,
        id: attributes.id,
        anchor: attributes.anchor
      });
    }
  }, {
    type: 'block',
    blocks: ['core/video'],
    isMatch: ({
      id
    }) => {
      if (!id) {
        return false;
      }
      const {
        getMedia
      } = (0, _data.select)(_coreData.store);
      const media = getMedia(id);
      return !!media && media.mime_type.includes('video');
    },
    transform: attributes => {
      return (0, _blocks.createBlock)('core/video', {
        src: attributes.href,
        caption: attributes.fileName,
        id: attributes.id,
        anchor: attributes.anchor
      });
    }
  }, {
    type: 'block',
    blocks: ['core/image'],
    isMatch: ({
      id
    }) => {
      if (!id) {
        return false;
      }
      const {
        getMedia
      } = (0, _data.select)(_coreData.store);
      const media = getMedia(id);
      return !!media && media.mime_type.includes('image');
    },
    transform: attributes => {
      return (0, _blocks.createBlock)('core/image', {
        url: attributes.href,
        caption: attributes.fileName,
        id: attributes.id,
        anchor: attributes.anchor
      });
    }
  }]
};
var _default = exports.default = transforms;
//# sourceMappingURL=transforms.js.map