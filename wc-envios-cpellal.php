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

require plugin_dir_path( __FILE__ ) . 'wc-envios-cpellal.conf.php';

add_action( 'woocommerce_init', 'envioscpellal_woocommerce_init' );
register_activation_hook(__FILE__, 'envioscpellal_repartidores_table_create');

function envioscpellal_woocommerce_init(){
	add_action('rest_api_init', function(){
		include_once( plugin_dir_path( __FILE__ ) . 'wc-api-repartidores.class.php' );
		include_once( plugin_dir_path( __FILE__ ) . 'wc-api-envios.class.php' );
		include_once( plugin_dir_path( __FILE__ ) . 'wc-api-orders.class.php' );
	});
	add_filter( 'woocommerce_rest_api_get_rest_namespaces', function($controllers){
		$controllers['wc/v3']['repartidores'] = 'WC_REST_Repartidores_Controller';
		$controllers['wc/v3']['envios'] = 'WC_REST_Envios_Controller';
		$controllers['wc/v3']['orders/unassigned'] = 'WC_REST_OrdersEnvios_Controller';
		return $controllers;
	});
	add_action('woocommerce_new_order', 'envioscpellal_woocommerce_new_order', 10, 2);
	add_filter('woocommerce_order_data_store_cpt_get_orders_query', 'envioscpellal_query_unassigned_orders', 10, 2);
}

function envioscpellal_query_unassigned_orders( $query, $query_vars ) {
	if ( ! empty( $query_vars['envio'] ) ) {
		$query['meta_query'][] = array(
			'key' => '_envio_id',
			'value' => esc_attr( $query_vars['envio'] ),
			);
	}
	return $query;
}

function envioscpellal_repartidores_table_create() {
    global $wpdb;
    $tabla_repartidores = $wpdb->prefix . TABLA_ENVIOSCPELLAL_REPARTIDORES;
    $tabla_envios = $wpdb->prefix . TABLA_ENVIOSCPELLAL_ENVIOS;
    
    $sql_repartidores = "CREATE TABLE IF NOT EXISTS 
		$tabla_repartidores (
                id INT NOT NULL AUTO_INCREMENT,
                nombre VARCHAR(50),
                telefono VARCHAR(15),
                eliminado BOOLEAN DEFAULT FALSE,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8";

    $sql_envios = "CREATE TABLE IF NOT EXISTS 
		$tabla_envios (
                id INT NOT NULL AUTO_INCREMENT,
                repartidor INT NOT NULL,
                horario_salida DATETIME,
                notificado BOOLEAN DEFAULT FALSE,
                PRIMARY KEY (id),
                FOREIGN KEY (repartidor) REFERENCES $tabla_repartidores(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
            
    // Ejecutar las consultas
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	$result_repartidores = dbDelta($sql_repartidores);
	$result_envios = dbDelta($sql_envios);
	if ($result_repartidores === false || $result_envios === false) {
		return false;
	}
	return true;
}

function envioscpellal_woocommerce_new_order($order_id, $order_data) {
	$order = new WC_Order($order_id);
	$order->update_meta_data(CAMPO_PEDIDO_METADATA_IDENVIO,-1);
	$order->update_meta_data(CAMPO_PEDIDO_METADATA_ORDENENVIO,-1);
	$order->save();
}
