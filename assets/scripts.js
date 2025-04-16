jQuery(function ($) {
    if (typeof myPluginData !== 'undefined' && myPluginData.communes) {
        const comunasPorRegion = myPluginData.communes;
        console.log(comunasPorRegion);


        function updateCities(selectElement, region) {
            var comunaSelect = $(selectElement);
            comunaSelect.empty().append('<option value="">Seleccione una Comuna</option>');

            if (comunasPorRegion[region]) {
                $.each(comunasPorRegion[region], function (key, value) {
                    console.log(key, value);
                    comunaSelect.append('<option value="' + key + '">' + key + '</option>');
                });
            }
        }

        function getPostalCode(city) {
            for (const regionCode in comunasPorRegion) {
                const comunas = comunasPorRegion[regionCode];
                if (comunas[city]) {
                    console.log(comunas[city]);
                    return comunas[city].postal_code; // Retorna el código postal
                }
            }
            return ''; // Retorna vacío si no se encuentra la comuna
        }

        $('#billing_city').change(function () {
            const comunaSeleccionada = $(this).val();
            const codigoPostal = getPostalCode(comunaSeleccionada);
            console.log(codigoPostal,comunaSeleccionada);
            $('#billing_postcode').val(codigoPostal);
        });

        // Manejo de cambios en el select de la región para la dirección de facturación
        $('select#billing_state').change(function () {
            var regionSeleccionada = $(this).val();
            updateCities('select#billing_city', regionSeleccionada);
        });

        // Manejo de cambios en el select de la región para la dirección de envío
        $('select#shipping_state').change(function () {
            var regionSeleccionada = $(this).val();
            updateCities('select#shipping_city', regionSeleccionada);
        });

        // Cargar comunas al inicio si ya hay una región seleccionada en la dirección de facturación
        var regionInicialBilling = $('select#billing_state').val();
        if (regionInicialBilling) {
            updateCities('select#billing_city', regionInicialBilling);
        }

        // Cargar comunas al inicio si ya hay una región seleccionada en la dirección de envío
        var regionInicialShipping = $('select#shipping_state').val();
        if (regionInicialShipping) {
            updateCities('select#shipping_city', regionInicialShipping);
        }
    } else {
        console.error('No se encontraron datos de comunas.');
    }    // Función para actualizar las comunas en un select

});
