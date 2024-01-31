"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _element = require("@wordpress/element");
var _data = require("@wordpress/data");
var _notices = require("@wordpress/notices");
/**
 * WordPress dependencies
 */

function useNavigationNotice({
  name,
  message = ''
} = {}) {
  const noticeRef = (0, _element.useRef)();
  const {
    createWarningNotice,
    removeNotice
  } = (0, _data.useDispatch)(_notices.store);
  const showNotice = (0, _element.useCallback)(customMsg => {
    if (noticeRef.current) {
      return;
    }
    noticeRef.current = name;
    createWarningNotice(customMsg || message, {
      id: noticeRef.current,
      type: 'snackbar'
    });
  }, [noticeRef, createWarningNotice, message, name]);
  const hideNotice = (0, _element.useCallback)(() => {
    if (!noticeRef.current) {
      return;
    }
    removeNotice(noticeRef.current);
    noticeRef.current = null;
  }, [noticeRef, removeNotice]);
  return [showNotice, hideNotice];
}
var _default = exports.default = useNavigationNotice;
//# sourceMappingURL=use-navigation-notice.js.map