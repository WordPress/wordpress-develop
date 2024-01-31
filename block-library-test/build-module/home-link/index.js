/**
 * WordPress dependencies
 */
import { _x } from '@wordpress/i18n';
import { home } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/home-link",
  category: "design",
  parent: ["core/navigation"],
  title: "Home Link",
  description: "Create a link that always points to the homepage of the site. Usually not necessary if there is already a site title link present in the header.",
  textdomain: "default",
  attributes: {
    label: {
      type: "string"
    }
  },
  usesContext: ["textColor", "customTextColor", "backgroundColor", "customBackgroundColor", "fontSize", "customFontSize", "style"],
  supports: {
    reusable: false,
    html: false,
    typography: {
      fontSize: true,
      lineHeight: true,
      __experimentalFontFamily: true,
      __experimentalFontWeight: true,
      __experimentalFontStyle: true,
      __experimentalTextTransform: true,
      __experimentalTextDecoration: true,
      __experimentalLetterSpacing: true,
      __experimentalDefaultControls: {
        fontSize: true
      }
    }
  },
  editorStyle: "wp-block-home-link-editor",
  style: "wp-block-home-link"
};
import edit from './edit';
import save from './save';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon: home,
  edit,
  save,
  example: {
    attributes: {
      label: _x('Home Link', 'block example')
    }
  }
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map