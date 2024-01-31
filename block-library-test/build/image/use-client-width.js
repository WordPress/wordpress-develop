"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = useClientWidth;
var _element = require("@wordpress/element");
/**
 * WordPress dependencies
 */

function useClientWidth(ref, dependencies) {
  const [clientWidth, setClientWidth] = (0, _element.useState)();
  function calculateClientWidth() {
    setClientWidth(ref.current?.clientWidth);
  }
  (0, _element.useEffect)(calculateClientWidth, dependencies);
  (0, _element.useEffect)(() => {
    const {
      defaultView
    } = ref.current.ownerDocument;
    defaultView.addEventListener('resize', calculateClientWidth);
    return () => {
      defaultView.removeEventListener('resize', calculateClientWidth);
    };
  }, []);
  return clientWidth;
}
//# sourceMappingURL=use-client-width.js.map