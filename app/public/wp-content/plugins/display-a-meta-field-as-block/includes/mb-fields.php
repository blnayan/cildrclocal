<?php
/**
 * The MBFields
 *
 * @package   MetaFieldBlock
 * @author    Phi Phan <mrphipv@gmail.com>
 * @copyright Copyright (c) 2025, Phi Phan
 */

namespace MetaFieldBlock;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( MBFields::class ) ) :
	/**
	 * The MBFields class.
	 */
	class MBFields extends CoreComponent {
		private $media_fields = [
			'media',
			'file',
			'file_upload',
			'file_advanced',
			'image',
			'image_upload',
			'image_advanced',
			'plupload_image',
			'thickbox_image',
		];

		private $no_value_fields = [
			'heading',
			'custom_html',
			'divider',
			'button',
		];

		private $embeded_fields = [
			'oembed',
			'map',
			'osm',
		];

		/**
		 * For simple fields, we can use the same separator for all fields.
		 */
		private $simple_field_types = [ 'text', 'url', 'email', 'range', 'number', 'slider', 'date', 'datetime', 'time' ];

		/**
		 * List of field types that will be ignored when cloning enabled.
		 *
		 * @var array
		 */
		private $ignored_clone_fields = [ 'oembed', 'image', 'image_advanced', 'image_upload', 'image_single', 'video', 'post', 'taxonomy', 'taxonomy_advanced', 'user', 'checkbox', 'switch', 'checkbox_list', 'radio', 'select', 'select_advanced', 'button_group', 'autocomplete', 'image_select' ];

		/**
		 * Run main hooks
		 *
		 * @return void
		 */
		public function run() {
			// Register custom rest fields.
			add_action( 'rest_api_init', [ $this, 'register_rest_field' ] );

			// Format special fields for rest.
			add_filter( '_mb_field_format_value_for_rest', [ $this, 'format_value_for_rest' ], 10, 5 );
		}

		/**
		 * Register custom rest field for metabox
		 *
		 * @return void
		 */
		public function register_rest_field() {
			$object_types = $this->the_plugin_instance->get_component( RestFields::class )->load_public_object_types();
			if ( $object_types ) {
				register_rest_field(
					$object_types,
					'mb',
					[
						'get_callback' => [ $this, 'get_rest_field' ],
						'schema'       => array(
							'type' => 'array',
						),
					]
				);
			}
		}

		/**
		 * Get rest value for metabox
		 *
		 * @param array           $object
		 * @param string          $key
		 * @param WP_REST_Request $request
		 * @return array
		 */
		public function get_rest_field( $object, $key, $request ) {
			$values = [];
			if ( ! function_exists( 'rwmb_get_value' ) ) {
				return $values;
			}

			$object_type = apply_filters( '_meta_field_block_mb_field_get_rest_object_type', 'post', $request, $object );
			$object_id   = $object['id'] ?? 0;

			return $this->get_rest_values( $object_id, $object_type );
		}

		/**
		 * Build rest values for objects
		 *
		 * @param mixed  $object_id
		 * @param string $object_type
		 * @return array
		 */
		public function get_rest_values( $object_id, $object_type = 'post' ) {
			$values = [];
			if ( ! function_exists( 'rwmb_get_value' ) ) {
				return $values;
			}

			$args   = [ 'object_type' => $object_type ];
			$fields = rwmb_get_object_fields( $object_id, $object_type );

			// Remove fields with with hide_from_rest = true or has no values.
			$fields = array_filter(
				$fields,
				function ( $field ) {
					return empty( $field['hide_from_rest'] ) && ! empty( $field['id'] ) && ! in_array( $field['type'], $this->no_value_fields, true );
				}
			);

			foreach ( $fields as $field ) {
				$value = rwmb_get_value( $field['id'], $args, $object_id );
				$value = $this->normalize_value( $field, $value, $args, $object_id );

				// Save option name for setting object type.
				if ( 'setting' === $object_type ) {
					$field['option_name'] = $object_id;
				}

				$values[ $field['id'] ] = apply_filters( '_mb_field_format_value_for_rest', $value, $object_id, $field, $value, $args );
			}

			return $values;
		}

		private function normalize_value( array &$field, $value, $args, $object_id ) {
			$value = $this->normalize_group_value( $field, $value, $args, $object_id );
			$value = $this->normalize_media_value( $field, $value );

			// Update label.
			$field['label'] = $field['name'] ?? '';

			return $value;
		}

		private function normalize_group_value( array &$field, $value, $args, $object_id ) {
			if ( 'group' !== $field['type'] ) {
				return $value;
			}

			if ( ! is_array( $value ) ) {
				$value = [];
			}

			unset( $value['_state'] );

			if ( $field['clone'] ?? false ) {
				foreach ( $value as $index => $value_item ) {
					foreach ( $field['fields'] as $sub_index => $subfield ) {
						if ( empty( $subfield['id'] ) || empty( $value_item[ $subfield['id'] ] ) ) {
							continue;
						}
						$subvalue = $value_item[ $subfield['id'] ];
						$subvalue = $this->normalize_value( $subfield, $value_item[ $subfield['id'] ], $args, $object_id );

						// Update field.
						$field['fields'][ $sub_index ] = $subfield;

						$value_item[ $subfield['id'] ] = apply_filters( '_mb_field_format_value_for_rest', $subvalue, $object_id, $subfield, $subvalue, $args );
					}
					$value[ $index ] = $value_item;
				}
			} else {
				foreach ( $field['fields'] as $index => $subfield ) {
					if ( empty( $subfield['id'] ) || empty( $value[ $subfield['id'] ] ) ) {
						continue;
					}
					$subvalue = $value[ $subfield['id'] ];
					$subvalue = $this->normalize_value( $subfield, $subvalue, $args, $object_id );

					// Update field.
					$field['fields'][ $index ] = $subfield;

					$value[ $subfield['id'] ] = apply_filters( '_mb_field_format_value_for_rest', $subvalue, $object_id, $subfield, $subvalue, $args );
				}
			}

			return $value;
		}

		private function normalize_media_value( array $field, $value ) {
			// Make sure values of file/image fields are always indexed 0, 1, 2, ...
			return is_array( $value ) && in_array( $field['type'], $this->media_fields, true ) ? array_values( $value ) : $value;
		}

		/**
		 * Filter the formatted value for a given field.
		 *
		 * @param mixed      $value_formatted The formatted value.
		 * @param string|int $post_id The post ID of the current object.
		 * @param array      $field The field array.
		 * @param mixed      $raw_value The raw value.
		 * @param array      $args The additional arguments.
		 * @param string     $format The format applied to the field value.
		 *
		 * @return mixed
		 */
		public function format_value_for_rest( $value_formatted, $post_id, $field, $raw_value, $args ) {
			$simple_value_formatted = $this->render_field( $value_formatted, $post_id, $field, $raw_value, $args );

			if ( $field['clone'] ?? false ) {
				$separator = $this->get_clone_field_separator( $field, $post_id, $value_formatted );
				$tags      = $this->get_clone_field_tag( $field, $post_id, $value_formatted );

				$field['clone_separator'] = $separator;
				$field['clone_tags']      = $tags;
			}

			$rest_formatted_value = [
				'simple_value_formatted' => $simple_value_formatted,
				'value_formatted'        => $value_formatted,
				'value'                  => $raw_value,
				'field'                  => $field,
			];

			return apply_filters(
				'meta_field_block_mb_field_format_value_for_rest',
				$rest_formatted_value,
				$post_id
			);
		}

		/**
		 * Get field value for front end by object id and field name.
		 *
		 * @param string     $field_name
		 * @param int/string $object_id
		 * @param string     $object_type
		 *
		 * @return mixed
		 */
		public function get_field_value( $field_name, $object_id, $object_type = '' ) {
			$args  = $object_type ? [ 'object_type' => $object_type ] : '';
			$field = rwmb_get_field_settings( $field_name, $args, $object_id );

			// There is no valid field.
			if ( ! $field ) {
				return [
					'value' => '',
					'field' => [],
				];
			}

			$value = in_array( $field['type'], $this->embeded_fields, true ) ? rwmb_meta( $field_name, $args, $object_id ) : rwmb_get_value( $field_name, $args, $object_id );
			$value = $this->normalize_value( $field, $value, $args, $object_id );

			// Add raw value to the field.
			$field['value'] = $value;

			if ( $field['clone'] ?? false ) {
				$separator = $this->get_clone_field_separator( $field, $object_id, $value );
				$tags      = $this->get_clone_field_tag( $field, $object_id, $value );

				$field['clone_separator'] = $separator;
				$field['clone_tags']      = $tags;
			}

			return [
				'value' => $this->render_field( $value, $object_id, $field, $value, $object_type ),
				'field' => $field,
			];
		}

		/**
		 * Render the field
		 *
		 * @param object $value
		 * @param mixed  $object_id
		 * @param array  $field
		 * @param mixed  $raw_value
		 * @param string $object_type
		 * @return void
		 */
		public function render_field( $value, $object_id, $field, $raw_value, $object_type = '' ) {
			// Get the value for rendering.
			$field_value = $this->render_mb_field( $value, $object_id, $field, $raw_value );

			return apply_filters( 'meta_field_block_get_mb_field', $field_value, $object_id, $field, $raw_value, $object_type );
		}

		/**
		 * Display value for MB fields
		 *
		 * @param mixed $value
		 * @param int   $post_id
		 * @param array $field
		 * @param array $raw_value
		 * @return string
		 */
		public function render_mb_field( $value, $post_id, $field, $raw_value ) {
			$field_value = $value;

			$field_type = $field['type'] ?? '';
			if ( $field_type ) {
				$format_func = 'format_field_' . $field_type;
				if ( is_callable( [ $this, $format_func ] ) ) {
					$field_value = $this->{$format_func}( $value, $field, $post_id, $raw_value );
				} else {
					if ( in_array( $field_type, [ 'date', 'datetime' ], true ) ) {
						$field_value = $this->format_field_datetime( $value, $field, $post_id, $raw_value );
					}
				}

				if ( ( $field['clone'] ?? false ) && ! ( in_array( $field_type, $this->ignored_clone_fields, true ) ) ) {
					if ( $field_value ) {
						if ( ! is_array( $field_value ) ) {
							$field_value = [ $field_value ];
						}

						// Is a numeric array of text.
						if ( array_keys( $field_value ) === range( 0, count( $field_value ) - 1 ) && ! is_array( $field_value[0] ?? [] ) ) {
							$separator             = $this->get_clone_field_separator( $field, $post_id, $field_value );
							[$tag_start, $tag_end] = array_values( $this->get_clone_field_tag( $field, $post_id, $field_value ) );

							$field_value = $tag_start . implode( $tag_end . $separator . $tag_start, $field_value ) . $tag_end;
						}
					} else {
						$field_value = '';
					}
				}

				$field_value = is_array( $field_value ) || is_object( $field_value ) ? '<code><em>' . __( 'This data type is not supported! Please contact the author for help.', 'display-a-meta-field-as-block' ) . '</em></code>' : $field_value;
			}

			return $field_value;
		}

		/**
		 * Get the clone field's separator
		 *
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $field_value
		 * @return string
		 */
		private function get_clone_field_separator( $field, $post_id, $field_value ) {
			$separator = '';
			if ( in_array( $field['type'] ?? '', $this->simple_field_types, true ) ) {
				$separator = ', ';
			}

			return apply_filters( 'meta_field_block_mb_clone_field_item_separator', $separator, $field, $post_id, $field_value );
		}

		/**
		 * Get the clone field's separator
		 *
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $field_value
		 * @return array
		 */
		private function get_clone_field_tag( $field, $post_id, $field_value ) {
			$tag = 'div';
			if ( in_array( $field['type'] ?? '', $this->simple_field_types, true ) ) {
				$tag = 'span';
			}

			$tag = apply_filters( 'meta_field_block_mb_clone_field_item_tag', $tag, $field, $post_id, $field_value );

			if ( $tag ) {
				// Convert to lowercase.
				$tag = strtolower( $tag );
				// Remove all invalid characters (allow: a-z, 0-9, -, _).
				$tag = preg_replace( '/[^a-z0-9\-_]/', '', $tag );

				// Ensure it starts with a letter (remove leading numbers/hyphens).
				$tag = preg_replace( '/^[^a-z]+/', '', $tag );
			}

			$tag_start = '';
			$tag_end   = '';
			if ( $tag ) {
				$tag_start = "<{$tag} class=\"value-repeater-item\">";
				$tag_end   = "</{$tag}>";
			}

			return [
				'tag_start' => $tag_start,
				'tag_end'   => $tag_end,
				'tag'       => $tag,
			];
		}

		/**
		 * Format single image
		 *
		 * @param array|int $image
		 * @param array     $field
		 *
		 * @return string
		 */
		public function format_image( $image, $field ) {
			$image_id = $image;
			if ( is_array( $image ) ) {
				if ( isset( $image['ID'] ) ) {
					$image_id = $image['ID'];
				} else {
					$key = array_key_first( $image );
					if ( isset( $image[ $key ]['ID'] ) ) {
						$image_id = $image[ $key ]['ID'] ?? 0;
					} else {
						$image_id = absint( $image[ $key ] );
					}
				}
			}
			return $image_id ? wp_get_attachment_image( $image_id, $field['image_size'] ?? 'full' ) : '';
		}

		/**
		 * Format image field type
		 *
		 * @param mixed $value
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $raw_value
		 * @return string
		 */
		public function format_field_image( $value, $field, $post_id, $raw_value ) {
			// Set default image size as full.
			$field['image_size'] = 'full';
			return $this->format_field_image_advanced( $value, $field, $post_id, $raw_value );
		}

		/**
		 * Format image upload field type
		 *
		 * @param mixed $value
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $raw_value
		 * @return string
		 */
		public function format_field_image_upload( $value, $field, $post_id, $raw_value ) {
			return $this->format_field_image_advanced( $value, $field, $post_id, $raw_value );
		}

		/**
		 * Get field value and ignore clone if it is enable
		 *
		 * @param mixed $value
		 * @param array $field
		 * @return mixed
		 */
		private function get_field_value_ignore_clone( $value, $field ) {
			// Don't support clonable.
			if ( $field['clone'] ?? false ) {
				$field_value = $value && is_array( $value ) && isset( $value[0] ) ? $value[0] : $value;
			} else {
				$field_value = $value;
			}

			return $field_value;
		}

		/**
		 * Format image advanced field type
		 *
		 * @param mixed $value
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $raw_value
		 * @return string
		 */
		public function format_field_image_advanced( $value, $field, $post_id, $raw_value ) {
			$field_value = $this->get_field_value_ignore_clone( $value, $field );

			if ( ! $field_value || ! is_array( $field_value ) ) {
				return '';
			}

			$field_value = array_map(
				function ( $item ) use ( $field ) {
					return $this->format_image( $item, $field );
				},
				array_values( $field_value )
			);

			if ( count( $field_value ) > 1 ) {
				$field_value = '<figure class="image-list"><figure class="image-item">' . implode( '</figure><figure class="image-item">', $field_value ) . '</figure></figure>';
			} else {
				$field_value = $field_value[0];
			}

			return $field_value;
		}

		/**
		 * Format single image field type
		 *
		 * @param mixed $value
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $raw_value
		 * @return string
		 */
		public function format_field_single_image( $value, $field, $post_id, $raw_value ) {
			$field_value = $this->get_field_value_ignore_clone( $value, $field );

			if ( ! $field_value ) {
				return '';
			}

			return $this->format_image( $field_value, $field );
		}

		/**
		 * Format video field type
		 *
		 * @param mixed $value
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $raw_value
		 * @return string
		 */
		public function format_field_video( $value, $field, $post_id, $raw_value ) {
			$field_value = $this->get_field_value_ignore_clone( $value, $field );

			if ( ! $field_value || ! is_array( $field_value ) ) {
				return '';
			}

			$field_value = array_map(
				function ( $video ) {
					$poster = $video['image']['src'] ?? '';
					if ( $poster === wp_mime_type_icon( $video['ID'] ) ) {
						// Not the default icon.
						$poster = '';
					}
					return "<video controls preload=\"metadata\" src=\"{$video['src']}\" width=\"{$video['dimensions']['width']}\" poster=\"{$poster}\" />";
				},
				array_values( $field_value )
			);

			if ( count( $field_value ) > 1 ) {
				$field_value = '<figure class="video-list"><figure class="video-item">' . implode( '</figure><figure class="video-item">', $field_value ) . '</figure></figure>';
			} else {
				$field_value = $field_value[0];
			}

			return $field_value;
		}

		/**
		 * Format post_object field type
		 *
		 * @param mixed $value
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $raw_value
		 * @return string
		 */
		public function format_field_post( $value, $field, $post_id, $raw_value ) {
			$field_value = $this->get_field_value_ignore_clone( $value, $field );

			$post_array = ! is_array( $field_value ) ? [ $field_value ] : $field_value;

			$post_array_markup = array_filter(
				array_map(
					function ( $post ) {
							return $this->get_post_link( $post );
					},
					$post_array
				)
			);

			if ( count( $post_array_markup ) === 0 ) {
				$field_value = '';
			} else {
				if ( count( $post_array_markup ) > 1 ) {
					$field_value = '<ul><li>' . implode( '</li><li>', $post_array_markup ) . '</li></ul>';
				} else {
					$field_value = $post_array_markup[0];
				}
			}

			return $field_value;
		}

		/**
		 * Render a post as link
		 *
		 * @param int|WP_Post $post
		 * @return string
		 */
		private function get_post_link( $post ) {
			if ( $post ) {
				$url = esc_url( get_permalink( $post ) );
				if ( $url ) {
					return sprintf(
						'<a class="post-link" href="%1$s" rel="bookmark">%2$s</a>',
						$url,
						esc_html( get_the_title( $post ) )
					);
				}
			}

			return '';
		}

		/**
		 * Format taxonomy_advanced field type
		 *
		 * @param mixed $value
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $raw_value
		 * @return string
		 */
		public function format_field_taxonomy_advanced( $value, $field, $post_id, $raw_value ) {
			return $this->format_field_taxonomy( $value, $field, $post_id, $raw_value );
		}

		/**
		 * Format taxonomy field type
		 *
		 * @param mixed $value
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $raw_value
		 * @return string
		 */
		public function format_field_taxonomy( $value, $field, $post_id, $raw_value ) {
			$field_value = $this->get_field_value_ignore_clone( $value, $field );

			if ( ! $field_value ) {
				return '';
			}

			$term_array = ! is_array( $field_value ) ? [ $field_value ] : $field_value;

			$term_array_markup = array_filter(
				array_map(
					function ( $term ) {
						if ( $term ) {
							$term_object = get_term( $term );
							if ( $term_object && $term_object instanceof \WP_Term ) {
								$term_link = get_term_link( $term_object );
								if ( is_wp_error( $term_link ) ) {
									return '';
								}

								return sprintf( '<a class="term-link" href="%1$s">%2$s</a>', $term_link, $term_object->name );
							}
						} else {
							return '';
						}
					},
					$term_array
				)
			);

			if ( count( $term_array_markup ) === 0 ) {
				$field_value = '';
			} else {
				if ( count( $term_array_markup ) > 1 ) {
					$field_value = '<ul><li>' . implode( '</li><li>', $term_array_markup ) . '</li></ul>';
				} else {
					$field_value = $term_array_markup[0];
				}
			}

			return $field_value;
		}

		/**
		 * Format user field type
		 *
		 * @param mixed $value
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $raw_value
		 * @return string
		 */
		public function format_field_user( $value, $field, $post_id, $raw_value ) {
			$field_value = $this->get_field_value_ignore_clone( $value, $field );

			if ( ! $field_value ) {
				return '';
			}

			$user_array = [];
			if ( is_array( $value ) ) {
				if ( isset( $value['display_name'] ) ) {
					// Return format as array and only 1 item.
					$user_array = [ $value ];
				} else {
					$user_array = $value;
				}
			} else {
				$user_array = [ $value ];
			}

			$user_array_markup = array_filter(
				array_map(
					function ( $user ) {
						$user_link         = '';
						$user_id           = 0;
						$user_display_name = '';

						if ( is_object( $user ) ) {
							$user_id           = $user->ID;
							$user_display_name = $user->display_name ?? '';
						} elseif ( is_numeric( $user ) ) {
							$user_object = get_userdata( $user );
							if ( $user_object ) {
								$user_id           = $user_object->ID;
								$user_display_name = $user_object->display_name ?? '';
							}
						} elseif ( is_array( $user ) ) {
							$user_id           = $user['ID'] ?? 0;
							$user_display_name = $user['display_name'] ?? '';
						}

						if ( $user_id && $user_display_name ) {
							return sprintf( '<a class="user-link" href="%1$s">%2$s</a>', get_author_posts_url( $user_id ), $user_display_name );
						}

						return '';
					},
					is_array( $user_array ) ? $user_array : []
				)
			);

			if ( count( $user_array_markup ) === 0 ) {
				$field_value = '';
			} else {
				if ( count( $user_array_markup ) > 1 ) {
					$field_value = '<ul><li>' . implode( '</li><li>', $user_array_markup ) . '</li></ul>';
				} else {
					$field_value = $user_array_markup[0];
				}
			}

			return $field_value;
		}

		/**
		 * Format datetime fields
		 *
		 * @param mixed $value
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $raw_value
		 * @return mixed
		 */
		public function format_field_datetime( $value, $field, $post_id, $raw_value ) {
			$field_value = $value;

			if ( ! $field_value ) {
				return '';
			}

			// Get format.
			$format = $this->get_datetime_format( $field, $post_id, $value );

			if ( $field['clone'] ?? false ) {
				if ( ! is_array( $value ) ) {
					$value = [ $value ];
				}

				$field_value = array_map(
					function ( $item ) use ( $field, $format ) {
						return $this->format_datetime( $item, $field, $format );
					},
					$value
				);
			} else {
				$field_value = $this->format_datetime( $value, $field, $format );
			}

			return $field_value;
		}

		/**
		 * Get datetime format
		 *
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $value
		 * @return string
		 */
		private function get_datetime_format( $field, $post_id, $value ) {
			$field_type  = $field['type'] ?? '';
			$date_format = get_option( 'date_format' );

			$format = $date_format;
			if ( 'datetime' === $field_type ) {
				$time_format = get_option( 'time_format' );
				$format      = "{$date_format} {$time_format}";
			}

			return apply_filters( 'meta_field_block_mb_field_datetime_format', $format, $field['id'] ?? '', $field, $post_id, $value );
		}

		/**
		 * Format a datetime value
		 *
		 * @param mixed  $value
		 * @param array  $field
		 * @param string $format
		 * @return string
		 */
		private function format_datetime( $value, $field, $format ) {
			$field_type = $field['type'] ?? '';

			$date = false;
			if ( $field['timestamp'] ?? false ) {
				if ( is_numeric( $value ) ) {
					$date = new \DateTime( "@{$value}" );
				}
			} else {
				$save_format = $field['save_format'] ?? '';
				if ( ! $save_format ) {
					$save_format = 'Y-m-d';
					if ( 'datetime' === $field_type ) {
						$save_format = 'Y-m-d H:i';
					}
				}
				$date = \DateTime::createFromFormat( $save_format, $value );
			}

			if ( $date ) {
				return $date->format( $format );
			}

			return $value;
		}

		/**
		 * Format checkbox fields
		 *
		 * @param mixed $value
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $raw_value
		 * @return string
		 */
		public function format_field_checkbox( $value, $field, $post_id, $raw_value ) {
			return $this->format_field_true_false( $value, $field, $post_id, $raw_value );
		}

		/**
		 * Format switch fields
		 *
		 * @param mixed $value
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $raw_value
		 * @return string
		 */
		public function format_field_switch( $value, $field, $post_id, $raw_value ) {
			return $this->format_field_true_false( $value, $field, $post_id, $raw_value );
		}

		/**
		 * Format true_false fields
		 *
		 * @param mixed $value
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $raw_value
		 * @return string
		 */
		public function format_field_true_false( $value, $field, $post_id, $raw_value ) {
			$field_value = $this->get_field_value_ignore_clone( $value, $field );

			$on_text  = $field['on_label'] ?? '';
			$off_text = $field['off_label'] ?? '';

			if ( empty( $on_text ) && empty( $off_text ) ) {
				$on_text  = _x( 'Yes', 'The display text for the "true" value of the true_false Meta Box field type', 'display-a-meta-field-as-block' );
				$off_text = _x( 'No', 'The display text for the "false" value of the true_false Meta Box field type', 'display-a-meta-field-as-block' );
			}

			$on_text  = apply_filters( 'meta_field_block_true_false_on_text', $on_text, $field['id'] ?? '', $field, $post_id, $value );
			$off_text = apply_filters( 'meta_field_block_true_false_off_text', $off_text, $field['id'] ?? '', $field, $post_id, $value );

			return $field_value ? $on_text : $off_text;
		}

		/**
		 * Format checkbox list fields
		 *
		 * @param mixed $value
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $raw_value
		 * @return string
		 */
		public function format_field_checkbox_list( $value, $field, $post_id, $raw_value ) {
			$field_value = $this->get_field_value_ignore_clone( $value, $field );

			if ( ! $field_value ) {
				return '';
			}

			// Get options.
			$options = $field['options'] ?? [];

			// Whether to display label instead of value.
			$display_label = $this->choice_item_display_label( $field, $post_id, $value );

			// Item separator.
			$separator = $this->choice_item_separator( $field, $post_id, $value );

			if ( is_array( $field_value ) ) {
				$refine_value = array_filter(
					array_map(
						function ( $item ) use ( $options, $display_label ) {
							$return_value = $item;

							if ( $display_label && $item && isset( $options[ $item ] ) ) {
								$return_value = $options[ $item ];
							}

							return $return_value;
						},
						$field_value
					)
				);

				if ( $refine_value ) {
					$field_value = '<span class="value-item">' . implode( '</span>' . $separator . '<span class="value-item">', $refine_value ) . '</span>';
				}
			}

			return $field_value;
		}

		/**
		 * Whether to display label instead of value for choice fields
		 *
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $value
		 * @return boolean
		 */
		private function choice_item_display_label( $field, $post_id, $value ) {
			return apply_filters( 'meta_field_block_mb_field_choice_item_display_label', false, $field['id'] ?? '', $field, $post_id, $value );
		}

		/**
		 * The item separator for choice fields
		 *
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $value
		 * @return boolean
		 */
		private function choice_item_separator( $field, $post_id, $value ) {
			return apply_filters( 'meta_field_block_mb_field_choice_item_separator', ', ', $field['id'] ?? '', $field, $post_id, $value );
		}

		/**
		 * Format radio fields
		 *
		 * @param mixed $value
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $raw_value
		 * @return string
		 */
		public function format_field_radio( $value, $field, $post_id, $raw_value ) {
			$field_value = $this->get_field_value_ignore_clone( $value, $field );

			if ( ! $field_value ) {
				return '';
			}

			// Get options.
			$options = $field['options'] ?? [];

			// Whether to display label instead of value.
			$display_label = $this->choice_item_display_label( $field, $post_id, $value );

			if ( $display_label && isset( $options[ $field_value ] ) ) {
				$field_value = $options[ $field_value ];
			}

			return $field_value;
		}

		/**
		 * Format button group fields
		 *
		 * @param mixed $value
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $raw_value
		 * @return string
		 */
		public function format_field_button_group( $value, $field, $post_id, $raw_value ) {
			return $this->format_field_select( $value, $field, $post_id, $raw_value );
		}

		/**
		 * Format select advanced fields
		 *
		 * @param mixed $value
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $raw_value
		 * @return string
		 */
		public function format_field_select_advanced( $value, $field, $post_id, $raw_value ) {
			return $this->format_field_select( $value, $field, $post_id, $raw_value );
		}

		/**
		 * Format autocomplete fields
		 *
		 * @param mixed $value
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $raw_value
		 * @return string
		 */
		public function format_field_autocomplete( $value, $field, $post_id, $raw_value ) {
			$field['multiple'] = true;
			return $this->format_field_select( $value, $field, $post_id, $raw_value );
		}

		/**
		 * Format image_select fields
		 *
		 * @param mixed $value
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $raw_value
		 * @return string
		 */
		public function format_field_image_select( $value, $field, $post_id, $raw_value ) {
			return $this->format_field_select( $value, $field, $post_id, $raw_value );
		}

		/**
		 * Format select fields
		 *
		 * @param mixed $value
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $raw_value
		 * @return string
		 */
		public function format_field_select( $value, $field, $post_id, $raw_value ) {
			$field_value = $this->get_field_value_ignore_clone( $value, $field );

			if ( ! $field_value ) {
				return '';
			}

			// Get options.
			$options = $field['options'] ?? [];

			// Whether to display label instead of value.
			$display_label = $this->choice_item_display_label( $field, $post_id, $value );

			// Item separator.
			$separator = $this->choice_item_separator( $field, $post_id, $value );

			if ( ( $field['multiple'] ?? false ) ) {
				if ( ! is_array( $field_value ) ) {
					$field_value = [ $field_value ];
				}

				$refine_value = array_filter(
					array_map(
						function ( $item ) use ( $options, $display_label ) {
							$return_value = $item;

							if ( $display_label && $item && isset( $options[ $item ] ) ) {
								$return_value = $options[ $item ];
							}

							return $return_value;
						},
						$field_value
					)
				);

				if ( $refine_value ) {
					$field_value = '<span class="value-item">' . implode( '</span>' . $separator . '<span class="value-item">', $refine_value ) . '</span>';
				}
			} else {
				if ( $display_label && isset( $options[ $field_value ] ) ) {
					$field_value = $options[ $field_value ];
				}
			}

			return $field_value;
		}

		/**
		 * Format textarea
		 *
		 * @param mixed $value
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $raw_value
		 * @return string
		 */
		public function format_field_textarea( $value, $field, $post_id, $raw_value ) {
			if ( ! $value ) {
				return '';
			}

			$format_type = apply_filters( 'meta_field_block_mb_field_textarea_format_type', '', $field['id'] ?? '', $field, $post_id, $value ); // wpautop, br.

			if ( $field['clone'] ?? false ) {
				if ( ! is_array( $value ) ) {
					$value = [ $value ];
				}

				$field_value = array_map(
					function ( $item ) use ( $format_type ) {
						return $this->format_text( $item, $format_type );
					},
					$value
				);
			} else {
				$field_value = $this->format_text( $value, $format_type );
			}

			return $field_value;
		}

		/**
		 * Format text
		 *
		 * @param string $text
		 * @param string $format_type
		 * @return string
		 */
		private function format_text( $text, $format_type ) {
			$formatted_text = $text;
			if ( 'wpautop' === $format_type ) {
				$formatted_text = wpautop( $text );
			} elseif ( 'br' === $format_type ) {
				$formatted_text = nl2br( $text );
			}

			return $formatted_text;
		}

		/**
		 * Format wysiwyg field
		 *
		 * @param mixed $value
		 * @param array $field
		 * @param int   $post_id
		 * @param mixed $raw_value
		 * @return string
		 */
		public function format_field_wysiwyg( $value, $field, $post_id, $raw_value ) {
			if ( ! $value ) {
				return '';
			}

			if ( $field['clone'] ?? false ) {
				if ( ! is_array( $value ) ) {
					$value = [ $value ];
				}

				$field_value = array_map(
					function ( $item ) {
						return $this->format_wysiwyg( $item );
					},
					$value
				);
			} else {
				$field_value = $this->format_wysiwyg( $value );
			}

			return $field_value;
		}

		/**
		 * Format wysiwyg text
		 *
		 * @param string $text
		 * @return string
		 */
		private function format_wysiwyg( $text ) {
			global $wp_embed;
			return do_shortcode( wpautop( $wp_embed->autoembed( $text ) ) );
		}
	}
endif;
