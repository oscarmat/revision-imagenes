<?php
/**
 * Tabla de verdad del veredicto: probabilidades de Jev -> ok / revisar / rechazar y motivos.
 *
 * Port de veredicto() y detectados() de la skill wp-revisar-imagenes (Python). Ambas implementaciones
 * se comprueban contra tests/fixtures/veredicto.json. Sin dependencias de WordPress.
 */

defined( 'ABSPATH' ) || defined( 'RI_TESTS' ) || exit;

class RI_Veredicto {

	const ALTO = 0.7;
	const BAJO = 0.3;

	/**
	 * @param array $preguntas  nombre => definición (malo, efecto, solo_si, ignorar_si, ignorar_si_ampliada).
	 * @param array $p          nombre => probabilidad (Noul) devuelta por Jev.
	 * @param bool  $ampliada   la imagen se amplió para llegar al mínimo de Jev (sale borrosa por construcción).
	 * @return array{nivel:string,motivos:string}
	 */
	public static function evaluar( array $preguntas, array $p, bool $ampliada = false, float $alto = self::ALTO, float $bajo = self::BAJO ): array {
		$nivel   = 'ok';
		$motivos = array();
		foreach ( $preguntas as $nombre => $q ) {
			if ( ! isset( $q['malo'] ) ) {
				continue;
			}
			if ( $ampliada && ! empty( $q['ignorar_si_ampliada'] ) ) {
				continue;
			}
			if ( isset( $q['ignorar_si'] ) && $p[ $q['ignorar_si'] ] >= $alto ) {
				continue;
			}
			if ( isset( $q['solo_si'] ) && $p[ $q['solo_si'] ] < 0.5 ) {
				continue;
			}
			$pm = 'si' === $q['malo'] ? $p[ $nombre ] : 1 - $p[ $nombre ];
			if ( $pm >= $alto ) {
				$efecto = $q['efecto'] ?? 'revisar';
			} elseif ( $pm >= $bajo ) {
				$efecto = 'revisar';
			} else {
				continue;
			}
			$motivos[] = sprintf( '%s=%.2f', $nombre, $p[ $nombre ] );
			if ( 'rechazar' === $efecto ) {
				$nivel = 'rechazar';
			} elseif ( 'ok' === $nivel ) {
				$nivel = 'revisar';
			}
		}
		return array( 'nivel' => $nivel, 'motivos' => implode( ',', $motivos ) );
	}

	/** Preguntas con `umbral` propio (p. ej. un operador por pregunta) cuya probabilidad lo alcanza. */
	public static function detectados( array $preguntas, array $p ): string {
		$nombres = array();
		foreach ( $preguntas as $nombre => $q ) {
			if ( isset( $q['umbral'] ) && $p[ $nombre ] >= $q['umbral'] ) {
				$nombres[] = 0 === strpos( $nombre, 'menciona_' ) ? substr( $nombre, 9 ) : $nombre;
			}
		}
		return implode( ',', $nombres );
	}
}
