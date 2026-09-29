# Changelog

## 1.1.1 (2026-09-23), seguridad
- `?trp_co_debug=1` solo funciona para administradores (`manage_options`). Antes cualquier visitante veía page_id, overrides y fragmentos del HTML en un comentario.
- El comentario de debug neutraliza `--` para que el contenido no pueda cerrarlo antes de tiempo.
- Autocompletado de páginas: los resultados se construyen con `.text()`/`.attr()` en lugar de concatenar HTML (DOM XSS con títulos de página).
- La tabla de overrides en el admin ya no renderiza el HTML guardado: muestra el texto escapado. El HTML completo sigue disponible, escapado, en "Show HTML".
- Guardar o editar overrides exige `unfiltered_html` además de `manage_options`, porque se inyecta HTML crudo en el frontend.
- `wp_safe_redirect` e `isset` en todos los parámetros del formulario.
