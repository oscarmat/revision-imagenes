<?php
/** Pantalla Ajustes > Revisión de imágenes: clave de la API de TypeSafe. */

defined( 'ABSPATH' ) || exit;

class RI_Ajustes {

	const PAGINA = 'revision-imagenes';

	public static function iniciar(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_ri_guardar_clave', array( __CLASS__, 'guardar' ) );
		add_filter( 'plugin_action_links_revision-imagenes/revision-imagenes.php', array( __CLASS__, 'enlace' ) );
	}

	/** Enlace «Ajustes» junto al plugin en la lista de Plugins. */
	public static function enlace( array $enlaces ): array {
		array_unshift( $enlaces, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGINA ) ) . '">Ajustes</a>' );
		return $enlaces;
	}

	public static function menu(): void {
		add_options_page( 'Revisión de imágenes', 'Revisión de imágenes', 'manage_options', self::PAGINA, array( __CLASS__, 'pintar' ) );
	}

	public static function guardar(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sin permisos.', 403 );
		}
		check_admin_referer( 'ri_guardar_clave' );
		$aviso = 'sin-cambios';
		if ( ! empty( $_POST['ri_borrar'] ) ) {
			RI_Clave::borrar();
			$aviso = 'borrada';
		} else {
			$clave = isset( $_POST['ri_clave'] ) ? trim( wp_unslash( $_POST['ri_clave'] ) ) : '';
			if ( '' !== $clave ) {
				$aviso = RI_Clave::guardar( $clave ) ? 'guardada' : 'error';
			}
		}
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGINA, 'aviso' => $aviso ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	public static function pintar(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$origen = RI_Clave::origen();
		$clave  = RI_Clave::obtener();
		$avisos = array( 'guardada' => 'Clave guardada.', 'borrada' => 'Clave borrada.', 'sin-cambios' => 'No se ha cambiado nada (el campo llegó vacío).', 'error' => 'No se pudo guardar: la clave no se puede leer de vuelta.' );
		$aviso  = isset( $_GET['aviso'] ) ? sanitize_key( $_GET['aviso'] ) : '';
		echo '<div class="wrap"><h1>Revisión de imágenes</h1>';
		if ( isset( $avisos[ $aviso ] ) ) {
			echo '<div class="notice notice-' . ( 'error' === $aviso ? 'error' : 'info' ) . ' is-dismissible"><p>' . esc_html( $avisos[ $aviso ] ) . '</p></div>';
		}
		echo '<h2>Clave de la API de TypeSafe (Jev)</h2>';
		if ( 'constante' === $origen ) {
			echo '<p>Definida en <code>wp-config.php</code> (<code>RI_TYPESAFE_API_KEY</code>), termina en <code>' . esc_html( RI_Clave::pista( (string) $clave ) ) . '</code>. Tiene prioridad sobre la guardada aquí.</p>';
		} elseif ( 'ajustes' === $origen ) {
			echo '<p>Guardada, cifrada, terminada en <code>' . esc_html( RI_Clave::pista( (string) $clave ) ) . '</code>.</p>';
		} else {
			echo '<p>No hay clave configurada.</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="ri_guardar_clave">';
		wp_nonce_field( 'ri_guardar_clave' );
		echo '<p><label for="ri_clave">Nueva clave</label><br><input type="password" id="ri_clave" name="ri_clave" class="regular-text" autocomplete="off"></p>';
		echo '<p><button class="button button-primary" type="submit">Guardar clave</button> ';
		if ( 'ajustes' === $origen ) {
			echo '<button class="button" type="submit" name="ri_borrar" value="1">Borrar la guardada</button>';
		}
		echo '</p></form></div>';
	}
}
