"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = WpEmbedPreview;
var _react = require("react");
var _compose = require("@wordpress/compose");
var _element = require("@wordpress/element");
/**
 * WordPress dependencies
 */

/** @typedef {import('react').SyntheticEvent} SyntheticEvent */

const attributeMap = {
  class: 'className',
  frameborder: 'frameBorder',
  marginheight: 'marginHeight',
  marginwidth: 'marginWidth'
};
function WpEmbedPreview({
  html
}) {
  const ref = (0, _element.useRef)();
  const props = (0, _element.useMemo)(() => {
    const doc = new window.DOMParser().parseFromString(html, 'text/html');
    const iframe = doc.querySelector('iframe');
    const iframeProps = {};
    if (!iframe) return iframeProps;
    Array.from(iframe.attributes).forEach(({
      name,
      value
    }) => {
      if (name === 'style') return;
      iframeProps[attributeMap[name] || name] = value;
    });
    return iframeProps;
  }, [html]);
  (0, _element.useEffect)(() => {
    const {
      ownerDocument
    } = ref.current;
    const {
      defaultView
    } = ownerDocument;

    /**
     * Checks for WordPress embed events signaling the height change when
     * iframe content loads or iframe's window is resized.  The event is
     * sent from WordPress core via the window.postMessage API.
     *
     * References:
     * window.postMessage:
     * https://developer.mozilla.org/en-US/docs/Web/API/Window/postMessage
     * WordPress core embed-template on load:
     * https://github.com/WordPress/WordPress/blob/HEAD/wp-includes/js/wp-embed-template.js#L143
     * WordPress core embed-template on resize:
     * https://github.com/WordPress/WordPress/blob/HEAD/wp-includes/js/wp-embed-template.js#L187
     *
     * @param {MessageEvent} event Message event.
     */
    function resizeWPembeds({
      data: {
        secret,
        message,
        value
      } = {}
    }) {
      if (message !== 'height' || secret !== props['data-secret']) {
        return;
      }
      ref.current.height = value;
    }
    defaultView.addEventListener('message', resizeWPembeds);
    return () => {
      defaultView.removeEventListener('message', resizeWPembeds);
    };
  }, []);
  return (0, _react.createElement)("div", {
    className: "wp-block-embed__wrapper"
  }, (0, _react.createElement)("iframe", {
    ref: (0, _compose.useMergeRefs)([ref, (0, _compose.useFocusableIframe)()]),
    title: props.title,
    ...props
  }));
}
//# sourceMappingURL=wp-embed-preview.js.map