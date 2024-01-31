"use strict";

var _interopRequireDefault = require("@babel/runtime/helpers/interopRequireDefault");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.CREATE_NAVIGATION_MENU_SUCCESS = exports.CREATE_NAVIGATION_MENU_PENDING = exports.CREATE_NAVIGATION_MENU_IDLE = exports.CREATE_NAVIGATION_MENU_ERROR = void 0;
exports.default = useCreateNavigationMenu;
var _blocks = require("@wordpress/blocks");
var _coreData = require("@wordpress/core-data");
var _data = require("@wordpress/data");
var _element = require("@wordpress/element");
var _useGenerateDefaultNavigationTitle = _interopRequireDefault(require("./use-generate-default-navigation-title"));
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

const CREATE_NAVIGATION_MENU_SUCCESS = exports.CREATE_NAVIGATION_MENU_SUCCESS = 'success';
const CREATE_NAVIGATION_MENU_ERROR = exports.CREATE_NAVIGATION_MENU_ERROR = 'error';
const CREATE_NAVIGATION_MENU_PENDING = exports.CREATE_NAVIGATION_MENU_PENDING = 'pending';
const CREATE_NAVIGATION_MENU_IDLE = exports.CREATE_NAVIGATION_MENU_IDLE = 'idle';
function useCreateNavigationMenu(clientId) {
  const [status, setStatus] = (0, _element.useState)(CREATE_NAVIGATION_MENU_IDLE);
  const [value, setValue] = (0, _element.useState)(null);
  const [error, setError] = (0, _element.useState)(null);
  const {
    saveEntityRecord,
    editEntityRecord
  } = (0, _data.useDispatch)(_coreData.store);
  const generateDefaultTitle = (0, _useGenerateDefaultNavigationTitle.default)(clientId);

  // This callback uses data from the two placeholder steps and only creates
  // a new navigation menu when the user completes the final step.
  const create = (0, _element.useCallback)(async (title = null, blocks = [], postStatus) => {
    // Guard against creating Navigations without a title.
    // Note you can pass no title, but if one is passed it must be
    // a string otherwise the title may end up being empty.
    if (title && typeof title !== 'string') {
      setError('Invalid title supplied when creating Navigation Menu.');
      setStatus(CREATE_NAVIGATION_MENU_ERROR);
      throw new Error(`Value of supplied title argument was not a string.`);
    }
    setStatus(CREATE_NAVIGATION_MENU_PENDING);
    setValue(null);
    setError(null);
    if (!title) {
      title = await generateDefaultTitle().catch(err => {
        setError(err?.message);
        setStatus(CREATE_NAVIGATION_MENU_ERROR);
        throw new Error('Failed to create title when saving new Navigation Menu.', {
          cause: err
        });
      });
    }
    const record = {
      title,
      content: (0, _blocks.serialize)(blocks),
      status: postStatus
    };

    // Return affords ability to await on this function directly
    return saveEntityRecord('postType', 'wp_navigation', record).then(response => {
      setValue(response);
      setStatus(CREATE_NAVIGATION_MENU_SUCCESS);

      // Set the status to publish so that the Navigation block
      // shows up in the multi entity save flow.
      if (postStatus !== 'publish') {
        editEntityRecord('postType', 'wp_navigation', response.id, {
          status: 'publish'
        });
      }
      return response;
    }).catch(err => {
      setError(err?.message);
      setStatus(CREATE_NAVIGATION_MENU_ERROR);
      throw new Error('Unable to save new Navigation Menu', {
        cause: err
      });
    });
  }, [saveEntityRecord, editEntityRecord, generateDefaultTitle]);
  return {
    create,
    status,
    value,
    error,
    isIdle: status === CREATE_NAVIGATION_MENU_IDLE,
    isPending: status === CREATE_NAVIGATION_MENU_PENDING,
    isSuccess: status === CREATE_NAVIGATION_MENU_SUCCESS,
    isError: status === CREATE_NAVIGATION_MENU_ERROR
  };
}
//# sourceMappingURL=use-create-navigation-menu.js.map