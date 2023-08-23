(function( $, wp, pagenow ) {
    var $document = $( document ),
		__ = wp.i18n.__,
		_x = wp.i18n._x,
		sprintf = wp.i18n.sprintf;

    wp.updates.installPluginSuccess = function( response ) {
		var $message = $( '.plugin-card-' + response.slug ).find( '.install-now' );

		$message
			.removeClass( 'updating-message' )
			.addClass( 'updated-message installed button-disabled' )
			.attr(
				'aria-label',
				sprintf(
					/* translators: %s: Plugin name and version. */
					_x( '%s installed!', 'plugin' ),
					response.pluginName
				)
			)
			.text( _x( 'Installed!', 'plugin' ) );

		wp.a11y.speak( __( 'Installation completed successfully.' ) );

		$document.trigger( 'wp-plugin-install-success', response );

		if ( response.activateUrl ) {
			setTimeout( function() {
				wp.updates.ajax(
					'check_plugin_dependencies',
					{
							slug: response.slug,
							success: function() {
								// Transform the 'Install' button into an 'Activate' button.
								$message.removeClass( 'install-now installed button-disabled updated-message' )
								.addClass( 'activate-now button-primary' )
								.attr( 'href', response.activateUrl );

								if ( 'plugins-network' === pagenow ) {
									$message
									.attr(
										'aria-label',
										sprintf(
										/* translators: %s: Plugin name. */
											_x( 'Network Activate %s', 'plugin' ),
											response.pluginName
										)
									)
									  .text( __( 'Network Activate' ) );
								} else {
									$message
									.attr(
										'aria-label',
										sprintf(
										/* translators: %s: Plugin name. */
											_x( 'Activate %s', 'plugin' ),
											response.pluginName
										)
									)
									.text( __( 'Activate' ) );
								}
							},
							error: function( error ) {
								$message
								.removeClass( 'install-now installed updated-message' )
								.addClass( 'activate-now button-primary' )
								.attr(
									'aria-label',
									sprintf(
									/* translators: 1: Plugin name, 2. The reason the plugin cannot be activated. */
										_x( 'Cannot activate %1$s. %2$s', 'plugin' ),
										response.pluginName,
										error.errorMessage
									)
								)
								.text( __( 'Activate' ) );
						}
					}
				);
			}, 1000 );
		}
	};
})( jQuery, window.wp, window._wpUpdatesSettings );
