<?php
// Cifrado de la clave: ida y vuelta, llave equivocada y valor dañado. Uso: php tests/run-clave.php
define( 'RI_TESTS', true );
require __DIR__ . '/../includes/class-ri-clave.php';

$llave  = sodium_crypto_generichash( 'sal', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
$otra   = sodium_crypto_generichash( 'otra', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
$clave  = 'sk-prueba-1234567890';
$cif    = RI_Clave::cifrar( $clave, $llave );
$checks = array(
	'ida y vuelta'            => RI_Clave::descifrar( $cif, $llave ) === $clave,
	'no contiene la clave'    => false === strpos( base64_decode( $cif ), $clave ),
	'dos cifrados difieren'   => $cif !== RI_Clave::cifrar( $clave, $llave ),
	'llave equivocada -> null' => null === RI_Clave::descifrar( $cif, $otra ),
	'basura -> null'          => null === RI_Clave::descifrar( 'no-es-base64!!', $llave ),
	'vacío -> null'           => null === RI_Clave::descifrar( '', $llave ),
	'pista'                   => '…7890' === RI_Clave::pista( $clave ),
);
$fallos = 0;
foreach ( $checks as $n => $ok ) {
	echo ( $ok ? 'ok     ' : 'FALLO  ' ) . $n . "\n";
	$fallos += $ok ? 0 : 1;
}
exit( $fallos ? 1 : 0 );
