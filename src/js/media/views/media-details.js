/* global MediaElementPlayer */
var AttachmentDisplay = wp.media.view.Settings.AttachmentDisplay,
	$ = jQuery,
	MediaDetails;

/**
 * wp.media.view.MediaDetails
 *
 * @memberOf wp.media.view
 *
 * @class
 * @augments wp.media.view.Settings.AttachmentDisplay
 * @augments wp.media.view.Settings
 * @augments wp.media.View
 * @augments wp.Backbone.View
 * @augments Backbone.View
 */
MediaDetails = AttachmentDisplay.extend(/** @lends wp.media.view.MediaDetails.prototype */{
	/**
	 * Initializes the media details view.
	 */
	initialize: function() {
		_.bindAll(this, 'success');
		this.players = [];
		this.listenTo( this.controller.states, 'close', wp.media.mixin.unsetPlayers );
		this.on( 'ready', this.setPlayer );
		this.on( 'media:setting:remove', wp.media.mixin.unsetPlayers, this );
		this.on( 'media:setting:remove', this.render );
		this.on( 'media:setting:remove', this.setPlayer );

		AttachmentDisplay.prototype.initialize.apply( this, arguments );
	},

	/**
	 * Handles events for the media details view.
	 *
	 * @return {Object} The events object.
	 */
	events: function(){
		return _.extend( {
			'click .remove-setting' : 'removeSetting',
			'change .content-track' : 'setTracks',
			'click .remove-track' : 'setTracks',
			'click .add-media-source' : 'addSource'
		}, AttachmentDisplay.prototype.events );
	},

	/**
	 * Prepares the data for the media details view.
	 *
	 * @return {Object} The prepared data.
	 */
	prepare: function() {
		return _.defaults({
			model: this.model.toJSON()
		}, this.options );
	},

	/**
	 * Removes a setting's UI when the model unsets it
	 *
	 * @fires wp.media.view.MediaDetails#media:setting:remove
	 *
	 * @param {JQuery.Event} e The jQuery event object.
	 */
	removeSetting : function(e) {
		var wrap = $( e.currentTarget ).parent(), setting;
		setting = wrap.find( 'input' ).data( 'setting' );

		if ( setting ) {
			this.model.unset( setting );
			this.trigger( 'media:setting:remove', this );
		}

		wrap.remove();
	},

	/**
	 * Sets the tracks for the media details view.
	 *
	 * @fires wp.media.view.MediaDetails#media:setting:remove
	 */
	setTracks : function() {
		var tracks = '';

		_.each( this.$('.content-track'), function(track) {
			tracks += $( track ).val();
		} );

		this.model.set( 'content', tracks );
		this.trigger( 'media:setting:remove', this );
	},

	/**
	 * Adds a source to the media details view.
	 *
	 * @param {JQuery.Event} e The jQuery event object.
	 */
	addSource : function( e ) {
		this.controller.lastMime = $( e.currentTarget ).data( 'mime' );
		this.controller.setState( 'add-' + this.controller.defaults.id + '-source' );
	},

	/**
	 * Loads the media player for the media details view.
	 */
	loadPlayer: function () {
		this.players.push( new MediaElementPlayer( this.media, this.settings ) );
		this.scriptXhr = false;
	},

	/**
	 * Sets the media player for the media details view.
	 */
	setPlayer : function() {
		var src;

		if ( this.players.length || ! this.media || this.scriptXhr ) {
			return;
		}

		src = this.model.get( 'src' );

		if ( src && src.indexOf( 'vimeo' ) > -1 && ! ( 'Vimeo' in window ) ) {
			this.scriptXhr = $.getScript( 'https://player.vimeo.com/api/player.js', _.bind( this.loadPlayer, this ) );
		} else {
			this.loadPlayer();
		}
	},

	/**
	 * Sets the media for the media details view.
	 *
	 * @abstract
	 */
	setMedia : function() {
		return this;
	},

	/**
	 * Handles the success event for the media details view.
	 *
	 * @param {MediaElementPlayer} mejs The media element player instance.
	 */
	success : function(mejs) {
		var autoplay = mejs.attributes.autoplay && 'false' !== mejs.attributes.autoplay;

		if ( 'flash' === mejs.pluginType && autoplay ) {
			mejs.addEventListener( 'canplay', function() {
				mejs.play();
			}, false );
		}

		this.mejs = mejs;
	},

	/**
	 * Renders the media details view.
	 *
	 * @return {wp.media.view.MediaDetails} Returns itself to allow chaining.
	 */
	render: function() {
		AttachmentDisplay.prototype.render.apply( this, arguments );

		setTimeout( _.bind( function() {
			this.scrollToTop();
		}, this ), 10 );

		this.settings = _.defaults( {
			success : this.success
		}, wp.media.mixin.mejsSettings );

		return this.setMedia();
	},

	/**
	 * Scrolls the media details view to the top.
	 */
	scrollToTop: function() {
		this.$( '.embed-media-settings' ).scrollTop( 0 );
	}
},/** @lends wp.media.view.MediaDetails */{
	instances : 0,
	/**
	 * When multiple players in the DOM contain the same src, things get weird.
	 *
	 * @param {HTMLElement} elem The HTML element containing the media sources to be prepared.
	 * @return {HTMLElement} The prepared element.
	 */
	prepareSrc : function( elem ) {
		var i = MediaDetails.instances++;
		_.each( $( elem ).find( 'source' ), function( source ) {
			source.src = [
				source.src,
				source.src.indexOf('?') > -1 ? '&' : '?',
				'_=',
				i
			].join('');
		} );

		return elem;
	}
});

module.exports = MediaDetails;
