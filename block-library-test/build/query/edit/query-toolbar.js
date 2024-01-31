"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = QueryToolbar;
var _react = require("react");
var _components = require("@wordpress/components");
var _compose = require("@wordpress/compose");
var _i18n = require("@wordpress/i18n");
var _icons = require("@wordpress/icons");
var _utils = require("../utils");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

function QueryToolbar({
  attributes: {
    query
  },
  setQuery,
  openPatternSelectionModal,
  name,
  clientId
}) {
  const hasPatterns = !!(0, _utils.usePatterns)(clientId, name).length;
  const maxPageInputId = (0, _compose.useInstanceId)(QueryToolbar, 'blocks-query-pagination-max-page-input');
  return (0, _react.createElement)(_react.Fragment, null, !query.inherit && (0, _react.createElement)(_components.ToolbarGroup, null, (0, _react.createElement)(_components.Dropdown, {
    contentClassName: "block-library-query-toolbar__popover",
    renderToggle: ({
      onToggle
    }) => (0, _react.createElement)(_components.ToolbarButton, {
      icon: _icons.settings,
      label: (0, _i18n.__)('Display settings'),
      onClick: onToggle
    }),
    renderContent: () => (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.BaseControl, null, (0, _react.createElement)(_components.__experimentalNumberControl, {
      __unstableInputWidth: "60px",
      label: (0, _i18n.__)('Items per Page'),
      labelPosition: "edge",
      min: 1,
      max: 100,
      onChange: value => {
        if (isNaN(value) || value < 1 || value > 100) {
          return;
        }
        setQuery({
          perPage: value
        });
      },
      step: "1",
      value: query.perPage,
      isDragEnabled: false
    })), (0, _react.createElement)(_components.BaseControl, null, (0, _react.createElement)(_components.__experimentalNumberControl, {
      __unstableInputWidth: "60px",
      label: (0, _i18n.__)('Offset'),
      labelPosition: "edge",
      min: 0,
      max: 100,
      onChange: value => {
        if (isNaN(value) || value < 0 || value > 100) {
          return;
        }
        setQuery({
          offset: value
        });
      },
      step: "1",
      value: query.offset,
      isDragEnabled: false
    })), (0, _react.createElement)(_components.BaseControl, {
      id: maxPageInputId,
      help: (0, _i18n.__)('Limit the pages you want to show, even if the query has more results. To show all pages use 0 (zero).')
    }, (0, _react.createElement)(_components.__experimentalNumberControl, {
      id: maxPageInputId,
      __unstableInputWidth: "60px",
      label: (0, _i18n.__)('Max page to show'),
      labelPosition: "edge",
      min: 0,
      onChange: value => {
        if (isNaN(value) || value < 0) {
          return;
        }
        setQuery({
          pages: value
        });
      },
      step: "1",
      value: query.pages,
      isDragEnabled: false
    })))
  })), hasPatterns && (0, _react.createElement)(_components.ToolbarGroup, {
    className: "wp-block-template-part__block-control-group"
  }, (0, _react.createElement)(_components.ToolbarButton, {
    onClick: openPatternSelectionModal
  }, (0, _i18n.__)('Replace'))));
}
//# sourceMappingURL=query-toolbar.js.map