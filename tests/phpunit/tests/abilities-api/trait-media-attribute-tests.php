<?php

declare( strict_types=1 );

/**
 * Tests of the attributes that `core/media-upload` and `core/media-update` both take.
 *
 * The test class writes one attachment through the hooks below; the author role may write it
 * but may not set other authors or edit the shared posts.
 *
 * @since 7.3.0
 */
trait WP_Abilities_Media_Attribute_Tests {

	/**
	 * Runs the ability under test with the given attributes.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $data   The attributes.
	 * @param string[]|null        $fields Optional. The fields. Default null.
	 * @return mixed The ability result.
	 */
	abstract protected function write( array $data, ?array $fields = null );

	/**
	 * Asserts that a write succeeded and returns the ID of the attachment.
	 *
	 * @since 7.3.0
	 *
	 * @param mixed  $result  The ability result.
	 * @param string $context What the call was, for the assertion messages.
	 * @return int The attachment ID.
	 */
	abstract protected function assert_written( $result, string $context ): int;

	/**
	 * Takes a snapshot before a write.
	 *
	 * @since 7.3.0
	 *
	 * @return array<string, mixed> The snapshot.
	 */
	abstract protected function snapshot_write(): array;

	/**
	 * Asserts that a refused write changed nothing.
	 *
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $before  The snapshot before the call.
	 * @param string               $context What the call was, for the assertion messages.
	 */
	abstract protected function assert_unchanged( array $before, string $context ): void;

