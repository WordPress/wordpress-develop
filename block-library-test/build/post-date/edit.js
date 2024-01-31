"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = PostDateEdit;
exports.is12HourFormat = is12HourFormat;
var _react = require("react");
var _classnames = _interopRequireDefault(require("classnames"));
var _coreData = require("@wordpress/core-data");
var _element = require("@wordpress/element");
var _date = require("@wordpress/date");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _keycodes = require("@wordpress/keycodes");
var _data = require("@wordpress/data");
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

function PostDateEdit({
  attributes: {
    textAlign,
    format,
    isLink,
    displayType
  },
  context: {
    postId,
    postType: postTypeSlug,
    queryId
  },
  setAttributes
}) {
  const blockProps = (0, _blockEditor.useBlockProps)({
    className: (0, _classnames.default)({
      [`has-text-align-${textAlign}`]: textAlign,
      [`wp-block-post-date__modified-date`]: displayType === 'modified'
    })
  });

  // Use internal state instead of a ref to make sure that the component
  // re-renders when the popover's anchor updates.
  const [popoverAnchor, setPopoverAnchor] = (0, _element.useState)(null);
  // Memoize popoverProps to avoid returning a new object every time.
  const popoverProps = (0, _element.useMemo)(() => ({
    anchor: popoverAnchor
  }), [popoverAnchor]);
  const isDescendentOfQueryLoop = Number.isFinite(queryId);
  const dateSettings = (0, _date.getSettings)();
  const [siteFormat = dateSettings.formats.date] = (0, _coreData.useEntityProp)('root', 'site', 'date_format');
  const [siteTimeFormat = dateSettings.formats.time] = (0, _coreData.useEntityProp)('root', 'site', 'time_format');
  const [date, setDate] = (0, _coreData.useEntityProp)('postType', postTypeSlug, displayType, postId);
  const postType = (0, _data.useSelect)(select => postTypeSlug ? select(_coreData.store).getPostType(postTypeSlug) : null, [postTypeSlug]);
  const dateLabel = displayType === 'date' ? (0, _i18n.__)('Post Date') : (0, _i18n.__)('Post Modified Date');
  let postDate = date ? (0, _react.createElement)("time", {
    dateTime: (0, _date.dateI18n)('c', date),
    ref: setPopoverAnchor
  }, (0, _date.dateI18n)(format || siteFormat, date)) : dateLabel;
  if (isLink && date) {
    postDate = (0, _react.createElement)("a", {
      href: "#post-date-pseudo-link",
      onClick: event => event.preventDefault()
    }, postDate);
  }
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, {
    group: "block"
  }, (0, _react.createElement)(_blockEditor.AlignmentControl, {
    value: textAlign,
    onChange: nextAlign => {
      setAttributes({
        textAlign: nextAlign
      });
    }
  }), date && displayType === 'date' && !isDescendentOfQueryLoop && (0, _react.createElement)(_components.ToolbarGroup, null, (0, _react.createElement)(_components.Dropdown, {
    popoverProps: popoverProps,
    renderContent: ({
      onClose
    }) => (0, _react.createElement)(_blockEditor.__experimentalPublishDateTimePicker, {
      currentDate: date,
      onChange: setDate,
      is12Hour: is12HourFormat(siteTimeFormat),
      onClose: onClose
    }),
    renderToggle: ({
      isOpen,
      onToggle
    }) => {
      const openOnArrowDown = event => {
        if (!isOpen && event.keyCode === _keycodes.DOWN) {
          event.preventDefault();
          onToggle();
        }
      };
      return (0, _react.createElement)(_components.ToolbarButton, {
        "aria-expanded": isOpen,
        icon: _icons.edit,
        title: (0, _i18n.__)('Change Date'),
        onClick: onToggle,
        onKeyDown: openOnArrowDown
      });
    }
  }))), (0, _react.createElement)(_blockEditor.InspectorControls, null, (0, _react.createElement)(_components.PanelBody, {
    title: (0, _i18n.__)('Settings')
  }, (0, _react.createElement)(_blockEditor.__experimentalDateFormatPicker, {
    format: format,
    defaultFormat: siteFormat,
    onChange: nextFormat => setAttributes({
      format: nextFormat
    })
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: postType?.labels.singular_name ? (0, _i18n.sprintf)(
    // translators: %s: Name of the post type e.g: "post".
    (0, _i18n.__)('Link to %s'), postType.labels.singular_name.toLowerCase()) : (0, _i18n.__)('Link to post'),
    onChange: () => setAttributes({
      isLink: !isLink
    }),
    checked: isLink
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Display last modified date'),
    onChange: value => setAttributes({
      displayType: value ? 'modified' : 'date'
    }),
    checked: displayType === 'modified',
    help: (0, _i18n.__)('Only shows if the post has been modified')
  }))), (0, _react.createElement)("div", {
    ...blockProps
  }, postDate));
}
function is12HourFormat(format) {
  // To know if the time format is a 12 hour time, look for any of the 12 hour
  // format characters: 'a', 'A', 'g', and 'h'. The character must be
  // unescaped, i.e. not preceded by a '\'. Coincidentally, 'aAgh' is how I
  // feel when working with regular expressions.
  // https://www.php.net/manual/en/datetime.format.php
  return /(?:^|[^\\])[aAgh]/.test(format);
}
//# sourceMappingURL=edit.js.map