<?php
/**
 * Cola de revisión en segundo plano con WP-Cron. Hay siempre como mucho un evento planificado: cada vez
 * que se planifica uno, se sitúa unos minutos después del último planificado, y al ejecutarse procesa un
 * lote pequeño y planifica el siguiente. Así las llamadas a Jev quedan escalonadas.
 */

defined( 'ABSPATH' ) || exit;

class RI_Cola {

	const HOOK     = 'ri_procesar_cola';
	const OPCION   = 'ri_cola';
	const BLOQUEO  = 'ri_cola_bloqueo';
	const INTENTOS = 3;   // por imagen, antes de darla por fallida
	const FALLOS   = 3;   // lotes seguidos sin ningún éxito, antes de pausar

	public static function iniciar(): void {
		add_action( self::HOOK, array( __CLASS__, 'procesar' ) );
		add_action( 'admin_init', array( __CLASS__, 'planificar' ) );  // reengancha la cadena si se perdió el evento
	}

	public static function desactivar(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function estado(): array {
		$e = get_option( self::OPCION );
		return array_merge( array(
			'pendientes' => array(), 'hechas' => 0, 'fallidas' => array(), 'intentos' => array(),
			'activa' => true, 'intervalo' => 5, 'lote' => 3, 'ultima' => 0, 'fallos_seguidos' => 0, 'ultimo_error' => '',
		), is_array( $e ) ? $e : array() );
	}

	private static function guardar( array $e ): void {
		update_option( self::OPCION, $e, false );
	}

	/** Resumen para REST y pantalla. */
	public static function resumen(): array {
		$e = self::estado();
		return array(
			'estado'       => $e['activa'] ? 'activa' : 'pausada',
			'pendientes'   => count( $e['pendientes'] ),
			'hechas'       => (int) $e['hechas'],
			'fallidas'     => $e['fallidas'],
			'intervalo'    => (int) $e['intervalo'],
			'lote'         => (int) $e['lote'],
			'proxima'      => wp_next_scheduled( self::HOOK ) ?: null,
			'ultima'       => $e['ultima'] ?: null,
			'ultimo_error' => $e['ultimo_error'],
			'wp_cron_desactivado' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
		);
	}

	public static function encolar( array $ids ): int {
		$e = self::estado();
		$nuevos = array_values( array_diff( array_unique( array_map( 'intval', $ids ) ), $e['pendientes'] ) );
		$e['pendientes'] = array_merge( $e['pendientes'], $nuevos );
		foreach ( $nuevos as $id ) {
			unset( $e['fallidas'][ $id ], $e['intentos'][ $id ] );
		}
		self::guardar( $e );
		self::planificar();
		return count( $nuevos );
	}

	/** Imágenes de la biblioteca que aún no tienen revisión. */
	public static function encolar_sin_revisar(): int {
		$ids = get_posts( array(
			'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image',
			'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC',
			'meta_query' => array( array( 'key' => 'jev_revision_fecha', 'compare' => 'NOT EXISTS' ) ),
		) );
		return self::encolar( $ids );
	}

	public static function pausar(): void {
		$e = self::estado();
		$e['activa'] = false;
		self::guardar( $e );
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function reanudar(): void {
		$e = self::estado();
		$e['activa'] = true;
		$e['fallos_seguidos'] = 0;
		$e['ultimo_error'] = '';
		self::guardar( $e );
		self::planificar();
	}

	public static function vaciar(): void {
		$e = self::estado();
		$e['pendientes'] = array();
		$e['intentos'] = array();
		self::guardar( $e );
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function ajustar( ?int $intervalo, ?int $lote ): void {
		$e = self::estado();
		if ( null !== $intervalo ) {
			$e['intervalo'] = max( 1, min( 120, $intervalo ) );
		}
		if ( null !== $lote ) {
			$e['lote'] = max( 1, min( 10, $lote ) );
		}
		self::guardar( $e );
	}

	/** Momento del siguiente evento: tras el último planificado, no antes de 30 s desde ahora. */
	public static function proximo_momento( int $ahora, int $ultima, int $intervalo_min ): int {
		return max( $ahora + 30, $ultima + $intervalo_min * 60 );
	}

	public static function planificar(): void {
		$e = self::estado();
		if ( ! $e['activa'] || ! $e['pendientes'] || wp_next_scheduled( self::HOOK ) ) {
			return;
		}
		$cuando = self::proximo_momento( time(), (int) $e['ultima'], (int) $e['intervalo'] );
		if ( false !== wp_schedule_single_event( $cuando, self::HOOK ) ) {
			$e['ultima'] = $cuando;
			self::guardar( $e );
		}
	}

	public static function procesar(): void {
		if ( get_transient( self::BLOQUEO ) ) {
			self::planificar();  // otra ejecución en curso: que la cadena no se pierda
			return;
		}
		set_transient( self::BLOQUEO, 1, 10 * MINUTE_IN_SECONDS );
		try {
			$e = self::estado();
			if ( $e['activa'] && $e['pendientes'] ) {
				$e = self::lote( $e );
				self::guardar( $e );
			}
		} finally {
			delete_transient( self::BLOQUEO );
		}
		self::planificar();
	}

	private static function lote( array $e ): array {
		$tomadas = array_slice( $e['pendientes'], 0, (int) $e['lote'] );
		$e['pendientes'] = array_slice( $e['pendientes'], count( $tomadas ) );
		$exitos = 0;
		$errores = 0;
		foreach ( $tomadas as $id ) {
			if ( 'attachment' !== get_post_type( $id ) ) {  // borrada entretanto
				continue;
			}
			$r = RI_Revisor::revisar( (int) $id );
			if ( ! isset( $r['error'] ) ) {
				$e['hechas']++;
				$exitos++;
				unset( $e['intentos'][ $id ] );
				continue;
			}
			$errores++;
			$e['ultimo_error'] = $r['error'];
			$e['intentos'][ $id ] = ( $e['intentos'][ $id ] ?? 0 ) + 1;
			if ( $e['intentos'][ $id ] >= self::INTENTOS ) {
				$e['fallidas'][ $id ] = $r['error'];
				unset( $e['intentos'][ $id ] );
			} else {
				$e['pendientes'][] = $id;
			}
		}
		$e['fallos_seguidos'] = $exitos > 0 ? 0 : ( $errores > 0 ? $e['fallos_seguidos'] + 1 : $e['fallos_seguidos'] );
		if ( $e['fallos_seguidos'] >= self::FALLOS ) {
			$e['activa'] = false;  // algo global falla (clave, API, configuración): no seguir gastando llamadas
		}
		return $e;
	}
}
