<?php
/**
 * Clave de la API de TypeSafe (Jev).
 *
 * Orden de lectura: constante RI_TYPESAFE_API_KEY en wp-config.php; si no, la guardada desde Ajustes.
 * La guardada va cifrada (libsodium, clave derivada de las sales de wp-config.php): nunca en texto plano
 * en la base de datos ni se vuelve a mostrar. Cuando WordPress incluya la API de secretos (prevista en 7.2)
 * se podrá migrar aquí sin tocar al resto del plugin.
 */

defined( 'ABSPATH' ) || defined( 'RI_TESTS' ) || exit;

class RI_Clave {

	const OPCION = 'ri_typesafe_clave';

	/** Cifra con una clave de 32 bytes. Devuelve base64(nonce . texto cifrado). */
	public static function cifrar( string $clave, string $llave ): string {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return base64_encode( $nonce . sodium_crypto_secretbox( $clave, $nonce, $llave ) );
	}

	/** Devuelve la clave en claro, o null si el valor está dañado o la llave no corresponde. */
	public static function descifrar( string $valor, string $llave ): ?string {
		$bin = base64_decode( $valor, true );
		if ( false === $bin || strlen( $bin ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return null;
		}
		$nonce = substr( $bin, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$claro = sodium_crypto_secretbox_open( substr( $bin, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, $llave );
		return false === $claro ? null : $claro;
	}

	/** Últimos caracteres para reconocerla sin exponerla. */
	public static function pista( string $clave ): string {
		return '…' . substr( $clave, -4 );
	}

	// ---- con WordPress ----

	private static function llave(): string {
		return sodium_crypto_generichash( wp_salt( 'secure_auth' ), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	/** Diagnóstico para `estado`: separa «no hay nada guardado» de «hay algo que no se puede descifrar». */
	public static function diagnostico(): array {
		$valor = (string) get_option( self::OPCION, '' );
		return array(
			'constante'      => defined( 'RI_TYPESAFE_API_KEY' ) && '' !== RI_TYPESAFE_API_KEY,
			'guardada'       => '' !== $valor,
			'descifra'       => '' !== $valor && null !== self::descifrar( $valor, self::llave() ),
			'longitud_guardada' => strlen( $valor ),
		);
	}

	public static function origen(): string {
		if ( defined( 'RI_TYPESAFE_API_KEY' ) && '' !== RI_TYPESAFE_API_KEY ) {
			return 'constante';
		}
		return null !== self::obtener() ? 'ajustes' : 'ninguna';
	}

	public static function obtener(): ?string {
		if ( defined( 'RI_TYPESAFE_API_KEY' ) && '' !== RI_TYPESAFE_API_KEY ) {
			return RI_TYPESAFE_API_KEY;
		}
		$valor = get_option( self::OPCION, '' );
		return '' === $valor ? null : self::descifrar( $valor, self::llave() );
	}

	/** Guarda la clave cifrada y comprueba que se puede leer de vuelta. */
	public static function guardar( string $clave ): bool {
		update_option( self::OPCION, self::cifrar( $clave, self::llave() ), false );
		return self::obtener() === $clave;
	}

	public static function borrar(): void {
		delete_option( self::OPCION );
	}
}
