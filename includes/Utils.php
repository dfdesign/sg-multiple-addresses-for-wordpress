<?php
namespace SGMA;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Utils{
	
	public static function validate_vat( string $country, string $vat ):bool {
		$curl = curl_init( 'https://ec.europa.eu/taxation_customs/vies/rest-api/ms/' . urlencode( $country ) . '/vat/' . urlencode( $vat ) );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 10 ); // Timeout after 10 seconds
		
		$vatValidationRequest = curl_exec( $curl );
		
		if ( $vatValidationRequest === false ) {
			throw new Exception( 'cURL Error: ' . curl_error( $curl ) );
		}
		
		curl_close( $curl );
		
		$decoded = json_decode( $vatValidationRequest, true );

		return isset($decoded['isValid']) && $decoded['isValid'] ? 1 : false;
	}
	
	public static function render_address_form( int $user_id ):string {
		add_thickbox();
		$woo_countries = new \WC_Countries();
		$countries = $woo_countries->get_countries();
		
		ob_start();
		?>
		<span class="thickbox button button-primary">Add new billing address</span>

		<div id="modal-window-id" style="display:none;">
			<div id="address-form">
				<div class="radio">
					<span class="sgma-adress-box-title">Set as default</span>
					<input type="radio" id="sgma_addreess_default_input" name="sgma_addreess_default_input">
				</div>
				<div>
					<label class="mylabel" for="addreess_identifier">Address Identifier</label>
					<input id="sgma_addreess_identifier_input" type="text" class="inputsize" name="addreess_identifier">
				</div>

				<div>
					<label class="mylabel" for="first_name">First Name</label>
					<input id="sgma_first_name_input" type="text" class="inputsize" name="first_name">
				</div>

				<div>
					<label class="mylabel" for="last_name">Last Name</label>
					<input id="sgma_last_name_input" type="text" class="inputsize" name="last_name">
				</div>

				<div>
					<label class="mylabel" for="company_name">Conpany Name</label>
					<input id="sgma_company_name_input" type="text" class="inputsize" name="company_name">
				</div>

				<div>
					<label class="mylabel" for="vat">VAT</label>
					<input id="sgma_vat_input" type="text" class="inputsize" name="vat">
				</div>

				<select id="sgma_country_codes">
					<?php
					foreach( $woo_countries->countries as $country_key => $country_name){
						?>
						<option value="<?php echo esc_attr( $country_key ); ?>"><?php echo esc_attr( $country_name ); ?></option>
						<?php
					}
					?>
				</select>
				
				<div id="sgma_states">
					<label class="mylabel" for="state">State</label>
					<input id="sgma_state_input" type="text" class="inputsize" name="state">
				</div>

				<div>
					<label class="mylabel" for="street_address">Street Address</label>
					<input id="sgma_street_address_input" type="text" class="inputsize" name="street_address">
				</div>

				<div>
					<label class="mylabel" for="town">Town</label>
					<input id="sgma_town_input" type="text" class="inputsize" name="town">
				</div>

				<div>
					<label class="mylabel" for="postal_code">Postal Code</label>
					<input id="sgma_postal_code_input" type="text" class="inputsize" name="postal_code">
				</div>

				<div>
					<label class="mylabel" for="phone">Phone</label>
					<input id="sgma_phone_input" type="text" class="inputsize" name="phone">
				</div>

				<div>
					<label class="mylabel" for="email">Email</label>
					<input id="sgma_email_input" type="text" class="inputsize" name="email">
				</div>
				<input id="sgma_address_id" type="hidden" class="inputsize" name="email">
			</div>
			<div id="sgma-form-message"></div>
			<input id="sgma_current_user_id_input" type="hidden" value="<?php echo esc_attr( $user_id ); ?>">
			<button id="submit_address" class="button button-primary">Add address</button>
		</div>
		<?php
		$content = ob_get_clean();
		return $content;
	}
	
	public static function render_addresses_boxes( array $user_addresses, int $user_id ):string {
		$default_billing_address = trim( get_user_meta( $user_id, 'sgma_default_billing_address', true ) );

		ob_start();
		?>
		<br>
		<div class="sgma-address-box-container">
			<?php if ( ! empty( $user_addresses ) ) : ?>
				<?php foreach ( $user_addresses as $address ) : 
					$address_id = esc_attr( $address['id'] );
					$is_default = $default_billing_address === $user_id . '-' . $address_id;
				?>
				<div class="sgma-address-box <?php echo $is_default ? 'sgma-address-default' : ''; ?>">
					<?php if ( $is_default ) : ?>
						<span class="sgma-address-box-container-top-title">Default</span>
					<?php endif; ?>
					<div><span class="sgma-address-value"># <?php echo esc_html( $user_id . '-' . $address_id ); ?></span></div>

					<?php
					// List of address fields to display
					$fields = [
						'Identifier' => 'address_identifier',
						'First Name' => 'first_name',
						'Last Name' => 'last_name',
						'Company Name' => 'company_name',
						'VAT' => 'vat',
						'Country' => 'country',
						'State' => 'state',
						'Street Address' => 'street_address',
						'Town' => 'town',
						'Phone' => 'phone',
						'Email' => 'email',
					];

					foreach ( $fields as $label => $key ) {
						if ( isset( $address[ $key ] ) ) {
							?>
							<div>
								<span class="sgma-address-box-title"><?php echo esc_html( $label ); ?></span>
								<span class="sgma-address-value"><?php echo esc_html( $address[ $key ] ); ?></span>
							</div>
							<?php
						}
					}
					?>

					<span data-address-id="<?php echo esc_attr( $address_id ); ?>" data-user-id="<?php echo esc_attr( $user_id ); ?>" class="thickbox button button-primary">Edit</span>
					<span data-address-id="<?php echo esc_attr( $address_id ); ?>" data-user-id="<?php echo esc_attr( $user_id ); ?>" class="delete-address button button-primary">Delete</span>
				</div>
				<?php endforeach; ?>
			<?php else : ?>
				<p>No addresses found.</p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	
	public static function get_next_address_id_number( array $user_addresses ):int {
		$latest_address_number = 0;

		$latest_address_number = array_reduce( $user_addresses, function( $carry, $address ) {
			return isset( $address['id'] ) && (int) $address['id'] > $carry ? (int) $address['id'] : $carry;
		}, $latest_address_number );

		return ++$latest_address_number;
	}
	
}