<?php
// Piezas puras: plan de imagen, cuerpo/respuesta de Jev, validación de config y etiquetas. Uso: php tests/run-piezas.php
define( 'RI_TESTS', true );
foreach ( array( 'imagen', 'jev', 'config', 'etiquetas' ) as $f ) {
	require __DIR__ . "/../includes/class-ri-{$f}.php";
}

$fallos = 0;
function comprobar( string $nombre, $obtenido, $esperado ): void {
	global $fallos;
	$ok = $obtenido === $esperado;
	echo ( $ok ? 'ok     ' : 'FALLO  ' ) . $nombre . "\n";
	if ( ! $ok ) {
		$fallos++;
		echo '  esperado: ' . json_encode( $esperado ) . "\n  obtenido: " . json_encode( $obtenido ) . "\n";
	}
}
function plan( $w, $h ) {
	$p = RI_Imagen::plan( $w, $h );
	return array( $p['w'], $p['h'], $p['ampliada'], $p['error'] );
}

// --- plan de imagen (mismas reglas que normalizar() en Python)
comprobar( 'grande se reduce', plan( 4000, 3000 ), array( 1568, 1176, false, null ) );
comprobar( 'dentro de rango no cambia', plan( 800, 600 ), array( 800, 600, false, null ) );
comprobar( 'miniatura 100x100 se amplía', plan( 100, 100 ), array( 280, 280, true, null ) );
comprobar( '300x300 no se amplía', plan( 300, 300 ), array( 300, 300, false, null ) );
comprobar( '280x280 justo en el área mínima', plan( 280, 280 ), array( 280, 280, false, null ) );
comprobar( '1000x270 se amplía', plan( 1000, 270 ), array( 1037, 280, true, null ) );
comprobar( 'banner extremo se omite', plan( 2000, 100 )[3] !== null, true );
comprobar( 'dimensiones 0 se omiten', plan( 0, 10 )[3], 'dimensiones no válidas' );

// --- Jev: cuerpo y respuesta
$qs   = array( 'a' => array( 'instructions' => 'Is `imagen` about {tema}?' ), 'b' => array( 'instructions' => 'x' ) );
$body = RI_Jev::cuerpo( $qs, 'locutorios', 'JPEG' );
comprobar( 'cuerpo: tema sustituido', $body['questions']['a']['instructions'], 'Is `imagen` about locutorios?' );
comprobar( 'cuerpo: imagen en base64', $body['assets']['img1']['data_base64'], base64_encode( 'JPEG' ) );
comprobar( 'cuerpo: una pregunta Noul por entrada', array_column( $body['questions'], 'type' ), array( 'noul', 'noul' ) );
comprobar( 'parsear ok', RI_Jev::parsear( '{"answers":{"a":{"noul":0.9},"b":{"noul":"0.1"}}}', $qs ), array( 'a' => 0.9, 'b' => 0.1 ) );
comprobar( 'parsear: falta pregunta -> error', is_string( RI_Jev::parsear( '{"answers":{"a":{"noul":0.9}}}', $qs ) ), true );
comprobar( 'parsear: sin answers -> error', is_string( RI_Jev::parsear( '{"error":"x"}', $qs ) ), true );
comprobar( 'parsear: no JSON -> error', is_string( RI_Jev::parsear( 'oops', $qs ) ), true );

// --- config
$ok = array( 'tema' => 't', 'preguntas' => array(
	'es_logotipo' => array( 'instructions' => 'x' ),
	'baja_calidad' => array( 'instructions' => 'x', 'malo' => 'si', 'ignorar_si' => 'es_logotipo' ),
) );
comprobar( 'config válida', RI_Config::validar( $ok ), array() );
comprobar( 'config sin tema', count( RI_Config::validar( array( 'preguntas' => $ok['preguntas'] ) ) ), 1 );
$mal = $ok;
$mal['preguntas']['baja_calidad']['ignorar_si'] = 'no_existe';
comprobar( 'ignorar_si a pregunta inexistente', count( RI_Config::validar( $mal ) ), 1 );
$mal = $ok;
$mal['preguntas']['baja_calidad']['malo'] = 'quizá';
comprobar( 'malo inválido', count( RI_Config::validar( $mal ) ), 1 );
comprobar( 'sin preguntas', count( RI_Config::validar( array( 'tema' => 't' ) ) ), 1 );

// --- etiquetas
comprobar( 'calcular', RI_Etiquetas::calcular( 'rechazar', 'aparecen_ninos=0.90,baja_calidad=0.50', 'western_union', true ), array(
	'estado-rechazar' => 'Rechazar', 'motivo-aparecen-ninos' => 'Motivo: aparecen_ninos', 'motivo-baja-calidad' => 'Motivo: baja_calidad',
	'operador-western-union' => 'Operador: western_union', 'ampliada' => 'Ampliada',
) );
comprobar( 'calcular ok sin nada', RI_Etiquetas::calcular( 'ok', '', '', false ), array( 'estado-ok' => 'Ok' ) );
comprobar( 'combinar respeta las manuales', RI_Etiquetas::combinar( array( 'estado-revisar', 'mi-etiqueta', 'ampliada' ), array( 'estado-ok' ) ), array( 'mi-etiqueta', 'estado-ok' ) );

printf( "%d fallos\n", $fallos );
exit( $fallos ? 1 : 0 );
