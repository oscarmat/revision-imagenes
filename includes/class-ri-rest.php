<?php
/** Endpoints REST /wp-json/revision-imagenes/v1 (solo administradores): config, estado y revisar. */

defined( 'ABSPATH' ) || exit;

class RI_Rest {

	const MAX_POR_PETICION = 10;

	public static function iniciar(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'rutas' ) );
	}

	public static function rutas(): void {
		$permiso = function () {
			return current_user_can( 'manage_options' );
		};
		register_rest_route( 'revision-imagenes/v1', '/config', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'leer_config' ), 'permission_callback' => $permiso ),
			array( 'methods' => 'PUT,POST', 'callback' => array( __CLASS__, 'guardar_config' ), 'permission_callback' => $permiso ),
		) );
		register_rest_route( 'revision-imagenes/v1', '/estado', array(
			'methods' => 'GET', 'callback' => array( __CLASS__, 'estado' ), 'permission_callback' => $permiso,
		) );
		register_rest_route( 'revision-imagenes/v1', '/cola', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'cola' ), 'permission_callback' => $permiso ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'cola_accion' ), 'permission_callback' => $permiso ),
		) );
		register_rest_route( 'revision-imagenes/v1', '/revisar', array(
			'methods' => 'POST', 'callback' => array( __CLASS__, 'revisar' ), 'permission_callback' => $permiso,
			'args'    => array( 'ids' => array( 'required' => true, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ) ),
		) );
	}

	public static function leer_config() {
		return rest_ensure_response( RI_Config::obtener() ?? new stdClass() );
	}

	public static function guardar_config( WP_REST_Request $req ) {
		$c = $req->get_json_params();
		$e = RI_Config::validar( $c );
		if ( $e ) {
			return new WP_REST_Response( array( 'errores' => $e ), 400 );
		}
		RI_Config::guardar( $c );
		return rest_ensure_response( array( 'guardada' => true, 'preguntas' => count( $c['preguntas'] ) ) );
	}

	public static function estado() {
		return rest_ensure_response( array(
			'version'  => '1.3.1',
			'php'      => PHP_VERSION,
			'wp'       => get_bloginfo( 'version' ),
			'clave'    => RI_Clave::origen(),
			'clave_diagnostico' => RI_Clave::diagnostico(),
			'config'   => null !== RI_Config::obtener(),
			'imagenes' => RI_Imagen::soporte(),
			'medios'   => self::total_imagenes(),
			'cola'     => RI_Cola::resumen(),
		) );
	}

	private static function total_imagenes(): int {
		$c = (array) wp_count_attachments( 'image' );
		unset( $c['trash'] );
		return (int) array_sum( $c );
	}

	public static function cola() {
		return rest_ensure_response( RI_Cola::resumen() );
	}

	/** Cuerpo: {accion: encolar|sin-revisar|pausar|reanudar|vaciar|ajustar, ids?, intervalo?, lote?}. */
	public static function cola_accion( WP_REST_Request $req ) {
		$p = (array) $req->get_json_params();
		switch ( $p['accion'] ?? '' ) {
			case 'encolar':
				RI_Cola::encolar( array_map( 'intval', (array) ( $p['ids'] ?? array() ) ) );
				break;
			case 'sin-revisar':
				RI_Cola::encolar_sin_revisar();
				break;
			case 'pausar':
				RI_Cola::pausar();
				break;
			case 'reanudar':
				RI_Cola::reanudar();
				break;
			case 'vaciar':
				RI_Cola::vaciar();
				break;
			case 'ajustar':
				RI_Cola::ajustar( isset( $p['intervalo'] ) ? (int) $p['intervalo'] : null, isset( $p['lote'] ) ? (int) $p['lote'] : null );
				break;
			default:
				return new WP_REST_Response( array( 'errores' => array( 'accion no válida' ) ), 400 );
		}
		return rest_ensure_response( RI_Cola::resumen() );
	}

	public static function revisar( WP_REST_Request $req ) {
		$ids = array_values( array_unique( array_map( 'intval', (array) $req->get_param( 'ids' ) ) ) );
		if ( ! $ids || count( $ids ) > self::MAX_POR_PETICION ) {
			return new WP_REST_Response( array( 'errores' => array( 'indica entre 1 y ' . self::MAX_POR_PETICION . ' ids por petición' ) ), 400 );
		}
		return rest_ensure_response( array_map( array( 'RI_Revisor', 'revisar' ), $ids ) );
	}
}
