<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Acceso a los idiomas configurados en TranslatePress y reglas de coincidencia
 * de la columna `language` de los overrides.
 *
 * Valores posibles de `language`:
 * - 'all' o '': aplica a todos los idiomas de traducción.
 * - Un locale completo (p. ej. 'en_US'): formato guardado desde la v1.2.0.
 * - Un slug de URL (p. ej. 'en'): filas legacy de v1.0–1.1, que siempre guardaban 'en'.
 */
class TRP_CO_Languages {

    const ALL = 'all';

    private static $settings = null;

    /**
     * Settings de TranslatePress, o array vacío si la API no está disponible.
     */
    private static function get_trp_settings() {
        if ( self::$settings !== null ) {
            return self::$settings;
        }

        self::$settings = array();

        if ( class_exists( 'TRP_Translate_Press' ) && method_exists( 'TRP_Translate_Press', 'get_trp_instance' ) ) {
            $trp = TRP_Translate_Press::get_trp_instance();
            if ( $trp && method_exists( $trp, 'get_component' ) ) {
                $component = $trp->get_component( 'settings' );
                if ( $component && method_exists( $component, 'get_settings' ) ) {
                    $settings = $component->get_settings();
                    if ( is_array( $settings ) ) {
                        self::$settings = $settings;
                    }
                }
            }
        }

        return self::$settings;
    }

    /**
     * Idiomas de traducción (sin el predeterminado), como locale => nombre.
     * Vacío si la API de TranslatePress no está disponible.
     */
    public static function get_translation_languages() {
        $settings = self::get_trp_settings();

        if ( empty( $settings['translation-languages'] ) || ! is_array( $settings['translation-languages'] ) ) {
            return array();
        }

        $default = isset( $settings['default-language'] ) ? $settings['default-language'] : '';
        $codes   = array_values( array_diff( $settings['translation-languages'], array( $default ) ) );

        $names = array();
        $trp   = TRP_Translate_Press::get_trp_instance();
        $langs = $trp ? $trp->get_component( 'languages' ) : null;
        if ( $langs && method_exists( $langs, 'get_language_names' ) ) {
            $names = $langs->get_language_names( $codes );
        }

        $result = array();
        foreach ( $codes as $code ) {
            $result[ $code ] = ! empty( $names[ $code ] ) ? $names[ $code ] : $code;
        }

        return $result;
    }

    /**
     * Slug de URL de un locale (p. ej. 'en_US' => 'en'), o '' si no se conoce.
     */
    public static function get_url_slug( $locale ) {
        $settings = self::get_trp_settings();

        if ( isset( $settings['url-slugs'][ $locale ] ) ) {
            return (string) $settings['url-slugs'][ $locale ];
        }

        return '';
    }

    /**
     * ¿El valor guardado aplica a todos los idiomas?
     */
    public static function is_all( $value ) {
        return $value === '' || $value === null || $value === self::ALL;
    }

    /**
     * ¿Un override con este `language` aplica al locale que se está renderizando?
     *
     * La comparación con el slug de URL es compatibilidad: las filas legacy tienen
     * 'en' y deben seguir aplicándose a 'en_US'.
     */
    public static function matches( $value, $locale ) {
        if ( self::is_all( $value ) ) {
            return true;
        }

        if ( $value === $locale ) {
            return true;
        }

        $slug = self::get_url_slug( $locale );

        return $slug !== '' && $value === $slug;
    }

    /**
     * Convierte un valor guardado al locale que le corresponde entre los idiomas
     * de traducción (para preseleccionarlo al editar filas legacy). Si no hay
     * coincidencia devuelve el valor tal cual.
     */
    public static function resolve_locale( $value ) {
        if ( self::is_all( $value ) ) {
            return self::ALL;
        }

        $languages = self::get_translation_languages();

        if ( isset( $languages[ $value ] ) ) {
            return $value;
        }

        foreach ( array_keys( $languages ) as $locale ) {
            if ( self::get_url_slug( $locale ) === $value ) {
                return $locale;
            }
        }

        return $value;
    }

    /**
     * Etiqueta legible para la tabla del admin.
     */
    public static function get_label( $value ) {
        if ( self::is_all( $value ) ) {
            return 'All languages';
        }

        $languages = self::get_translation_languages();
        $locale    = self::resolve_locale( $value );

        if ( isset( $languages[ $locale ] ) ) {
            return $languages[ $locale ];
        }

        return $value;
    }
}
