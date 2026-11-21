<?php
/** Revisa una imagen: prepara, pregunta a Jev, calcula el veredicto y lo guarda como etiquetas y meta. */

defined( 'ABSPATH' ) || exit;

class RI_Revisor {

	/** @return array{id:int,estado?:string,motivos?:string,detectados?:string,ampliada?:bool,error?:string} */
	public static function revisar( int $id ): array {
		$cfg = RI_Config::obtener();
		if ( ! $cfg ) {
			return array( 'id' => $id, 'error' => 'no hay configuración (la envía Fede por REST)' );
		}
		$clave = RI_Clave::obtener();
		if ( null === $clave ) {
			return array( 'id' => $id, 'error' => 'no hay clave de TypeSafe (Ajustes > Revisión de imágenes)' );
		}
		if ( 'attachment' !== get_post_type( $id ) || 0 !== strpos( (string) get_post_mime_type( $id ), 'image/' ) ) {
			return array( 'id' => $id, 'error' => 'el medio no existe o no es una imagen' );
		}

		$img = RI_Imagen::preparar( $id );
		if ( isset( $img['error'] ) ) {  // no se puede enviar a Jev: se marca y no cuenta como «no»
			RI_Etiquetas::aplicar( $id, array( 'omitida' => 'Omitida' ) );
			update_post_meta( $id, 'jev_puntuaciones', wp_json_encode( array( 'omitida' => $img['error'] ), JSON_UNESCAPED_UNICODE ) );
			update_post_meta( $id, 'jev_revision_fecha', gmdate( 'c' ) );
			return array( 'id' => $id, 'estado' => 'omitida', 'motivos' => $img['error'] );
		}

		$p = RI_Jev::consultar( $clave, $cfg['preguntas'], $cfg['tema'], $img['jpeg'] );
		if ( is_wp_error( $p ) ) {  // fallo de la API: no se etiqueta nada
			return array( 'id' => $id, 'error' => $p->get_error_message() );
		}

		$alto = isset( $cfg['alto'] ) ? (float) $cfg['alto'] : RI_Veredicto::ALTO;
		$bajo = isset( $cfg['bajo'] ) ? (float) $cfg['bajo'] : RI_Veredicto::BAJO;
		$v    = RI_Veredicto::evaluar( $cfg['preguntas'], $p, $img['ampliada'], $alto, $bajo );
		$det  = RI_Veredicto::detectados( $cfg['preguntas'], $p );

		RI_Etiquetas::aplicar( $id, RI_Etiquetas::calcular( $v['nivel'], $v['motivos'], $det, $img['ampliada'] ) );
		update_post_meta( $id, 'jev_puntuaciones', wp_json_encode( array_merge( $p, array( 'ampliada' => $img['ampliada'] ) ), JSON_UNESCAPED_UNICODE ) );
		update_post_meta( $id, 'jev_revision_fecha', gmdate( 'c' ) );
		return array( 'id' => $id, 'estado' => $v['nivel'], 'motivos' => $v['motivos'], 'detectados' => $det, 'ampliada' => $img['ampliada'] );
	}
}
