<?php
require_once plugin_dir_path( __FILE__ ) . 'wc-envios-cpellal.conf.php';

class WC_REST_Envios_Controller {
	protected $namespace = 'wc/v3';
	protected $rest_base = '/envios';
	
	public function register_routes() {
		register_rest_route(
			$this->namespace, 
			'/' . $this->rest_base .  '/?(?P<id>\d+)?/?',
			array(
				array(
					'methods' => 'GET',
					'callback' => array( $this, 'get_envios'),
					'permission_callback' => '__return_true', 
				),
				array(
					'methods' => 'POST',
					'callback' => array( $this, 'add_envio'),
					'permission_callback' => '__return_true', 
				),
				array(
					'methods' => 'DELETE',
					'callback' => array( $this, 'del_envio'),
					'permission_callback' => '__return_true', 
				),
			)
		);
	}

	public function get_envios($request) {
		global $wpdb;
		$envios = [];
		$tabla_envios = $wpdb->prefix . TABLA_ENVIOSCPELLAL_ENVIOS;
		$tabla_repartidores = $wpdb->prefix . TABLA_ENVIOSCPELLAL_REPARTIDORES;
		$param_id = $request->get_param('id');
		
		$query = "SELECT e.id, e.repartidor AS id_repartidor, r.nombre AS nombre_repartidor, e.horario_salida 
							FROM $tabla_envios AS e INNER JOIN $tabla_repartidores AS r
							ON e.repartidor = r.id";
		if( ! is_null( $param_id ) ) {
			$query .= $wpdb->prepare(" WHERE e.id = %d LIMIT 1", $param_id);
		}
		$query.=";";
		$results = $wpdb->get_results($query);
		foreach ( $results as $row ) {
			$envios[] = [
				'id' 							=> $row->id,
				'id_repartidor' 		=> $row->id_repartidor,
				'repartidor' 			=> $row->nombre_repartidor,
				'horario_salida' 	=> $row->horario_salida,
				'notificado'				=> $row->notificado
			];
		}
		if( ! is_null( $param_id ) ) $envios=$envios[0];
		$resp = new WP_REST_Response($envios, 200);
		$resp->header('Content-Type', 'application/json');
		$resp->header('X-WP-Total', count($envios));
		return $resp;
	}
	
	public function add_envio( $request ) {
		global $wpdb;
		$tabla_envios = $wpdb->prefix . TABLA_ENVIOSCPELLAL_ENVIOS;
		$params = $request->get_params();
		
		$date = DateTime::createFromFormat("Y-m-d\TH:i:s.u\Z", $params['horario_salida']);
		if ($date === false){ //|| $date->format("Y-m-d\TH:i:s.u\Z") !== $params['horario_salida']) {
			return new WP_Error( 'invalid_parameter', 'La fecha recibida no tiene un formato adecuado.', array( 'status' => 400 ) ); }
		if( ! isset( $params['repartidor'] ) || ! ctype_digit( $params['repartidor'] )) {
			return new WP_Error( 'invalid_parameter', 'ID de repartidor faltante o inválido.', array( 'status' => 400 ) );}
		if( ! isset($params['orders']) || ! is_array( $params['orders'])) {
			return new WP_Error( 'invalid_parameter', 'Órdenes para asignar al envío incorrectas.', array( 'status' => 400 ) );}
		foreach ($params['orders'] as $order) {
			if (! ctype_digit($order)) {
				return new WP_Error( 'invalid_parameter', 'Órdenes para asignar al envío incorrectas.', array( 'status' => 400 ) );
			}
		}
		
		$wpdb->query('START TRANSACTION');
		
		$result = $wpdb->insert(
			$wpdb->prefix . TABLA_ENVIOSCPELLAL_ENVIOS,
			array(
				'repartidor' => $params['repartidor'],
				'horario_salida' => $params['horario_salida']
			)
		);
		if(!$result){
			$wpdb->query('ROLLBACK');
			return new WP_Error( 'error', 'No se pudo registrar el envío.', array( 'status' => 500 ) );
		}
		
		$last_envio = $wpdb->get_results("SELECT * FROM $tabla_envios ORDER BY id DESC LIMIT 1;");
		if(!$last_envio){
			return new WP_Error( 'error', 'Falló al obtener el envío registrado.', array( 'status' => 500 ) );
		}
		
		try {			
			foreach ($params['orders'] as $order_id)	{
				$order = wc_get_order($order_id);
				if ($order) {
					$order->update_meta_data(CAMPO_PEDIDO_METADATA_IDENVIO, $last_envio[0]->id);
					$order->save();
				} else {
					return new WP_Error( 'error', 'Falló al intentar crear un envío con un pedido inexistente.', array( 'status' => 400 ) );
				}
			}
			$wpdb->query('COMMIT');
		} catch (Exception $e) {
			$wpdb->query('ROLLBACK');
			return new WP_Error( 'error', 'Falló al intentar asignar los pedidos.', array( 'status' => 500 ) );
		}
		
		$ret = new WP_REST_Response($last_envio[0], 200);
		$ret->header('Content-Type', 'application/json');
		return $ret;
	}
	
	public function del_envio( $request ) {
		global $wpdb;
		$params = $request->get_params();
		if( ! isset( $params['id'] ) || ! ctype_digit( $params['id'] )) {
			return new WP_Error( 'invalid_parameter', 'Parámetro ID faltante o inválido.', array( 'status' => 400 ) );
		}
		$wpdb->query('START TRANSACTION');
		
		try:
			$query_orders = new WC_Order_Query( array(
					'envio' => $params['id'],
					'status' => 'processing'
			) );
			$orders = $query_orders->get_orders();
			foreach($orders as $order){
				$order->update_meta_data(CAMPO_PEDIDO_METADATA_IDENVIO, -1);
			}
		catch (Exception $e) {
			$wpdb->query('ROLLBACK');
			return new WP_Error( 'error', 'Falló al intentar liberar los pedidos.', array( 'status' => 500 ) );
		}
		
		$result = $wpdb->delete(
			$wpdb->prefix . TABLA_ENVIOSCPELLAL_ENVIOS,
			array( 'id' => $params['id'] ),
			array( '%d' )
		);
		if(!$result){
			$wpdb->query('ROLLBACK');
			return new WP_Error( 'error', 'No se pudo eliminar el envío.', array( 'status' => 500 ) );
		}
	}
	
}
