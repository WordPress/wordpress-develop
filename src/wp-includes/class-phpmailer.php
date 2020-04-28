<?php

/**
 * The PHPMailer class has been moved to the wp-includes/PHPMailer subdirectory and now uses the PHPMailer\PHPMailer namespace.
 */
_deprecated_file( basename( __FILE__ ), '5.5.0', WPINC . '/PHPMailer/PHPMailer.php', __( 'The PHPMailer class has been moved to wp-includes/PHPMailer subdirectory and now uses the PHPMailer\PHPMailer namespace.' ) );
include __DIR__ .'/PHPMailer/PHPMailer.php';

/**
 * Class PHPMailer.
 *
 * Use PHPMailer\PHPMailer\PHPMailer instead.
 *
 * @since 2.2.0
 * @deprecated 5.5.0
 */
class PHPMailer {
	/**
	 * Constructor.
	 *
	 * @deprecated 5.5.0
	 *
	 * @param boolean $exceptions Should we throw external exceptions?
	 * @return PHPMailer\PHPMailer\PHPMailer An instance of the new, correctly namespaced PHPMailer.
	 */
	public function __construct( $exceptions = null )
	{
		_deprecated_function( __METHOD__, '5.5', 'PHPMailer\PHPMailer\PHPMailer::construct()' );

		return new PHPMailer\PHPMailer\PHPMailer( $exceptions );
	}
}
