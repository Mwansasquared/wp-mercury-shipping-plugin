jQuery(document).ready(function ($) {

    let countriesDropdown = $('#billing_country'),
        citiesField = $('#billing_city');    


    // Handle country change event
    countriesDropdown.on('change', function () {
        let selectedCountryId = $(this).val();
        
        // handleSelectedLocation(selectedCountryId);

    });

    citiesField.on('change', function () {
        let selectedCity = $(this).val();
        handleSelectedLocation(selectedCity);

    });

    function handleSelectedLocation(selectedCity) {

        let selectedCountryName = $('#billing_country option:selected').text();

        let mercurySelectedCountry = custom_location_data.countries.find(country => country.country_name === selectedCountryName);

        //bug:: some countries are not being found due to different naming e.g in WC (United States (US)) while in Mercury list (United States)

        if (mercurySelectedCountry) {

            let mercurySelectedCountryId = mercurySelectedCountry.country_id;

            $.ajax({
                type: 'POST',
                url: custom_location_data.ajax_url,
                data: {
                    action: 'get_cities_from_database',
                    nonce: custom_location_data.nonce,
                },
                success: function (response) {

                    let mercurySelectedCity = response.data.cities.find(city => city.city_name === selectedCity);

                    if(mercurySelectedCity) {
                        let mercurySelectedCityId = mercurySelectedCity.city_id;

                        callExternalAPI(mercurySelectedCountryId, mercurySelectedCityId);
                    }
                },
                error: function (error) {
                    console.error(error.responseText);
                },
            });

        } else {

            console.error('Country ID not found for: ', selectedCountryName);
        }

        
    }

    function callExternalAPI(countryId, cityId) {

        console.log('countryId In API call: ', countryId);
        console.log('cityId In API call: ', cityId);

        $.ajax({
            type: 'POST',
            url: custom_location_data.ajax_url,
            data: {
                action: 'ajax_get_mercury_shipping_fee',
                nonce: custom_location_data.nonce,
                country_id: countryId,
                city_id: cityId,
            },
            success: function (apiResponse) {

                        let shippingFee = apiResponse.data;
                console.log('shipping fee: ', shippingFee)
                    },
            error: function (error) {
                        console.error(error.responseText);
                    },
        });
    }

});
