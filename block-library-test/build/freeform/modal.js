"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = ModalEdit;
var _react = require("react");
var _blockEditor = require("@wordpress/block-editor");
var _components = require("@wordpress/components");
var _element = require("@wordpress/element");
var _i18n = require("@wordpress/i18n");
var _data = require("@wordpress/data");
var _icons = require("@wordpress/icons");
var _compose = require("@wordpress/compose");
/**
 * WordPress dependencies
 */

function ModalAuxiliaryActions({
  onClick,
  isModalFullScreen
}) {
  // 'small' to match the rules in editor.scss.
  const isMobileViewport = (0, _compose.useViewportMatch)('small', '<');
  if (isMobileViewport) {
    return null;
  }
  return (0, _react.createElement)(_components.Button, {
    onClick: onClick,
    icon: _icons.fullscreen,
    isPressed: isModalFullScreen,
    label: isModalFullScreen ? (0, _i18n.__)('Exit fullscreen') : (0, _i18n.__)('Enter fullscreen')
  });
}
function ClassicEdit(props) {
  const styles = (0, _data.useSelect)(select => select(_blockEditor.store).getSettings().styles);
  (0, _element.useEffect)(() => {
    const {
      baseURL,
      suffix,
      settings
    } = window.wpEditorL10n.tinymce;
    window.tinymce.EditorManager.overrideDefaults({
      base_url: baseURL,
      suffix
    });
    window.wp.oldEditor.initialize(props.id, {
      tinymce: {
        ...settings,
        setup(editor) {
          editor.on('init', () => {
            const doc = editor.getDoc();
            styles.forEach(({
              css
            }) => {
              const styleEl = doc.createElement('style');
              styleEl.innerHTML = css;
              doc.head.appendChild(styleEl);
            });
          });
        }
      }
    });
    return () => {
      window.wp.oldEditor.remove(props.id);
    };
  }, []);
  return (0, _react.createElement)("textarea", {
    ...props
  });
}
function ModalEdit(props) {
  const {
    clientId,
    attributes: {
      content
    },
    setAttributes,
    onReplace
  } = props;
  const [isOpen, setOpen] = (0, _element.useState)(false);
  const [isModalFullScreen, setIsModalFullScreen] = (0, _element.useState)(false);
  const id = `editor-${clientId}`;
  const onClose = () => content ? setOpen(false) : onReplace([]);
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_blockEditor.BlockControls, null, (0, _react.createElement)(_components.ToolbarGroup, null, (0, _react.createElement)(_components.ToolbarButton, {
    onClick: () => setOpen(true)
  }, (0, _i18n.__)('Edit')))), content && (0, _react.createElement)(_element.RawHTML, null, content), (isOpen || !content) && (0, _react.createElement)(_components.Modal, {
    title: (0, _i18n.__)('Classic Editor'),
    onRequestClose: onClose,
    shouldCloseOnClickOutside: false,
    overlayClassName: "block-editor-freeform-modal",
    isFullScreen: isModalFullScreen,
    className: "block-editor-freeform-modal__content",
    headerActions: (0, _react.createElement)(ModalAuxiliaryActions, {
      onClick: () => setIsModalFullScreen(!isModalFullScreen),
      isModalFullScreen: isModalFullScreen
    })
  }, (0, _react.createElement)(ClassicEdit, {
    id: id,
    defaultValue: content
  }), (0, _react.createElement)(_components.Flex, {
    className: "block-editor-freeform-modal__actions",
    justify: "flex-end",
    expanded: false
  }, (0, _react.createElement)(_components.FlexItem, null, (0, _react.createElement)(_components.Button, {
    variant: "tertiary",
    onClick: onClose
  }, (0, _i18n.__)('Cancel'))), (0, _react.createElement)(_components.FlexItem, null, (0, _react.createElement)(_components.Button, {
    variant: "primary",
    onClick: () => {
      setAttributes({
        content: window.wp.oldEditor.getContent(id)
      });
      setOpen(false);
    }
  }, (0, _i18n.__)('Save'))))));
}
//# sourceMappingURL=modal.js.map