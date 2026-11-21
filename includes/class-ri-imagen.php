<?php
/**
 * Prepara una imagen de la biblioteca para Jev: JPEG de 280-1568 px por lado y 78.400 px de área mínima.
 *
 * Jev no reescala y rechaza lados menores de 280 px, así que las imágenes pequeñas se amplían
 * (`ampliada` = true: salen borrosas y calidad, tema y defectos de construcción no son fiables).
 * Port de normalizar() de la skill wp-revisar-imagenes. Usa Imagick; si no está, GD.
 */

defined( 'ABSPATH' ) || defined( 'RI_TESTS' ) || exit;

class RI_Imagen {

	const LADO_MIN    = 280;
	const LADO_MAX    = 1568;
	const PIXELES_MIN = 78400;

	/**
	 * Tamaño final para Jev a partir del original (sin tocar píxeles).
	 *
	 * @return array{w:int,h:int,ampliada:bool,error:?string}
	 */
	public static function plan( int $w, int $h ): array {
		if ( $w < 1 || $h < 1 ) {
			return array( 'w' => $w, 'h' => $h, 'ampliada' => false, 'error' => 'dimensiones no válidas' );
		}
		$nw = $w;
		$nh = $h;
		if ( max( $w, $h ) > self::LADO_MAX ) {
			$s  = self::LADO_MAX / max( $w, $h );
			$nw = (int) round( $w * $s );
			$nh = (int) round( $h * $s );
		}
		$ampliada = min( $nw, $nh ) < self::LADO_MIN;
		if ( $ampliada ) {
			$s  = self::LADO_MIN / min( $w, $h );
			$nw = (int) round( $w * $s );
			$nh = (int) round( $h * $s );
		}
		$error = null;
		if ( max( $nw, $nh ) > self::LADO_MAX || $nw * $nh < self::PIXELES_MIN ) {
			$error = sprintf( 'proporción extrema (%dx%d); Jev admite 280-1568 px por lado', $nw, $nh );
		}
		return array( 'w' => $nw, 'h' => $nh, 'ampliada' => $ampliada, 'error' => $error );
	}

	/** Formatos que este servidor sabe leer, para el diagnóstico. */
	public static function soporte(): array {
		$s = array( 'imagick' => class_exists( 'Imagick' ), 'gd' => function_exists( 'gd_info' ) );
		if ( $s['imagick'] ) {
			$s['imagick_version'] = Imagick::getVersion()['versionString'] ?? '';
			$s['imagick_formatos'] = array_values( array_intersect( array( 'AVIF', 'WEBP', 'HEIC', 'GIF', 'PNG', 'JPEG' ), Imagick::queryFormats() ) );
		}
		if ( $s['gd'] ) {
			$g = gd_info();
			$s['gd_avif'] = ! empty( $g['AVIF Support'] );
			$s['gd_webp'] = ! empty( $g['WebP Support'] );
		}
		return $s;
	}

	/**
	 * @return array{jpeg:string,ampliada:bool}|array{error:string}
	 */
	public static function preparar( int $id ): array {
		$ruta = get_attached_file( $id );
		if ( ! $ruta || ! is_readable( $ruta ) ) {
			return array( 'error' => 'no se encuentra el fichero del medio' );
		}
		$fallo = null;
		if ( class_exists( 'Imagick' ) ) {
			try {
				return self::con_imagick( $ruta );
			} catch ( Throwable $e ) {  // p. ej. ImageMagick sin soporte de AVIF: se prueba con GD
				$fallo = $e;
			}
		}
		if ( function_exists( 'imagecreatefromstring' ) ) {
			try {
				return self::con_gd( $ruta );
			} catch ( Throwable $e ) {
				$fallo = $e;
			}
		}
		if ( $fallo ) {
			return array( 'error' => 'no se pudo convertir: ' . substr( $fallo->getMessage(), 0, 120 ) );
		}
		return array( 'error' => 'el servidor no tiene Imagick ni GD' );
	}

	private static function con_imagick( string $ruta ): array {
		$im = new Imagick();
		$im->readImage( $ruta . '[0]' );
		if ( method_exists( $im, 'autoOrient' ) ) {
			$im->autoOrient();
		}
		$im->setImageBackgroundColor( 'white' );
		$im = $im->mergeImageLayers( Imagick::LAYERMETHOD_FLATTEN );
		$im->transformImageColorspace( Imagick::COLORSPACE_SRGB );
		$plan = self::plan( $im->getImageWidth(), $im->getImageHeight() );
		if ( $plan['error'] ) {
			return array( 'error' => $plan['error'] );
		}
		if ( $plan['w'] !== $im->getImageWidth() || $plan['h'] !== $im->getImageHeight() ) {
			$im->resizeImage( $plan['w'], $plan['h'], Imagick::FILTER_LANCZOS, 1 );
		}
		$im->setImageFormat( 'jpeg' );
		$im->setImageCompressionQuality( 90 );
		$im->stripImage();
		return array( 'jpeg' => $im->getImageBlob(), 'ampliada' => $plan['ampliada'] );
	}

	private static function con_gd( string $ruta ): array {
		$origen = @imagecreatefromstring( (string) file_get_contents( $ruta ) );
		if ( ! $origen ) {
			return array( 'error' => 'GD no puede leer este formato' );
		}
		$w    = imagesx( $origen );
		$h    = imagesy( $origen );
		$plan = self::plan( $w, $h );
		if ( $plan['error'] ) {
			return array( 'error' => $plan['error'] );
		}
		$lienzo = imagecreatetruecolor( $plan['w'], $plan['h'] );
		imagefill( $lienzo, 0, 0, imagecolorallocate( $lienzo, 255, 255, 255 ) );
		imagecopyresampled( $lienzo, $origen, 0, 0, 0, 0, $plan['w'], $plan['h'], $w, $h );
		ob_start();
		imagejpeg( $lienzo, null, 90 );
		return array( 'jpeg' => (string) ob_get_clean(), 'ampliada' => $plan['ampliada'] );
	}
}
