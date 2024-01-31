"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.TOOLSPANEL_DROPDOWNMENU_PROPS = void 0;
// The following dropdown menu props aim to provide a consistent offset and
// placement for ToolsPanel menus for block controls to match color popovers.
const TOOLSPANEL_DROPDOWNMENU_PROPS = exports.TOOLSPANEL_DROPDOWNMENU_PROPS = {
  popoverProps: {
    placement: 'left-start',
    offset: 259 // Inner sidebar width (248px) - button width (24px) - border (1px) + padding (16px) + spacing (20px)
  }
};
//# sourceMappingURL=constants.js.map