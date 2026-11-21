<?php
defined( 'ABSPATH' ) || exit;

/**
 * Autoactualización desde las releases de GitHub (Update URI https://github.com/oscarmat/revision-imagenes).
 * Solo se activa si la carpeta del plugin es real en este sitio: si es un enlace simbólico (otro sitio
 * comparte la carpeta), el actualizador de WordPress la borraría y rompería el enlace.
 */
class RI_Actualizador {
	const REPO = 'oscarmat/revision-imagenes';
	const ASSET = 'revision-imagenes.zip';
	const CACHE = 'ri_ultima_release';

	public static function iniciar() {
		if ( is_link( WP_PLUGIN_DIR . '/revision-imagenes' ) ) {
			return;
		}
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'filtrar' ), 10, 4 );
	}

	/** Extrae versión y zip de la respuesta de la API de GitHub; null si no es válida. */
	public static function interpretar( $release ) {
		if ( ! is_array( $release ) || empty( $release['tag_name'] ) || empty( $release['assets'] ) ) {
			return null;
		}
		$version = ltrim( (string) $release['tag_name'], 'vV' );
		foreach ( $release['assets'] as $asset ) {
			if ( isset( $asset['name'], $asset['browser_download_url'] ) && self::ASSET === $asset['name'] ) {
				return array( 'version' => $version, 'package' => $asset['browser_download_url'], 'url' => $release['html_url'] ?? '' );
			}
		}
		return null;
	}

	public static function filtrar( $update, $datos, $archivo, $locales ) {
		if ( 'revision-imagenes/revision-imagenes.php' !== $archivo ) {
			return $update;
		}
		$info = get_transient( self::CACHE );
		if ( false === $info ) {
			$r = wp_remote_get( 'https://api.github.com/repos/' . self::REPO . '/releases/latest', array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'application/vnd.github+json' ),
			) );
			$info = ( ! is_wp_error( $r ) && 200 === wp_remote_retrieve_response_code( $r ) )
				? self::interpretar( json_decode( wp_remote_retrieve_body( $r ), true ) )
				: null;
			set_transient( self::CACHE, $info ?: array(), 6 * HOUR_IN_SECONDS );
		}
		if ( empty( $info['version'] ) ) {
			return $update;
		}
		return array(
			'slug'    => 'revision-imagenes',
			'version' => $info['version'],
			'url'     => $info['url'],
			'package' => $info['package'],
		);
	}
}
