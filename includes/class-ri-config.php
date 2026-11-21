<?php
/** Configuración de la revisión (tema, preguntas, umbrales). La escribe Fede por REST; se guarda como opción. */

defined( 'ABSPATH' ) || defined( 'RI_TESTS' ) || exit;

class RI_Config {

	const OPCION = 'ri_config';

	/** @return string[] errores; vacío si la configuración es válida. */
	public static function validar( $c ): array {
		$e = array();
		if ( ! is_array( $c ) ) {
			return array( 'la configuración debe ser un objeto' );
		}
		if ( ! isset( $c['tema'] ) || ! is_string( $c['tema'] ) || '' === trim( $c['tema'] ) ) {
			$e[] = 'falta «tema»';
		}
		foreach ( array( 'alto', 'bajo' ) as $k ) {
			if ( isset( $c[ $k ] ) && ( ! is_numeric( $c[ $k ] ) || $c[ $k ] <= 0 || $c[ $k ] >= 1 ) ) {
				$e[] = "«{$k}» debe estar entre 0 y 1";
			}
		}
		if ( empty( $c['preguntas'] ) || ! is_array( $c['preguntas'] ) ) {
			return array_merge( $e, array( 'faltan «preguntas»' ) );
		}
		foreach ( $c['preguntas'] as $n => $q ) {
			if ( ! is_string( $n ) || ! preg_match( '/^[a-z][a-z0-9_]*$/', $n ) ) {
				$e[] = 'nombre de pregunta no válido: ' . ( is_string( $n ) ? $n : '#' . $n );
				continue;
			}
			if ( ! is_array( $q ) || empty( $q['instructions'] ) || ! is_string( $q['instructions'] ) ) {
				$e[] = "{$n}: falta «instructions»";
				continue;
			}
			if ( isset( $q['malo'] ) && ! in_array( $q['malo'], array( 'si', 'no' ), true ) ) {
				$e[] = "{$n}: «malo» debe ser si o no";
			}
			if ( isset( $q['efecto'] ) && ! in_array( $q['efecto'], array( 'rechazar', 'revisar' ), true ) ) {
				$e[] = "{$n}: «efecto» debe ser rechazar o revisar";
			}
			foreach ( array( 'solo_si', 'ignorar_si' ) as $k ) {
				if ( isset( $q[ $k ] ) && ! isset( $c['preguntas'][ $q[ $k ] ] ) ) {
					$e[] = "{$n}: «{$k}» apunta a una pregunta que no existe";
				}
			}
			if ( isset( $q['umbral'] ) && ( ! is_numeric( $q['umbral'] ) || $q['umbral'] <= 0 || $q['umbral'] > 1 ) ) {
				$e[] = "{$n}: «umbral» debe estar entre 0 y 1";
			}
		}
		return $e;
	}

	public static function obtener(): ?array {
		$c = get_option( self::OPCION, null );
		return is_array( $c ) ? $c : null;
	}

	public static function guardar( array $c ): void {
		update_option( self::OPCION, $c, false );
	}
}
