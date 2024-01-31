"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _react = require("react");
var _i18n = require("@wordpress/i18n");
var _components = require("@wordpress/components");
var _element = require("@wordpress/element");
/**
 * WordPress dependencies
 */

const options = [{
  value: 'auto',
  label: (0, _i18n.__)('Auto')
}, {
  value: 'metadata',
  label: (0, _i18n.__)('Metadata')
}, {
  value: 'none',
  label: (0, _i18n._x)('None', 'Preload value')
}];
const VideoSettings = ({
  setAttributes,
  attributes
}) => {
  const {
    autoplay,
    controls,
    loop,
    muted,
    playsInline,
    preload
  } = attributes;
  const autoPlayHelpText = (0, _i18n.__)('Autoplay may cause usability issues for some users.');
  const getAutoplayHelp = _element.Platform.select({
    web: (0, _element.useCallback)(checked => {
      return checked ? autoPlayHelpText : null;
    }, []),
    native: autoPlayHelpText
  });
  const toggleFactory = (0, _element.useMemo)(() => {
    const toggleAttribute = attribute => {
      return newValue => {
        setAttributes({
          [attribute]: newValue
        });
      };
    };
    return {
      autoplay: toggleAttribute('autoplay'),
      loop: toggleAttribute('loop'),
      muted: toggleAttribute('muted'),
      controls: toggleAttribute('controls'),
      playsInline: toggleAttribute('playsInline')
    };
  }, []);
  const onChangePreload = (0, _element.useCallback)(value => {
    setAttributes({
      preload: value
    });
  }, []);
  return (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Autoplay'),
    onChange: toggleFactory.autoplay,
    checked: !!autoplay,
    help: getAutoplayHelp
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Loop'),
    onChange: toggleFactory.loop,
    checked: !!loop
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Muted'),
    onChange: toggleFactory.muted,
    checked: !!muted
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Playback controls'),
    onChange: toggleFactory.controls,
    checked: !!controls
  }), (0, _react.createElement)(_components.ToggleControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Play inline'),
    onChange: toggleFactory.playsInline,
    checked: !!playsInline
  }), (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Preload'),
    value: preload,
    onChange: onChangePreload,
    options: options,
    hideCancelButton: true
  }));
};
var _default = exports.default = VideoSettings;
//# sourceMappingURL=edit-common-settings.js.map