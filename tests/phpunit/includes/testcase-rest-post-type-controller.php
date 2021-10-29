<?php

abstract class WP_Test_REST_Post_Type_Controller_Testcase extends WP_Test_REST_Controller_Testcase {

	protected function check_post_data( $post, $data, $context, $links ) {
		$post_type_obj = get_post_type_object( $post->post_type );

		// Standard fields.
		$this->assertSame( $post->ID, $data['id'] );
		$this->assertSame( $post->post_name, $data['slug'] );
		$this->assertSame( get_permalink( $post->ID ), $data['link'] );
		if ( '0000-00-00 00:00:00' === $post->post_date_gmt ) {
			$post_date_gmt = gmdate( 'Y-m-d H:i:s', strtotime( $post->post_date ) - ( get_option( 'gmt_offset' ) * 3600 ) );
			$this->assertSame( mysql_to_rfc3339( $post_date_gmt ), $data['date_gmt'] );
		} else {
			$this->assertSame( mysql_to_rfc3339( $post->post_date_gmt ), $data['date_gmt'] );
		}
		$this->assertSame( mysql_to_rfc3339( $post->post_date ), $data['date'] );

		if ( '0000-00-00 00:00:00' === $post->post_modified_gmt ) {
			$post_modified_gmt = gmdate( 'Y-m-d H:i:s', strtotime( $post->post_modified ) - ( get_option( 'gmt_offset' ) * 3600 ) );
			$this->assertSame( mysql_to_rfc3339( $post_modified_gmt ), $data['modified_gmt'] );
		} else {
			$this->assertSame( mysql_to_rfc3339( $post->post_modified_gmt ), $data['modified_gmt'] );
		}
		$this->assertSame( mysql_to_rfc3339( $post->post_modified ), $data['modified'] );

		// Author.
		if ( post_type_supports( $post->post_type, 'author' ) ) {
			$this->assertEquals( $post->post_author, $data['author'] );
		} else {
			$this->assertEmpty( $data['author'] );
		}

		// Post parent.
		if ( $post_type_obj->hierarchical ) {
			$this->assertArrayHasKey( 'parent', $data );
			if ( $post->post_parent ) {
				if ( is_int( $data['parent'] ) ) {
					$this->assertSame( $post->post_parent, $data['parent'] );
				} else {
					$this->assertSame( $post->post_parent, $data['parent']['id'] );
					$this->check_get_post_response( $data['parent'], get_post( $data['parent']['id'] ), 'view-parent' );
				}
			} else {
				$this->assertEmpty( $data['parent'] );
			}
		} else {
			$this->assertArrayNotHasKey( 'parent', $data );
		}

		// Page attributes.
		if ( $post_type_obj->hierarchical && post_type_supports( $post->post_type, 'page-attributes' ) ) {
			$this->assertSame( $post->menu_order, $data['menu_order'] );
		} else {
			$this->assertArrayNotHasKey( 'menu_order', $data );
		}

		// Comments.
		if ( post_type_supports( $post->post_type, 'comments' ) ) {
			$this->assertSame( $post->comment_status, $data['comment_status'] );
			$this->assertSame( $post->ping_status, $data['ping_status'] );
		} else {
			$this->assertArrayNotHasKey( 'comment_status', $data );
			$this->assertArrayNotHasKey( 'ping_status', $data );
		}

		if ( 'post' === $post->post_type ) {
			$this->assertSame( is_sticky( $post->ID ), $data['sticky'] );
		}

		if ( 'post' === $post->post_type && 'edit' === $context ) {
			$this->assertSame( $post->post_password, $data['password'] );
		}

		if ( 'page' === $post->post_type ) {
			$this->assertSame( get_page_template_slug( $post->ID ), $data['template'] );
		}

		if ( post_type_supports( $post->post_type, 'thumbnail' ) ) {
			$this->assertSame( (int) get_post_thumbnail_id( $post->ID ), $data['featured_media'] );
		} else {
			$this->assertArrayNotHasKey( 'featured_media', $data );
		}

		// Check post format.
		if ( post_type_supports( $post->post_type, 'post-formats' ) ) {
			$post_format = get_post_format( $post->ID );
			if ( empty( $post_format ) ) {
				$this->assertSame( 'standard', $data['format'] );
			} else {
				$this->assertSame( get_post_format( $post->ID ), $data['format'] );
			}
		} else {
			$this->assertArrayNotHasKey( 'format', $data );
		}

		// Check filtered values.
		if ( post_type_supports( $post->post_type, 'title' ) ) {
			add_filter( 'protected_title_format', array( $this, 'protected_title_format' ) );
			$this->assertSame( get_the_title( $post->ID ), $data['title']['rendered'] );
			remove_filter( 'protected_title_format', array( $this, 'protected_title_format' ) );
			if ( 'edit' === $context ) {
				$this->assertSame( $post->post_title, $data['title']['raw'] );
			} else {
				$this->assertArrayNotHasKey( 'raw', $data['title'] );
			}
		} else {
			$this->assertArrayNotHasKey( 'title', $data );
		}

		if ( post_type_supports( $post->post_type, 'editor' ) ) {
			// TODO: Apply content filter for more accurate testing.
			if ( ! $post->post_password ) {
				$this->assertSame( wpautop( $post->post_content ), $data['content']['rendered'] );
			}

			if ( 'edit' === $context ) {
				$this->assertSame( $post->post_content, $data['content']['raw'] );
			} else {
				$this->assertArrayNotHasKey( 'raw', $data['content'] );
			}
		} else {
			$this->assertArrayNotHasKey( 'content', $data );
		}

		if ( post_type_supports( $post->post_type, 'excerpt' ) ) {
			if ( empty( $post->post_password ) ) {
				// TODO: Apply excerpt filter for more accurate testing.
				$this->assertSame( wpautop( $post->post_excerpt ), $data['excerpt']['rendered'] );
			} else {
				// TODO: Better testing for excerpts for password protected posts.
			}
			if ( 'edit' === $context ) {
				$this->assertSame( $post->post_excerpt, $data['excerpt']['raw'] );
			} else {
				$this->assertArrayNotHasKey( 'raw', $data['excerpt'] );
			}
		} else {
			$this->assertArrayNotHasKey( 'excerpt', $data );
		}

		$this->assertSame( $post->post_status, $data['status'] );
		$this->assertSame( $post->guid, $data['guid']['rendered'] );

		if ( 'edit' === $context ) {
			$this->assertSame( $post->guid, $data['guid']['raw'] );
		}

		$taxonomies = wp_list_filter( get_object_taxonomies( $post->post_type, 'objects' ), array( 'show_in_rest' => true ) );
		foreach ( $taxonomies as $taxonomy ) {
			$this->assertArrayHasKey( $taxonomy->rest_base, $data );
			$terms = wp_get_object_terms( $post->ID, $taxonomy->name, array( 'fields' => 'ids' ) );
			sort( $terms );
			sort( $data[ $taxonomy->rest_base ] );
			$this->assertSame( $terms, $data[ $taxonomy->rest_base ] );
		}

		// Test links.
		if ( $links ) {

			$links     = test_rest_expand_compact_links( $links );
			$post_type = get_post_type_object( $data['type'] );
			$this->assertSame( $links['self'][0]['href'], rest_url( 'wp/v2/' . $post_type->rest_base . '/' . $data['id'] ) );
			$this->assertSame( $links['collection'][0]['href'], rest_url( 'wp/v2/' . $post_type->rest_base ) );
			$this->assertSame( $links['about'][0]['href'], rest_url( 'wp/v2/types/' . $data['type'] ) );

			if ( post_type_supports( $post->post_type, 'author' ) && $data['author'] ) {
				$this->assertSame( $links['author'][0]['href'], rest_url( 'wp/v2/users/' . $data['author'] ) );
			}

			if ( post_type_supports( $post->post_type, 'comments' ) ) {
				$this->assertSame( $links['replies'][0]['href'], add_query_arg( 'post', $data['id'], rest_url( 'wp/v2/comments' ) ) );
			}

			if ( post_type_supports( $post->post_type, 'revisions' ) ) {
				$this->assertSame( $links['version-history'][0]['href'], rest_url( 'wp/v2/' . $post_type->rest_base . '/' . $data['id'] . '/revisions' ) );
			}

			if ( $post_type->hierarchical && ! empty( $data['parent'] ) ) {
				$this->assertSame( $links['up'][0]['href'], rest_url( 'wp/v2/' . $post_type->rest_base . '/' . $data['parent'] ) );
			}

			if ( ! in_array( $data['type'], array( 'attachment', 'nav_menu_item', 'revision' ), true ) ) {
				$this->assertSame( $links['https://api.w.org/attachment'][0]['href'], add_query_arg( 'parent', $data['id'], rest_url( 'wp/v2/media' ) ) );
			}

			if ( ! empty( $data['featured_media'] ) ) {
				$this->assertSame( $links['https://api.w.org/featuredmedia'][0]['href'], rest_url( 'wp/v2/media/' . $data['featured_media'] ) );
			}

			$num = 0;
			foreach ( $taxonomies as $key => $taxonomy ) {
				$this->assertSame( $taxonomy->name, $links['https://api.w.org/term'][ $num ]['attributes']['taxonomy'] );
				$this->assertSame( add_query_arg( 'post', $data['id'], rest_url( 'wp/v2/' . $taxonomy->rest_base ) ), $links['https://api.w.org/term'][ $num ]['href'] );
				$num++;
			}
		}

	}

