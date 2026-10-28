<?php
/** Native RCM adapter with operation checks, permissions and optimistic revisions. */
declare(strict_types=1);
namespace WeRock\RealCategoryManagementAbilities;
use DevOwl\RealCategoryLibrary\Core;
use DevOwl\RealCategoryLibrary\TaxTree;
use DevOwl\RealCategoryLibrary\Options;
use DevOwl\RealCategoryLibrary\rest\Service;
use DevOwl\RealCategoryLibrary\rest\Term;
use DevOwl\RealCategoryLibrary\rest\Options as RestOptions;
defined('ABSPATH') || exit;
final class Adapter {
    /** Return bounded errors without leaking native exception details. */
    public static function error(string $code,string $message):\WP_Error {return new \WP_Error('wrd_rcm_'.$code,$message);}
    /** Describe the native functions required by each operation. */
    public static function available(string $op):bool {
        if(!function_exists('wp_rcl_active')||!is_callable([Service::class,'permit'])||!class_exists(TaxTree::class))return false;
        $requirements=match($op){
            'create-term'=>[[Term::class,'createItem']],
            'rename-term'=>[[Term::class,'updateItem']],
            'delete-empty-term'=>[[Term::class,'deleteItem']],
            'relocate-term'=>[[Core::class,'getInstance'],['DevOwl\\RealCategoryLibrary\\TaxOrder','relocate']],
            'get-settings','update-settings'=>[[Options::class,'getInstance'],[RestOptions::class,'instance'],[RestOptions::class,'routePostTypePatch']],
            default=>[[TaxTree::class,'getAvailablePostTypes'],[TaxTree::class,'getCats']],
        };
        foreach($requirements as [$class,$method])if(!method_exists($class,$method))return false;
        return !in_array($op,['assign-posts','get-assignments'],true)||function_exists('wp_set_object_terms');
    }
    /** Run under one per-site advisory lock; native UI writers still use optimistic checks. */
    public static function run(string $op,array $a,bool $write):array|\WP_Error {
        if(!current_user_can('manage_categories'))return self::error('permission','manage_categories is required.');
        if($op==='get-status'){
            $ops=['list-contexts','get-tree','create-term','rename-term','relocate-term','delete-empty-term','get-assignments','assign-posts','get-settings','update-settings'];
            return ['source_version'=>defined('RCL_VERSION')?RCL_VERSION:null,'reviewed_source_version'=>'4.3.7','active_for_user'=>function_exists('wp_rcl_active')&&(bool)wp_rcl_active(),'operations'=>array_combine($ops,array_map([self::class,'available'],$ops))];
        }
        if(is_multisite())return self::error('scope','Multisite has not been validated.');
        if(!self::available($op))return self::error('unavailable','Required native functions are unavailable.');
        $permit=Service::permit(in_array($op,['get-settings','update-settings'],true)?'manage_options':'manage_categories');
        if(is_wp_error($permit))return $permit;
        if(!$write)return self::dispatch($op,$a,false);
        if(trim($a['reason']??'')==='')return self::error('reason','A meaningful reason is required.');
        global $wpdb;
        $lock='wrd_rcm_'.substr(hash('sha256',DB_NAME.':'.$wpdb->prefix),0,40);
        if((string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)',$lock))!=='1')return self::error('busy','Another category operation is running.');
        try{return self::dispatch($op,$a,!($a['dry_run']??true));}
        finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }
    /** Validate the exact type/taxonomy pair instead of accepting native fallback selection. */
    private static function context(array $a):TaxTree|\WP_Error {
        $type=$a['post_type'];$tax=$a['taxonomy'];$obj=get_taxonomy($tax);
        if(!$obj||!$obj->hierarchical||!is_object_in_taxonomy($type,$tax)||!post_type_exists($type))return self::error('context','Invalid hierarchical taxonomy/post type pair.');
        if(!current_user_can($obj->cap->manage_terms))return self::error('permission','Native taxonomy capability is required.');
        $tree=new TaxTree($type,$tax);
        if(!$tree->isAvailable()||!isset($tree->getTaxos()[$tax]))return self::error('context','The native category tree is unavailable for this pair.');
        if(!Core::getInstance()->isPro()&&($type!=='post'||$tax!=='category'))return self::error('edition','This context requires the native Pro implementation.');
        return $tree;
    }
    /** Hash persistent taxonomy state independently of language-filtered UI trees. */
    private static function snapshot(string $tax):array {
        global $wpdb;
        $rows=$wpdb->get_results($wpdb->prepare("SELECT t.term_id,t.name,t.slug,t.term_group,t.term_order,tt.term_taxonomy_id,tt.description,tt.parent,tt.count FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=t.term_id WHERE tt.taxonomy=%s ORDER BY t.term_id",$tax),ARRAY_A);
        $rels=$wpdb->get_results($wpdb->prepare("SELECT r.object_id,r.term_taxonomy_id,r.term_order FROM {$wpdb->term_relationships} r JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=r.term_taxonomy_id WHERE tt.taxonomy=%s ORDER BY r.object_id,r.term_taxonomy_id",$tax),ARRAY_A);
        $meta=$wpdb->get_results($wpdb->prepare("SELECT m.term_id,m.meta_key,m.meta_value FROM {$wpdb->termmeta} m JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=m.term_id WHERE tt.taxonomy=%s ORDER BY m.meta_id",$tax),ARRAY_A);
        if($wpdb->last_error)throw new \RuntimeException('State read failed');
        return ['rows'=>$rows,'revision'=>hash('sha256',wp_json_encode([$tax,$rows,$rels,$meta]))];
    }
    /** Read native effective settings and include raw values in the revision. */
    private static function settings(string $type):array|\WP_Error {
        $contexts=TaxTree::getAvailablePostTypes();
        if(empty($contexts[$type]['available']))return self::error('context','Post type has no available native tree.');
        $tree=new TaxTree($type);$options=Options::getInstance();
        $values=['active'=>$options->isActive($tree),'fastMode'=>$options->isFastMode($tree)];
        return ['post_type'=>$type,'settings'=>$values,'revision'=>hash('sha256',wp_json_encode([$type,$values,get_option('rcl-active-'.$type,null),get_option('rcl-fast-mode-'.$type,null)]))];
    }
    /** Verify object permissions and read assignments without exposing content bodies. */
    private static function assignments(array $a):array|\WP_Error {
        $items=[];$ids=$a['post_ids'];sort($ids,SORT_NUMERIC);
        foreach($ids as $id){
            $post=get_post($id);
            if(!$post||$post->post_type!==$a['post_type']||!current_user_can('edit_post',$id))return self::error('permission','Every object must exist, match post_type and be editable.');
            $terms=wp_get_object_terms($id,$a['taxonomy'],['fields'=>'ids']);if(is_wp_error($terms))return $terms;
            sort($terms,SORT_NUMERIC);$items[]=['post_id'=>$id,'term_ids'=>$terms];
        }
        $snapshot=self::snapshot($a['taxonomy']);
        return ['items'=>$items,'revision'=>hash('sha256',wp_json_encode([$a['post_type'],$items,$snapshot['revision']]))];
    }
    /** Validate one term in this exact taxonomy and honor native visibility filters. */
    private static function term(int $id,string $tax):\WP_Term|\WP_Error {
        $term=get_term($id,$tax);
        if(!$term||is_wp_error($term)||!apply_filters('RCL/Node/Visible',true,$term))return self::error('term','Term is missing or unavailable.');
        return $term;
    }
    /** Execute the documented native controller and unwrap its response. */
    private static function native(object $controller,string $method,array $params):mixed {
        $request=new \WP_REST_Request('POST');foreach($params as $key=>$value)$request->set_param($key,$value);
        $result=$controller->$method($request);
        return $result instanceof \WP_REST_Response?$result->get_data():$result;
    }
    /** Dispatch small verbs; all writes validate before the dry-run boundary. */
    private static function dispatch(string $op,array $a,bool $persist):array|\WP_Error {
        if($op==='list-contexts'){
            $result=[];
            foreach(TaxTree::getAvailablePostTypes() as $type=>$data){
                if(!$data['available'])continue;$tree=new TaxTree($type);$tax=[];
                foreach($tree->getTaxos() as $name=>$obj)if(current_user_can($obj->cap->manage_terms))$tax[]=['taxonomy'=>$name,'label'=>$obj->label,'sortable'=>(bool)apply_filters('RCL/Sorting',true,$name)];
                if($tax)$result[]=['post_type'=>$type,'taxonomies'=>$tax,'active'=>$data['active'],'fastMode'=>$data['fastMode']];
            }
            return ['contexts'=>$result];
        }
        if(in_array($op,['get-settings','update-settings'],true)){
            $state=self::settings($a['post_type']);if(is_wp_error($state)||$op==='get-settings')return $state;
            if(!$a['changes'])return self::error('input','Supply at least one setting.');
            if(isset($a['changes']['fastMode'])&&$a['changes']['fastMode']&&!Core::getInstance()->isPro())return self::error('edition','Fast mode requires Pro.');
        }else{
            $tree=self::context($a);if(is_wp_error($tree))return $tree;
            if(in_array($op,['get-assignments','assign-posts'],true))$state=self::assignments($a);
            else{$snap=self::snapshot($a['taxonomy']);$state=['tree'=>$tree->getCats(),'terms'=>$snap['rows'],'revision'=>$snap['revision']];}
            if(is_wp_error($state)||in_array($op,['get-tree','get-assignments'],true))return $state;
        }
        if(!hash_equals($state['revision'],$a['expected_revision']))return self::error('revision','State changed. Read a fresh revision and preview again.');
        $tax=$a['taxonomy']??'';$term=null;
        if(isset($a['term_id'])){
            $term=self::term($a['term_id'],$tax);if(is_wp_error($term))return $term;
            $cap=$op==='delete-empty-term'?'delete_term':'edit_term';
            if(!current_user_can($cap,$term->term_id))return self::error('permission','Native term capability is required.');
        }
        if(isset($a['name'])&&(trim($a['name'])===''||sanitize_text_field($a['name'])!==$a['name']))return self::error('input','Use a nonempty plain-text name.');
        if($op==='create-term'&&!current_user_can(get_taxonomy($tax)->cap->edit_terms))return self::error('permission','Native edit_terms capability is required.');
        if(isset($a['parent'])&&$a['parent']>0){$parent=self::term($a['parent'],$tax);if(is_wp_error($parent))return $parent;}
        if($op==='relocate-term'){
            if(!apply_filters('RCL/Sorting',true,$tax))return self::error('sorting','Native sorting is disabled.');
            if($a['parent']===$a['term_id']||in_array($a['term_id'],get_ancestors($a['parent'],$tax,'taxonomy'),true))return self::error('cycle','A term cannot be moved into itself or a descendant.');
            if($a['before_id']>0){$next=self::term($a['before_id'],$tax);if(is_wp_error($next))return $next;if($next->term_id===$a['term_id']||(int)$next->parent!==$a['parent'])return self::error('sibling','before_id must be another child of the target parent.');}
            // Native preview runs the same ordering algorithm without persistence.
            $preview=Core::getInstance()->getTaxOrder()->relocate($a['post_type'],$tax,$a['term_id'],$a['parent'],$a['before_id'],false);
            if(is_wp_error($preview))return $preview;
        }
        if($op==='delete-empty-term'){
            global $wpdb;
            $used=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE term_taxonomy_id=%d",$term->term_taxonomy_id));
            $children=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy=%s AND parent=%d",$tax,$term->term_id));
            if($used||$children||(int)get_option('default_'.$tax,0)===$term->term_id)return self::error('not_empty','Only empty, childless non-default terms can be deleted.');
        }
        if($op==='assign-posts'){
            if(!current_user_can(get_taxonomy($tax)->cap->assign_terms))return self::error('permission','Native assign_terms capability is required.');
            if($a['target_id']>0){$target=self::term($a['target_id'],$tax);if(is_wp_error($target))return $target;}
            if($a['target_id']===0&&$a['mode']==='copy')return self::error('input','Copy requires a target category.');
        }
        $plan=$a;unset($plan['reason'],$plan['expected_revision']);
        if(!$persist)return ['dry_run'=>true,'revision'=>$state['revision'],'plan'=>$plan,'effects'=>$op==='rename-term'?['slug'=>sanitize_title($a['name'])]:($op==='relocate-term'?['order'=>$preview]:new \stdClass())];
        // All persisted changes use the native controller or its exact WordPress save API.
        $params=['type'=>$a['post_type'],'taxonomy'=>$tax,'id'=>$a['term_id']??0,'name'=>$a['name']??'','parent'=>$a['parent']??0];
        $result=match($op){
            'create-term'=>self::native(new Term(),'createItem',$params),
            'rename-term'=>self::native(new Term(),'updateItem',$params),
            'delete-empty-term'=>self::native(new Term(),'deleteItem',$params),
            'relocate-term'=>Core::getInstance()->getTaxOrder()->relocate($a['post_type'],$tax,$a['term_id'],$a['parent'],$a['before_id'],true),
            'update-settings'=>self::native(RestOptions::instance(),'routePostTypePatch',['post_type'=>$a['post_type']]+$a['changes']),
            'assign-posts'=>self::assign($a),
            default=>self::error('operation','Unknown operation.'),
        };
        if(is_wp_error($result))return $result;
        if($op==='relocate-term')clean_term_cache(array_map('intval',array_column($state['terms'],'term_id')),$tax);
        $after=match($op){'update-settings'=>self::settings($a['post_type']),'assign-posts'=>self::assignments($a),default=>self::snapshot($tax)};
        if(is_wp_error($after))return $after;
        return ['dry_run'=>false,'native_result'=>$result,'state'=>$after];
    }
    /** Preserve native assignment semantics while propagating errors ignored by its REST controller. */
    private static function assign(array $a):bool|\WP_Error {
        foreach($a['post_ids'] as $id){$result=wp_set_object_terms($id,$a['target_id'],$a['taxonomy'],$a['mode']==='copy');if(is_wp_error($result))return $result;}
        return true;
    }
}
