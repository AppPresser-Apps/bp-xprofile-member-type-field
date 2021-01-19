<?php
/*
Plugin Name: Buddypress xProfile Member Type Field
Description: Add a Member Type select field type to Extended Profiles in BuddyPress.
Version: 1.0.0
Author: modemlooper, AppPresser
Author URI: https://apppresser.com
Plugin URI: https://apppresser.com
*/


// set our version here - bumping this will cause CSS and JS files to be reloaded.
define( 'BP_XPROFILE_MEMBERTYPE_FIELD_VERSION', '1.0.0' );

/**
 * Buddypress xProfile Member Type Field Class.
 *
 * A class that encapsulates plugin functionality.
 *
 * @since 1.0.0
 */
class BP_XProfile_Member_Type_Field {



	/**
	 * Initialises this object.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// use translation files
		$this->enable_translation();

		if ( function_exists( 'bp_xprofile_get_field_types' ) ) {

			// include class
			require_once 'buddypress-xprofile-membertype-field-class.php';

			// register with BP the 2.0 way...
			add_filter( 'bp_xprofile_get_field_types', array( $this, 'add_field_type' ) );

			// we need to parse the edit value in BP 2.0
			add_filter( 'bp_get_the_profile_field_edit_value', array( $this, 'get_field_value' ), 30, 3 );

		}

		// show our field type in read mode after all BuddyPress filters
		add_filter( 'bp_get_the_profile_field_value', array( $this, 'get_field_value' ), 30, 3 );

		// filter for those who use xprofile_get_field_data instead of get_field_value
		add_filter( 'xprofile_get_field_data', array( $this, 'get_field_data' ), 15, 3 );

		add_action( 'xprofile_data_after_save', array( $this, 'update_member_type' ), 15 );

		add_action( 'xprofile_fields_saved_field', array( $this, 'save_field_meta' ) );
		// add_action( 'xprofile_fields_saved_field', array( $this, 'save_multi_field_meta' ) );

		add_filter( 'bp_xprofile_field_get_children', array( $this, 'xprofile_get_groups' ), 10, 3 );

		// add BP Profile Search compatibility
		$this->bps_compat();

	}

	/**
	 * Get Profiel group data filter. Adds member types as options on member type field.
	 */
	public function xprofile_get_groups( $children, $for_editing, $obj ) {

		if ( 'membertype' === $obj->type ) {

			$registered_member_types = bp_get_member_types( null, 'object' );

			$children = array();

			foreach ( $registered_member_types as $member_type ) {

				$child = (object) array(
					'id'                => $member_type->db_id,
					'group_id'          => $obj->group_id,
					'parent_id'         => $obj->id,
					'type'              => 'option',
					'name'              => $member_type->labels['singular_name'],
					'description'       => '',
					'is_required'       => 0,
					'is_default_option' => 0,
					'field_order'       => 0,
					'option_order'      => 1,
					'order_by'          => '',
					'can_delete'        => 1,
				);

				$children[] = $child;

			}
		}

		return $children;

	}

		/**
		 * Save the text when the field is saved
		 *
		 * @param BP_XProfile_Field $field xprofile field.
		 */
	public function save_field_meta( $field ) {

		if ( 'membertype' !== $field->type ) {
			return;
		}

	}


	/**
	 * Update the member type of a user when member type field is updated
	 *
	 * @param Object $data_field Xprofile data object.
	 */
	public function update_member_type( $data_field ) {

		$field = xprofile_get_field( $data_field->field_id );

		// we only need to worry about member type field.
		if ( 'membertype' !== $field->type ) {
			return;
		}

		$user_id     = $data_field->user_id;
		$member_type = maybe_unserialize( $data_field->value );

		// validate too?
		if ( empty( $member_type ) ) {

			// remove all member type?
			bp_set_member_type( $user_id, '' );
			return;
		}
		// Is this members type registered and active?, Then update.
		if ( bp_get_member_type_object( $member_type ) ) {
			bp_set_member_type( $user_id, $member_type );
		}

	}



	/**
	 * Load translation files.
	 *
	 * A good reference on how to implement translation in WordPress:
	 * http://ottopress.com/2012/internationalization-youre-probably-doing-it-wrong/
	 *
	 * @since 1.0.0
	 */
	public function enable_translation() {

		// not used, as there are no translations as yet.
		load_plugin_textdomain(
			// unique name.
			'buddypress-xprofile-membertype-field',
			// deprecated argument.
			false,
			// relative path to directory containing translation files.
			dirname( plugin_basename( __FILE__ ) ) . '/languages/'
		);

	}



