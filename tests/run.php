<?php
// Ejecuta los casos de tests/fixtures/veredicto.json contra RI_Veredicto. Uso: php tests/run.php
define( 'RI_TESTS', true );
require __DIR__ . '/../includes/class-ri-veredicto.php';

$f     = json_decode( file_get_contents( __DIR__ . '/fixtures/veredicto.json' ), true );
$fallos = 0;
foreach ( $f['casos'] as $c ) {
	$r   = RI_Veredicto::evaluar( $f['preguntas'], $c['p'], $c['ampliada'], $f['alto'], $f['bajo'] );
	$got = array( 'nivel' => $r['nivel'], 'motivos' => $r['motivos'], 'detectados' => RI_Veredicto::detectados( $f['preguntas'], $c['p'] ) );
	if ( $got !== $c['esperado'] ) {
		$fallos++;
		echo "FALLO {$c['nombre']}\n  esperado: " . json_encode( $c['esperado'] ) . "\n  obtenido: " . json_encode( $got ) . "\n";
	}
}
printf( "%d casos, %d fallos\n", count( $f['casos'] ), $fallos );
exit( $fallos ? 1 : 0 );
