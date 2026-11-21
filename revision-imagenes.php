<?php
/**
 * Plugin Name: Revisión de imágenes
 * Description: Taxonomía y metadatos para los veredictos de Jev sobre la biblioteca de medios (skill wp-revisar-imagenes de Fede). Añade columna y filtro en la biblioteca para localizar y borrar las rechazadas.
 * Version: 1.3.1
 * Update URI: https://github.com/oscarmat/revision-imagenes
 *
 * - Taxonomía `revision_imagen` (etiquetas, sin jerarquía): estado (rechazar/revisar/ok), motivos y operadores detectados.
 *   Fede crea los términos y los asigna por REST (POST /wp/v2/revision_imagen y POST /wp/v2/media/{id}).
 * - Meta `jev_puntuaciones` (JSON con la probabilidad de cada pregunta) y `jev_revision_fecha`. Solo lectura en el panel.
 * No borra ni modifica imágenes por sí solo.
 */

defined( 'ABSPATH' ) || exit;

const RI_TAXONOMIA = 'revision_imagen';

require_once __DIR__ . '/includes/class-ri-veredicto.php';
require_once __DIR__ . '/includes/class-ri-clave.php';
require_once __DIR__ . '/includes/class-ri-ajustes.php';
require_once __DIR__ . '/includes/class-ri-imagen.php';
require_once __DIR__ . '/includes/class-ri-jev.php';
require_once __DIR__ . '/includes/class-ri-config.php';
require_once __DIR__ . '/includes/class-ri-etiquetas.php';
require_once __DIR__ . '/includes/class-ri-revisor.php';
require_once __DIR__ . '/includes/class-ri-cola.php';
require_once __DIR__ . '/includes/class-ri-rest.php';
require_once __DIR__ . '/includes/class-ri-actualizador.php';

RI_Ajustes::iniciar();
RI_Cola::iniciar();
RI_Rest::iniciar();
register_deactivation_hook( __FILE__, array( 'RI_Cola', 'desactivar' ) );

// Acción masiva en la biblioteca: añade las imágenes elegidas a la cola de revisión.
add_filter( 'bulk_actions-upload', function ( $acciones ) {
	if ( current_user_can( 'manage_options' ) ) {  // gasta llamadas de pago a Jev: mismo permiso que el resto del plugin
		$acciones['ri_revisar'] = 'Revisar con Jev';
	}
	return $acciones;
} );
add_filter( 'handle_bulk_actions-upload', function ( $url, $accion, $ids ) {
	if ( 'ri_revisar' !== $accion || ! current_user_can( 'manage_options' ) ) {
		return $url;
	}
	$ids = array_slice( array_filter( array_map( 'intval', (array) $ids ), function ( $id ) {
		return current_user_can( 'edit_post', $id );
	} ), 0, 500 );
	return add_query_arg( 'ri_encoladas', RI_Cola::encolar( $ids ), $url );
}, 10, 3 );
add_action( 'admin_notices', function () {
	if ( isset( $_GET['ri_encoladas'] ) ) {
		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( (int) $_GET['ri_encoladas'] ) . ' imágenes añadidas a la cola de revisión.</p></div>';
	}
} );
RI_Actualizador::iniciar();

add_action( 'init', function () {
	register_taxonomy( RI_TAXONOMIA, 'attachment', array(
		'labels'            => array(
			'name'          => 'Revisión de imágenes',
			'singular_name' => 'Etiqueta de revisión',
			'menu_name'     => 'Revisión de imágenes',
			'all_items'     => 'Todas las etiquetas de revisión',
		),
		'public'            => false,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rest_base'         => RI_TAXONOMIA,
		'query_var'         => RI_TAXONOMIA,
		'hierarchical'      => false,
		// Los medios sin entrada padre no entran en el recuento por defecto (salía 0 en todas las etiquetas).
		'update_count_callback' => '_update_generic_term_count',
		'rewrite'           => false,
		'capabilities'      => array(
			'manage_terms' => 'manage_options',
			'edit_terms'   => 'manage_options',
			'delete_terms' => 'manage_options',
			'assign_terms' => 'upload_files',
		),
	) );

	foreach ( array( 'jev_puntuaciones', 'jev_revision_fecha' ) as $clave ) {
		register_post_meta( 'attachment', $clave, array(
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => function () {
				return current_user_can( 'upload_files' );
			},
		) );
	}
} );

// Desplegable de filtro en la biblioteca de medios (vista de lista).
add_action( 'restrict_manage_posts', function ( $post_type = '' ) {
	if ( 'attachment' !== $post_type ) {
		return;
	}
	wp_dropdown_categories( array(
		'taxonomy'        => RI_TAXONOMIA,
		'name'            => RI_TAXONOMIA,
		'value_field'     => 'slug',
		'selected'        => isset( $_GET[ RI_TAXONOMIA ] ) ? sanitize_text_field( wp_unslash( $_GET[ RI_TAXONOMIA ] ) ) : '',
		'show_option_all' => 'Toda revisión',
		'hide_empty'      => false,
		'orderby'         => 'name',
	) );
} );

// Filtro de la biblioteca por etiqueta: se aplica de forma explícita para no depender de la query var de una taxonomía no pública.
add_action( 'pre_get_posts', function ( $q ) {
	global $pagenow;
	if ( ! is_admin() || 'upload.php' !== $pagenow || ! $q->is_main_query() || empty( $_GET[ RI_TAXONOMIA ] ) ) {
		return;
	}
	$tax_query   = (array) $q->get( 'tax_query' );
	$tax_query[] = array(
		'taxonomy' => RI_TAXONOMIA,
		'field'    => 'slug',
		'terms'    => sanitize_title( wp_unslash( $_GET[ RI_TAXONOMIA ] ) ),
	);
	$q->set( 'tax_query', $tax_query );
} );

// Puntuaciones de Jev visibles (solo lectura) al editar un medio.
add_filter( 'attachment_fields_to_edit', function ( $campos, $post ) {
	$json = get_post_meta( $post->ID, 'jev_puntuaciones', true );
	if ( '' === $json ) {
		return $campos;
	}
	$campos['jev_puntuaciones'] = array(
		'label' => 'Puntuaciones Jev',
		'input' => 'html',
		'html'  => '<textarea readonly rows="6" style="width:100%;font-family:monospace">' . esc_textarea( $json ) . '</textarea>',
	);
	return $campos;
}, 10, 2 );
