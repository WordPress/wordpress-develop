/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
const EMPTY_IMAGE_MEDIA = [];

/**
 * Retrieves the extended media info for each gallery image from the store. This is used to
 * determine which image size options are available for the current gallery.
 *
 * @param {Array} innerBlockImages An array of the innerBlock images currently in the gallery.
 *
 * @return {Array} An array of media info options for each gallery image.
 */
export default function useGetMedia(innerBlockImages) {
  return useSelect(select => {
    var _select$getMediaItems;
    const imageIds = innerBlockImages.map(imageBlock => imageBlock.attributes.id).filter(id => id !== undefined);
    if (imageIds.length === 0) {
      return EMPTY_IMAGE_MEDIA;
    }
    return (_select$getMediaItems = select(coreStore).getMediaItems({
      include: imageIds.join(','),
      per_page: -1,
      orderby: 'include'
    })) !== null && _select$getMediaItems !== void 0 ? _select$getMediaItems : EMPTY_IMAGE_MEDIA;
  }, [innerBlockImages]);
}
//# sourceMappingURL=use-get-media.js.map