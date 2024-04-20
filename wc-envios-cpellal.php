<?php
/*
Plugin Name: WC Envíos CPellaL
Description: WC Envíos CPellaL
Version: dev-0.1
Author: Silvano Emanuel Roques
Author URI: https://dev.lode.uno/
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require plugin_dir_path( __FILE__ ) . 'wc-api-repartidores.conf.php';

add_action( 'woocommerce_init', 'envioscpellal_woocommerce_init' );
register_activation_hook(__FILE__, 'envioscpellal_repartidores_table_create');

function envioscpellal_woocommerce_init(){
	$logger = wc_get_logger();
	$logger->info("envioscpellal_woocommerce_init");
	
	add_action('rest_api_init', function(){
		include_once( plugin_dir_path( __FILE__ ) . 'wc-api-repartidores.class.php' );
	});
	add_filter( 'woocommerce_rest_api_get_rest_namespaces', function($controllers){
		$controllers['wc/v3']['repartidores'] = 'WC_REST_Repartidores_Controller';
		return $controllers;
	});
}

function envioscpellal_repartidores_table_create() {
	$logger = wc_get_logger();
	$logger->info("envioscpellal_repartidores_table_create");
	
    global $wpdb;
    $tabla_repartidores = $wpdb->prefix . TABLA_ENVIOSCPELLAL_REPARTIDORES;
    
    $sql_repartidores = "CREATE TABLE IF NOT EXISTS 
		$tabla_repartidores (
                id INT NOT NULL AUTO_INCREMENT,
                nombre VARCHAR(50),
                telefono VARCHAR(15),
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
            
    // Ejecutar las consultas
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_repartidores);
}

