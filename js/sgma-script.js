jQuery(document).ready(function () {
    // Cache frequently used elements
    const $sgmaCountryCodes = jQuery('#sgma_country_codes');
    const $sgmaStates = jQuery('#sgma_states');
    const $sgmaAddressBoxContainer = jQuery('.sgma-adress-box-container');
    
    // Handle submit address click
    jQuery('#submit_address').on('click', async function () {
        const user_id = jQuery('#sgma_current_user_id_input').val();
        console.log(user_id);

        try {
            await storeAddress();
            const response = await getAddressesHtml(user_id);
            $sgmaAddressBoxContainer.html(response.data);
        } catch (error) {
            console.error('Error processing address:', error);
        }
    });

    // Handle country code change and fetch states
    $sgmaCountryCodes.on('change', function () {
        const country_code = this.value;

        fetchStatesByCountry(country_code);
    });

    // Open modal on .thickbox click
    jQuery(document).on('click', '.thickbox', async function () {
        const address_id = jQuery(this).data('address-id');
        const user_id = jQuery(this).data('user-id');

        if (address_id !== undefined) {
            try {
                const address = await getAddress(address_id, user_id);
                const codes = await getStateCodes(address.country);
                openAddressModal('Update address', address, codes);
            } catch (error) {
                console.error('Error opening address modal:', error);
            }
        } else {
            openAddressModal('Add address', {});
        }
    });

    // Handle delete address click
    jQuery(document).on('click', '.delete-address', async function () {
        const address_id = jQuery(this).data('address-id');
        const user_id = jQuery(this).data('user-id');

        try {
            await deleteAddress(address_id, user_id);
            const response = await getAddressesHtml(user_id);
            $sgmaAddressBoxContainer.html(response.data);
        } catch (error) {
            console.error('Error deleting address:', error);
        }
    });

    // Helper function to fetch states by country
    async function fetchStatesByCountry(country_code) {
        try {
            const response = await jQuery.post(sgma_wp.ajax_url, {
                action: 'sgma_get_states_by_country',
                nonce: sgma_wp.nonce,
                contentType: 'application/json; charset=utf-8',
                dataType: 'json',
                country_code
            });

            if (response.success && Object.keys(response.data).length > 0) {
                updateStateDropdown(response.data);
            } else {
                setStateInputField();
            }
        } catch (error) {
            console.error('Error fetching states:', error);
        }
    }

    // Update state dropdown
    function updateStateDropdown(states) {
        $sgmaStates.html('<label class="mylabel" for="state">State</label><select><option value="">Choose state</option></select>');
        const $select = $sgmaStates.find('select');
        $select.empty();

        Object.keys(states).forEach((key, index) => {
            const isSelected = index === 0 ? 'selected' : '';
            $select.append(`<option value="${key}" ${isSelected}>${states[key]}</option>`);
        });

        $select.on('change', function () {
            $select.find('option').each(function () {
                jQuery(this).prop('selected', jQuery(this).val() === $select.val());
            });
        });
    }

    // Fallback to state input field if no states available
    function setStateInputField() {
        $sgmaStates.html('<label class="mylabel" for="state">State</label><input id="sgma_state_input" type="text" class="inputsize" name="state">');
    }

    // Function to open modal and populate fields
    function openAddressModal(title, address = {}, stateCodes = {}) {
        tb_show(title, '#TB_inline?inlineId=modal-window-id');

        jQuery('#sgma_first_name_input').val(address.first_name || '');
        jQuery('#sgma_last_name_input').val(address.last_name || '');
        jQuery('#sgma_company_name_input').val(address.company_name || '');
        jQuery('#sgma_vat_input').val(address.vat || '');
        jQuery('#sgma_street_address_input').val(address.street_address || '');
        jQuery('#sgma_town_input').val(address.town || '');
        jQuery('#sgma_postal_code_input').val(address.postal_code || '');
        jQuery('#sgma_phone_input').val(address.phone || '');
        jQuery('#sgma_email_input').val(address.email || '');
        jQuery('#sgma_address_id').val(address.id || '');

        if (!stateCodes.data) {
            jQuery('#sgma_state_input').val(address.state || '');
        } else {
            updateStateDropdown(stateCodes.data);
            jQuery('#sgma_states select').val(address.state || '');
        }
    }

    // Async function definitions
    async function storeAddress() {
        const data = {
            action: 'sgma_store_address',
            nonce: sgma_wp.nonce,
            address_id: jQuery('#sgma_address_id').val(),
            address_identifier: jQuery('#sgma_addreess_identifier_input').val(),
            first_name: jQuery('#sgma_first_name_input').val(),
            last_name: jQuery('#sgma_last_name_input').val(),
            company_name: jQuery('#sgma_company_name_input').val(),
            vat: jQuery('#sgma_vat_input').val(),
            country_code: jQuery('#sgma_country_codes').find(':selected').val(),
            state_code: jQuery('#sgma_states select').val() || jQuery('#sgma_state_input').val(),
            street_address: jQuery('#sgma_street_address_input').val(),
            town: jQuery('#sgma_town_input').val(),
            postal_code: jQuery('#sgma_postal_code_input').val(),
            phone: jQuery('#sgma_phone_input').val(),
            email: jQuery('#sgma_email_input').val(),
            user_id: jQuery('#sgma_current_user_id_input').val(),
            default_address: jQuery('#sgma_addreess_default_input').prop('checked')
        };

        const response = await jQuery.post(sgma_wp.ajax_url, data);
        if (!response.success) {
            jQuery('#sgma-form-message').text(response.data);
            throw new Error(response.data);
        }

        console.log(response);
        tb_remove();
    }

    async function getAddress(address_id, user_id) {
        const response = await jQuery.post(sgma_wp.ajax_url, {
            action: 'sgma_get_address',
            nonce: sgma_wp.nonce,
            address_id,
            user_id
        });

        if (!response.data) {
            throw new Error('Error getting address');
        }

        return response.data[0];
    }

    async function deleteAddress(address_id, user_id) {
        const response = await jQuery.post(sgma_wp.ajax_url, {
            action: 'sgma_delete_address',
            nonce: sgma_wp.nonce,
            address_id,
            user_id
        });

        if (!response.data) {
            throw new Error('Error deleting address');
        }

        return response.data;
    }

    async function getAddressesHtml(user_id) {
        const response = await jQuery.post(sgma_wp.ajax_url, {
            action: 'sgma_get_addresses_html',
            nonce: sgma_wp.nonce,
            user_id
        });

        if (!response.data) {
            throw new Error('Error getting addresses');
        }

        return response.data;
    }

    async function getStateCodes(country_code) {
        const response = await jQuery.post(sgma_wp.ajax_url, {
            action: 'sgma_get_states_by_country',
            nonce: sgma_wp.nonce,
            country_code
        });

        return response.data || {};
    }
});
