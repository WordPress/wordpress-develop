<?php
/**
 * WP AI Client: WP_AI_Client_Capabilities class
 *
 * @package WordPress
 * @subpackage AI
 * @since 7.0.0
 */

/**
 * Manages capabilities for the AI Client.
 *
 * @since 7.0.0
 */
class WP_AI_Client_Capabilities {

	/**
	 * Capability to prompt AI models directly.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	public const PROMPT_AI = 'prompt_ai';

	/**
	 * Capability to list AI providers.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	public const LIST_AI_PROVIDERS = 'list_ai_providers';

	/**
	 * Capability to list AI models.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	public const LIST_AI_MODELS = 'list_ai_models';

	/**
	 * Grants the prompt_ai capability to administrators.
	 *
	 * This method is intended to be used as a filter callback for 'user_has_cap'.
	 * It will grant the 'prompt_ai' capability to users who have the 'manage_options' capability.
	 *
	 * For customization, this filter callback can be removed and replaced with custom logic.
	 *
	 * @since 7.0.0
	 *
	 * @param array<string, bool> $allcaps An array of all the user's capabilities.
	 * @return array<string, bool> The filtered array of capabilities.
	 */
	public static function grant_prompt_ai_to_administrators( array $allcaps ): array {
		if ( isset( $allcaps['manage_options'] ) && $allcaps['manage_options'] ) {
			$allcaps[ self::PROMPT_AI ] = true;
		}
		return $allcaps;
	}

	/**
	 * Grants the list_ai_providers and list_ai_models capabilities to administrators.
	 *
	 * This method is intended to be used as a filter callback for 'user_has_cap'.
	 * It will grant the 'list_ai_providers' and 'list_ai_models' capabilities to users
	 * who have the 'manage_options' capability.
	 *
	 * For customization, this filter callback can be removed and replaced with custom logic.
	 *
	 * @since 7.0.0
	 *
	 * @param array<string, bool> $allcaps An array of all the user's capabilities.
	 * @return array<string, bool> The filtered array of capabilities.
	 */
	public static function grant_list_ai_providers_models_to_administrators( array $allcaps ): array {
		if ( isset( $allcaps['manage_options'] ) && $allcaps['manage_options'] ) {
			$allcaps[ self::LIST_AI_PROVIDERS ] = true;
			$allcaps[ self::LIST_AI_MODELS ]    = true;
		}
		return $allcaps;
	}
}