	protected function check_get_posts_response( $response, $context = 'view' ) {
		$this->assertNotWPError( $response );
		$response = rest_ensure_response( $response );
		$this->assertSame( 200, $response->get_status() );

		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'X-WP-Total', $headers );
		$this->assertArrayHasKey( 'X-WP-TotalPages', $headers );

		$all_data = $response->get_data();
		foreach ( $all_data as $data ) {
			$post = get_post( $data['id'] );
			// As the links for the post are "response_links" format in the data array,
			// we have to pull them out and parse them.
			$links = $data['_links'];
			foreach ( $links as &$links_array ) {
				foreach ( $links_array as &$link ) {
					$attributes         = array_diff_key(
						$link,
						array(
							'href' => 1,
							'name' => 1,
						)
					);
					$link               = array_diff_key( $link, $attributes );
					$link['attributes'] = $attributes;
				}
			}

			$this->check_post_data( $post, $data, $context, $links );
		}
	}

	protected function check_get_post_response( $response, $context = 'view' ) {
		$this->assertNotWPError( $response );
		$response = rest_ensure_response( $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$post = get_post( $data['id'] );
		$this->check_post_data( $post, $data, $context, $response->get_links() );

	}

	protected function check_create_post_response( $response ) {
		$this->assertNotWPError( $response );
		$response = rest_ensure_response( $response );

		$this->assertSame( 201, $response->get_status() );
		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'Location', $headers );

		$data = $response->get_data();
		$post = get_post( $data['id'] );
		$this->check_post_data( $post, $data, 'edit', $response->get_links() );
	}

	protected function check_update_post_response( $response ) {
		$this->assertNotWPError( $response );
		$response = rest_ensure_response( $response );

		$this->assertSame( 200, $response->get_status() );
		$headers = $response->get_headers();
		$this->assertArrayNotHasKey( 'Location', $headers );

		$data = $response->get_data();
		$post = get_post( $data['id'] );
		$this->check_post_data( $post, $data, 'edit', $response->get_links() );
	}

	protected function set_post_data( $args = array() ) {
		$defaults = array(
			'title'   => 'Post Title',
			'content' => 'Post content',
			'excerpt' => 'Post excerpt',
			'name'    => 'test',
			'status'  => 'publish',
			'author'  => get_current_user_id(),
			'type'    => 'post',
		);

		return wp_parse_args( $args, $defaults );
	}

	protected function set_raw_post_data( $args = array() ) {
		return wp_parse_args(
			$args,
			$this->set_post_data(
				array(
					'title'   => array(
						'raw' => 'Post Title',
					),
					'content' => array(
						'raw' => 'Post content',
					),
					'excerpt' => array(
						'raw' => 'Post excerpt',
					),
				)
			)
		);
	}

	/**
	 * Overwrite the default protected title format.
	 *
	 * By default WordPress will show password protected posts with a title of
	 * "Protected: %s", as the REST API communicates the protected status of a post
	 * in a machine readable format, we remove the "Protected: " prefix.
	 *
	 * @return string
	 */
	public function protected_title_format() {
		return '%s';
	}
}
