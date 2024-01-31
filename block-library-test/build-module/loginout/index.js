/**
 * WordPress dependencies
 */
import { login as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/loginout",
  title: "Login/out",
  category: "theme",
  description: "Show login & logout links.",
  keywords: ["login", "logout", "form"],
  textdomain: "default",
  attributes: {
    displayLoginAsForm: {
      type: "boolean",
      "default": false
    },
    redirectToCurrent: {
      type: "boolean",
      "default": true
    }
  },
  supports: {
    className: true,
    spacing: {
      margin: true,
      padding: true,
      __experimentalDefaultControls: {
        margin: false,
        padding: false
      }
    },
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
  }
};
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  edit
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map