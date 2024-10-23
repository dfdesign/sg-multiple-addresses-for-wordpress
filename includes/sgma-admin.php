<?php
namespace SGMA;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use \SGMA\Utils as Utils;

class SGMA_Admin{
	public function __construct(){
		// Backend and frontend address display
		add_action( 'edit_user_profile', [ $this, 'show_addresses_on_backend'], 100 );
		add_action( 'woocommerce_after_edit_account_address_form', [ $this, 'show_addresses_on_frontend'], 100 );

		// Enqueue scripts and styles for both frontend and backend
		add_action( 'admin_enqueue_scripts', [ $this, 'sgma_enqueue_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'sgma_enqueue_assets' ] );

		// AJAX actions for logged-in and non-logged-in users
		$ajax_actions = [
			'sgma_store_address' => 'sgma_save_address',
			'sgma_get_address' => 'sgma_get_address',
			'sgma_delete_address' => 'sgma_delete_address',
			'sgma_get_states_by_country' => 'sgma_get_states_by_country',
			'sgma_get_addresses_html' => 'sgma_get_addresses_html',
		];

		foreach ($ajax_actions as $action => $callback) {
			add_action( "wp_ajax_$action", [ $this, $callback ] );
			add_action( "wp_ajax_nopriv_$action", [ $this, $callback ] );
		}
	}
	
	public function show_addresses_on_frontend():void {
		echo $this->get_addresses_html( get_current_user_id() );
	}
	
	public function show_addresses_on_backend( $user ):void {
		echo $this->get_addresses_html( $user->ID );
	}
	
	public function get_addresses_html( int $user_id ):string {
		$user_addresses = get_user_meta( $user_id, 'sgma_addresses', true);
		
		ob_start();

		echo Utils::render_address_form( $user_id);
		echo Utils::render_addresses_boxes( $user_addresses, $user_id );
		
		return ob_get_clean();
	}
	
	public function sgma_enqueue_assets():void {
		// Enqueue scripts
		wp_register_script( 'sgma-script', plugin_dir_url(__FILE__) . '../js/sgma-script.js', ['jquery'], false, true );
		wp_enqueue_script( 'sgma-script' );
		wp_enqueue_script( 'thickbox' );

		// Enqueue styles
		wp_register_style( 'sgma-style', plugin_dir_url(__FILE__) . '../css/sgma-style.css' );
		wp_enqueue_style( 'sgma-style' );
		wp_enqueue_style( 'thickbox' );

		// Localize script with nonce and ajax URL
		wp_localize_script( 'sgma-script', 'sgma_wp', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'sgma_nonce' ),
		]);
	}
	
	public function sgma_enqueue_styles():void {
        wp_register_style( 'sgma-style', plugin_dir_url(__FILE__) . '../css/sgma-style.css' );
        wp_enqueue_style( 'sgma-style' );
    }
	
	public function sgma_get_address(){

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'sgma_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// Sanitize and validate inputs
		$address_id = isset( $_POST['address_id'] ) ? sanitize_text_field( trim( $_POST['address_id'] ) ) : '';
		$user_id = isset( $_POST['user_id'] ) ? sanitize_text_field( trim( $_POST['user_id'] ) ) : '';

		if ( empty( $address_id ) || empty( $user_id ) ) {
			wp_send_json_error( 'Invalid address ID or user ID', 400 );
		}

		// Check permissions
		if ( get_current_user_id() != $user_id && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'You have no permission to access that data.', 403 );
		}

		// Retrieve user addresses
		$user_addresses = get_user_meta( $user_id, 'sgma_addresses', true );

		if ( empty( $user_addresses ) || ! is_array( $user_addresses ) ) {
			wp_send_json_error( 'No addresses found for the user.', 404 );
		}

		// Find the address with the matching ID
		$address = array_filter( $user_addresses, function( $adr ) use ( $address_id ) {
			return isset( $adr['id'] ) && $adr['id'] == $address_id;
		});

