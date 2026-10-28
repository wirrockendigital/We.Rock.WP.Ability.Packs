<?php
/** Native Polylang operations with bounded inputs and optimistic revisions. */
declare(strict_types=1);
namespace WeRock\PolylangAbilities;
defined('ABSPATH') || exit;
final class Adapter {
    public const SETTINGS=['default_lang','hide_default','rewrite','redirect_lang','browser','media_support','post_types','taxonomies','sync'];

    /** Return stable errors without exposing native exception or database diagnostics. */
    public static function error(string $code,string $message):\WP_Error {
        return new \WP_Error('wrd_polylang_'.$code,$message,['status'=>409]);
    }

    /** Require native APIs per operation; source versions are evidence, never a gate. */
    public static function guard(string $op,array $a=[]):?\WP_Error {
        if(!function_exists('PLL') || !defined('POLYLANG_VERSION') || !is_object(PLL()))return self::error('target','Active Polylang is required.');
        if(is_multisite() || defined('POLYLANG_PRO'))return self::error('edition','This adapter targets single-site Polylang Free.');
        if(!current_user_can('manage_options'))return self::error('permission','Native administration permission is required.');
        $m=PLL()->model??null;
        $required=[[$m->languages??null,'get_list'],[$m->languages??null,'get'],[PLL()->options??null,'get_all']];
        if(in_array($op,['get-settings','update-setting'],true))$required[]=[PLL()->options??null,'get_schema'];
        if($op==='update-setting')foreach(['get','set','save'] as $method)$required[]=[PLL()->options??null,$method];
        if(in_array($op,['create-language','update-language'],true)) {
            if(!current_user_can('manage_languages'))return self::error('permission','Native language permission is required.');
            $required[]=[$m->languages??null,$op==='create-language'?'add':'update'];
            if(!class_exists('WP_Syntex\\Polylang\\REST\\V1\\Languages'))return self::error('native_api','Native language controller is unavailable.');
        }
        if($op==='update-setting'&&!class_exists('WP_Syntex\\Polylang\\REST\\V1\\Settings'))return self::error('native_api','Native settings controller is unavailable.');
        if(in_array($op,['list-strings','translate-string'],true)) {
            if(!current_user_can('manage_translations'))return self::error('permission','Native translation permission is required.');
            $required[]=['PLL_Admin_Strings','get_strings'];
            $required[]=['PLL_Admin_Strings','sanitize_string_translation'];
            foreach($op==='translate-string'?['import_from_db','export_to_db','translate_if_any','make_entry','add_entry','delete_entry']:['import_from_db','translate_if_any'] as $method) {
                if(!method_exists('PLL_MO',$method))return self::error('native_api','Native string catalog API is unavailable.');
            }
        }
        $kind=$a['kind']??'post';
        if(in_array($op,['list-content','get-content','set-content-language','link-translation','unlink-translation','get-language-links'],true)) {
            foreach(['pll_is_translated_post_type','pll_is_translated_taxonomy','pll_home_url',"pll_get_{$kind}_language","pll_get_{$kind}_translations"] as $fn)if(!is_callable($fn))return self::error('native_api','Required native function is unavailable: '.$fn);
            if($op==='set-content-language'&&!is_callable("pll_set_{$kind}_language"))return self::error('native_api','Native language assignment is unavailable.');
            if($op==='link-translation'&&!is_callable("pll_save_{$kind}_translations"))return self::error('native_api','Native translation linking is unavailable.');
            if($op==='unlink-translation')$required[]=[$m->$kind??null,'delete_translation'];
        }
        foreach($required as [$object,$method])if(!(is_object($object)||is_string($object)) || !is_callable([$object,$method]))return self::error('native_api','A required native method is unavailable: '.$method);
        return null;
    }

    /** Hash a stable logical state, preserving scalar types. */
    private static function hash(array $state):string {return hash('sha256',wp_json_encode($state));}

