<?php

/**
 * Selectbox xprofile field type.
 *
 * @since 1.0.0
 */
class BP_XProfile_Field_Type_Member_Type extends BP_XProfile_Field_Type {

	/**
	 * Constructor for the selectbox field type.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct();

		$this->category = _x( 'Multi Fields', 'xprofile field type category', 'buddypress' );
		$this->name     = _x( 'Member Type Select Box', 'xprofile field type', 'buddypress' );

		$this->supports_multiple_defaults = false;
		$this->accepts_null_value         = true;
		$this->supports_options           = false;

		$this->set_format( '', 'replace' );

		/**
		 * Fires inside __construct() method for BP_XProfile_Field_Type_Member_Type class.
		 *
		 * @since 1.0.0
		 *
		 * @param BP_XProfile_Field_Type_Member_Type $this Current instance of
		 *                                               the field type select box.
		 */
		do_action( 'bp_xprofile_field_type_member_type', $this );
	}

	/**
	 * Is it a valid member type?
	 *
	 * @param mixed $val field value to test.
	 *
	 * @return boolean
	 */
	public function is_valid( $val ) {

		// if a registered member type, mark as valid.
		if ( empty( $val ) || bp_get_member_type_object( str_replace( ' ', '-', strtolower( $val ) ) ) ) {
			return true;
		}

		return false;

	}

	/**
	 * Output the edit field HTML for this field type.
	 *
	 * Must be used inside the {@link bp_profile_fields()} template loop.
	 *
	 * @since 1.0.0
	 *
	 * @param array $raw_properties Optional key/value array of
	 *                              {@link http://dev.w3.org/html5/markup/select.html permitted attributes}
	 *                              that you want to add.
	 */
	public function edit_field_html( array $raw_properties = array() ) {

		// User_id is a special optional parameter that we pass to
		// {@link bp_the_profile_field_options()}.
		if ( isset( $raw_properties['user_id'] ) ) {
			$user_id = (int) $raw_properties['user_id'];
			unset( $raw_properties['user_id'] );
		} else {
			$user_id = bp_displayed_user_id();
		} ?>

		<legend id="<?php bp_the_profile_field_input_name(); ?>-1">
			<?php bp_the_profile_field_name(); ?>
			<?php bp_the_profile_field_required_label(); ?>
		</legend>

		<?php

		/** This action is documented in bp-xprofile/bp-xprofile-classes */
		do_action( bp_get_the_profile_field_errors_action() );
		?>

		<select <?php echo $this->get_edit_field_html_elements( $raw_properties ); ?> aria-labelledby="<?php bp_the_profile_field_input_name(); ?>-1" aria-describedby="<?php bp_the_profile_field_input_name(); ?>-3">
			<?php bp_the_profile_field_options( array( 'user_id' => $user_id ) ); ?>
		</select>

		<?php if ( bp_get_the_profile_field_description() ) : ?>
			<p class="description" id="<?php bp_the_profile_field_input_name(); ?>-3"><?php bp_the_profile_field_description(); ?></p>
		<?php endif; ?>

		<?php
	}

	/**
	 * Output the edit field options HTML for this field type.
	 *
	 * BuddyPress considers a field's "options" to be, for example, the items in a selectbox.
	 * These are stored separately in the database, and their templating is handled separately.
	 *
	 * This templating is separate from {@link BP_XProfile_Field_Type::edit_field_html()} because
	 * it's also used in the wp-admin screens when creating new fields, and for backwards compatibility.
	 *
	 * Must be used inside the {@link bp_profile_fields()} template loop.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Optional. The arguments passed to {@link bp_the_profile_field_options()}.
	 */
	public function edit_field_options_html( array $args = array() ) {
		$original_option_values = maybe_unserialize( BP_XProfile_ProfileData::get_value_byid( $this->field_obj->id, $args['user_id'] ) );
		$default                = bp_xprofile_get_meta( $this->field_obj->id, 'field', 'bpmtp_field_default_value', true );

		if ( ! empty( $_POST[ 'field_' . $this->field_obj->id ] ) ) {
			$option_values = (array) $_POST[ 'field_' . $this->field_obj->id ];
			$option_values = array_map( 'sanitize_text_field', $option_values );
		} else {

			if ( $original_option_values === '' && $default ) {
				$option_values = (array) $default;
			} else {
				$option_values = (array) $original_option_values;
			}
		}

		$display_type = bp_xprofile_get_meta( $this->field_obj->id, 'field', 'bpmtp_field_display_type', true );

		// member types list as array.
		$options     = self::get_member_types();
		$restriction = bp_xprofile_get_meta( $this->field_obj->id, 'field', 'bpmtp_field_restriction', true );

		if ( 'restricted' === $restriction ) {
			$new_options    = array();
			$selected_types = bp_xprofile_get_meta( $this->field_obj->id, 'field', 'bpmtp_field_selected_types', true );

			if ( empty( $selected_types ) ) {
				$selected_types = array();
			}

			foreach ( $options as $key => $label ) {

				if ( in_array( $key, $selected_types ) ) {
					$new_options[ $key ] = $label;
				}
			}

			$options = $new_options;
		}

		if ( 'radio' === $display_type ) {
			$this->_edit_options_html_radio( $option_values, $options );
		} else {
			$this->_edit_options_html( $option_values, $options );
		}

	}

	/**
	 * @param $option_values
	 * @param $options
	 */
	protected function _edit_options_html( $option_values, $options ) {
		$selected = '';
		if ( empty( $option_values ) || in_array( 'none', $option_values ) ) {
			$selected = ' selected="selected"';
		}

		$html = '<option value="" ' . $selected . ' >----' . /* translators: no option picked in select box */
				'</option>';

		echo $html;

		foreach ( $options as $member_type => $label ) {

			$selected = '';
			// Run the allowed option name through the before_save filter, so we'll be sure to get a match.
			$allowed_options = xprofile_sanitize_data_value_before_save( $member_type, false, false );

			// First, check to see whether the user-entered value matches.
			if ( in_array( $allowed_options, (array) $option_values ) ) {
				$selected = ' selected="selected"';
			}

			echo apply_filters( 'bp_get_the_profile_field_options_member_type', '<option' . $selected . ' value="' . esc_attr( stripslashes( $member_type ) ) . '">' . $label . '</option>', $member_type, $this->field_obj->id, $selected );

		}
	}

	protected function _edit_options_html_radio( $option_values, $options ) {

		foreach ( $options as $member_type => $label ) {

			$selected = '';
			// Run the allowed option name through the before_save filter, so we'll be sure to get a match.
			$allowed_options = xprofile_sanitize_data_value_before_save( $member_type, false, false );
			// First, check to see whether the user-entered value matches.
			if ( in_array( $allowed_options, (array) $option_values ) ) {
				$selected = ' checked="checked"';
			}

			$new_html = sprintf(
				'<label for="%3$s"><input %1$s type="radio" name="%2$s" id="%3$s" value="%4$s">%5$s</label>',
				$selected,
				esc_attr( "field_{$this->field_obj->id}" ),
				esc_attr( "option_{$member_type}" ),
				esc_attr( stripslashes( $member_type ) ),
				esc_html( stripslashes( $label ) )
			);

			echo apply_filters( 'bp_get_the_profile_field_options_member_type', $new_html, $member_type, $this->field_obj->id, $selected );

		}

	}

	/**
	 * Output HTML for this field type on the wp-admin Profile Fields screen.
	 *
	 * Must be used inside the {@link bp_profile_fields()} template loop.
	 *
	 * @since 1.0.0
	 *
	 * @param array $raw_properties Optional key/value array of permitted attributes that you want to add.
	 */
	public function admin_field_html( array $raw_properties = array() ) {
		?>

		<label for="<?php bp_the_profile_field_input_name(); ?>" class="screen-reader-text">
		<?php
		/* translators: accessibility text */
		esc_html_e( 'Select', 'buddypress' );
		?>
		</label>
		<select <?php echo $this->get_edit_field_html_elements( $raw_properties ); ?>>
			<?php bp_the_profile_field_options(); ?>
		</select>

		<?php
	}

	/**
	 * This method usually outputs HTML for this field type's children options
	 * on the wp-admin Profile Fields "Add Field" and "Edit Field" screens, but
	 * for this field type, we don't want it, so it's stubbed out.
	 *
	 * @since 1.0.0
	 *
	 * @param BP_XProfile_Field $current_field The current profile field on the add/edit screen.
	 * @param string            $control_type Optional. HTML input type used to render the current field's child options.
	 */
	public function admin_new_field_html( BP_XProfile_Field $current_field, $control_type = '' ) {}


	/**
	 * Get member types as associative array.
	 *
	 * @staticvar array $member_types
	 * @return array
	 */
	private static function get_member_types() {

		static $member_types = null;

		if ( isset( $member_types ) ) {
			return $member_types;
		}

		$registered_member_types = bp_get_member_types( null, 'object' );

		if ( empty( $registered_member_types ) ) {
			$member_types = $registered_member_types;

			return $member_types;
		}

		foreach ( $registered_member_types as $type_name => $member_type_object ) {
			$member_types[ $type_name ] = $member_type_object->labels['singular_name'];
		}

		return apply_filters( 'bp_xprofile_member_type_field_allowed_types', $member_types, $registered_member_types );
	}


}
