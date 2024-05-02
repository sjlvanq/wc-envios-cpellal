<?php
require_once plugin_dir_path( __FILE__ ) . 'wc-envios-cpellal.conf.php';

class WC_REST_OrdersEnvios_Controller {
	protected $namespace = 'wc/v3';
	protected $rest_base = 'envios/orders';
	
	public function register_routes() {
		register_rest_route(
			$this->namespace, 
			'/' . $this->rest_base . '/(?P<id>-?\d+)',
			array(
				array(
					'methods' 							=> 'GET',
					'callback' 							=> array( $this, 'get_envio_orders'),
					'permission_callback' 		=> '__return_true', 
				),
			)
		);
	}
	public function get_envio_orders($request) {
		$params = $request->get_params();
		/* La validación puede hacerse en una función aparte y añadirse al array de register_rest_route
		 * de todos modos parece innecesaria por la regexp que define el endpoint: '/(?P<id>-?\d+)',
                'args'                => array(
                    'id' => array(
                        'validate_callback' => array( $this, 'validate_order_id' ),
                        'required'          => true,
                        'description'       => 'Order ID.',
                        'type'              => 'integer',
                    ),
                ),
		*/
		if( ! isset( $params['id'] ) || ! preg_match('/^(-?\d+)$/', $params['id'] )) {
			return new WP_Error( 'invalid_parameter', 'Parámetro ID faltante o inválido.', array( 'status' => 400 ) );
		}
		$query = new WC_Order_Query( array(
				'envio' => $params['id'],
				'status' => 'processing'
		) );
		$orders = $query->get_orders();
		$order_data = [];
		$order_data_items = [];
		foreach( $orders as $order ) {
			foreach( $order->get_items() as $item_id => $item ) {$order_data_items[] = $item->get_data();}
			$order_data[] = array_merge( $order->get_data(), Array('line_items'=>$order_data_items) );
			$order_data_items = [];
		}
		$resp = new WP_REST_Response($order_data, 200);
		$resp->header('Content-Type', 'application/json');
		$resp->header('X-WP-Total', count($order_data));
		return $resp;
	}
}