    /** Read only stable language properties; computed HTML never enters revisions. */
    private static function languages():array {
        $out=[];
        foreach(PLL()->model->languages->get_list() as $language)$out[]=array_intersect_key($language->to_array(),array_flip(['term_id','slug','locale','name','is_rtl','term_group','flag_code','is_default']));
        usort($out,static fn($a,$b)=>$a['term_id']<=>$b['term_id']);
        return $out;
    }

    /** Include settings and languages in every revision to detect context changes. */
    private static function config():array {return ['languages'=>self::languages(),'settings'=>PLL()->options->get_all()];}

    /** Resolve a language rather than permitting native silent fallback. */
    private static function language(string $slug) {
        return PLL()->model->languages->get($slug) ?: self::error('language','Configured language not found.');
    }

    /** Validate object existence, translatability and per-object native permissions. */
    private static function content(string $kind,int $id) {
        $object=$kind==='post'?get_post($id):get_term($id);
        if(!$object || is_wp_error($object))return self::error('missing','Content not found.');
        $type=$kind==='post'?$object->post_type:$object->taxonomy;
        $valid=$kind==='post'?pll_is_translated_post_type($type):pll_is_translated_taxonomy($type);
        if(!$valid)return self::error('type','This content type is not translated by Polylang.');
        if(!current_user_can($kind==='post'?'edit_post':'edit_term',$id))return self::error('permission','The content is not editable by this user.');
        $language="pll_get_{$kind}_language";$translations="pll_get_{$kind}_translations";
        $group=$translations($id);ksort($group);
        return ['kind'=>$kind,'id'=>$id,'type'=>$type,'title'=>$kind==='post'?$object->post_title:$object->name,'language'=>$language($id)?:null,'translations'=>$group,'modified'=>$kind==='post'?$object->post_modified_gmt:null];
    }

    /** Include all group members and verify edit rights before a group mutation. */
    private static function content_state(string $kind,int $id) {
        $object=self::content($kind,$id);if(is_wp_error($object))return $object;
        $members=[];
        foreach($object['translations'] as $member) {
            $row=self::content($kind,(int)$member);if(is_wp_error($row))return $row;
            $members[]=$row;
        }
        return ['content'=>$object,'revision'=>self::hash([self::config(),$object,$members])];
    }

    /** Obtain the native catalog visible in this runtime and retain stable IDs. */
    private static function strings():array {
        $out=[];
        foreach(\PLL_Admin_Strings::get_strings() as $string) {
            $id=hash('sha256',$string['string']);$out[$id]=$string;
        }
        ksort($out);return $out;
    }

    /** Guard against overwriting another string edit in the same language catalog. */
    private static function string_state(string $slug) {
        $language=self::language($slug);if(is_wp_error($language))return $language;
        $strings=self::strings();$mo=new \PLL_MO();$mo->import_from_db($language);
        return ['language'=>$language,'strings'=>$strings,'mo'=>$mo,'revision'=>self::hash([self::config(),$strings,get_term_meta($language->term_id,'_pll_strings_translations',true)])];
    }

