<?php
require_once plugin_dir_path( __FILE__ ) . 'wc-api-repartidores.conf.php';

class WC_REST_Repartidores_Controller {
	protected $namespace = 'wc/v3';
	protected $rest_base = '/repartidores';
	
	public function register_routes() {
		register_rest_route(
			$this->namespace, 
			'/' . $this->rest_base .  '/?(?P<id>\d+)?/?',
			array(
				array(
					'methods' => 'GET',
					'callback' => array( $this, 'get_repartidores'),
					'permission_callback' => '__return_true', 
				),
				array(
					'methods' => 'POST',
					'callback' => array( $this, 'add_repartidor'),
					'permission_callback' => '__return_true', 
				),
				array(
					'methods' => 'PUT',
					'callback' => array( $this, 'edit_repartidor'),
					'permission_callback' => '__return_true',
				),
				array(
					'methods' => 'DELETE',
					'callback' => array( $this, 'del_repartidor'),
					'permission_callback' => '__return_true', 
				),
			)
		);
	}
	
	public function get_repartidores($request) {
		global $wpdb;
		$repartidores = [];
		$tabla_repartidores = $wpdb->prefix . TABLA_ENVIOSCPELLAL_REPARTIDORES;
		$param_id = $request->get_param('id');
		
		$query = "SELECT id, nombre, telefono FROM $tabla_repartidores";
		if( ! is_null( $param_id ) ) {
			$query .= $wpdb->prepare(" WHERE id = %d LIMIT 1", $param_id);
		}
		$query.=";";
		$l = wc_get_logger();
		$l->info($query);
		$results = $wpdb->get_results($query);
		$l->info("resultados",$results);
		foreach ( $results as $row ) {
			$repartidores[] = [
				'id' => $row->id,
				'nombre' => $row->nombre,
				'telefono' => $row->telefono
			];
		}
		if( ! is_null( $param_id ) ) $repartidores=$repartidores[0];
		$resp = new WP_REST_Response($repartidores, 200);
		$resp->header('Content-Type', 'application/json');
		$resp->header('X-WP-Total', count($repartidores));
		return $resp;
	}
	
	public function add_repartidor( $request ) {
		global $wpdb;
		$params = $request->get_params();
		$l = wc_get_logger();
		$l->info("parametros",$params);
		if ( ! isset( $params['nombre'] ) || ! preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚüÜ\s]+$/', $params['nombre'] )) {
			return new WP_Error( 'invalid_parameter', 'El nombre del repartidor ha sido omitido o contiene caracteres inválidos.', array( 'status' => 400 ) ); }
		if ( isset($params['telefono']) && ! preg_match('/^[0-9]+$/', $params['telefono'] )) {
			return new WP_Error( 'invalid_parameter', 'El número telefónico contiene caracteres inválidos.', array( 'status' => 400 ) ); }
		
		$result = $wpdb->insert(
			$wpdb->prefix . TABLA_ENVIOSCPELLAL_REPARTIDORES,
			array(
				'nombre' => $params['nombre'],
				'telefono' => $params['telefono']
			)
		);
		if(!$result){
			return new WP_Error( 'error', 'No se pudo agregar el repartidor.', array( 'status' => 500 ) );
		}
		// ToDo: devolver el objeto completo
		$ret = new WP_REST_Response(array("id"=>$params['id']), 200);
		$ret->header('Content-Type', 'application/json');
		return $ret;
	}
	
	public function edit_repartidor( $request ) {
		global $wpdb;
		$params = $request->get_params();
		$l = wc_get_logger();
		$l->info("edit_repartidor",$params);
		if( ! isset( $params['id'] ) || ! ctype_digit( $params['id'] )) {
			return new WP_Error( 'invalid_parameter', 'El parámetro ID ha sido omitido o es inválido.', array( 'status' => 400 ) ); }
		if ( ! isset( $params['nombre'] ) || ! preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚüÜ\s]+$/', $params['nombre'] )) {
			return new WP_Error( 'invalid_parameter', 'El nombre del repartidor ha sido omitido o contiene caracteres inválidos.', array( 'status' => 400 ) ); }
		if ( isset( $params['telefono'] ) && ! preg_match('/^[0-9]+$/', $params['telefono'] )) {
			return new WP_Error( 'invalid_parameter', 'El número telefónico contiene caracteres inválidos.', array( 'status' => 400 ) ); }
		
		$result = $wpdb->update(
			$wpdb->prefix . TABLA_ENVIOSCPELLAL_REPARTIDORES,
			array(
				'nombre' => $params['nombre'],
				'telefono' => $params['telefono']
			),
			array( 'id' => $params['id'] )
		);
		if(!$result){
			return new WP_Error( 'error', 'No se pudieron actualizar los datos del repartidor.', array( 'status' => 500 ) );
		}
		// ToDo: devolver el objeto completo
		$ret = new WP_REST_Response(array("id"=>$params['id']), 200);
		$ret->header('Content-Type', 'application/json');
		return $ret;
	}
	
	public function del_repartidor( $request ) {
		global $wpdb;
		$params = $request->get_params();
		if( ! isset( $params['id'] ) || ! ctype_digit( $params['id'] )) {
			return new WP_Error( 'invalid_parameter', 'Parámetro ID faltante o inválido.', array( 'status' => 400 ) );
		}
		$result = $wpdb->delete(
			$wpdb->prefix . TABLA_ENVIOSCPELLAL_REPARTIDORES,
			array( 'id' => $params['id'] ),
			array( '%d' )
		);
		if(!$result){
			return new WP_Error( 'error', 'No se pudo eliminar el repartidor.', array( 'status' => 500 ) );
		}
	}

}