	/**
	 * The attributes are checked in the order author, post, date, slug.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_attribute_checks_run_in_order(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();
		$this->skip_input_validation();

		$data = array(
			'author' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
			'post'   => self::$post_ids['revision'],
			'date'   => array(),
			'slug'   => '!!!',
		);

		foreach ( array( 'author', 'post', 'date', 'slug' ) as $attribute ) {
			$this->assert_error_with_params( $this->write( $data ), 'media_invalid_attribute', "data[{$attribute}]", "An invalid {$attribute} first" );
			unset( $data[ $attribute ] );
		}
	}

	/**
	 * The caller's own ID is allowed as the author.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_own_author_id_is_allowed(): void {
		$this->login_as( 'author' );
		$this->register_abilities();

		$id = $this->assert_written( $this->write( array( 'author' => self::$user_ids['author'] ) ), 'A write with the own author' );
		$this->assertSame( self::$user_ids['author'], (int) get_post_field( 'post_author', $id ), 'The caller should be the author.' );
	}

	/**
	 * Another author needs `edit_others_posts`, checked before the user is looked up.
	 *
	 * @ticket 66719
	 * @dataProvider data_other_authors
	 * @since 7.3.0
	 *
	 * @param string $author The author, as a fixture name.
	 */
	public function test_other_author_needs_edit_others_posts( string $author ): void {
		$this->login_as( 'author' );
		$this->register_abilities();

		$before = $this->snapshot_write();
		$result = $this->write( array( 'author' => self::$user_ids[ $author ] ?? REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );

		$this->assert_error( $result, 'media_cannot_edit_others', 403, 'A write as another author' );
		$this->assert_error_message( $result, static::OTHER_AUTHOR_MESSAGE, 'A write as another author' );
		$this->assert_unchanged( $before, 'A write as another author' );
	}

	/**
	 * An author that names no user is an invalid author for a caller who may set authors;
	 * another existing user is stored.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_author_for_a_caller_who_may_set_authors(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->write( array( 'author' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$this->assert_error_with_params( $result, 'media_invalid_attribute', 'data[author]', 'A missing author' );
		$this->assert_error_message( $result, 'Invalid author ID.', 'A missing author' );

		$id = $this->assert_written( $this->write( array( 'author' => self::$user_ids['author'] ) ), 'A write as another author' );
		$this->assertSame( self::$user_ids['author'], (int) get_post_field( 'post_author', $id ), 'The author sent should be stored.' );
	}

	/**
	 * A new parent needs `edit_post` on it, checked before it is looked up.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_new_parent_needs_edit_post(): void {
		$this->login_as( 'author' );
		$this->register_abilities();

		$before = $this->snapshot_write();

		foreach ( array( self::$post_ids['post'], REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) as $post ) {
			$result = $this->write( array( 'post' => $post ) );

			$this->assert_error( $result, 'media_cannot_attach', 403, 'A write to a forbidden parent' );
			$this->assert_error_message( $result, static::ATTACH_MESSAGE, 'A write to a forbidden parent' );
		}

		$this->assert_unchanged( $before, 'A write to a forbidden parent' );
	}

	/**
	 * A revision or an attachment cannot be the parent.
	 *
	 * @ticket 66719
	 * @dataProvider data_parents_of_an_invalid_type
	 * @since 7.3.0
	 *
	 * @param string $post The post, as a fixture name.
	 */
	public function test_parent_of_an_invalid_type( string $post ): void {
		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->write( array( 'post' => self::$post_ids[ $post ] ) );

		$this->assert_error_with_params( $result, 'media_invalid_attribute', 'data[post]', 'A parent of an invalid type' );
		$this->assert_error_message( $result, 'Invalid parent type.', 'A parent of an invalid type' );
	}

	/**
	 * A parent of an invalid type that the caller may not edit is refused as a missing post
	 * is, before its type is checked.
	 *
	 * @ticket 66719
	 * @dataProvider data_parents_of_an_invalid_type
	 * @since 7.3.0
	 *
	 * @param string $post The post, as a fixture name.
	 */
	public function test_parent_capability_is_checked_before_the_type( string $post ): void {
		$this->login_as( 'author' );
		$this->register_abilities();

		$missing = $this->write( array( 'post' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$result  = $this->write( array( 'post' => self::$post_ids[ $post ] ) );

		$this->assert_error( $result, 'media_cannot_attach', 403, 'A write to a forbidden parent of an invalid type' );
		$this->assertSame( $missing->get_error_message(), $result->get_error_message(), 'The message should be the one of a missing post.' );
		$this->assertSame( $missing->get_error_data(), $result->get_error_data(), 'The error data should be the one of a missing post.' );
	}

	/**
	 * The site date is read in the site's time zone and the GMT date in UTC; the site date
	 * wins when both are sent.
	 *
	 * @ticket 66719
	 * @dataProvider data_dates
	 * @since 7.3.0
	 *
	 * @param array<string, string> $date  The date input.
	 * @param string                $local The expected stored local date.
	 * @param string                $gmt   The expected stored GMT date.
	 */
	public function test_date( array $date, string $local, string $gmt ): void {
		update_option( 'timezone_string', 'America/New_York' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$id = $this->assert_written( $this->write( array( 'date' => $date ) ), 'A write with a date' );

		$this->assertSame( $local, get_post_field( 'post_date', $id ), 'The local date should be stored.' );
		$this->assertSame( $gmt, get_post_field( 'post_date_gmt', $id ), 'The GMT date should be stored.' );
	}

	/**
	 * A date Core cannot parse is an invalid date.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_unparsable_date_is_invalid(): void {
		$this->login_as( 'editor' );
		$this->register_abilities();
		$this->skip_input_validation();

		$this->assert_error_with_params( $this->write( array( 'date' => array( 'gmt' => 'not a date' ) ) ), 'media_invalid_attribute', 'data[date]', 'An unparsable date' );
	}

	/**
	 * A slug with nothing left after sanitizing is invalid; a taken slug is made unique.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_slug(): void {
		self::factory()->post->create( array( 'post_name' => 'mdt-taken-slug' ) );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$this->assert_error_with_params( $this->write( array( 'slug' => '!!!' ) ), 'media_invalid_attribute', 'data[slug]', 'A slug with nothing left' );

		$id   = $this->assert_written( $this->write( array( 'slug' => 'MDT-Taken-Slug' ) ), 'A write with a taken slug' );
		$slug = get_post_field( 'post_name', $id );

		$this->assertNotSame( 'mdt-taken-slug', $slug, 'The taken slug should be made unique.' );
		$this->assertStringStartsWith( 'mdt-taken-slug', $slug, 'The unique slug should start with the sanitized slug.' );
	}

	/**
	 * The alt text is sanitized as a text field, a listener after the save sees it, and an
	 * empty one is saved as an empty text.
	 *
	 * @ticket 66719
	 * @since 7.3.0
	 */
	public function test_alt_text(): void {
		$seen = array();
		add_action(
			'wp_after_insert_post',
			static function ( int $post_id, WP_Post $post ) use ( &$seen ): void {
				if ( 'attachment' === $post->post_type ) {
					$seen[] = get_post_meta( $post_id, '_wp_attachment_image_alt', true );
				}
			},
			10,
			2
		);

		$this->login_as( 'editor' );
		$this->register_abilities();

		$id = $this->assert_written( $this->write( array( 'alt_text' => "<b>MDT</b>\n alt " ) ), 'A write with markup in the alt text' );
		$this->assertSame( array( sanitize_text_field( "<b>MDT</b>\n alt " ) ), $seen, 'The listener should run once and see the sanitized alt text.' );
		$this->assertSame( $seen[0], get_post_meta( $id, '_wp_attachment_image_alt', true ), 'The alt text should be sanitized as a text field.' );

		$result = $this->write( array( 'alt_text' => '' ), array( 'alt_text' ) );
		$id     = $this->assert_written( $result, 'A write with an empty alt text' );
		$this->assertSame( '', $result['alt_text'], 'The output alt text should be empty.' );
		$this->assertSame( '', get_post_meta( $id, '_wp_attachment_image_alt', true ), 'The stored alt text should be empty.' );
	}

	/**
	 * Markup in the title, caption and description is kept for a caller with
	 * `unfiltered_html` and filtered for one without it.
	 *
	 * @ticket 66719
	 * @dataProvider data_markup_roles
	 * @since 7.3.0
	 *
	 * @param string $role        The role.
	 * @param bool   $kept        Whether the markup is kept.
	 * @param bool   $super_admin Optional. Whether the caller is a super admin. Default false.
	 */
	public function test_markup_follows_unfiltered_html( string $role, bool $kept, bool $super_admin = false ): void {
		if ( $super_admin ) {
			grant_super_admin( self::$user_ids[ $role ] );
		}

		$this->login_as( $role );
		$this->register_abilities();

		$markup = 'MDT <script>alert(1)</script>';
		$id     = $this->assert_written(
			$this->write(
				array(
					'title'       => $markup,
					'caption'     => $markup,
					'description' => $markup,
				)
			),
			'A write with markup'
		);

		foreach ( array( 'post_title', 'post_excerpt', 'post_content' ) as $field ) {
			$this->assertSame( $kept, str_contains( get_post_field( $field, $id, 'raw' ), '<script>' ), $kept ? "The markup in {$field} should be kept." : "The markup in {$field} should be filtered." );
		}
	}

	/**
	 * Backslashes in the title, caption, description and alt text are stored as they were
	 * sent.
	 *
	 * @ticket 66719
	 * @dataProvider data_texts_with_backslashes
	 * @since 7.3.0
	 *
	 * @param string $text The text.
	 */
	public function test_backslashes_are_stored_as_sent( string $text ): void {
		$this->grant_cap( 'unfiltered_html' );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$id = $this->assert_written(
			$this->write(
				array(
					'title'       => $text,
					'caption'     => $text,
					'description' => $text,
					'alt_text'    => $text,
				)
			),
			'A write with backslashes'
		);

		$this->assertSame( $text, get_post_field( 'post_title', $id, 'raw' ), 'The title should be stored as sent.' );
		$this->assertSame( $text, get_post_field( 'post_excerpt', $id, 'raw' ), 'The caption should be stored as sent.' );
		$this->assertSame( $text, get_post_field( 'post_content', $id, 'raw' ), 'The description should be stored as sent.' );
		$this->assertSame( $text, get_post_meta( $id, '_wp_attachment_image_alt', true ), 'The alt text should be stored as sent.' );
	}

	/**
	 * Attributes outside the input schema, such as an author below 1 or a key that is not an
	 * attribute, are invalid input, and nothing is written.
	 *
	 * @ticket 66719
	 * @dataProvider data_attributes_outside_the_schema
	 * @since 7.3.0
	 *
	 * @param array<string, mixed> $data The attributes.
	 */
	public function test_attributes_outside_the_schema_are_invalid_input( array $data ): void {
		$this->login_as( 'administrator' );
		$this->register_abilities();

		$before = $this->snapshot_write();

		$this->assert_invalid_input( $this->write( array_merge( array( 'title' => 'MDT invalid' ), $data ) ), 'Attributes outside the schema' );
		$this->assert_unchanged( $before, 'Attributes outside the schema' );
	}

	/**
	 * On multisite an author who is not a member of the site is an invalid author.
	 *
	 * @ticket 66719
	 * @group ms-required
	 * @since 7.3.0
	 */
	public function test_author_of_another_site_is_invalid(): void {
		$outsider = self::factory()->user->create();
		remove_user_from_blog( $outsider, get_current_blog_id() );

		$this->login_as( 'editor' );
		$this->register_abilities();

		$result = $this->write( array( 'author' => $outsider ) );

		$this->assert_error_with_params( $result, 'media_invalid_attribute', 'data[author]', 'An author of another site' );
		$this->assert_error_message( $result, 'Invalid author ID.', 'An author of another site' );
	}

	/**
	 * On multisite a super admin who is not a member of the site may send their own ID as
	 * the author.
	 *
	 * @ticket 66719
	 * @group ms-required
	 * @since 7.3.0
	 */
	public function test_super_admin_outside_the_site_may_send_the_own_id(): void {
		$super_admin = self::factory()->user->create();
		remove_user_from_blog( $super_admin, get_current_blog_id() );
		grant_super_admin( $super_admin );

		wp_set_current_user( $super_admin );
		$this->register_abilities();

		$id = $this->assert_written( $this->write( array( 'author' => $super_admin ) ), 'A write by a super admin outside the site' );
		$this->assertSame( (string) $super_admin, get_post_field( 'post_author', $id ), 'The stored author should be the super admin.' );
	}
}
