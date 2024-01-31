"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.TemplatePartImportControls = TemplatePartImportControls;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _element = require("@wordpress/element");
var _data = require("@wordpress/data");
var _components = require("@wordpress/components");
var _coreData = require("@wordpress/core-data");
var _notices = require("@wordpress/notices");
var _hooks = require("./utils/hooks");
var _transformers = require("./utils/transformers");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const SIDEBARS_QUERY = {
  per_page: -1,
  _fields: 'id,name,description,status,widgets'
};
function TemplatePartImportControls({
  area,
  setAttributes
}) {
  const [selectedSidebar, setSelectedSidebar] = (0, _element.useState)('');
  const [isBusy, setIsBusy] = (0, _element.useState)(false);
  const registry = (0, _data.useRegistry)();
  const {
    sidebars,
    hasResolved
  } = (0, _data.useSelect)(select => {
    const {
      getSidebars,
      hasFinishedResolution
    } = select(_coreData.store);
    return {
      sidebars: getSidebars(SIDEBARS_QUERY),
      hasResolved: hasFinishedResolution('getSidebars', [SIDEBARS_QUERY])
    };
  }, []);
  const {
    createErrorNotice
  } = (0, _data.useDispatch)(_notices.store);
  const createFromBlocks = (0, _hooks.useCreateTemplatePartFromBlocks)(area, setAttributes);
  const options = (0, _element.useMemo)(() => {
    const sidebarOptions = (sidebars !== null && sidebars !== void 0 ? sidebars : []).filter(widgetArea => widgetArea.id !== 'wp_inactive_widgets' && widgetArea.widgets.length > 0).map(widgetArea => {
      return {
        value: widgetArea.id,
        label: widgetArea.name
      };
    });
    if (!sidebarOptions.length) {
      return [];
    }
    return [{
      value: '',
      label: (0, _i18n.__)('Select widget area')
    }, ...sidebarOptions];
  }, [sidebars]);

  // Render an empty node while data is loading to avoid SlotFill re-positioning bug.
  // See: https://github.com/WordPress/gutenberg/issues/15641.
  if (!hasResolved) {
    return (0, _react.createElement)(_components.__experimentalSpacer, {
      marginBottom: "0"
    });
  }
  if (hasResolved && !options.length) {
    return null;
  }
  async function createFromWidgets(event) {
    event.preventDefault();
    if (isBusy || !selectedSidebar) {
      return;
    }
    setIsBusy(true);
    const sidebar = options.find(({
      value
    }) => value === selectedSidebar);
    const {
      getWidgets
    } = registry.resolveSelect(_coreData.store);

    // The widgets API always returns a successful response.
    const widgets = await getWidgets({
      sidebar: sidebar.value,
      _embed: 'about'
    });
    const skippedWidgets = new Set();
    const blocks = widgets.flatMap(widget => {
      const block = (0, _transformers.transformWidgetToBlock)(widget);

      // Skip the block if we have no matching transformations.
      if (!block) {
        skippedWidgets.add(widget.id_base);
        return [];
      }
      return block;
    });
    await createFromBlocks(blocks, /* translators: %s: name of the widget area */
    (0, _i18n.sprintf)((0, _i18n.__)('Widget area: %s'), sidebar.label));
    if (skippedWidgets.size) {
      createErrorNotice((0, _i18n.sprintf)( /* translators: %s: the list of widgets */
      (0, _i18n.__)('Unable to import the following widgets: %s.'), Array.from(skippedWidgets).join(', ')), {
        type: 'snackbar'
      });
    }
    setIsBusy(false);
  }
  return (0, _react.createElement)(_components.__experimentalSpacer, {
    marginBottom: "4"
  }, (0, _react.createElement)(_components.__experimentalHStack, {
    as: "form",
    onSubmit: createFromWidgets
  }, (0, _react.createElement)(_components.FlexBlock, null, (0, _react.createElement)(_components.SelectControl, {
    label: (0, _i18n.__)('Import widget area'),
    value: selectedSidebar,
    options: options,
    onChange: value => setSelectedSidebar(value),
    disabled: !options.length,
    __next40pxDefaultSize: true,
    __nextHasNoMarginBottom: true
  })), (0, _react.createElement)(_components.FlexItem, {
    style: {
      marginBottom: '8px',
      marginTop: 'auto'
    }
  }, (0, _react.createElement)(_components.Button, {
    __next40pxDefaultSize: true,
    variant: "primary",
    type: "submit",
    isBusy: isBusy,
    "aria-disabled": isBusy || !selectedSidebar
  }, (0, _i18n._x)('Import', 'button label')))));
}
//# sourceMappingURL=import-controls.js.map