<?php
/**
 * Cliente de Jev (TypeSafe System One): una petición por imagen con todas sus preguntas (Noul).
 * Un fallo de la API es siempre un error, nunca una respuesta «no».
 */

defined( 'ABSPATH' ) || defined( 'RI_TESTS' ) || exit;

class RI_Jev {

	const URL = 'https://api.typesafe.ai/v1/systemone';

	public static function cuerpo( array $preguntas, string $tema, string $jpeg ): array {
		$qs = array();
		foreach ( $preguntas as $nombre => $q ) {
			$qs[ $nombre ] = array( 'type' => 'noul', 'instructions' => str_replace( '{tema}', $tema, $q['instructions'] ) );
		}
		return array(
			'model'     => 'jev-latest',
			'state'     => array( 'imagen' => 'img1' ),
			'assets'    => array( 'img1' => array( 'type' => 'image', 'format' => 'jpg', 'data_base64' => base64_encode( $jpeg ) ) ),
			'questions' => $qs,
		);
	}

	/** @return array<string,float>|string  probabilidades por pregunta, o el motivo del error. */
	public static function parsear( $json, array $preguntas ) {
		$d = is_string( $json ) ? json_decode( $json, true ) : $json;
		if ( ! is_array( $d ) || ! isset( $d['answers'] ) || ! is_array( $d['answers'] ) ) {
			return 'respuesta de Jev sin «answers»';
		}
		$p = array();
		foreach ( $preguntas as $nombre => $_ ) {
			if ( ! isset( $d['answers'][ $nombre ]['noul'] ) || ! is_numeric( $d['answers'][ $nombre ]['noul'] ) ) {
				return "falta la respuesta a «{$nombre}»";
			}
			$p[ $nombre ] = (float) $d['answers'][ $nombre ]['noul'];
		}
		return $p;
	}

	/** @return array<string,float>|WP_Error */
	public static function consultar( string $clave, array $preguntas, string $tema, string $jpeg ) {
		$r = wp_remote_post( self::URL, array(
			'timeout' => 60,
			'headers' => array(
				'Authorization'              => 'Bearer ' . $clave,
				'Content-Type'               => 'application/json',
				'TypeSafe-Danger-Zone-Images' => 'true',
			),
			'body'    => wp_json_encode( self::cuerpo( $preguntas, $tema, $jpeg ) ),
		) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$codigo = wp_remote_retrieve_response_code( $r );
		if ( 200 !== $codigo ) {
			return new WP_Error( 'ri_jev_http', "Jev respondió HTTP {$codigo}: " . substr( wp_remote_retrieve_body( $r ), 0, 200 ) );
		}
		$p = self::parsear( wp_remote_retrieve_body( $r ), $preguntas );
		return is_string( $p ) ? new WP_Error( 'ri_jev_formato', $p ) : $p;
	}
}