    /** Execute bounded reads, or serialize a preview/write under a per-site advisory lock. */
    public static function run(string $op,array $a,bool $mutation=false) {
        if($op==='get-status') {
            $availability=[];
            foreach(ACFW_Pack::definitions() as $name=>$def) {
                $error=self::guard(substr($name,9));$availability[$name]=$error?$error->get_error_code():'available';
            }
            return ['version'=>defined('POLYLANG_VERSION')?POLYLANG_VERSION:null,'source_version'=>'3.9.1','version_policy'=>'informational','free'=>defined('POLYLANG_VERSION')&&!defined('POLYLANG_PRO'),'operations'=>$availability];
        }
        if($error=self::guard($op,$a))return $error;
        if($mutation) {
            global $wpdb;
            $lock='wrd_pll_'.substr(hash('sha256',DB_NAME.':'.$wpdb->prefix),0,48);
            if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))!==1)return self::error('busy','Another pack write is in progress.');
            try {return self::write($op,$a);}
            finally {$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
        }
        if($op==='list-languages')return ['languages'=>self::languages(),'revision'=>self::hash(self::config())];
        if($op==='get-settings')return ['settings'=>PLL()->options->get_all(),'writable_schema'=>array_intersect_key(PLL()->options->get_schema()['properties'],array_flip(self::SETTINGS)),'post_types'=>array_values(PLL()->model->get_translated_post_types()),'taxonomies'=>array_values(PLL()->model->get_translated_taxonomies()),'available_post_types'=>array_values(get_post_types(['_builtin'=>false])),'available_taxonomies'=>array_values(array_diff(get_taxonomies(['_builtin'=>false]),get_taxonomies(['_pll'=>true]))),'revision'=>self::hash(self::config())];
        if($op==='get-content')return self::content_state($a['kind'],$a['id']);
        if($op==='list-content')return self::list_content($a);
        if($op==='list-strings') {
            $state=self::string_state($a['language']);if(is_wp_error($state))return $state;
            $rows=[];
            foreach($state['strings'] as $id=>$string) {
                if(isset($a['search'])&&stripos($string['string'].' '.$string['name'],$a['search'])===false)continue;
                $rows[]=['id'=>$id]+$string+['translation'=>$state['mo']->translate_if_any($string['string'])];
            }
            return ['items'=>array_slice($rows,$a['offset']??0,$a['limit']??25),'total'=>count($rows),'revision'=>$state['revision'],'catalog_scope'=>'strings registered in this request'];
        }
        if($op==='get-language-links') {
            $translations=[];
            if(isset($a['post_id'])) {$post=self::content('post',$a['post_id']);if(is_wp_error($post))return $post;$translations=$post['translations'];}
            $links=[];
            foreach(self::languages() as $language) {
                $slug=$language['slug'];$id=$translations[$slug]??null;
                $links[]=['language'=>$slug,'name'=>$language['name'],'home_url'=>pll_home_url($slug),'post_id'=>$id,'url'=>isset($a['post_id'])?($id?get_permalink($id):null):pll_home_url($slug)];
            }
            return ['links'=>$links];
        }
        return self::error('operation','Unknown operation.');
    }

    /** Page native content without leaking body text or accepting arbitrary query arguments. */
    private static function list_content(array $a) {
        $type=$a['type'];$kind=$a['kind'];
        if(!($kind==='post'?pll_is_translated_post_type($type):pll_is_translated_taxonomy($type)))return self::error('type','Type is not translated.');
        if(isset($a['language'])&&is_wp_error(self::language($a['language'])))return self::error('language','Configured language not found.');
        $args=['lang'=>$a['language']??'','offset'=>$a['offset']??0,'orderby'=>'ID','order'=>'ASC'];
        if($kind==='post') {
            $q=new \WP_Query($args+['post_type'=>$type,'post_status'=>['publish','draft','pending','private','future','inherit'],'posts_per_page'=>$a['limit']??25,'s'=>$a['search']??'','fields'=>'ids']);$ids=$q->posts;
        } else {
            $ids=get_terms($args+['taxonomy'=>$type,'hide_empty'=>false,'number'=>$a['limit']??25,'search'=>$a['search']??'','fields'=>'ids']);
            if(is_wp_error($ids))return self::error('query','Native term query failed.');
        }
        $rows=[];foreach($ids as $id){$row=self::content($kind,(int)$id);if(!is_wp_error($row))$rows[]=$row;}
        return ['items'=>$rows,'offset'=>$a['offset']??0,'limit'=>$a['limit']??25,'next_offset'=>count($ids)===($a['limit']??25)?($a['offset']??0)+count($ids):null];
    }

    /** Validate a native REST request fully before executing any controller mutation. */
    private static function request(string $route,array $params) {
        $request=new \WP_REST_Request('POST',$route);$request->set_body_params($params);
        // Match the registered route to obtain the vendor validation and sanitization callbacks.
        $routes=rest_get_server()->get_routes();$attributes=null;
        foreach($routes as $pattern=>$handlers)if(preg_match('@^'.$pattern.'$@',$route)) {
            foreach($handlers as $handler)if(!empty($handler['methods']['POST'])){$attributes=$handler;break 2;}
        }
        if(!$attributes)return self::error('native_api','Native REST route is unavailable.');
        $request->set_attributes($attributes);
        if(preg_match('~/languages/(\d+)$~',$route,$match))$request->set_url_params(['term_id'=>(int)$match[1]]);
        $valid=$request->has_valid_params();if(is_wp_error($valid))return self::error('input','Native schema validation failed.');
        $valid=$request->sanitize_params();if(is_wp_error($valid))return self::error('input','Native sanitization failed.');
        $permission=call_user_func($attributes['permission_callback'],$request);
        if(is_wp_error($permission)||!$permission)return self::error('permission','Native controller permission denied.');
        return $request;
    }

    /** Preserve native lifecycle hooks and return independent post-mutation state. */
    private static function write(string $op,array $a) {
        $state=null;
        if(isset($a['kind']))$state=self::content_state($a['kind'],$a['id']);
        elseif($op==='translate-string')$state=self::string_state($a['language']);
        if(is_wp_error($state))return $state;
        $revision=$state['revision']??self::hash(self::config());
        if(!hash_equals($revision,$a['expected_revision']))return self::error('revision','State changed; read a fresh revision before retrying.');
        $preview=['dry_run'=>true,'operation'=>$op,'revision'=>$revision,'native_hooks_on_execution'=>true];
        if(in_array($op,['create-language','update-language','update-setting'],true))return self::write_config($op,$a,$preview);
        if($op==='translate-string') {
            $string=$state['strings'][$a['string_id']]??null;
            if(!$string)return self::error('string','String is not registered in this request.');
            // REST does not initialize the admin sanitizer; apply it explicitly when absent.
            $translation=trim($a['translation']);
            if(!has_filter('pll_sanitize_string_translation',['PLL_Admin_Strings','sanitize_string_translation']))$translation=\PLL_Admin_Strings::sanitize_string_translation($translation,$string['name'],$string['context'],$string['string'],$state['mo']->translate_if_any($string['string']));
            $translation=apply_filters('pll_sanitize_string_translation',$translation,$string['name'],$string['context'],$string['string'],$state['mo']->translate_if_any($string['string']));
            if(!is_string($translation))return self::error('input','Native sanitizer did not return a string.');
            if($a['dry_run']??true)return $preview+['translation'=>$translation];
            if($translation==='')$state['mo']->delete_entry($string['string']);
            else $state['mo']->add_entry($state['mo']->make_entry($string['string'],$translation));
            $state['mo']->export_to_db($state['language']);do_action('pll_save_strings_translations');
            $fresh=self::string_state($a['language']);
            if($fresh['mo']->translate_if_any($string['string'])!==$translation)return self::error('readback','String save outcome is uncertain; re-read state.');
            return ['dry_run'=>false,'revision'=>$fresh['revision'],'translation'=>$translation];
        }
        $kind=$a['kind'];$id=$a['id'];$object=$state['content'];$group=$object['translations'];
        if($op==='set-content-language') {
            if(is_wp_error(self::language($a['language'])))return self::error('language','Configured language not found.');
            if($object['language']!==$a['language']&&count($group)>1)return self::error('linked','Unlink this object before changing its language.');
            if($a['dry_run']??true)return $preview+['language'=>$a['language']];
            $fn="pll_set_{$kind}_language";$fn($id,$a['language']);
        } elseif($op==='link-translation') {
            $other=self::content_state($kind,$a['translation_id']);if(is_wp_error($other))return $other;
            if(!hash_equals($other['revision'],$a['translation_revision']))return self::error('revision','Translation changed; read its fresh revision.');
            $target=$other['content'];
            if($target['id']===$id||$target['type']!==$object['type']||!$object['language']||!$target['language']||$target['language']===$object['language'])return self::error('translation','Translations must have the same content type and different assigned languages.');
            if(isset($group[$target['language']])&&(int)$group[$target['language']]!==$target['id'])return self::error('occupied','The language already has a translation.');
            foreach($target['translations'] as $lang=>$member)if($member!==$target['id']&&($group[$lang]??null)!==$member)return self::error('foreign_group','Translation belongs to another group; unlink it explicitly first.');
            $group[$object['language']]=$id;$group[$target['language']]=$target['id'];ksort($group);
            if($a['dry_run']??true)return $preview+['translations'=>$group];
            $fn="pll_save_{$kind}_translations";$fn($group);
        } elseif($op==='unlink-translation') {
            if($a['dry_run']??true)return $preview+['id'=>$id];
            PLL()->model->$kind->delete_translation($id);
        } else return self::error('operation','Unknown mutation.');
        $fresh=self::content_state($kind,$id);if(is_wp_error($fresh))return $fresh;
        if($op==='set-content-language'&&$fresh['content']['language']!==$a['language'])return self::error('readback','Assignment outcome is uncertain; re-read state.');
        if($op==='link-translation'&&$fresh['content']['translations']!==$group)return self::error('readback','Link outcome is uncertain; re-read both objects.');
        if($op==='unlink-translation'&&count($fresh['content']['translations'])>1)return self::error('readback','Unlink outcome is uncertain; re-read state.');
        return ['dry_run'=>false]+$fresh;
    }

    /** Reject coercible but wrongly typed values before native REST sanitization. */
    private static function setting_type($value,string $type):bool {
        return match($type){'boolean'=>is_bool($value),'string'=>is_string($value),'array'=>is_array($value)&&array_is_list($value),default=>false};
    }

    /** Send only a delta to the native controllers; reject silent lossy sanitization. */
    private static function write_config(string $op,array $a,array $preview) {
        if($op==='update-setting') {
            $name=$a['name'];$schema=PLL()->options->get_schema()['properties'][$name]??null;
            if(!$schema||!in_array($name,self::SETTINGS,true)||!self::setting_type($a['value'],$schema['type']??'')||is_wp_error(rest_validate_value_from_schema($a['value'],$schema)))return self::error('input','Value does not match the native writable setting.');
            if($name==='default_lang'&&is_wp_error(self::language($a['value'])))return self::error('language','Default language must already exist.');
            if(in_array($name,['post_types','taxonomies'],true)) {
                $valid=$name==='post_types'?get_post_types(['_builtin'=>false]):array_diff(get_taxonomies(['_builtin'=>false]),get_taxonomies(['_pll'=>true]));
                if(array_diff($a['value'],$valid))return self::error('type','Unknown or unsupported content type.');
            }
            $params=[$name=>$a['value']];$route='/pll/v1/settings';
        } elseif($op==='create-language') {
            foreach(self::languages() as $language)if($language['slug']===$a['slug']||$language['locale']===$a['locale'])return self::error('duplicate','Language slug or locale already exists.');
            $params=array_diff_key($a,array_flip(['expected_revision','reason','dry_run']));$params['no_default_cat']=true;$route='/pll/v1/languages';
        } else {
            $language=self::language($a['language']);if(is_wp_error($language))return $language;
            if(!$a['changes'])return self::error('input','Provide at least one changed language property.');
            $params=$a['changes'];$route='/pll/v1/languages/'.$language->term_id;
        }
        $request=self::request($route,$params);if(is_wp_error($request))return $request;
        if($a['dry_run']??true)return $preview+['changes'=>$params];
        $result=rest_do_request($request);
        if($result->is_error())return self::error('native_write','Native controller rejected the write; inspect current state before retrying.');
        // Native options are saved on shutdown too; force persistence before returning success.
        if(is_callable([PLL()->options,'save']))PLL()->options->save();
        if($op==='update-setting'&&PLL()->options->get($a['name'])!==$a['value'])return self::error('readback','Native setting differs from the requested value.');
        return ['dry_run'=>false,'value'=>$result->get_data(),'revision'=>self::hash(self::config())];
    }
}
