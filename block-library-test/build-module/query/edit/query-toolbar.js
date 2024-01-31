import { createElement, Fragment } from "react";
/**
 * WordPress dependencies
 */
import { ToolbarGroup, Dropdown, ToolbarButton, BaseControl, __experimentalNumberControl as NumberControl } from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';
import { settings } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { usePatterns } from '../utils';
export default function QueryToolbar({
  attributes: {
    query
  },
  setQuery,
  openPatternSelectionModal,
  name,
  clientId
}) {
  const hasPatterns = !!usePatterns(clientId, name).length;
  const maxPageInputId = useInstanceId(QueryToolbar, 'blocks-query-pagination-max-page-input');
  return createElement(Fragment, null, !query.inherit && createElement(ToolbarGroup, null, createElement(Dropdown, {
    contentClassName: "block-library-query-toolbar__popover",
    renderToggle: ({
      onToggle
    }) => createElement(ToolbarButton, {
      icon: settings,
      label: __('Display settings'),
      onClick: onToggle
    }),
    renderContent: () => createElement(Fragment, null, createElement(BaseControl, null, createElement(NumberControl, {
      __unstableInputWidth: "60px",
      label: __('Items per Page'),
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
    })), createElement(BaseControl, null, createElement(NumberControl, {
      __unstableInputWidth: "60px",
      label: __('Offset'),
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
    })), createElement(BaseControl, {
      id: maxPageInputId,
      help: __('Limit the pages you want to show, even if the query has more results. To show all pages use 0 (zero).')
    }, createElement(NumberControl, {
      id: maxPageInputId,
      __unstableInputWidth: "60px",
      label: __('Max page to show'),
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
  })), hasPatterns && createElement(ToolbarGroup, {
    className: "wp-block-template-part__block-control-group"
  }, createElement(ToolbarButton, {
    onClick: openPatternSelectionModal
  }, __('Replace'))));
}
//# sourceMappingURL=query-toolbar.js.map