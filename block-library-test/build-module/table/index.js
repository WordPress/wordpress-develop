/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { blockTable as icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import initBlock from '../utils/init-block';
import deprecated from './deprecated';
import edit from './edit';
const metadata = {
  $schema: "https://schemas.wp.org/trunk/block.json",
  apiVersion: 3,
  name: "core/table",
  title: "Table",
  category: "text",
  description: "Create structured content in rows and columns to display information.",
  textdomain: "default",
  attributes: {
    hasFixedLayout: {
      type: "boolean",
      "default": false
    },
    caption: {
      type: "rich-text",
      source: "rich-text",
      selector: "figcaption"
    },
    head: {
      type: "array",
      "default": [],
      source: "query",
      selector: "thead tr",
      query: {
        cells: {
          type: "array",
          "default": [],
          source: "query",
          selector: "td,th",
          query: {
            content: {
              type: "rich-text",
              source: "rich-text"
            },
            tag: {
              type: "string",
              "default": "td",
              source: "tag"
            },
            scope: {
              type: "string",
              source: "attribute",
              attribute: "scope"
            },
            align: {
              type: "string",
              source: "attribute",
              attribute: "data-align"
            },
            colspan: {
              type: "string",
              source: "attribute",
              attribute: "colspan"
            },
            rowspan: {
              type: "string",
              source: "attribute",
              attribute: "rowspan"
            }
          }
        }
      }
    },
    body: {
      type: "array",
      "default": [],
      source: "query",
      selector: "tbody tr",
      query: {
        cells: {
          type: "array",
          "default": [],
          source: "query",
          selector: "td,th",
          query: {
            content: {
              type: "rich-text",
              source: "rich-text"
            },
            tag: {
              type: "string",
              "default": "td",
              source: "tag"
            },
            scope: {
              type: "string",
              source: "attribute",
              attribute: "scope"
            },
            align: {
              type: "string",
              source: "attribute",
              attribute: "data-align"
            },
            colspan: {
              type: "string",
              source: "attribute",
              attribute: "colspan"
            },
            rowspan: {
              type: "string",
              source: "attribute",
              attribute: "rowspan"
            }
          }
        }
      }
    },
    foot: {
      type: "array",
      "default": [],
      source: "query",
      selector: "tfoot tr",
      query: {
        cells: {
          type: "array",
          "default": [],
          source: "query",
          selector: "td,th",
          query: {
            content: {
              type: "rich-text",
              source: "rich-text"
            },
            tag: {
              type: "string",
              "default": "td",
              source: "tag"
            },
            scope: {
              type: "string",
              source: "attribute",
              attribute: "scope"
            },
            align: {
              type: "string",
              source: "attribute",
              attribute: "data-align"
            },
            colspan: {
              type: "string",
              source: "attribute",
              attribute: "colspan"
            },
            rowspan: {
              type: "string",
              source: "attribute",
              attribute: "rowspan"
            }
          }
        }
      }
    }
  },
  supports: {
    anchor: true,
    align: true,
    color: {
      __experimentalSkipSerialization: true,
      gradients: true,
      __experimentalDefaultControls: {
        background: true,
        text: true
      }
    },
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
      __experimentalFontStyle: true,
      __experimentalFontWeight: true,
      __experimentalLetterSpacing: true,
      __experimentalTextTransform: true,
      __experimentalTextDecoration: true,
      __experimentalDefaultControls: {
        fontSize: true
      }
    },
    __experimentalBorder: {
      __experimentalSkipSerialization: true,
      color: true,
      style: true,
      width: true,
      __experimentalDefaultControls: {
        color: true,
        style: true,
        width: true
      }
    },
    __experimentalSelector: ".wp-block-table > table"
  },
  styles: [{
    name: "regular",
    label: "Default",
    isDefault: true
  }, {
    name: "stripes",
    label: "Stripes"
  }],
  editorStyle: "wp-block-table-editor",
  style: "wp-block-table"
};
import save from './save';
import transforms from './transforms';
const {
  name
} = metadata;
export { metadata, name };
export const settings = {
  icon,
  example: {
    attributes: {
      head: [{
        cells: [{
          content: __('Version'),
          tag: 'th'
        }, {
          content: __('Jazz Musician'),
          tag: 'th'
        }, {
          content: __('Release Date'),
          tag: 'th'
        }]
      }],
      body: [{
        cells: [{
          content: '5.2',
          tag: 'td'
        }, {
          content: 'Jaco Pastorius',
          tag: 'td'
        }, {
          content: __('May 7, 2019'),
          tag: 'td'
        }]
      }, {
        cells: [{
          content: '5.1',
          tag: 'td'
        }, {
          content: 'Betty Carter',
          tag: 'td'
        }, {
          content: __('February 21, 2019'),
          tag: 'td'
        }]
      }, {
        cells: [{
          content: '5.0',
          tag: 'td'
        }, {
          content: 'Bebo Valdés',
          tag: 'td'
        }, {
          content: __('December 6, 2018'),
          tag: 'td'
        }]
      }]
    },
    viewportWidth: 450
  },
  transforms,
  edit,
  save,
  deprecated
};
export const init = () => initBlock({
  name,
  metadata,
  settings
});
//# sourceMappingURL=index.js.map