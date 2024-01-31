"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = CalendarEdit;
var _react = require("react");
var _memize = _interopRequireDefault(require("memize"));
var _icons = require("@wordpress/icons");
var _components = require("@wordpress/components");
var _data = require("@wordpress/data");
var _serverSideRender = _interopRequireDefault(require("@wordpress/server-side-render"));
var _blockEditor = require("@wordpress/block-editor");
var _coreData = require("@wordpress/core-data");
var _i18n = require("@wordpress/i18n");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Returns the year and month of a specified date.
 *
 * @see `WP_REST_Posts_Controller::prepare_date_response()`.
 *
 * @param {string} date Date in `ISO8601/RFC3339` format.
 * @return {Object} Year and date of the specified date.
 */
const getYearMonth = (0, _memize.default)(date => {
  if (!date) {
    return {};
  }
  const dateObj = new Date(date);
  return {
    year: dateObj.getFullYear(),
    month: dateObj.getMonth() + 1
  };
});
function CalendarEdit({
  attributes
}) {
  const blockProps = (0, _blockEditor.useBlockProps)();
  const {
    date,
    hasPosts,
    hasPostsResolved
  } = (0, _data.useSelect)(select => {
    const {
      getEntityRecords,
      hasFinishedResolution
    } = select(_coreData.store);
    const singlePublishedPostQuery = {
      status: 'publish',
      per_page: 1
    };
    const posts = getEntityRecords('postType', 'post', singlePublishedPostQuery);
    const postsResolved = hasFinishedResolution('getEntityRecords', ['postType', 'post', singlePublishedPostQuery]);
    let _date;

    // FIXME: @wordpress/block-library should not depend on @wordpress/editor.
    // Blocks can be loaded into a *non-post* block editor.
    // eslint-disable-next-line @wordpress/data-no-store-string-literals
    const editorSelectors = select('core/editor');
    if (editorSelectors) {
      const postType = editorSelectors.getEditedPostAttribute('type');
      // Dates are used to overwrite year and month used on the calendar.
      // This overwrite should only happen for 'post' post types.
      // For other post types the calendar always displays the current month.
      if (postType === 'post') {
        _date = editorSelectors.getEditedPostAttribute('date');
      }
    }
    return {
      date: _date,
      hasPostsResolved: postsResolved,
      hasPosts: postsResolved && posts?.length === 1
    };
  }, []);
  if (!hasPosts) {
    return (0, _react.createElement)("div", {
      ...blockProps
    }, (0, _react.createElement)(_components.Placeholder, {
      icon: _icons.calendar,
      label: (0, _i18n.__)('Calendar')
    }, !hasPostsResolved ? (0, _react.createElement)(_components.Spinner, null) : (0, _i18n.__)('No published posts found.')));
  }
  return (0, _react.createElement)("div", {
    ...blockProps
  }, (0, _react.createElement)(_components.Disabled, null, (0, _react.createElement)(_serverSideRender.default, {
    block: "core/calendar",
    attributes: {
      ...attributes,
      ...getYearMonth(date)
    }
  })));
}
//# sourceMappingURL=edit.js.map