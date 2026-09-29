# Changelog

## 1.3.0 (2026-09-29)
- La página se mudó de *Ajustes → TRP Context Overrides* a una pestaña **Context Overrides** dentro de *Ajustes → TranslatePress*, junto a las demás pestañas de TranslatePress. Ya no aparece como entrada suelta en el menú Ajustes.
- La URL anterior (`options-general.php?page=trp-context-overrides`) redirige a la nueva, así que los marcadores siguen funcionando.
- Enlace "Overrides" en la fila del plugin, en la lista de plugins.
- La página usa el encabezado y los estilos de TranslatePress, y marca *Ajustes → TranslatePress* en el menú.

## 1.2.1 (2026-09-29)
- El updater solo considera GitHub Releases con el asset `hm-trp-context-overrides.zip`. Antes, si el último release no lo traía, Plugin Update Checker caía al tag más alto o a la rama `main` y ofrecía el código fuente (sin `vendor/`), lo que dejaba el plugin sin updater.

## 1.2.0 (2026-09-29)
- **Actualizaciones automáticas desde GitHub.** Las versiones nuevas aparecen en Escritorio → Actualizaciones (Plugin Update Checker, sin token). Solo se instala el ZIP adjunto al Release, nunca el código fuente. La 1.2.0 hay que subirla a mano una vez; de ahí en adelante se actualiza sola.
- **Overrides por idioma.** El formulario tiene un selector de idioma: "All languages" o cualquiera de los idiomas de traducción de TranslatePress (el predeterminado no aparece porque nunca se traduce). Se guarda el locale completo (p. ej. `en_US`).
- Corrección: la columna `language` siempre se guardaba como `'en'` y el motor no la usaba, así que en un sitio con varios idiomas un override se aplicaba a todos.
- El motor aplica un override si su idioma es `all`/vacío, si coincide con el locale que se está renderizando, o si coincide con el slug de URL de ese locale. Esto último mantiene funcionando las filas creadas antes de 1.2.0 (`'en'` sigue aplicando a `en_US`).
- Al editar una fila antigua con `'en'` se preselecciona el locale cuyo slug es `en`. Si no hay ninguno, se conserva el valor tal cual.
- Nueva columna "Language" en la tabla del admin.
- Si la API de TranslatePress no está disponible, el selector solo ofrece "All languages".
- Desinstalar ya no borra la tabla si hay otra copia del plugin instalada (p. ej. una subida desde "Code → Download ZIP" de GitHub, que usa otra carpeta). Antes, borrar una de las dos copias eliminaba los overrides de ambas.
- La columna `language` pasa de `VARCHAR(10)` a `VARCHAR(20)` para admitir locales como `de_DE_formal`. El upgrade es automático y no toca los datos.

## 1.1.1 (2026-09-23), seguridad
- `?trp_co_debug=1` solo funciona para administradores (`manage_options`). Antes cualquier visitante veía page_id, overrides y fragmentos del HTML en un comentario.
- El comentario de debug neutraliza `--` para que el contenido no pueda cerrarlo antes de tiempo.
- Autocompletado de páginas: los resultados se construyen con `.text()`/`.attr()` en lugar de concatenar HTML (DOM XSS con títulos de página).
- La tabla de overrides en el admin ya no renderiza el HTML guardado: muestra el texto escapado. El HTML completo sigue disponible, escapado, en "Show HTML".
- Guardar o editar overrides exige `unfiltered_html` además de `manage_options`, porque se inyecta HTML crudo en el frontend.
- `wp_safe_redirect` e `isset` en todos los parámetros del formulario.
