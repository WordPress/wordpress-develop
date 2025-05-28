<?php
// phpcs:ignoreFile
declare( strict_types=1 );

require_once __DIR__ . '/AddMetaBenchBase.php';

class AddMetaBench extends AddMetaBenchBase {
	/**
	 * @ParamProviders("provideMetaData")
	 */
	public function benchAddMeta( array $meta ): void {
		foreach ( $meta as $key => $value ) {
			add_post_meta( $this->post_id, $key, $value );
		}
	}
}