	// ##########################################################################



	/**
	 * Add details of our xProfile field type. (BuddyPress 2.0)
	 *
	 * @since 1.0.0
	 *
	 * @param array Key/value pairs (field type => class name).
	 * @return array Key/value pairs (field type => class name).
	 */
	function add_field_type( $fields ) {

		// make sure we get an array.
		if ( is_array( $fields ) ) {

			// add our field to the array.
			$fields['membertype'] = 'BP_XProfile_Field_Type_Member_Type';

		} else {

			// create array with our item.
			$fields = array( 'membertype' => 'BP_XProfile_Field_Type_Member_Type' );

		}

		// --<
		return $fields;

	}



	// ##########################################################################



	/**
	 * Register our field type.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field_types The existing array of field types
	 * @return array $field_types The modified array of field types
	 */
	function register_field_type( $field_types ) {

		// make sure we get an array.
		if ( is_array( $field_types ) ) {

			// append our item.
			$field_types[] = 'membertype';

		} else {

			// set array with our item.
			$field_types = array( 'membertype' );

		}

		// --<
		return $field_types;

	}


	/**
	 * Show our field type in read mode.
	 *
	 * @since 1.0.0
	 *
	 * @param string  $value The existing value of the field
	 * @param string  $type The type of field
	 * @param integer $user_id The numeric ID of the WordPress user
	 * @return string $value The modified value of the field
	 */
	public function get_field_value( $value = '', $type = '', $user_id = '' ) {

		// is it our field type?
		if ( 'membertype' === $type ) {

			// we want the raw data, unfiltered.
			global $field;
			$value = $field->data->value;

			// apply content filter.
			$value = apply_filters( 'bp_xprofile_field_type_membertype_content', stripslashes( $value ) );

			// return filtered value.
			return apply_filters( 'bp_xprofile_field_type_membertype_value', $value );

		}

		// fallback.
		return $value;

	}



	/**
	 * Filter for those who use xprofile_get_field_data instead of get_field_value.
	 *
	 * @since 1.0.0
	 *
	 * @param string  $value The existing value of the field
	 * @param string  $type The type of field
	 * @param integer $user_id The numeric ID of the WordPress user
	 * @return string $value The modified value of the field
	 */
	function get_field_data( $value = '', $field_id = '', $user_id = '' ) {

		// check we get a field ID.
		if ( $field_id === '' ) {
			return $value; }

		// get field object.
		$field = new BP_XProfile_Field( $field_id );

		// is it ours?
		if ( $field->type == 'membertype' ) {

			// apply content filter.
			$value = apply_filters( 'bp_xprofile_field_type_membertype_content', stripslashes( $value ) );

			// return filtered value.
			return apply_filters( 'bp_xprofile_field_type_membertype_value', $value );

		}

		// fallback.
		return $value;

	}


	/**
	 * BP Profile Search compatibility.
	 *
	 * @see http://dontdream.it/bp-profile-search/custom-profile-field-types/
	 *
	 * @since 1.0.0
	 */
	public function bps_compat() {

		// bail unless BP Profile Search present.
		if ( ! defined( 'BPS_VERSION' ) ) {
			return;
		}

		// add filters
		add_filter( 'bps_field_validation_type', array( $this, 'bps_field_compat' ), 10, 2 );
		add_filter( 'bps_field_html_type', array( $this, 'bps_field_compat' ), 10, 2 );
		add_filter( 'bps_field_criteria_type', array( $this, 'bps_field_compat' ), 10, 2 );
		add_filter( 'bps_field_query_type', array( $this, 'bps_field_compat' ), 10, 2 );

	}



	/**
	 * BP Profile Search field compatibility.
	 *
	 * @since 1.0.0
	 *
	 * @param string $field_type The existing xProfile field type
	 * @param object $field The xProfile field object
	 * @return string $field_type The modified xProfile field type
	 */
	public function bps_field_compat( $field_type, $field ) {

		// cast our field type as 'textbox'.
		switch ( $field->type ) {
			case 'membertype':
				$field_type = 'select';
				break;
		}

		// --<
		return $field_type;

	}



} // class ends



/**
 * Initialise our plugin after BuddyPress initialises.
 *
 * @since 1.0.0
 */
function bp_xprofile_member_type_field() {

	// make global in scope.
	global $bp_xprofile_member_type_field;

	// init plugin.
	$bp_xprofile_member_type_field = new BP_XProfile_Member_Type_Field();

}

// add action for plugin loaded.
add_action( 'bp_loaded', 'bp_xprofile_member_type_field', 999 );
