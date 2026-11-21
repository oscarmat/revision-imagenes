<?php
// Cola en segundo plano con stubs mínimos de WordPress. Uso: php tests/run-cola.php
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
$GLOBALS['opt'] = $GLOBALS['cron'] = $GLOBALS['tr'] = array();
$GLOBALS['tipos'] = array();      // id => post_type; ausente = borrada
$GLOBALS['resp']  = array();      // id => respuesta de revisar (por defecto ok)
function get_option( $k ) { return $GLOBALS['opt'][ $k ] ?? false; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opt'][ $k ] = $v; return true; }
function get_transient( $k ) { return $GLOBALS['tr'][ $k ] ?? false; }
function set_transient( $k, $v, $t ) { $GLOBALS['tr'][ $k ] = $v; }
function delete_transient( $k ) { unset( $GLOBALS['tr'][ $k ] ); }
function wp_next_scheduled( $h ) { return $GLOBALS['cron'][ $h ] ?? false; }
function wp_schedule_single_event( $t, $h ) { $GLOBALS['cron'][ $h ] = $t; return true; }
function wp_clear_scheduled_hook( $h ) { unset( $GLOBALS['cron'][ $h ] ); }
function get_post_type( $id ) { return $GLOBALS['tipos'][ $id ] ?? false; }
function add_action() {}
class RI_Revisor {
	public static function revisar( int $id ): array { return $GLOBALS['resp'][ $id ] ?? array( 'id' => $id, 'estado' => 'ok' ); }
}
require __DIR__ . '/../includes/class-ri-cola.php';

$fallos = 0;
function comprobar( $n, $o, $e ) {
	global $fallos;
	$ok = $o === $e;
	echo ( $ok ? 'ok     ' : 'FALLO  ' ) . $n . "\n";
	if ( ! $ok ) { $fallos++; echo '  esperado: ' . json_encode( $e ) . "\n  obtenido: " . json_encode( $o ) . "\n"; }
}
function ejecutar() { unset( $GLOBALS['cron'][ RI_Cola::HOOK ] ); RI_Cola::procesar(); }
function reiniciar( $ids ) {
	$GLOBALS['opt'] = $GLOBALS['cron'] = $GLOBALS['tr'] = $GLOBALS['resp'] = array();
	$GLOBALS['tipos'] = array_fill_keys( $ids, 'attachment' );
}

comprobar( 'momento: tras la última planificada', RI_Cola::proximo_momento( 1000, 5000, 5 ), 5300 );
comprobar( 'momento: nunca antes de 30 s desde ahora', RI_Cola::proximo_momento( 1000, 0, 5 ), 1030 );

reiniciar( range( 1, 7 ) );
comprobar( 'encolar devuelve nuevos', RI_Cola::encolar( array( 1, 2, 3, 4, 5, 6, 7, 3 ) ), 7 );
comprobar( 'no duplica', RI_Cola::encolar( array( 2, 3 ) ), 0 );
$primera = $GLOBALS['cron'][ RI_Cola::HOOK ];
comprobar( 'hay un evento planificado', $primera > time(), true );
ejecutar();
comprobar( 'lote de 3 hechas', RI_Cola::resumen()['hechas'], 3 );
comprobar( 'quedan 4', RI_Cola::resumen()['pendientes'], 4 );
comprobar( 'siguiente evento 5 min después del anterior', $GLOBALS['cron'][ RI_Cola::HOOK ], $primera + 300 );
ejecutar(); ejecutar();
comprobar( 'cola vacía', RI_Cola::resumen()['pendientes'], 0 );
comprobar( 'sin pendientes no se planifica', isset( $GLOBALS['cron'][ RI_Cola::HOOK ] ), false );

reiniciar( array( 1, 2 ) );
unset( $GLOBALS['tipos'][2] );  // borrada
RI_Cola::encolar( array( 1, 2 ) ); ejecutar();
comprobar( 'borrada se ignora', array( RI_Cola::resumen()['hechas'], RI_Cola::resumen()['fallidas'] ), array( 1, array() ) );

reiniciar( array( 1, 2 ) );
$GLOBALS['resp'][1] = array( 'id' => 1, 'error' => 'imagen rota' );
RI_Cola::encolar( array( 1, 2 ) );
ejecutar();
comprobar( 'error: la otra se hace, la fallida reintenta', array( RI_Cola::resumen()['hechas'], RI_Cola::resumen()['pendientes'] ), array( 1, 1 ) );
ejecutar(); ejecutar();
comprobar( 'a los 3 intentos pasa a fallidas', array_keys( RI_Cola::resumen()['fallidas'] ), array( 1 ) );

reiniciar( array( 1, 2, 3, 4, 5, 6, 7, 8, 9, 10 ) );
foreach ( range( 1, 10 ) as $i ) { $GLOBALS['resp'][ $i ] = array( 'id' => $i, 'error' => 'API caída' ); }
RI_Cola::encolar( range( 1, 10 ) );
ejecutar(); ejecutar(); ejecutar();
comprobar( 'tres lotes sin éxito pausan', RI_Cola::resumen()['estado'], 'pausada' );
comprobar( 'pausada no planifica', isset( $GLOBALS['cron'][ RI_Cola::HOOK ] ), false );
RI_Cola::reanudar();
comprobar( 'reanudar planifica', isset( $GLOBALS['cron'][ RI_Cola::HOOK ] ), true );

reiniciar( array( 1 ) );
RI_Cola::encolar( array( 1 ) );
$GLOBALS['tr'][ RI_Cola::BLOQUEO ] = 1;
RI_Cola::procesar();
comprobar( 'bloqueo impide ejecución simultánea', RI_Cola::resumen()['hechas'], 0 );

echo $fallos ? "$fallos fallos\n" : "0 fallos\n";
exit( $fallos ? 1 : 0 );
