<?php
/** Site-local draft operations through the native WordPress REST controllers. */
declare(strict_types=1);
namespace WeRock\RealCookieBannerAbilities;
defined('ABSPATH') || exit;

final class Adapter
{
    public const TYPES=['service'=>'rcb-cookie','blocker'=>'rcb-blocker'];
    public const SETTINGS=['rcb-banner-active','rcb-blocker-active','rcb-operator-country','rcb-cookie-policy-id','rcb-operator-contact-form-id','rcb-set-cookies-via-manager'];

    /** Return stable errors without response bodies, arbitrary code or personal data. */
    public static function error(string $code,string $message): \WP_Error
    {
        return new \WP_Error('wrd_rcb_'.$code,$message,['status'=>409]);
    }

    /** Check the requested native data/REST surface without exact-version gating. */
    public static function guard(string $operation = 'get-status', ?string $kind = null): ?\WP_Error
    {
        if(!defined('RCB_VERSION'))return self::error('target','Active Real Cookie Banner is required.');
        if(is_multisite())return self::error('multisite','Only single-site WordPress is supported.');
        if(!current_user_can('manage_real_cookie_banner'))return self::error('permission','Native Real Cookie Banner management capability is required.');
        // Multilingual synchronization can affect writes, while native reads remain safe.
        if(in_array($operation,['create-draft','update-draft','trash-draft','restore-draft'],true) &&
            (defined('ICL_SITEPRESS_VERSION') || function_exists('pll_current_language')))return self::error('multilingual','Multilingual draft changes require native administration.');
        if($operation==='list-groups'&&!taxonomy_exists('rcb-cookie-group'))return self::error('native_api','Native service-group taxonomy unavailable.');
        if($kind!==null) {
            $type=self::TYPES[$kind]??null;
            if($type===null || !post_type_exists($type))return self::error('native_api','Requested native post type unavailable.');
            if(in_array($operation,['create-draft','update-draft'],true)) {
                $postType=get_post_type_object($type);
                if(!$postType || !$postType->show_in_rest || !is_callable([$postType,'get_rest_controller']))return self::error('native_api','Native REST post type unavailable.');
                $controller=$postType->get_rest_controller();
                $method=$operation==='create-draft'?'create_item':'update_item';
                if(!is_object($controller) || !is_callable([$controller,$method]))return self::error('native_api','Native REST write controller unavailable.');
            }
        }
        return null;
    }

    /** Expose an explicit, bounded subset of the native metadata contract. */
    public static function fields(string $kind): array
    {
        $text=static fn(int $max)=>['type'=>'string','maxLength'=>$max];
        $bool=['type'=>'boolean'];
        $ids=['type'=>'array','maxItems'=>30,'uniqueItems'=>true,'items'=>['type'=>'integer','minimum'=>1]];
        $fields=['title'=>['type'=>'string','minLength'=>1,'maxLength'=>200],'description'=>$text(20000)];
        if($kind==='service') {
            $fields['groups']=$ids;
            $meta=['isProviderCurrentWebsite'=>$bool,'provider'=>$text(2000),'providerContactPhone'=>$text(100),'providerContactEmail'=>$text(254),'providerContactLink'=>$text(2000),'uniqueName'=>['type'=>'string','minLength'=>1,'maxLength'=>100,'pattern'=>'^[a-zA-Z0-9_-]+$'],'providerPrivacyPolicyUrl'=>$text(2000),'providerLegalNoticeUrl'=>$text(2000),'codeOptIn'=>$text(100000),'codeOptOut'=>$text(100000),'codeOnPageLoad'=>$text(100000),'executePriority'=>['type'=>'integer','minimum'=>0,'maximum'=>999]];
        } else {
            $fields['service_ids']=$ids;
            $meta=['rules'=>$text(20000),'isVisual'=>$bool,'isVisualDarkMode'=>$bool,'visualBlur'=>['type'=>'integer','minimum'=>0,'maximum'=>100],'visualHeroButtonText'=>$text(300),'shouldForceToShowVisual'=>$bool];
        }
        $fields['meta']=objectSchema($meta)+['minProperties'=>1];
        return $fields;
    }

    /** Hash raw persisted state, including metadata and taxonomy assignments. */
    public static function revision(int $id): string
    {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID=%d",$id),ARRAY_A);
        $meta=$wpdb->get_results($wpdb->prepare("SELECT meta_key,meta_value FROM {$wpdb->postmeta} WHERE post_id=%d ORDER BY meta_id",$id),ARRAY_A);
        $terms=$wpdb->get_results($wpdb->prepare("SELECT term_taxonomy_id,term_order FROM {$wpdb->term_relationships} WHERE object_id=%d ORDER BY term_taxonomy_id",$id),ARRAY_A);
        return hash('sha256',wp_json_encode([$row,$meta,$terms]));
    }

