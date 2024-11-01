<?php
/*
Plugin Name: Upload to Envira via Gravity Forms
Description: Attaches photos posted in a Gravity Form to a selected Envira Gallery.
Version: 1.1
Author: Rhino Group
Author URI: http://www.rhinogroup.com
License: GPL-2.0+
*/

// Make sure Gravity Forms is active and already loaded.
if ( class_exists( "GFForms" ) && class_exists( "Envira_Gallery" ) ) {

	GFForms::include_addon_framework();

	class GFEnviraIntegrator extends GFAddOn {

		// The following class variables are used by the Framework.
		// They are defined in GFAddOn and should be overridden.
		// The version number is used for example during add-on upgrades.
		protected $_version = "1.0";
		// The Framework will display an appropriate message on the plugins page if necessary
		protected $_min_gravityforms_version = "1.8.7";
		// A short, lowercase, URL-safe unique identifier for the add-on.
		// This will be used for storing options, filters, actions, URLs and text-domain localization.
		protected $_slug = "suma-gf-envira-addon";
		// Relative path to the plugin from the plugins folder.
		protected $_path = "upload-to-envira-via-gravity-forms/addon.php";
		// Full path to the plugin.
		protected $_full_path = __FILE__;
		// Title of the plugin to be used on the settings page, form settings and plugins page.
		protected $_title = "Upload to Envira via Gravity Forms";
		// Short version of the plugin title to be used on menus and other places where a less verbose string is useful.
		protected $_short_title = "Envira Gallery";

		/**
		 * The init_frontend() function is called during the WordPress init hook but only on the front-end.
		 * Runs after WordPress has finished loading but before any headers are sent.
		 * GFAddOn::init_frontend() handles the loading of the scripts and styles
		 * so don't forget to call parent::init_frontend() unless you want to handle the script loading differently.
		 */
		public function init_frontend() {
			parent::init_frontend();
			add_action( 'gform_after_submission', array( $this, 'after_submission' ), 10, 2 );
		}

		/**
		 * This is the target for the gform_after_submission action hook.
		 * Executed at the end of the submission process (after form validation, notification, and entry creation).
		 *
		 * @param array $entry The Entry Object containing the submitted form values
		 * @param array $form The Form Object containing all the form and field settings
		 *
		 * @return bool
		 */
		public function after_submission( $entry, $form ) {

			include_once( ABSPATH . 'wp-admin/includes/image.php' );

			// Use the helper method to get the form settings and make sure it's enabled for this form
			$form_settings = $this->get_form_settings( $form );

			if ( ! $form_settings || ! $form_settings["is_enabled"] || empty( $form_settings["gallery_id"] ) ) {
				return;
			}

			// Retrieve the field IDs for the photo name and caption
			$title_field_id   = $form_settings['title_field_id'];
			$caption_field_id = $form_settings['caption_field_id'];

			$entry_title = '';
			$entry_descr = '';
			$photo_urls  = [];

			// Loop each form field and find the file uploads
			foreach ( $form['fields'] as $field ) {

				if ( $field->type != 'fileupload' ) {

					if ( $field->id == $title_field_id ) {

						// Load data from title entry
						$entry_title = $this->get_entry_data( $entry, $field );

					} else if ( $field->id == $caption_field_id ) {

						// Load data from description entry
						$entry_descr = $this->get_entry_data( $entry, $field );
					}
				} else {

					// Handle any file upload fields
					$urls = json_decode( $entry[ $field->id ] );
					if ( $urls == null )
						$urls = $entry[ $field->id ];
					if ( is_array( $urls ) ) {
						foreach ($urls as $url) {
							$photo_urls[] = $url;
						}
					} else if ( ! empty( $urls ) ) {
						$photo_urls[] = $urls;
					}
				}
			}

			// Get Envira photo gallery id from GF setting
			$gallery_id = $form_settings["gallery_id"];

			try {
				foreach ( $photo_urls as $photo_url ) {

					if ( empty( $photo_url ) )
						continue;

					// Copy file to standard upload directory
					$wp_upload_dir = wp_upload_dir();
					$url_parts     = pathinfo( $photo_url );
					$url_path      = parse_url( $photo_url )['path'];
					$filename      = strtolower( $url_parts['filename'] ) . '.' . $url_parts['extension'];
					$file_path     = trailingslashit( $wp_upload_dir['path'] ) . $filename;

					copy( ABSPATH . substr( $url_path, 1 ), $file_path );

					$name = trailingslashit( $wp_upload_dir['url'] ) . $filename;

					$attachment = array(
						'guid'           => $name,
						'post_mime_type' => wp_check_filetype( $file_path )['type'],
						'post_parent'    => $gallery_id,
						'post_title'     => sanitize_text_field( $entry_title ),
						'post_content'   => sanitize_text_field( $entry_descr ),
						'post_status'    => 'inherit'
					);

					// Create attachment out of photo
					/***
					 * NOTE: It's important to use the file's relative path here ($file_path), otherwise image
					 * sizes won't be generated
					 */
					$attachment_id = wp_insert_attachment( $attachment, $file_path, $gallery_id );

					// Generate the metadata for the attachment, and update the database record.
					$attach_data = wp_generate_attachment_metadata( $attachment_id, $file_path );

					wp_update_attachment_metadata( $attachment_id, $attach_data );

					// Set post meta to show that this image is attached to one or more Envira galleries.
					$has_gallery = get_post_meta( $attachment_id, '_eg_has_gallery', true );
					if ( empty( $has_gallery ) ) {
						$has_gallery = [];
					}

					$has_gallery[] = $gallery_id;
					update_post_meta( $attachment_id, '_eg_has_gallery', $has_gallery );

					// Retrieve gallery item list data
					$env_data = get_post_meta( $gallery_id, '_eg_in_gallery', true );
					$env_data = is_array( $env_data ) ? $env_data : [];
					// Push our new photo element into the array
					array_push( $env_data, $attachment_id );
					// Update the DB with our revised gallery array
					update_post_meta( $gallery_id, '_eg_in_gallery', $env_data );

					// Retrieve the gallery data from DB
					$env_gallery_data = get_post_meta( $gallery_id, '_eg_gallery_data', true );
					if ( ! array_key_exists( 'gallery', $env_gallery_data ) ) {
						$env_gallery_data['gallery'] = [];
					}
					// Build a new photo element that will be added to the gallery
					$info = array(
						'status'  => $form_settings['publish_status'],
						'src'     => addslashes( $name ),
						'title'   => addslashes( $entry_title ),
						'link'    => addslashes( $name ),
						'caption' => addslashes( $entry_descr ),
						'alt'     => addslashes( $entry_title ),
						'thumb'   => ''
					);

					// If the photo should be published right away, set the id
					if ( $info['status'] == 'active' ) {
						$info['id'] = $attachment_id;
					}

					// Add our photo element to the start of the gallery array
					$env_gallery_data['gallery'] = array( $attachment_id => $info ) + $env_gallery_data['gallery'];
					// Update the DB with our revised gallery data
					update_post_meta( $gallery_id, '_eg_gallery_data', $env_gallery_data );

					do_action( 'envira_gallery_ajax_load_image', $attachment_id, $gallery_id );

					// Flush Envira's cache for this gallery, otherwise the thumbnail
					// won't show up in the front-end until the gallery is saved.
					if ( $info['status'] == 'active' ) {
						envira_flush_gallery_caches( $gallery_id );
					}

				}
			} catch ( Exception $ex ) {
				error_log( $ex->getMessage() );
			}
		}

		/**
		 * Override the form_settings_field() function and return the configuration for the Form Settings.
		 * Updating is handled by the Framework.
		 *
		 * @param array $form The Form object
		 *
		 * @return array
		 */
		public function form_settings_fields( $form ) {

			$fields    = $this->get_form_fields_as_options( $form );
			$galleries = $this->get_envira_galleries_as_options();

			if ( sizeof( $galleries ) < 2 ) {

				//No galleries have been created

				return array(
					array(
						"title"  => __( "Envira Settings", $this->get_slug() ),
						"fields" => array(
							array(
								"name"  => "invalid",
								"label" => __( "No Envira galleries have been configured", $this->get_slug() ),
								"type"  => "radio",
								"choices" => array()
							)
						)
					)
				);
			}

			return array(
				array(
					"title"  => __( "Envira Settings", $this->get_slug() ),
					"fields" => array(
						array(
							"name"    => "is_enabled",
							"tooltip" => __( "Activate this setting to post any file uploads to an Envira Gallery." ),
							"label"   => __( "Save to Envira", $this->get_slug() ),
							"onclick" => "jQuery(this).closest('form').submit();",
							// refresh the page so show/hide the settings below. Settings are not saved until the save button is pressed.
							"type"    => "checkbox",
							"choices" => array(
								array(
									"label" => __( "Enable saving to Envira", $this->_slug ),
									"name"  => "is_enabled"
								)
							)
						),
						array(
							"name"    => "gallery_id",
							"class"   => "small",
							"tooltip" => __( "Select the Envira gallery you would like uploads saved into.", $this->get_slug() ),
							"label"   => __( "Envira Gallery", $this->get_slug() ),
							"type"    => "select",
							"choices" => $galleries
						),
						array(
							'label'   => __( 'Publish Status', $this->get_slug() ),
							'type'    => 'select',
							'name'    => 'publish_status',
							'tooltip' => __( 'Choose whether to leave in Draft status or to Publish immediately', $this->get_slug() ),
							'choices' => array(
								array(
									'label' => __( 'Draft', $this->get_slug() ),
									'value' => 'pending',
								),
								array(
									'label' => __( 'Published', $this->get_slug() ),
									'value' => 'active',
								)
							),
						),
						array(
							"name"    => "title_field_id",
							"class"   => "small",
							"tooltip" => __( "Select the GF field to use for a photo title.", $this->get_slug() ),
							"label"   => __( "Title Field", $this->get_slug() ),
							"type"    => "select",
							"choices" => $fields
						),
						array(
							"name"    => "caption_field_id",
							"class"   => "small",
							"tooltip" => __( "Select the GF field to use for a photo caption.", $this->get_slug() ),
							"label"   => __( "Caption Field", $this->get_slug() ),
							"type"    => "select",
							"choices" => $fields
						)
					)
				)
			);
		}

		/***
		 * Builds an array of Envira Gallery name-value pairs
		 *
		 * @return array
		 */
		private function get_envira_galleries_as_options() {

			$options   = [];
			$galleries = envira_get_galleries( false );

			$options[] = [
				'value' => '',
				'label' => 'None'
			];

			if ( ! empty( $galleries ) ) {
				foreach ( $galleries as $gallery ) {

					$post      = get_post( $gallery['id'] );
					$options[] = [
						'value' => $post->ID,
						'label' => $post->post_title
					];
				}
			}

			return $options;
		}

		/***
		 * Builds an array of name-value pairs from the Gravity Form fields
		 *
		 * @param array $form The Form Object containing all the form and field settings
		 *
		 * @return array
		 */
		private function get_form_fields_as_options( $form ) {

			$options             = [];
			$allowed_field_types = [ 'text', 'textarea', 'hidden' ];

			$options[] = [
				'value' => '',
				'label' => 'None'
			];

			foreach ( $form['fields'] as $field ) {
				if ( in_array( $field->type, $allowed_field_types ) ) {
					$options[] = [
						'value' => $field->id,
						'label' => $field->label
					];
				}
			}

			return $options;
		}

		/***
		 * Retrieves user-entered value from form entries
		 *
		 * @param array $entry User entered data
		 * @param GF_Field $field Gravity Forms field
		 *
		 * @return string
		 */
		private function get_entry_data( $entry, $field ) {

			$value = '';

			if ( $field->type == 'name' ) {

				foreach ( $field->inputs as $input ) {

					if ( strtolower( $input['label'] ) == 'first' ) {
						$value .= $entry[ $input['id'] ] . ' ';
					} else if ( strtolower( $input['label'] ) == 'last' ) {
						$value .= $entry[ $input['id'] ];
					}
				}
			} else if ( $field->type == 'text' || $field->type == 'textarea' ) {
				$value = $entry[ $field->id ];
			}

			return $value;
		}

	}

	new GFEnviraIntegrator();
}

?>