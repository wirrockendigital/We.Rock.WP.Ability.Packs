<?php
/** Exercise the real adapter against an isolated native API contract double. */
declare(strict_types=1);
define('ABSPATH',__DIR__);define('POLYLANG_VERSION','99.0.0');define('DB_NAME','test');
class WP_Error { public function __construct(public string $code,public string $message='',public array $data=[]){} public function get_error_code(){return $this->code;} }
function is_wp_error($v){return $v instanceof WP_Error;}
function add_action(...$args){}
function current_user_can(...$args){return $GLOBALS['allowed'];}
function is_multisite(){return false;}
function wp_json_encode($v){return json_encode($v);}
function PLL(){return $GLOBALS['pll'];}
function pll_is_translated_post_type($v){return $v==='post';}
function pll_is_translated_taxonomy($v){return $v==='category';}
function pll_home_url($v){return 'https://example.test/'.$v.'/';}
function get_post($id){return isset($GLOBALS['objects'][$id])?(object)$GLOBALS['objects'][$id]:null;}
function get_term($id){return get_post($id);}
function pll_get_post_language($id){return $GLOBALS['objects'][$id]['language'];}
function pll_get_term_language($id){return pll_get_post_language($id);}
function pll_get_post_translations($id){return $GLOBALS['objects'][$id]['translations'];}
function pll_get_term_translations($id){return pll_get_post_translations($id);}
function pll_set_post_language($id,$lang){$GLOBALS['objects'][$id]['language']=$lang;$GLOBALS['objects'][$id]['translations']=[$lang=>$id];}
function pll_set_term_language($id,$lang){pll_set_post_language($id,$lang);}
function pll_save_post_translations($group){foreach($group as $id)$GLOBALS['objects'][$id]['translations']=$group;}
function pll_save_term_translations($group){pll_save_post_translations($group);}
class Languages {
 public function get_list(){return array_map(fn($slug)=>new Language($slug),['de','en','fr']);}
 public function get($slug){return in_array($slug,['de','en','fr'],true)?new Language($slug):false;}
}
class Language {public function __construct(public string $slug){} public function to_array(){return ['term_id'=>array_search($this->slug,['de','en','fr'])+1,'slug'=>$this->slug];}}
class Options { public function get_all(){return ['default_lang'=>'de'];} }
class Model {
 public $languages;public $post;public $term;
 public function __construct(){$this->languages=new Languages();$this->post=$this;$this->term=$this;}
 public function delete_translation($id){$g=$GLOBALS['objects'][$id]['translations'];foreach($g as $member){unset($GLOBALS['objects'][$member]['translations'][$GLOBALS['objects'][$id]['language']]);}$GLOBALS['objects'][$id]['translations']=[$GLOBALS['objects'][$id]['language']=>$id];}
}
class Database {public $prefix='wp_';public function prepare($sql,...$args){return $sql;}public function get_var($sql){return 1;}}
$GLOBALS['wpdb']=new Database();$GLOBALS['allowed']=true;$GLOBALS['pll']=(object)['model'=>new Model(),'options'=>new Options()];
foreach([1=>'de',2=>'en',3=>'en',4=>'fr'] as $id=>$lang)$GLOBALS['objects'][$id]=['ID'=>$id,'post_type'=>'post','taxonomy'=>'category','post_title'=>'Fixture','name'=>'Fixture','post_modified_gmt'=>'2026-09-20','language'=>$lang,'translations'=>[$lang=>$id]];
require dirname(__DIR__).'/wrd-polylang-ability-pack.php';
use WeRock\PolylangAbilities\Adapter;
$count=0;
/** Fail immediately so a red check cannot be summarized as acceptance. */
function check($pass,$name){global $count;if(!$pass)throw new RuntimeException($name);$count++;}
/** Read a fresh native snapshot before each isolated mutation. */
function rev($id,$kind='post'){return Adapter::run('get-content',['kind'=>$kind,'id'=>$id])['revision'];}
$base=['kind'=>'post','id'=>1,'reason'=>'Contract test'];
check(Adapter::guard('get-content',$base)===null,'different version is allowed');
check(is_wp_error(Adapter::guard('create-language')),'missing controller fails closed');
$GLOBALS['allowed']=false;check(is_wp_error(Adapter::run('get-content',$base)),'permission denial');$GLOBALS['allowed']=true;
check(is_wp_error(Adapter::run('get-content',['kind'=>'post','id'=>999])),'missing content');
$r=rev(1);$before=$GLOBALS['objects'];
check(Adapter::run('set-content-language',$base+['language'=>'fr','expected_revision'=>$r],true)['dry_run'],'default preview');
check($GLOBALS['objects']===$before,'preview preserves state');
check(is_wp_error(Adapter::run('set-content-language',$base+['language'=>'xx','expected_revision'=>$r],true)),'unknown language');
check(is_wp_error(Adapter::run('set-content-language',$base+['language'=>'fr','expected_revision'=>str_repeat('0',64)],true)),'stale source revision');
check(!is_wp_error(Adapter::run('link-translation',$base+['translation_id'=>2,'translation_revision'=>rev(2),'expected_revision'=>$r,'dry_run'=>false],true)),'native linking');
check($GLOBALS['objects'][2]['translations']===['de'=>1,'en'=>2],'symmetric links');
check(is_wp_error(Adapter::run('set-content-language',$base+['language'=>'fr','expected_revision'=>rev(1),'dry_run'=>false],true)),'linked language change rejected');
check(is_wp_error(Adapter::run('link-translation',$base+['translation_id'=>3,'translation_revision'=>rev(3),'expected_revision'=>rev(1),'dry_run'=>false],true)),'occupied language rejected');
check(is_wp_error(Adapter::run('link-translation',$base+['translation_id'=>4,'translation_revision'=>str_repeat('0',64),'expected_revision'=>rev(1),'dry_run'=>false],true)),'stale target revision');
check(!is_wp_error(Adapter::run('unlink-translation',$base+['expected_revision'=>rev(1),'dry_run'=>false],true)),'native unlinking');
check($GLOBALS['objects'][1]['language']==='de'&&count($GLOBALS['objects'])===4,'unlink preserves language and content');
check(!is_wp_error(Adapter::run('set-content-language',$base+['language'=>'fr','expected_revision'=>rev(1),'dry_run'=>false],true)),'native assignment');
check($GLOBALS['objects'][1]['language']==='fr','assignment readback');
$term=['kind'=>'term','id'=>2,'reason'=>'Contract test'];
check(!is_wp_error(Adapter::run('link-translation',$term+['translation_id'=>4,'translation_revision'=>rev(4,'term'),'expected_revision'=>rev(2,'term'),'dry_run'=>false],true)),'native term linking');
check(!is_wp_error(Adapter::run('unlink-translation',$term+['expected_revision'=>rev(2,'term'),'dry_run'=>false],true)),'native term unlinking');
check(!is_wp_error(Adapter::run('set-content-language',$term+['language'=>'de','expected_revision'=>rev(2,'term'),'dry_run'=>false],true)),'native term assignment');
$GLOBALS['objects'][3]['translations']=['en'=>3,'fr'=>4];$GLOBALS['objects'][4]['translations']=['en'=>3,'fr'=>4];
check(is_wp_error(Adapter::run('link-translation',['kind'=>'post','id'=>2,'reason'=>'Contract test','translation_id'=>3,'translation_revision'=>rev(3),'expected_revision'=>rev(2),'dry_run'=>false],true)),'foreign group rejected');
$GLOBALS['objects'][4]['post_type']='unsupported';check(is_wp_error(Adapter::run('get-content',['kind'=>'post','id'=>4])),'untranslated type rejected');
echo json_encode(['passed'=>$count])."\n";