    /** Build a collection token that changes when any target row changes. */
    public static function catalog(string $kind): array
    {
        global $wpdb;
        $ids=$wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type=%s ORDER BY ID",self::TYPES[$kind]));
        $revisions=[];foreach($ids as $id)$revisions[(int)$id]=self::revision((int)$id);
        return ['ids'=>array_map('intval',$ids),'revision'=>hash('sha256',wp_json_encode($revisions))];
    }

    /** Return metadata by default and expose complete editable content only on explicit request. */
    public static function item(string $kind,int $id,bool $details=false)
    {
        clean_post_cache($id);$p=get_post($id);
        if(!$p || $p->post_type!==self::TYPES[$kind])return self::error('missing','Requested target item does not exist.');
        $out=['id'=>$id,'kind'=>$kind,'title'=>$p->post_title,'status'=>$p->post_status,'revision'=>self::revision($id)];
        if($details) {
            $out['description']=$p->post_content;$out['meta']=[];
            foreach(self::fields($kind)['meta']['properties'] as $key=>$schema) {
                $value=get_post_meta($id,$key,true);
                $out['meta'][$key]=match($schema['type']){'boolean'=>(bool)$value,'integer'=>(int)$value,default=>(string)$value};
            }
            if($kind==='service')$out['groups']=wp_get_object_terms($id,'rcb-cookie-group',['fields'=>'ids']);
            else $out['service_ids']=json_decode((string)get_post_meta($id,'services',true),true)??[];
        }
        return $out;
    }

    /** Call the native REST dispatcher so permissions, schemas and lifecycle hooks remain intact. */
    private static function request(string $kind,?int $id,array $params)
    {
        $r=new \WP_REST_Request('POST','/wp/v2/'.self::TYPES[$kind].($id?'/'.$id:''));
        $r->set_body_params($params);
        $response=rest_do_request($r);
        if($response->is_error())return self::error('native','Native REST operation failed. Re-read current state before retrying.');
        return $response->get_data();
    }

    /** Map ergonomic fields to reviewed native post/meta/taxonomy fields. */
    public static function payload(string $kind,array $changes)
    {
        $data=[];
        if(isset($changes['title']))$data['title']=$changes['title'];
        if(isset($changes['description']))$data['content']=$changes['description'];
        if(isset($changes['meta']))$data['meta']=$changes['meta'];
        if(isset($changes['groups'])) {
            foreach($changes['groups'] as $id)if(!term_exists($id,'rcb-cookie-group'))return self::error('reference','Unknown service group.');
            $data['rcb-cookie-group']=$changes['groups'];
        }
        if(isset($changes['service_ids'])) {
            foreach($changes['service_ids'] as $id)if(get_post_type($id)!=='rcb-cookie' || get_post_status($id)==='trash')return self::error('reference','Unknown or trashed connected service.');
            $data['meta']['services']=wp_json_encode($changes['service_ids']);
        }
        foreach($data['meta']??[] as $key=>$value) {
            if(str_ends_with($key,'Url') || in_array($key,['providerContactLink'],true)) {
                if($value!=='' && (!wp_http_validate_url($value) || !in_array(strtolower((string)parse_url($value,PHP_URL_SCHEME)),['http','https'],true)))return self::error('url','Provider links must be valid public HTTP(S) URLs.');
            }
            if($key==='providerContactEmail' && $value!=='' && !is_email($value))return self::error('email','Provider email is invalid.');
        }
        return $data;
    }

    /** Dispatch reads without requesting consent records or native revision generation. */
    public static function run(string $operation,array $a)
    {
        if($operation==='get-status')return ['version'=>defined('RCB_VERSION')?RCB_VERSION:null,'source_version'=>'5.4.0','source_version_matches'=>defined('RCB_VERSION')&&RCB_VERSION==='5.4.0','version_policy'=>'informational','supported'=>self::guard()===null,'writes_supported'=>self::guard('create-draft','service')===null,'write_scope'=>'service and blocker drafts'];
        if($e=self::guard($operation,$a['kind']??null))return $e;
        if($operation==='get-settings') {
            $out=[];foreach(self::SETTINGS as $key)$out[$key]=get_option($key,null);
            return ['settings'=>$out];
        }
        if($operation==='list-groups') {
            $terms=get_terms(['taxonomy'=>'rcb-cookie-group','hide_empty'=>false,'number'=>100,'orderby'=>'term_id','order'=>'ASC']);
            if(is_wp_error($terms))return self::error('native','Group read failed.');
            return ['items'=>array_map(static fn($t)=>['id'=>$t->term_id,'name'=>$t->name,'is_essential'=>(bool)get_term_meta($t->term_id,'isEssential',true)],$terms)];
        }
        $kind=$a['kind'];
        if($operation==='get-edit-schema')return ['changes_schema'=>objectSchema(self::fields($kind)),'kind'=>$kind];
        if($operation==='get-item')return self::item($kind,$a['id'],$a['include_details']??false);
        if($operation==='list-items') {
            $catalog=self::catalog($kind);
            return ['items'=>array_map(static fn($id)=>self::item($kind,$id),array_slice($catalog['ids'],$a['offset']??0,$a['limit']??25)),'total'=>count($catalog['ids']),'catalog_revision'=>$catalog['revision']];
        }
        if(isset($a['changes'])) {
            $schema=objectSchema(self::fields($kind),$operation==='create-draft'?['title']:[])+['minProperties'=>1];
            if(is_wp_error(rest_validate_value_from_schema($a['changes'],$schema)))return self::error('input','Changes do not match the selected kind schema.');
        }
        return self::write($operation,$a);
    }

