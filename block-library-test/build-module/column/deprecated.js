import { createElement } from "react";
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { InnerBlocks } from '@wordpress/block-editor';
const deprecated = [{
  attributes: {
    verticalAlignment: {
      type: 'string'
    },
    width: {
      type: 'number',
      min: 0,
      max: 100
    }
  },
  isEligible({
    width
  }) {
    return isFinite(width);
  },
  migrate(attributes) {
    return {
      ...attributes,
      width: `${attributes.width}%`
    };
  },
  save({
    attributes
  }) {
    const {
      verticalAlignment,
      width
    } = attributes;
    const wrapperClasses = classnames({
      [`is-vertically-aligned-${verticalAlignment}`]: verticalAlignment
    });
    const style = {
      flexBasis: width + '%'
    };
    return createElement("div", {
      className: wrapperClasses,
      style: style
    }, createElement(InnerBlocks.Content, null));
  }
}];
export default deprecated;
//# sourceMappingURL=deprecated.js.map