		if ( empty( $address ) ) {
			wp_send_json_error( 'Address not found.', 404 );
		}

		// Return the matched address
		wp_send_json_success( array_values( $address ), 200 );
		
	}
	
	public function sgma_delete_address(){
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'sgma_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce', 400 );
		}

		// Sanitize and validate inputs
		$address_id = isset( $_POST['address_id'] ) ? sanitize_text_field( trim( $_POST['address_id'] ) ) : '';
		$user_id = isset( $_POST['user_id'] ) ? sanitize_text_field( trim( $_POST['user_id'] ) ) : '';

		if ( empty( $address_id ) || empty( $user_id ) ) {
			wp_send_json_error( 'Invalid address ID or user ID', 400 );
		}

		// Retrieve user addresses
		$user_addresses = get_user_meta( $user_id, 'sgma_addresses', true );

		// Check if user has permissions or is the owner of the address
		if ( get_current_user_id() != $user_id && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'You have no permission to access this data.', 403 );
		}

		// Check if user has addresses
		if ( empty( $user_addresses ) || ! is_array( $user_addresses ) ) {
			wp_send_json_error( 'No addresses found for the user.', 404 );
		}

		// Filter out the address with the matching ID
		$filtered_addresses = array_filter( $user_addresses, function( $address ) use ( $address_id ) {
			return isset( $address['id'] ) && $address['id'] != $address_id;
		});

		// If no address is deleted, send an error response
		if ( count( $filtered_addresses ) === count( $user_addresses ) ) {
			wp_send_json_error( 'Address not found.', 404 );
		}

		// Update the user's addresses
		update_user_meta( $user_id, 'sgma_addresses', $filtered_addresses );

		// Send success response with updated addresses
		wp_send_json_success( array_values( $filtered_addresses ), 200 );
	}
	
	public function sgma_save_address():void {

		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'sgma_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce', 400 );
			return;
		}

		// Sanitize inputs
		$id = sanitize_text_field(trim($_POST['address_id']));
		$order_hash = sanitize_text_field(trim($_POST['order_hash']));
		$address_identifier = sanitize_text_field(trim($_POST['address_identifier']));
		$first_name = sanitize_text_field(trim($_POST['first_name']));
		$last_name = sanitize_text_field(trim($_POST['last_name']));
		$company_name = sanitize_text_field(trim($_POST['company_name']));
		$vat = sanitize_text_field(trim($_POST['vat']));
		$country_code = sanitize_text_field(trim($_POST['country_code']));
		$state_code = sanitize_text_field(trim($_POST['state_code']));
		$street_address = sanitize_text_field(trim($_POST['street_address']));
		$town = sanitize_text_field(trim($_POST['town']));
		$postal_code = sanitize_text_field(trim($_POST['postal_code']));
		$phone = sanitize_text_field(trim($_POST['phone']));
		$email = sanitize_email(trim($_POST['email']));
		$user_id = sanitize_text_field(trim($_POST['user_id']));
		$default_address = sanitize_text_field(trim($_POST['default_address']));

		// Validate mandatory fields
		if ( empty( $first_name ) ) {
			wp_send_json_error( 'First name cannot be empty', 400 );
			return;
		}
		if ( empty( $last_name ) ) {
			wp_send_json_error( 'Last name cannot be empty', 400 );
			return;
		}

		// VAT Validation
		if ( ! empty( $vat ) ) {
			$eu_countries_list = ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'];

			if ( in_array( $country_code, $eu_countries_list ) ) {
				if ( ! Utils::validate_vat( $country_code, $vat ) ) {
					wp_send_json_error( 'Invalid VAT number', 400 );
					return;
				}
			}
		}

		// Check user permissions
		if ( get_current_user_id() != $user_id && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'You have no permission to access this data.', 403 );
			return;
		}

		// Retrieve user addresses
		$user_addresses = get_user_meta( $user_id, 'sgma_addresses', true );
		if ( ! is_array( $user_addresses ) ) {
			$user_addresses = [];
		}

		if ( $id ) { // If updating an address
			foreach ( $user_addresses as $key => $a ) {
				if ( $a['id'] == $id ) {
					$user_addresses[$key] = array_merge($user_addresses[$key], [
						'address_identifier' => $address_identifier,
						'first_name' => $first_name,
						'last_name' => $last_name,
						'company_name' => $company_name,
						'vat' => $vat,
						'country' => $country_code,
						'state' => $state_code,
						'street_address' => $street_address,
						'town' => $town,
						'postal_code' => $postal_code,
						'phone' => $phone,
						'email' => $email
					]);
				}
			}

			// Set default address if applicable
			if ( $default_address == 'true' ) {
				update_user_meta( $user_id, 'sgma_default_billing_address', $user_id . '-' . $id );
				$this->update_user_default_address_meta( $user_id, $first_name, $last_name, $town, $postal_code, $email, $phone, $company_name, $street_address, $street_address, $country_code, $state_code );
			}
		} else { // If creating a new address
			$latest_address_number = Utils::get_next_address_id_number( $user_addresses );

			$new_address = [
				'id' => $latest_address_number,
				'address_identifier' => $address_identifier,
				'first_name' => $first_name,
				'last_name' => $last_name,
				'company_name' => $company_name,
				'vat' => $vat,
				'country' => $country_code,
				'state' => $state_code,
				'street_address' => $street_address,
				'town' => $town,
				'postal_code' => $postal_code,
				'phone' => $phone,
				'email' => $email
			];

			$user_addresses[] = $new_address;
		}

		// Update the user's addresses
		update_user_meta( $user_id, 'sgma_addresses', $user_addresses );

		wp_send_json_success( 'Address successfully saved', 200 );
		return;
	}
	
	public function sgma_get_states_by_country():void {

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'sgma_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce', 400 );
			return;
		}

		$country_code = isset( $_POST['country_code'] ) ? sanitize_text_field( trim( $_POST['country_code'] ) ) : '';
		if ( empty( $country_code ) ) {
			wp_send_json_error( 'Country code is required', 400 );
			return;
		}

		$countries_obj = new \WC_Countries();
		$states = $countries_obj->get_states( $country_code );

		wp_send_json_success( $states, 200 );
		return;
	}
	
	public function sgma_get_addresses_html( int $user_id ):void {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'sgma_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce', 400 );
			return;
        }
		
		$user_id = isset( $_POST['user_id'] ) ? sanitize_text_field( trim( $_POST['user_id'] ) ) : '';
		
		if ( empty( $user_id ) || ! get_user_by( 'id', $user_id ) ) {
			wp_send_json_error( 'Invalid user ID', 400 );
			return;
		}
		
		$user_addresses = get_user_meta( $user_id, 'sgma_addresses', true);
		$addresses_html = Utils::render_addresses_boxes( $user_addresses, $user_id );
		
		wp_send_json_success( $addresses_html, 200 );
		return;
	}
	
	public function update_user_default_address_meta( int $user_id, string $fname, string $lname, string $city, string $zip, string $address_email, string $phone, string $company, string $address1, string $address2, string $country, string $state ):void {
		$billing_data = [
			'first_name' => $fname,
			'last_name' => $lname,
			'billing_city' => $city,
			'billing_postcode'  => $zip,
			'billing_email' => $address_email,
			'billing_phone' => $phone,
			'billing_company' => $company,
			'billing_address_1'	=> $address1,
			'billing_address_2'	=> $address2,
			'billing_country' => $country,
			'billing_state' => $state,
			'billing_first_name' => $fname,
			'billing_last_name'	=> $lname
		];
		foreach( $billing_data as $billing_meta_key => $billing_meta_value ) {
			update_user_meta( $user_id, $billing_meta_key, $billing_meta_value );
		}
	}
}