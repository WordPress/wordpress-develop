"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _icons = require("./icons");
/**
 * Internal dependencies
 */

const variations = [{
  isDefault: true,
  name: 'wordpress',
  attributes: {
    service: 'wordpress'
  },
  title: 'WordPress',
  icon: _icons.WordPressIcon
}, {
  name: 'fivehundredpx',
  attributes: {
    service: 'fivehundredpx'
  },
  title: '500px',
  icon: _icons.FivehundredpxIcon
}, {
  name: 'amazon',
  attributes: {
    service: 'amazon'
  },
  title: 'Amazon',
  icon: _icons.AmazonIcon
}, {
  name: 'bandcamp',
  attributes: {
    service: 'bandcamp'
  },
  title: 'Bandcamp',
  icon: _icons.BandcampIcon
}, {
  name: 'behance',
  attributes: {
    service: 'behance'
  },
  title: 'Behance',
  icon: _icons.BehanceIcon
}, {
  name: 'chain',
  attributes: {
    service: 'chain'
  },
  title: 'Link',
  icon: _icons.ChainIcon
}, {
  name: 'codepen',
  attributes: {
    service: 'codepen'
  },
  title: 'CodePen',
  icon: _icons.CodepenIcon
}, {
  name: 'deviantart',
  attributes: {
    service: 'deviantart'
  },
  title: 'DeviantArt',
  icon: _icons.DeviantArtIcon
}, {
  name: 'dribbble',
  attributes: {
    service: 'dribbble'
  },
  title: 'Dribbble',
  icon: _icons.DribbbleIcon
}, {
  name: 'dropbox',
  attributes: {
    service: 'dropbox'
  },
  title: 'Dropbox',
  icon: _icons.DropboxIcon
}, {
  name: 'etsy',
  attributes: {
    service: 'etsy'
  },
  title: 'Etsy',
  icon: _icons.EtsyIcon
}, {
  name: 'facebook',
  attributes: {
    service: 'facebook'
  },
  title: 'Facebook',
  icon: _icons.FacebookIcon
}, {
  name: 'feed',
  attributes: {
    service: 'feed'
  },
  title: 'RSS Feed',
  icon: _icons.FeedIcon
}, {
  name: 'flickr',
  attributes: {
    service: 'flickr'
  },
  title: 'Flickr',
  icon: _icons.FlickrIcon
}, {
  name: 'foursquare',
  attributes: {
    service: 'foursquare'
  },
  title: 'Foursquare',
  icon: _icons.FoursquareIcon
}, {
  name: 'goodreads',
  attributes: {
    service: 'goodreads'
  },
  title: 'Goodreads',
  icon: _icons.GoodreadsIcon
}, {
  name: 'google',
  attributes: {
    service: 'google'
  },
  title: 'Google',
  icon: _icons.GoogleIcon
}, {
  name: 'github',
  attributes: {
    service: 'github'
  },
  title: 'GitHub',
  icon: _icons.GitHubIcon
}, {
  name: 'gravatar',
  attributes: {
    service: 'gravatar'
  },
  title: 'Gravatar',
  icon: _icons.GravatarIcon
}, {
  name: 'instagram',
  attributes: {
    service: 'instagram'
  },
  title: 'Instagram',
  icon: _icons.InstagramIcon
}, {
  name: 'lastfm',
  attributes: {
    service: 'lastfm'
  },
  title: 'Last.fm',
  icon: _icons.LastfmIcon
}, {
  name: 'linkedin',
  attributes: {
    service: 'linkedin'
  },
  title: 'LinkedIn',
  icon: _icons.LinkedInIcon
}, {
  name: 'mail',
  attributes: {
    service: 'mail'
  },
  title: 'Mail',
  keywords: ['email', 'e-mail'],
  icon: _icons.MailIcon
}, {
  name: 'mastodon',
  attributes: {
    service: 'mastodon'
  },
  title: 'Mastodon',
  icon: _icons.MastodonIcon
}, {
  name: 'meetup',
  attributes: {
    service: 'meetup'
  },
  title: 'Meetup',
  icon: _icons.MeetupIcon
}, {
  name: 'medium',
  attributes: {
    service: 'medium'
  },
  title: 'Medium',
  icon: _icons.MediumIcon
}, {
  name: 'patreon',
  attributes: {
    service: 'patreon'
  },
  title: 'Patreon',
  icon: _icons.PatreonIcon
}, {
  name: 'pinterest',
  attributes: {
    service: 'pinterest'
  },
  title: 'Pinterest',
  icon: _icons.PinterestIcon
}, {
  name: 'pocket',
  attributes: {
    service: 'pocket'
  },
  title: 'Pocket',
  icon: _icons.PocketIcon
}, {
  name: 'reddit',
  attributes: {
    service: 'reddit'
  },
  title: 'Reddit',
  icon: _icons.RedditIcon
}, {
  name: 'skype',
  attributes: {
    service: 'skype'
  },
  title: 'Skype',
  icon: _icons.SkypeIcon
}, {
  name: 'snapchat',
  attributes: {
    service: 'snapchat'
  },
  title: 'Snapchat',
  icon: _icons.SnapchatIcon
}, {
  name: 'soundcloud',
  attributes: {
    service: 'soundcloud'
  },
  title: 'SoundCloud',
  icon: _icons.SoundCloudIcon
}, {
  name: 'spotify',
  attributes: {
    service: 'spotify'
  },
  title: 'Spotify',
  icon: _icons.SpotifyIcon
}, {
  name: 'telegram',
  attributes: {
    service: 'telegram'
  },
  title: 'Telegram',
  icon: _icons.TelegramIcon
}, {
  name: 'threads',
  attributes: {
    service: 'threads'
  },
  title: 'Threads',
  icon: _icons.ThreadsIcon
}, {
  name: 'tiktok',
  attributes: {
    service: 'tiktok'
  },
  title: 'TikTok',
  icon: _icons.TiktokIcon
}, {
  name: 'tumblr',
  attributes: {
    service: 'tumblr'
  },
  title: 'Tumblr',
  icon: _icons.TumblrIcon
}, {
  name: 'twitch',
  attributes: {
    service: 'twitch'
  },
  title: 'Twitch',
  icon: _icons.TwitchIcon
}, {
  name: 'twitter',
  attributes: {
    service: 'twitter'
  },
  title: 'Twitter',
  icon: _icons.TwitterIcon
}, {
  name: 'vimeo',
  attributes: {
    service: 'vimeo'
  },
  title: 'Vimeo',
  icon: _icons.VimeoIcon
}, {
  name: 'vk',
  attributes: {
    service: 'vk'
  },
  title: 'VK',
  icon: _icons.VkIcon
}, {
  name: 'whatsapp',
  attributes: {
    service: 'whatsapp'
  },
  title: 'WhatsApp',
  icon: _icons.WhatsAppIcon
}, {
  name: 'x',
  attributes: {
    service: 'x'
  },
  keywords: ['twitter'],
  title: 'X',
  icon: _icons.XIcon
}, {
  name: 'yelp',
  attributes: {
    service: 'yelp'
  },
  title: 'Yelp',
  icon: _icons.YelpIcon
}, {
  name: 'youtube',
  attributes: {
    service: 'youtube'
  },
  title: 'YouTube',
  icon: _icons.YouTubeIcon
}];

/**
 * Add `isActive` function to all `social link` variations, if not defined.
 * `isActive` function is used to find a variation match from a created
 *  Block by providing its attributes.
 */
variations.forEach(variation => {
  if (variation.isActive) return;
  variation.isActive = (blockAttributes, variationAttributes) => blockAttributes.service === variationAttributes.service;
});
var _default = exports.default = variations;
//# sourceMappingURL=variations.js.map