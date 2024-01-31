"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.TemplatePartAdvancedControls = TemplatePartAdvancedControls;
var _react = require("react");
var _coreData = require("@wordpress/core-data");
var _components = require("@wordpress/components");
var _i18n = require("@wordpress/i18n");
var _data = require("@wordpress/data");
var _importControls = require("./import-controls");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const htmlElementMessages = {
  header: (0, _i18n.__)('The <header> element should represent introductory content, typically a group of introductory or navigational aids.'),
  main: (0, _i18n.__)('The <main> element should be used for the primary content of your document only.'),
  section: (0, _i18n.__)("The <section> element should represent a standalone portion of the document that can't be better represented by another element."),
  article: (0, _i18n.__)('The <article> element should represent a self-contained, syndicatable portion of the document.'),
  aside: (0, _i18n.__)("The <aside> element should represent a portion of a document whose content is only indirectly related to the document's main content."),
  footer: (0, _i18n.__)('The <footer> element should represent a footer for its nearest sectioning element (e.g.: <section>, <article>, <main> etc.).')
};
function TemplatePartAdvancedControls({
  tagName,
  setAttributes,
  isEntityAvailable,
  templatePartId,
  defaultWrapper,
  hasInnerBlocks
}) {
  const [area, setArea] = (0, _coreData.useEntityProp)('postType', 'wp_template_part', 'area', templatePartId);
  const [title, setTitle] = (0, _coreData.useEntityProp)('postType', 'wp_template_part', 'title', templatePartId);
  const definedAreas = (0, _data.useSelect)(select => {
    // FIXME: @wordpress/block-library should not depend on @wordpress/editor.
    // Blocks can be loaded into a *non-post* block editor.
    /* eslint-disable-next-line @wordpress/data-no-store-string-literals */
    return select('core/editor').__experimentalGetDefaultTemplatePartAreas();
  }, []);
  const areaOptions = definedAreas.map(({
    label,
    area: _area
  }) => ({
    label,
    value: _area
  }));
  return (0, _react.createElement)(_react.Fragment, null, isEntityAvailable && (0, _react.createElement)(_react.Fragment, null, (0, _react.createElement)(_components.TextControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Title'),
    value: title,
    onChange: value => {
      setTitle(value);
    },
    onFocus: event => event.target.select()
  }), (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    label: (0, _i18n.__)('Area'),
    labelPosition: "top",
    options: areaOptions,
    value: area,
    onChange: setArea
  })), (0, _react.createElement)(_components.SelectControl, {
    __nextHasNoMarginBottom: true,
    __next40pxDefaultSize: true,
    label: (0, _i18n.__)('HTML element'),
    options: [{
      label: (0, _i18n.sprintf)( /* translators: %s: HTML tag based on area. */
      (0, _i18n.__)('Default based on area (%s)'), `<${defaultWrapper}>`),
      value: ''
    }, {
      label: '<header>',
      value: 'header'
    }, {
      label: '<main>',
      value: 'main'
    }, {
      label: '<section>',
      value: 'section'
    }, {
      label: '<article>',
      value: 'article'
    }, {
      label: '<aside>',
      value: 'aside'
    }, {
      label: '<footer>',
      value: 'footer'
    }, {
      label: '<div>',
      value: 'div'
    }],
    value: tagName || '',
    onChange: value => setAttributes({
      tagName: value
    }),
    help: htmlElementMessages[tagName]
  }), !hasInnerBlocks && (0, _react.createElement)(_importControls.TemplatePartImportControls, {
    area: area,
    setAttributes: setAttributes
  }));
}
//# sourceMappingURL=advanced-controls.js.map