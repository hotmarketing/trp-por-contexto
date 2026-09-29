<?php
// Pruebas del filtrado por idioma con stubs de WordPress y TranslatePress. Sin dependencias.
define('ABSPATH', '/');
$GLOBALS['TRP_SETTINGS'] = null; $GLOBALS['ROWS'] = array();
function add_filter(){} function get_queried_object_id(){ return 42; } function get_the_ID(){ return 42; }
function current_user_can(){ return false; }
class TRP_Settings_Stub { function get_settings(){ return $GLOBALS['TRP_SETTINGS']; } }
class TRP_Langs_Stub { function get_language_names($c){ $n=array('en_US'=>'English','fr_FR'=>'Français','de_DE_formal'=>'Deutsch (Sie)'); return array_intersect_key($n, array_flip($c)); } }
class TRP_Translate_Press { static function get_trp_instance(){ return new self; } function get_component($c){ return $c==='settings'? new TRP_Settings_Stub : new TRP_Langs_Stub; } }
class TRP_CO_Database { static function get_overrides($p){ return $GLOBALS['ROWS']; } }
$R=dirname(__DIR__).'/includes/';
require $R.'class-languages.php'; require $R.'class-override-engine.php';
function row($id,$lang,$o,$t){ return (object)array('id'=>$id,'language'=>$lang,'override_type'=>'string','original'=>$o,'translated'=>$t); }
function reset_cache(){ $r=new ReflectionProperty('TRP_CO_Languages','settings'); $r->setAccessible(true); $r->setValue(null,null); }
$fail=0; function check($name,$got,$exp){ global $fail; $ok=$got===$exp; if(!$ok)$fail++; echo ($ok?'OK  ':'FAIL')." $name".($ok?'':" => got [$got] exp [$exp]")."\n"; }

// 1) guanajuato.mx: es -> en, filas legacy 'en'
$GLOBALS['TRP_SETTINGS']=array('default-language'=>'es_MX','translation-languages'=>array('es_MX','en_US'),'url-slugs'=>array('es_MX'=>'es','en_US'=>'en'));
$GLOBALS['ROWS']=array(row(1,'en','Hola','HELLO'));
$e=new TRP_CO_Override_Engine;
check('legacy en aplica a en_US', $e->apply_overrides('<p>Hola</p>','en_US','en_US'), '<p>HELLO</p>');
check('idiomas del form (sin default)', json_encode(TRP_CO_Languages::get_translation_languages()), '{"en_US":"English"}');
check('legacy en preselecciona en_US', TRP_CO_Languages::resolve_locale('en'), 'en_US');
check('etiqueta legacy', TRP_CO_Languages::get_label('en'), 'English');

// 2) multi-idioma es -> en, fr, de formal
reset_cache();
$GLOBALS['TRP_SETTINGS']=array('default-language'=>'es_MX','translation-languages'=>array('es_MX','en_US','fr_FR','de_DE_formal'),'url-slugs'=>array('es_MX'=>'es','en_US'=>'en','fr_FR'=>'fr','de_DE_formal'=>'de'));
$GLOBALS['ROWS']=array(row(1,'en_US','a1','x_en'), row(2,'fr_FR','b2','x_fr'), row(3,'all','c3','x_all'), row(4,'','d4','x_empty'), row(5,'en','e5','x_legacy'), row(6,'de_DE_formal','f6','x_de'));
$h='a1|b2|c3|d4|e5|f6';
$e=new TRP_CO_Override_Engine; check('render en_US', $e->apply_overrides($h,'en_US','en_US'), 'x_en|b2|x_all|x_empty|x_legacy|f6');
$e=new TRP_CO_Override_Engine; check('render fr_FR', $e->apply_overrides($h,'fr_FR','fr_FR'), 'a1|x_fr|x_all|x_empty|e5|f6');
$e=new TRP_CO_Override_Engine; check('render de_DE_formal', $e->apply_overrides($h,'de_DE_formal','de_DE_formal'), 'a1|b2|x_all|x_empty|e5|x_de');
$e=new TRP_CO_Override_Engine; check('preview: usa $language_code', $e->apply_overrides($h,'es_MX','fr_FR'), 'a1|x_fr|x_all|x_empty|e5|f6');
$e=new TRP_CO_Override_Engine; check('sin locale: aplica todo (como antes)', $e->apply_overrides($h,'',''), 'x_en|x_fr|x_all|x_empty|x_legacy|x_de');

// 3) API de TranslatePress no disponible
reset_cache(); $GLOBALS['TRP_SETTINGS']=null;
check('sin API: form solo Todos', json_encode(TRP_CO_Languages::get_translation_languages()), '[]');
check('sin API: legacy en se conserva', TRP_CO_Languages::resolve_locale('en'), 'en');
$GLOBALS['ROWS']=array(row(1,'all','A','ALL'), row(2,'en_US','B','EN'));
$e=new TRP_CO_Override_Engine; check('sin API: en_US exacto sigue aplicando', $e->apply_overrides('A|B','en_US','en_US'), 'ALL|EN');
echo $fail ? "\n$fail FALLAS\n" : "\nTodo OK\n";
exit($fail ? 1 : 0);