    /** Lock native records before rechecking revisions and applying one bounded mutation. */
    private static function write(string $operation,array $a)
    {
        global $wpdb;
        $kind=$a['kind'];$id=$a['id']??null;
        foreach([$wpdb->posts,$wpdb->postmeta,$wpdb->term_relationships] as $table) {
            if($wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table))!=='InnoDB')return self::error('storage','InnoDB is required for draft mutations.');
        }
        $lock=substr('wrd_rcb_'.hash('sha256',DB_NAME.':'.$wpdb->prefix),0,64);
        if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))!==1)return self::error('busy','Another pack operation is in progress.');
        $committed=false;
        try {
            if($wpdb->query('START TRANSACTION')===false)throw new \RuntimeException();
            // Lock the target collection and all affected child rows before comparing state.
            if($wpdb->query($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type=%s ORDER BY ID FOR UPDATE",self::TYPES[$kind]))===false)throw new \RuntimeException();
            if($id)foreach([[$wpdb->postmeta,'post_id'],[$wpdb->term_relationships,'object_id']] as [$table,$column]) {
                if($wpdb->query($wpdb->prepare("SELECT * FROM $table WHERE $column=%d FOR UPDATE",$id))===false)throw new \RuntimeException();
            }
            $current=$id?self::item($kind,$id):null;
            if(is_wp_error($current))return $current;
            $revision=$id?$current['revision']:self::catalog($kind)['revision'];
            if(!hash_equals($revision,$a['expected_revision']))return self::error('revision','State changed. Read the current revision before retrying.');
            if($id && ($operation==='restore-draft' ? $current['status']!=='trash' : $current['status']!=='draft'))return self::error('state','Only draft items, or trashed drafts for restore, can be changed.');
            if($operation==='restore-draft' && get_post_meta($id,'_wp_trash_meta_status',true)!=='draft')return self::error('state','Only items originally trashed from draft status can be restored.');
            if($operation==='trash-draft' && (!defined('EMPTY_TRASH_DAYS') || EMPTY_TRASH_DAYS<1))return self::error('trash_disabled','WordPress trash must be enabled.');
            $payload=self::payload($kind,$a['changes']??[]);
            if(is_wp_error($payload))return $payload;
            if($a['dry_run']??true)return ['dry_run'=>true,'kind'=>$kind,'id'=>$id,'operation'=>$operation,'changed_fields'=>array_keys($a['changes']??[])];
            switch($operation) {
                case 'create-draft':
                    $result=self::request($kind,null,$payload+['status'=>'draft']);
                    if(is_wp_error($result))return $result;
                    $id=(int)($result['id']??0);break;
                case 'update-draft':
                    $result=self::request($kind,$id,$payload);
                    if(is_wp_error($result))return $result;
                    break;
                case 'trash-draft':
                    // wp_trash_post permanently deletes when trash is disabled; fail closed first.
                    if(!defined('EMPTY_TRASH_DAYS') || EMPTY_TRASH_DAYS<1)return self::error('trash_disabled','WordPress trash must be enabled.');
                    if(!wp_trash_post($id))throw new \RuntimeException();break;
                case 'restore-draft':
                    if(!wp_untrash_post($id))throw new \RuntimeException();break;
                default:throw new \RuntimeException();
            }
            $expected=$operation==='trash-draft'?'trash':'draft';
            clean_post_cache($id);
            if(!$id || get_post_status($id)!==$expected || $wpdb->last_error)throw new \RuntimeException();
            if($wpdb->query('COMMIT')===false)throw new \RuntimeException();
            $committed=true;
            return ['dry_run'=>false,'item'=>self::item($kind,$id)];
        } finally {
            // Native hooks can touch caches/options; an uncertain failure requires reconciliation.
            if(!$committed)$wpdb->query('ROLLBACK');
            if($id)clean_post_cache($id);
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));
        }
    }
}
