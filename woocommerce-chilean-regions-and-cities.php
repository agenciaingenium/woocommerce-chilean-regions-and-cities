<?php
/*
Plugin Name: Regiones y Ciudades de Chile para WooCommerce
Plugin URI: https://github.com/agenciaingenium/woocommerce-chilean-regions-and-cities
Description:       Añade listas desplegables de regiones y ciudades de Chile en el checkout de WooCommerce.
Version:           1.0.0
Requires at least: 5.6
Requires PHP: 7.4
Requires Plugins: woocommerce
Author:            Clevers Devs
Author URI: https://clevers.dev
License:           GPL v2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: regiones-ciudades-chile
Domain Path: /languages
Update URI:https://clevers.dev/plugins/regiones-ciudades-chile
Version: 1.2.3
Tested up to: 6.6
GitHub Plugin URI: agenciaingenium/woocommerce-chilean-regions-and-cities
Primary Branch: main
Release Asset: true


 * WC requires at least: 9.5
 * WC tested up to: 9.5
 * WC-Order-Storage: custom
 *
*/

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Automattic\WooCommerce\Utilities\OrderUtil;


if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}


define('MULTICOURIER_VERSION', '1.0.0');
define('MULTICOURIER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MULTICOURIER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MULTICOURIER_API_URL', 'https://app.multicouriers.cl/api/');

//define( 'MULTICOURIER_API_URL', 'https://multicouriers.test/api/' );

class CCFW_Logger
{
    public static function log($message, $level = 'info')
    {
        if (!function_exists('wc_get_logger')) {
            return;
        }
        $logger = wc_get_logger();
        $context = array('source' => 'woocommerce-chilean-regions-and-cities', 'version' => MULTICOURIER_VERSION);;
        $message = is_array($message) || is_object($message) ? wp_json_encode($message) : $message;
        $logger->log($level, $message, $context);
    }
}

if (!class_exists('MC_Shipping')) {
    class MC_Shipping
    {

        private static $instance = null;
        private string $id;
        private ?string $title;
        private ?string $method_description;
        private string $enabled;
        /**
         * @var mixed|null
         */
        private mixed $cities;

        public function __construct()
        {
            $this->id = 'your_shipping_method'; // ID del método de envío
            $this->title = __('Tarifa de Envío Personalizada', 'woocommerce');
            $this->method_description = __('Tarifas de envío según la comuna seleccionada.', 'woocommerce');
            $this->enabled = "yes"; // Habilitado por defecto
            $this->init();
        }

        function init(): void
        {
            add_action('before_woocommerce_init', [$this, 'declareCompatibility']);
            add_filter('woocommerce_checkout_fields', [$this, 'modifyCheckoutFields']);
            add_filter('woocommerce_default_address_fields', [$this, 'wc_reorder_region_field']);
            add_filter('woocommerce_get_country_locale', [$this, 'wc_change_state_label_locale']);
            add_filter('default_checkout_billing_state', '__return_empty_string');
            add_filter('default_checkout_shipping_state', '__return_empty_string');
            add_action('wp_enqueue_scripts', [$this, 'enqueue_my_script']);
        }


        public function enqueue_my_script()
        {
            error_log('hola');
            // Encola tu script
            wp_enqueue_script(
                'my-custom-script',
                plugin_dir_url(__FILE__) . 'assets/scripts.js',
                array('jquery'),
                '1.0.0',
                true
            );

            // Obtiene las comunas
            $communes = $this->load_communes();

            // Pasa los datos al script
            wp_localize_script(
                'my-custom-script',
                'myPluginData',
                array(
                    'communes' => $communes
                )
            );
        }

        public function declareCompatibility(): void
        {
            if (class_exists(FeaturesUtil::class)) {
                FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        }

        public static function getInstance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        // Modificar campos del checkout
        public function modifyCheckoutFields($fields)
        {

            $fields['billing']['billing_city'] = [
                'type' => 'select',
                'label' => 'Comuna',
                'placeholder' => 'Seleccione una Comuna',
                'required' => true,
                'class' => ['form-row-wide'],
                'options' => ['' => 'Seleccione una Comuna']
            ];
            $fields['shipping']['shipping_city'] = [
                'type' => 'select',
                'label' => 'Comuna',
                'placeholder' => 'Seleccione una Comuna',
                'required' => true,
                'class' => ['form-row-wide'],
                'options' => ['' => 'Seleccione una Comuna']
            ];

            return $fields;
        }

        function wc_reorder_region_field($address_fields)
        {
            $address_fields['state']['priority'] = 60;
            $address_fields['city']['priority'] = 65;

            return $address_fields;
        }

        function wc_change_state_label_locale($locale)
        {
            $locale['CL']['state']['label'] = __('Región', 'woocommerce');
            $locale['CL']['city']['label'] = __('Comuna', 'woocommerce');

            return $locale;
        }

        function load_communes()
        {
            $api_token = get_option('api_token') ?? '';

            $args = array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_token,
                    'Accept' => 'application/json'
                ),
                //'sslverify' => defined( 'MULTICOURIER_SSL_VERIFY' ) ? MULTICOURIER_SSL_VERIFY : true
                'sslverify' => false
            );
            $response = wp_remote_get(MULTICOURIER_API_URL . 'chile/cities', $args);

            if (is_wp_error($response)) {
                error_log('Error al obtener comunas: ' . $response->get_error_message());

                return;
            }

            $body = wp_remote_retrieve_body($response);
            error_log($body);


            $data = json_decode($body, true);

            return $data;
            error_log('Respuesta del API: ' . $data);

            //error_log( 'Respuesta del API: ' . var_dump( $states ) );
            if (json_last_error() !== JSON_ERROR_NONE) {
                //error_log( 'Error al decodificar JSON: ' . json_last_error_msg() );
                return;
            }

            $communes = array();
            foreach ($data as $region => $cities) {
                foreach ($cities as $city_name => $city_id) {
                    $communes[$city_id] = $city_name;
                }
            }

            if (!empty($communes)) {
                error_log('Comunas cargadas: ' . count($communes));

                return $communes;
            } else {
                error_log('No se encontraron comunas en la respuesta.');
            }
        }

    }

}
MC_Shipping::getInstance();