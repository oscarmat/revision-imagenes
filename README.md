# Revisión de imágenes

Plugin de WordPress para clasificar la biblioteca de medios con preguntas a Jev (TypeSafe) y localizar las imágenes a borrar.

## Estado

Fase 1 (en curso):
- `revision-imagenes.php`: taxonomía `revision_imagen` (estado, motivos, operadores detectados), meta `jev_puntuaciones` y `jev_revision_fecha`, columna y filtro en la biblioteca.
- `includes/class-ri-veredicto.php`: tabla de verdad (probabilidades -> ok / revisar / rechazar). Port de la skill `wp-revisar-imagenes` de Fede.
- `tests/fixtures/veredicto.json`: casos compartidos con la implementación Python. `php tests/run.php` los ejecuta.

Cola en segundo plano (WP-Cron, un evento cada pocos minutos tras el último planificado), pantalla en Ajustes, acción masiva «Revisar con Jev» y REST `/cola`.

## Diseño

- Fede configura preguntas, umbrales y etiquetas; el plugin ejecuta y guarda los resultados.
- Clave de Jev: `wp_get_secret()` si existe (API de secretos prevista en WordPress 7.2); si no, constante `RI_TYPESAFE_API_KEY` en `wp-config.php`. Nunca en texto plano en la base de datos.
- Borrado manual y nativo desde la biblioteca; con `MEDIA_TRASH` es reversible.
- Las preguntas concretas no viven en este repo, son de cada uso.

## Instalar

Crear el zip con la carpeta `revision-imagenes/` dentro y subirlo en Plugins, Añadir nuevo, Subir plugin.
