"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.useCommentAvatar = useCommentAvatar;
exports.useUserAvatar = useUserAvatar;
var _blockEditor = require("@wordpress/block-editor");
var _coreData = require("@wordpress/core-data");
var _i18n = require("@wordpress/i18n");
var _data = require("@wordpress/data");
/**
 * WordPress dependencies
 */

function getAvatarSizes(sizes) {
  const minSize = sizes ? sizes[0] : 24;
  const maxSize = sizes ? sizes[sizes.length - 1] : 96;
  const maxSizeBuffer = Math.floor(maxSize * 2.5);
  return {
    minSize,
    maxSize: maxSizeBuffer
  };
}
function useDefaultAvatar() {
  const {
    avatarURL: defaultAvatarUrl
  } = (0, _data.useSelect)(select => {
    const {
      getSettings
    } = select(_blockEditor.store);
    const {
      __experimentalDiscussionSettings
    } = getSettings();
    return __experimentalDiscussionSettings;
  });
  return defaultAvatarUrl;
}
function useCommentAvatar({
  commentId
}) {
  const [avatars] = (0, _coreData.useEntityProp)('root', 'comment', 'author_avatar_urls', commentId);
  const [authorName] = (0, _coreData.useEntityProp)('root', 'comment', 'author_name', commentId);
  const avatarUrls = avatars ? Object.values(avatars) : null;
  const sizes = avatars ? Object.keys(avatars) : null;
  const {
    minSize,
    maxSize
  } = getAvatarSizes(sizes);
  const defaultAvatar = useDefaultAvatar();
  return {
    src: avatarUrls ? avatarUrls[avatarUrls.length - 1] : defaultAvatar,
    minSize,
    maxSize,
    // translators: %s is the Author name.
    alt: authorName ?
    // translators: %s is the Author name.
    (0, _i18n.sprintf)((0, _i18n.__)('%s Avatar'), authorName) : (0, _i18n.__)('Default Avatar')
  };
}
function useUserAvatar({
  userId,
  postId,
  postType
}) {
  const {
    authorDetails
  } = (0, _data.useSelect)(select => {
    const {
      getEditedEntityRecord,
      getUser
    } = select(_coreData.store);
    if (userId) {
      return {
        authorDetails: getUser(userId)
      };
    }
    const _authorId = getEditedEntityRecord('postType', postType, postId)?.author;
    return {
      authorDetails: _authorId ? getUser(_authorId) : null
    };
  }, [postType, postId, userId]);
  const avatarUrls = authorDetails?.avatar_urls ? Object.values(authorDetails.avatar_urls) : null;
  const sizes = authorDetails?.avatar_urls ? Object.keys(authorDetails.avatar_urls) : null;
  const {
    minSize,
    maxSize
  } = getAvatarSizes(sizes);
  const defaultAvatar = useDefaultAvatar();
  return {
    src: avatarUrls ? avatarUrls[avatarUrls.length - 1] : defaultAvatar,
    minSize,
    maxSize,
    alt: authorDetails ?
    // translators: %s is the Author name.
    (0, _i18n.sprintf)((0, _i18n.__)('%s Avatar'), authorDetails?.name) : (0, _i18n.__)('Default Avatar')
  };
}
//# sourceMappingURL=hooks.js.map