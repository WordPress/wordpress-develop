"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _i18n = require("@wordpress/i18n");
var _icons = require("./icons");
/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

/** @typedef {import('@wordpress/blocks').WPBlockVariation} WPBlockVariation */

/**
 * The embed provider services.
 *
 * @type {WPBlockVariation[]}
 */
const variations = [{
  name: 'twitter',
  title: 'Twitter',
  icon: _icons.embedTwitterIcon,
  keywords: ['tweet', (0, _i18n.__)('social')],
  description: (0, _i18n.__)('Embed a tweet.'),
  patterns: [/^https?:\/\/(www\.)?twitter\.com\/.+/i],
  attributes: {
    providerNameSlug: 'twitter',
    responsive: true
  }
}, {
  name: 'youtube',
  title: 'YouTube',
  icon: _icons.embedYouTubeIcon,
  keywords: [(0, _i18n.__)('music'), (0, _i18n.__)('video')],
  description: (0, _i18n.__)('Embed a YouTube video.'),
  patterns: [/^https?:\/\/((m|www)\.)?youtube\.com\/.+/i, /^https?:\/\/youtu\.be\/.+/i],
  attributes: {
    providerNameSlug: 'youtube',
    responsive: true
  }
}, {
  // Deprecate Facebook Embed per FB policy
  // See: https://developers.facebook.com/docs/plugins/oembed-legacy
  name: 'facebook',
  title: 'Facebook',
  icon: _icons.embedFacebookIcon,
  keywords: [(0, _i18n.__)('social')],
  description: (0, _i18n.__)('Embed a Facebook post.'),
  scope: ['block'],
  patterns: [],
  attributes: {
    providerNameSlug: 'facebook',
    previewable: false,
    responsive: true
  }
}, {
  // Deprecate Instagram per FB policy
  // See: https://developers.facebook.com/docs/instagram/oembed-legacy
  name: 'instagram',
  title: 'Instagram',
  icon: _icons.embedInstagramIcon,
  keywords: [(0, _i18n.__)('image'), (0, _i18n.__)('social')],
  description: (0, _i18n.__)('Embed an Instagram post.'),
  scope: ['block'],
  patterns: [],
  attributes: {
    providerNameSlug: 'instagram',
    responsive: true
  }
}, {
  name: 'wordpress',
  title: 'WordPress',
  icon: _icons.embedWordPressIcon,
  keywords: [(0, _i18n.__)('post'), (0, _i18n.__)('blog')],
  description: (0, _i18n.__)('Embed a WordPress post.'),
  attributes: {
    providerNameSlug: 'wordpress'
  }
}, {
  name: 'soundcloud',
  title: 'SoundCloud',
  icon: _icons.embedAudioIcon,
  keywords: [(0, _i18n.__)('music'), (0, _i18n.__)('audio')],
  description: (0, _i18n.__)('Embed SoundCloud content.'),
  patterns: [/^https?:\/\/(www\.)?soundcloud\.com\/.+/i],
  attributes: {
    providerNameSlug: 'soundcloud',
    responsive: true
  }
}, {
  name: 'spotify',
  title: 'Spotify',
  icon: _icons.embedSpotifyIcon,
  keywords: [(0, _i18n.__)('music'), (0, _i18n.__)('audio')],
  description: (0, _i18n.__)('Embed Spotify content.'),
  patterns: [/^https?:\/\/(open|play)\.spotify\.com\/.+/i],
  attributes: {
    providerNameSlug: 'spotify',
    responsive: true
  }
}, {
  name: 'flickr',
  title: 'Flickr',
  icon: _icons.embedFlickrIcon,
  keywords: [(0, _i18n.__)('image')],
  description: (0, _i18n.__)('Embed Flickr content.'),
  patterns: [/^https?:\/\/(www\.)?flickr\.com\/.+/i, /^https?:\/\/flic\.kr\/.+/i],
  attributes: {
    providerNameSlug: 'flickr',
    responsive: true
  }
}, {
  name: 'vimeo',
  title: 'Vimeo',
  icon: _icons.embedVimeoIcon,
  keywords: [(0, _i18n.__)('video')],
  description: (0, _i18n.__)('Embed a Vimeo video.'),
  patterns: [/^https?:\/\/(www\.)?vimeo\.com\/.+/i],
  attributes: {
    providerNameSlug: 'vimeo',
    responsive: true
  }
}, {
  name: 'animoto',
  title: 'Animoto',
  icon: _icons.embedAnimotoIcon,
  description: (0, _i18n.__)('Embed an Animoto video.'),
  patterns: [/^https?:\/\/(www\.)?(animoto|video214)\.com\/.+/i],
  attributes: {
    providerNameSlug: 'animoto',
    responsive: true
  }
}, {
  name: 'cloudup',
  title: 'Cloudup',
  icon: _icons.embedContentIcon,
  description: (0, _i18n.__)('Embed Cloudup content.'),
  patterns: [/^https?:\/\/cloudup\.com\/.+/i],
  attributes: {
    providerNameSlug: 'cloudup',
    responsive: true
  }
}, {
  // Deprecated since CollegeHumor content is now powered by YouTube.
  name: 'collegehumor',
  title: 'CollegeHumor',
  icon: _icons.embedVideoIcon,
  description: (0, _i18n.__)('Embed CollegeHumor content.'),
  scope: ['block'],
  patterns: [],
  attributes: {
    providerNameSlug: 'collegehumor',
    responsive: true
  }
}, {
  name: 'crowdsignal',
  title: 'Crowdsignal',
  icon: _icons.embedContentIcon,
  keywords: ['polldaddy', (0, _i18n.__)('survey')],
  description: (0, _i18n.__)('Embed Crowdsignal (formerly Polldaddy) content.'),
  patterns: [/^https?:\/\/((.+\.)?polldaddy\.com|poll\.fm|.+\.crowdsignal\.net|.+\.survey\.fm)\/.+/i],
  attributes: {
    providerNameSlug: 'crowdsignal',
    responsive: true
  }
}, {
  name: 'dailymotion',
  title: 'Dailymotion',
  icon: _icons.embedDailymotionIcon,
  keywords: [(0, _i18n.__)('video')],
  description: (0, _i18n.__)('Embed a Dailymotion video.'),
  patterns: [/^https?:\/\/(www\.)?dailymotion\.com\/.+/i],
  attributes: {
    providerNameSlug: 'dailymotion',
    responsive: true
  }
}, {
  name: 'imgur',
  title: 'Imgur',
  icon: _icons.embedPhotoIcon,
  description: (0, _i18n.__)('Embed Imgur content.'),
  patterns: [/^https?:\/\/(.+\.)?imgur\.com\/.+/i],
  attributes: {
    providerNameSlug: 'imgur',
    responsive: true
  }
}, {
  name: 'issuu',
  title: 'Issuu',
  icon: _icons.embedContentIcon,
  description: (0, _i18n.__)('Embed Issuu content.'),
  patterns: [/^https?:\/\/(www\.)?issuu\.com\/.+/i],
  attributes: {
    providerNameSlug: 'issuu',
    responsive: true
  }
}, {
  name: 'kickstarter',
  title: 'Kickstarter',
  icon: _icons.embedContentIcon,
  description: (0, _i18n.__)('Embed Kickstarter content.'),
  patterns: [/^https?:\/\/(www\.)?kickstarter\.com\/.+/i, /^https?:\/\/kck\.st\/.+/i],
  attributes: {
    providerNameSlug: 'kickstarter',
    responsive: true
  }
}, {
  name: 'mixcloud',
  title: 'Mixcloud',
  icon: _icons.embedAudioIcon,
  keywords: [(0, _i18n.__)('music'), (0, _i18n.__)('audio')],
  description: (0, _i18n.__)('Embed Mixcloud content.'),
  patterns: [/^https?:\/\/(www\.)?mixcloud\.com\/.+/i],
  attributes: {
    providerNameSlug: 'mixcloud',
    responsive: true
  }
}, {
  name: 'pocket-casts',
  title: 'Pocket Casts',
  icon: _icons.embedPocketCastsIcon,
  keywords: [(0, _i18n.__)('podcast'), (0, _i18n.__)('audio')],
  description: (0, _i18n.__)('Embed a podcast player from Pocket Casts.'),
  patterns: [/^https:\/\/pca.st\/\w+/i],
  attributes: {
    providerNameSlug: 'pocket-casts',
    responsive: true
  }
}, {
  name: 'reddit',
  title: 'Reddit',
  icon: _icons.embedRedditIcon,
  description: (0, _i18n.__)('Embed a Reddit thread.'),
  patterns: [/^https?:\/\/(www\.)?reddit\.com\/.+/i],
  attributes: {
    providerNameSlug: 'reddit',
    responsive: true
  }
}, {
  name: 'reverbnation',
  title: 'ReverbNation',
  icon: _icons.embedAudioIcon,
  description: (0, _i18n.__)('Embed ReverbNation content.'),
  patterns: [/^https?:\/\/(www\.)?reverbnation\.com\/.+/i],
  attributes: {
    providerNameSlug: 'reverbnation',
    responsive: true
  }
}, {
  name: 'screencast',
  title: 'Screencast',
  icon: _icons.embedVideoIcon,
  description: (0, _i18n.__)('Embed Screencast content.'),
  patterns: [/^https?:\/\/(www\.)?screencast\.com\/.+/i],
  attributes: {
    providerNameSlug: 'screencast',
    responsive: true
  }
}, {
  name: 'scribd',
  title: 'Scribd',
  icon: _icons.embedContentIcon,
  description: (0, _i18n.__)('Embed Scribd content.'),
  patterns: [/^https?:\/\/(www\.)?scribd\.com\/.+/i],
  attributes: {
    providerNameSlug: 'scribd',
    responsive: true
  }
}, {
  name: 'slideshare',
  title: 'Slideshare',
  icon: _icons.embedContentIcon,
  description: (0, _i18n.__)('Embed Slideshare content.'),
  patterns: [/^https?:\/\/(.+?\.)?slideshare\.net\/.+/i],
  attributes: {
    providerNameSlug: 'slideshare',
    responsive: true
  }
}, {
  name: 'smugmug',
  title: 'SmugMug',
  icon: _icons.embedPhotoIcon,
  description: (0, _i18n.__)('Embed SmugMug content.'),
  patterns: [/^https?:\/\/(.+\.)?smugmug\.com\/.*/i],
  attributes: {
    providerNameSlug: 'smugmug',
    previewable: false,
    responsive: true
  }
}, {
  name: 'speaker-deck',
  title: 'Speaker Deck',
  icon: _icons.embedContentIcon,
  description: (0, _i18n.__)('Embed Speaker Deck content.'),
  patterns: [/^https?:\/\/(www\.)?speakerdeck\.com\/.+/i],
  attributes: {
    providerNameSlug: 'speaker-deck',
    responsive: true
  }
}, {
  name: 'tiktok',
  title: 'TikTok',
  icon: _icons.embedVideoIcon,
  keywords: [(0, _i18n.__)('video')],
  description: (0, _i18n.__)('Embed a TikTok video.'),
  patterns: [/^https?:\/\/(www\.)?tiktok\.com\/.+/i],
  attributes: {
    providerNameSlug: 'tiktok',
    responsive: true
  }
}, {
  name: 'ted',
  title: 'TED',
  icon: _icons.embedVideoIcon,
  description: (0, _i18n.__)('Embed a TED video.'),
  patterns: [/^https?:\/\/(www\.|embed\.)?ted\.com\/.+/i],
  attributes: {
    providerNameSlug: 'ted',
    responsive: true
  }
}, {
  name: 'tumblr',
  title: 'Tumblr',
  icon: _icons.embedTumblrIcon,
  keywords: [(0, _i18n.__)('social')],
  description: (0, _i18n.__)('Embed a Tumblr post.'),
  patterns: [/^https?:\/\/(.+)\.tumblr\.com\/.+/i],
  attributes: {
    providerNameSlug: 'tumblr',
    responsive: true
  }
}, {
  name: 'videopress',
  title: 'VideoPress',
  icon: _icons.embedVideoIcon,
  keywords: [(0, _i18n.__)('video')],
  description: (0, _i18n.__)('Embed a VideoPress video.'),
  patterns: [/^https?:\/\/videopress\.com\/.+/i],
  attributes: {
    providerNameSlug: 'videopress',
    responsive: true
  }
}, {
  name: 'wordpress-tv',
  title: 'WordPress.tv',
  icon: _icons.embedVideoIcon,
  description: (0, _i18n.__)('Embed a WordPress.tv video.'),
  patterns: [/^https?:\/\/wordpress\.tv\/.+/i],
  attributes: {
    providerNameSlug: 'wordpress-tv',
    responsive: true
  }
}, {
  name: 'amazon-kindle',
  title: 'Amazon Kindle',
  icon: _icons.embedAmazonIcon,
  keywords: [(0, _i18n.__)('ebook')],
  description: (0, _i18n.__)('Embed Amazon Kindle content.'),
  patterns: [/^https?:\/\/([a-z0-9-]+\.)?(amazon|amzn)(\.[a-z]{2,4})+\/.+/i, /^https?:\/\/(www\.)?(a\.co|z\.cn)\/.+/i],
  attributes: {
    providerNameSlug: 'amazon-kindle'
  }
}, {
  name: 'pinterest',
  title: 'Pinterest',
  icon: _icons.embedPinterestIcon,
  keywords: [(0, _i18n.__)('social'), (0, _i18n.__)('bookmark')],
  description: (0, _i18n.__)('Embed Pinterest pins, boards, and profiles.'),
  patterns: [/^https?:\/\/([a-z]{2}|www)\.pinterest\.com(\.(au|mx))?\/.*/i],
  attributes: {
    providerNameSlug: 'pinterest'
  }
}, {
  name: 'wolfram-cloud',
  title: 'Wolfram',
  icon: _icons.embedWolframIcon,
  description: (0, _i18n.__)('Embed Wolfram notebook content.'),
  patterns: [/^https?:\/\/(www\.)?wolframcloud\.com\/obj\/.+/i],
  attributes: {
    providerNameSlug: 'wolfram-cloud',
    responsive: true
  }
}];

/**
 * Add `isActive` function to all `embed` variations, if not defined.
 * `isActive` function is used to find a variation match from a created
 *  Block by providing its attributes.
 */
variations.forEach(variation => {
  if (variation.isActive) return;
  variation.isActive = (blockAttributes, variationAttributes) => blockAttributes.providerNameSlug === variationAttributes.providerNameSlug;
});
var _default = exports.default = variations;
//# sourceMappingURL=variations.js.map