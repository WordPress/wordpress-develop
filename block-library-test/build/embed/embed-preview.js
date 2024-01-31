"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _util = require("./util");
var _dedupe = _interopRequireDefault(require("classnames/dedupe"));
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _blockEditor = require("@wordpress/block-editor");
var _element = require("@wordpress/element");
var _blocks = require("@wordpress/blocks");
var _wpEmbedPreview = _interopRequireDefault(require("./wp-embed-preview"));
/**
 * Internal dependencies
 */

/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

class EmbedPreview extends _element.Component {
  constructor() {
    super(...arguments);
    this.hideOverlay = this.hideOverlay.bind(this);
    this.state = {
      interactive: false
    };
  }
  static getDerivedStateFromProps(nextProps, state) {
    if (!nextProps.isSelected && state.interactive) {
      // We only want to change this when the block is not selected, because changing it when
      // the block becomes selected makes the overlap disappear too early. Hiding the overlay
      // happens on mouseup when the overlay is clicked.
      return {
        interactive: false
      };
    }
    return null;
  }
  hideOverlay() {
    // This is called onMouseUp on the overlay. We can't respond to the `isSelected` prop
    // changing, because that happens on mouse down, and the overlay immediately disappears,
    // and the mouse event can end up in the preview content. We can't use onClick on
    // the overlay to hide it either, because then the editor misses the mouseup event, and
    // thinks we're multi-selecting blocks.
    this.setState({
      interactive: true
    });
  }
  render() {
    const {
      preview,
      previewable,
      url,
      type,
      caption,
      onCaptionChange,
      isSelected,
      className,
      icon,
      label,
      insertBlocksAfter
    } = this.props;
    const {
      scripts
    } = preview;
    const {
      interactive
    } = this.state;
    const html = 'photo' === type ? (0, _util.getPhotoHtml)(preview) : preview.html;
    const parsedHost = new URL(url).host.split('.');
    const parsedHostBaseUrl = parsedHost.splice(parsedHost.length - 2, parsedHost.length - 1).join('.');
    const iframeTitle = (0, _i18n.sprintf)(
    // translators: %s: host providing embed content e.g: www.youtube.com
    (0, _i18n.__)('Embedded content from %s'), parsedHostBaseUrl);
    const sandboxClassnames = (0, _dedupe.default)(type, className, 'wp-block-embed__wrapper');

    // Disabled because the overlay div doesn't actually have a role or functionality
    // as far as the user is concerned. We're just catching the first click so that
    // the block can be selected without interacting with the embed preview that the overlay covers.
    /* eslint-disable jsx-a11y/no-static-element-interactions */
    const embedWrapper = 'wp-embed' === type ? (0, _react.createElement)(_wpEmbedPreview.default, {
      html: html
    }) : (0, _react.createElement)("div", {
      className: "wp-block-embed__wrapper"
    }, (0, _react.createElement)(_components.SandBox, {
      html: html,
      scripts: scripts,
      title: iframeTitle,
      type: sandboxClassnames,
      onFocus: this.hideOverlay
    }), !interactive && (0, _react.createElement)("div", {
      className: "block-library-embed__interactive-overlay",
      onMouseUp: this.hideOverlay
    }));
    /* eslint-enable jsx-a11y/no-static-element-interactions */

    return (0, _react.createElement)("figure", {
      className: (0, _dedupe.default)(className, 'wp-block-embed', {
        'is-type-video': 'video' === type
      })
    }, previewable ? embedWrapper : (0, _react.createElement)(_components.Placeholder, {
      icon: (0, _react.createElement)(_blockEditor.BlockIcon, {
        icon: icon,
        showColors: true
      }),
      label: label
    }, (0, _react.createElement)("p", {
      className: "components-placeholder__error"
    }, (0, _react.createElement)("a", {
      href: url
    }, url)), (0, _react.createElement)("p", {
      className: "components-placeholder__error"
    }, (0, _i18n.sprintf)( /* translators: %s: host providing embed content e.g: www.youtube.com */
    (0, _i18n.__)("Embedded content from %s can't be previewed in the editor."), parsedHostBaseUrl))), (!_blockEditor.RichText.isEmpty(caption) || isSelected) && (0, _react.createElement)(_blockEditor.RichText, {
      identifier: "caption",
      tagName: "figcaption",
      className: (0, _blockEditor.__experimentalGetElementClassName)('caption'),
      placeholder: (0, _i18n.__)('Add caption'),
      value: caption,
      onChange: onCaptionChange,
      inlineToolbar: true,
      __unstableOnSplitAtEnd: () => insertBlocksAfter((0, _blocks.createBlock)((0, _blocks.getDefaultBlockName)()))
    }));
  }
}
var _default = exports.default = EmbedPreview;
//# sourceMappingURL=embed-preview.js.map