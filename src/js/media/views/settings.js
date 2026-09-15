var View = wp.media.View,
	$ = Backbone.$,
	Settings;

/**
 * wp.media.view.Settings
 *
 * @memberOf wp.media.view
 *
 * @class
 * @augments wp.media.View
 * @augments wp.Backbone.View
 * @augments Backbone.View
 */
Settings = View.extend(/** @lends wp.media.view.Settings.prototype */{
	events: {
		'click button':    'updateHandler',
		'change input':    'updateHandler',
		'change select':   'updateHandler',
		'change textarea': 'updateHandler'
	},

	/**
	 * Initializes the settings view.
	 */
	initialize: function() {
		this.model = this.model || new Backbone.Model();
		this.listenTo( this.model, 'change', this.updateChanges );
	},

	/**
	 * Prepares the data for rendering.
	 *
	 * @return {Object} The data to be used in the template.
	 */
	prepare: function() {
		return _.defaults({
			model: this.model.toJSON()
		}, this.options );
	},
	/**
	 * Renders the settings view.
	 *
	 * @return {wp.media.view.Settings} Returns itself to allow chaining.
	 */
	render: function() {
		View.prototype.render.apply( this, arguments );
		// Select the correct values.
		_( this.model.attributes ).chain().keys().each( this.update, this );
		return this;
	},
	/**
	 * Updates the selected value for a setting.
	 *
	 * @param {string} key The key of the setting to update.
	 */
	update: function( key ) {
		var value = this.model.get( key ),
			$setting = this.$('[data-setting="' + key + '"]'),
			$buttons, $value;

		// Bail if we didn't find a matching setting.
		if ( ! $setting.length ) {
			return;
		}

		// Attempt to determine how the setting is rendered and update
		// the selected value.

		// Handle dropdowns.
		if ( $setting.is('select') ) {
			$value = $setting.find('[value="' + value + '"]');

			if ( $value.length ) {
				$setting.find('option').prop( 'selected', false );
				$value.prop( 'selected', true );
			} else {
				// If we can't find the desired value, record what *is* selected.
				this.model.set( key, $setting.find(':selected').val() );
			}

		// Handle button groups.
		} else if ( $setting.hasClass('button-group') ) {
			$buttons = $setting.find( 'button' )
				.removeClass( 'active' )
				.attr( 'aria-pressed', 'false' );
			$buttons.filter( '[value="' + value + '"]' )
				.addClass( 'active' )
				.attr( 'aria-pressed', 'true' );

		// Handle text inputs and textareas.
		} else if ( $setting.is('input[type="text"], textarea') ) {
			if ( ! $setting.is(':focus') ) {
				$setting.val( value );
			}
		// Handle checkboxes.
		} else if ( $setting.is('input[type="checkbox"]') ) {
			$setting.prop( 'checked', !! value && 'false' !== value );
		}
	},
	/**
	 * Updates the model when a setting is changed.
	 *
	 * @param {JQuery.Event} event The jQuery event object associated with the setting change.
	 */
	updateHandler: function( event ) {
		var $setting = $( event.target ).closest('[data-setting]'),
			value = event.target.value,
			userSetting;

		event.preventDefault();

		if ( ! $setting.length ) {
			return;
		}

		// Use the correct value for checkboxes.
		if ( $setting.is('input[type="checkbox"]') ) {
			value = $setting[0].checked;
		}

		// Update the corresponding setting.
		this.model.set( $setting.data('setting'), value );

		// If the setting has a corresponding user setting,
		// update that as well.
		userSetting = $setting.data('userSetting');
		if ( userSetting ) {
			window.setUserSetting( userSetting, value );
		}
	},

	/**
	 * Updates the view when the model changes.
	 *
	 * @param {Backbone.Model} model The model that changed.
	 */
	updateChanges: function( model ) {
		if ( model.hasChanged() ) {
			_( model.changed ).chain().keys().each( this.update, this );
		}
	}
});

module.exports = Settings;
