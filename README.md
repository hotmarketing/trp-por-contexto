# HM TRP Context Overrides

Plugin de WordPress que extiende [TranslatePress](https://wordpress.org/plugins/translatepress-multilingual/) para **sobrescribir traducciones en una página específica**.

TranslatePress traduce cada string de forma global: si "Inicio" se traduce como "Home", queda "Home" en todo el sitio. Con este plugin puedes decir "en *esta* página, y en *este* idioma, usa otra traducción".

## Requisitos

- WordPress 6.0+
- PHP 7.4+
- TranslatePress activo

## Instalación

1. Descarga el ZIP de la última versión desde **[Releases](https://github.com/hotmarketing/trp-por-contexto/releases)** (`hm-trp-context-overrides.zip`).
2. En WordPress: *Plugins → Añadir nuevo → Subir plugin* y sube ese ZIP.
3. Actívalo. Los overrides se administran en *Ajustes → TranslatePress*, pestaña **Context Overrides** (también hay un enlace "Overrides" en la fila del plugin, en la lista de plugins).

> ⚠️ **No uses el botón "Code → Download ZIP" de GitHub.** Ese ZIP trae la carpeta `trp-por-contexto-main/`, y WordPress lo instalaría como un plugin distinto. Si después borras el original desde el admin, se ejecuta su desinstalación, que **elimina la tabla con todos tus overrides**. Usa siempre el ZIP de Releases, cuya carpeta es `hm-trp-context-overrides/`. (Desde la 1.2.0, la desinstalación no borra la tabla si detecta otra copia instalada, pero no dependas de eso.)

## Uso

Cada override tiene:

| Campo | Qué es |
|-------|--------|
| **Override type** | *String Replace* o *Selector* (ver abajo) |
| **Page** | La página o entrada donde aplica |
| **Language** | *All languages* o un idioma de traducción específico |
| **Original / Element ID** | Qué buscar |
| **Contextual translation** | Con qué reemplazarlo |

### Modos

- **String Replace:** reemplazo exacto sobre el HTML ya traducido. En "Original" pega el texto **tal como aparece traducido** en el HTML (puede incluir etiquetas).
- **Selector:** reemplaza el contenido (innerHTML) del elemento con ese `id`. Escribe el id sin `#`.

### Idiomas

Los overrides solo se aplican en idiomas de traducción. El idioma predeterminado del sitio nunca pasa por el filtro de TranslatePress, así que no aparece en el selector.

### Depuración

Como administrador, agrega `?trp_co_debug=1` a cualquier URL traducida. Al final del HTML aparece un comentario con la página detectada, el idioma y qué overrides coincidieron.

## Permisos

- Ver y borrar overrides: `manage_options`.
- Crear o editar: además `unfiltered_html`, porque el contenido se inyecta como HTML crudo en el frontend. Si tu sitio define `DISALLOW_UNFILTERED_HTML`, nadie podrá guardar overrides.

## Actualizar

Desde la 1.2.0 las versiones nuevas aparecen solas en *Escritorio → Actualizaciones*, como cualquier plugin. Los overrides existentes se conservan y el esquema de la tabla se actualiza solo.

Si tienes una versión anterior a la 1.2.0, sube el ZIP de Releases a mano una vez (*Subir plugin → Reemplazar*). A partir de ahí ya se actualiza sola.

## Desinstalar

Al **borrar** el plugin desde el admin se elimina la tabla `{prefijo}trp_context_overrides` y la opción `trp_co_version`, salvo que haya otra copia del plugin instalada. Desactivarlo no borra nada.

## Para desarrolladores

- Filtro que usa: `trp_translated_html`.
- Tabla: `{prefijo}trp_context_overrides`.
- Pruebas: `./tests/run.sh` (PHP puro, sin dependencias).
- Dependencias: `composer install` (solo [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)). `vendor/` no está en el repo; va dentro del ZIP del release.
- **Publicar una versión:** `git tag vX.Y.Z && git push origin vX.Y.Z`. El workflow corre las pruebas, arma `hm-trp-context-overrides.zip` (con `vendor/`), le pone la versión del tag al encabezado y a `TRP_CO_VERSION`, y crea el Release. Un tag con sufijo (`v1.3.0-rc.1`) sale como prerelease y los sitios no lo reciben.

## Licencia

GPL-2.0-or-later. Ver [LICENSE](LICENSE).

Hecho por [Hot Marketing](https://hotmarketing.mx).
