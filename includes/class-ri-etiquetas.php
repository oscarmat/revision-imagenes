<?php
/** Etiquetas de la taxonomía revision_imagen que salen de un veredicto. Las que pone una persona no se tocan. */

defined( 'ABSPATH' ) || defined( 'RI_TESTS' ) || exit;

class RI_Etiquetas {

	const PREFIJOS = array( 'estado-', 'motivo-', 'operador-' );
	const FIJAS    = array( 'ampliada', 'omitida' );

	/** ¿La etiqueta la gestiona el plugin (y por tanto se reescribe al recalcular)? */
	public static function es_nuestra( string $slug ): bool {
		if ( in_array( $slug, self::FIJAS, true ) ) {
			return true;
		}
		foreach ( self::PREFIJOS as $p ) {
			if ( 0 === strpos( $slug, $p ) ) {
				return true;
			}
		}
		return false;
	}

	/** @return array<string,string> slug => nombre */
	public static function calcular( string $nivel, string $motivos, string $detectados, bool $ampliada ): array {
		$t = array( "estado-{$nivel}" => ucfirst( $nivel ) );
		foreach ( '' === $motivos ? array() : explode( ',', $motivos ) as $m ) {
			$n = explode( '=', $m )[0];
			$t[ 'motivo-' . str_replace( '_', '-', $n ) ] = 'Motivo: ' . $n;
		}
		foreach ( '' === $detectados ? array() : explode( ',', $detectados ) as $o ) {
			$t[ 'operador-' . str_replace( '_', '-', $o ) ] = 'Operador: ' . $o;
		}
		if ( $ampliada ) {
			$t['ampliada'] = 'Ampliada';
		}
		return $t;
	}

	/** Slugs finales: los de otra procedencia que ya tenía el medio, más los calculados. */
	public static function combinar( array $actuales, array $nuevos ): array {
		$propios = array_filter( $actuales, function ( $s ) {
			return ! self::es_nuestra( $s );
		} );
		return array_values( array_unique( array_merge( $propios, $nuevos ) ) );
	}

	// ---- con WordPress ----

	public static function aplicar( int $id, array $calculadas ): void {
		$taxo = RI_TAXONOMIA;
		foreach ( $calculadas as $slug => $nombre ) {
			if ( ! term_exists( $slug, $taxo ) ) {
				wp_insert_term( $nombre, $taxo, array( 'slug' => $slug ) );
			}
		}
		$actuales = wp_get_object_terms( $id, $taxo, array( 'fields' => 'slugs' ) );
		wp_set_object_terms( $id, self::combinar( is_array( $actuales ) ? $actuales : array(), array_keys( $calculadas ) ), $taxo );
	}
}
