<?php
/** Execute only through authenticated WP-CLI; never deploy this fixture as a plugin file. */
if(!defined('WP_CLI') || !WP_CLI)throw new RuntimeException('WP-CLI required.');
use WeRock\CodeSnippetsAbilities\Adapter;
$GLOBALS['wrd_cs_checks']=[];$fixtureId=null;
/** Assert without exposing native records or code in failure output. */
function wrd_cs_assert($condition,string $label): void {
    if(!$condition)throw new RuntimeException($label);
    $GLOBALS['wrd_cs_checks'][]=$label;
}
/** Exercise actual registered WP_Ability validation and host permission callbacks. */
function wrd_cs_call(string $operation,array $args=[]): array {
    $ability=wp_get_ability('code-snippets/'.$operation);
    if(!$ability)throw new RuntimeException('Ability missing: '.$operation);
    $result=$ability->execute($args);
    if(is_wp_error($result))throw new RuntimeException($operation.': '.$result->get_error_code());
    return $result['result'];
}
/** Require a specific expected failure through the real registered ability. */
function wrd_cs_reject(string $operation,array $args,string $suffix): void {
    $r=wp_get_ability('code-snippets/'.$operation)->execute($args);
    wrd_cs_assert(is_wp_error($r) && str_ends_with($r->get_error_code(),$suffix),'reject '.$suffix);
}
$before=Adapter::listing(['limit'=>100]);
$originalRevisions=array_column($before['items'],'revision','id');
try {
    wrd_cs_assert(wrd_cs_call('get-status')['supported'],'target supported');
    wrd_cs_assert(wrd_cs_call('validate-code',['code'=>'echo ;','scope'=>'global'])['valid']===false,'invalid PHP rejected without evaluation');
    $create=['expected_revision'=>$before['catalog_revision'],'reason'=>'Scoped pack acceptance test','changes'=>['name'=>'WRD ability acceptance fixture','code'=>'/* WRD acceptance fixture: deliberately no executable statements. */','scope'=>'global']];
    wrd_cs_assert(wrd_cs_call('create-snippet',$create)['dry_run']===true,'create preview defaults true');
    wrd_cs_assert(Adapter::listing([])['catalog_revision']===$before['catalog_revision'],'preview preserved catalog');
    $new=wrd_cs_call('create-snippet',$create+['dry_run'=>false])['snippet'];$fixtureId=$new['id'];
    wrd_cs_assert(!$new['active'],'created inactive');
    wrd_cs_reject('create-snippet',$create+['dry_run'=>false],'revision');
    $read=wrd_cs_call('get-snippet',['id'=>$fixtureId]);
    wrd_cs_assert(!isset($read['code']),'code omitted by default');
    wrd_cs_assert(isset(wrd_cs_call('get-snippet',['id'=>$fixtureId,'include_code'=>true])['code']),'explicit code read');
    $base=['id'=>$fixtureId,'expected_revision'=>$read['revision'],'reason'=>'Scoped pack acceptance test'];
    wrd_cs_reject('activate-snippet',$base,'acknowledgement');
    $update=$base+['changes'=>['name'=>'WRD ability acceptance fixture updated','priority'=>11]];
    wrd_cs_assert(wrd_cs_call('update-snippet',$update)['dry_run'],'update preview');
    wrd_cs_assert(wrd_cs_call('get-snippet',['id'=>$fixtureId])['revision']===$read['revision'],'update preview unchanged');
    $new=wrd_cs_call('update-snippet',$update+['dry_run'=>false])['snippet'];
    wrd_cs_assert($new['priority']===11 && !$new['active'],'native inactive update');
    wrd_cs_reject('update-snippet',$update+['dry_run'=>false],'revision');
    $base['expected_revision']=$new['revision'];
    wrd_cs_assert(wrd_cs_call('activate-snippet',$base+['acknowledge_execution'=>true])['dry_run'],'activate preview');
    $new=wrd_cs_call('activate-snippet',$base+['acknowledge_execution'=>true,'dry_run'=>false])['snippet'];
    wrd_cs_assert($new['active'],'native activation of harmless fixture');
    $base['expected_revision']=$new['revision'];
    wrd_cs_reject('update-snippet',$base+['changes'=>['name'=>'Must reject']],'state');
    wrd_cs_reject('trash-snippet',$base,'state');
    $new=wrd_cs_call('deactivate-snippet',$base+['dry_run'=>false])['snippet'];
    wrd_cs_assert(!$new['active'],'native deactivation');
    $base['expected_revision']=$new['revision'];
    \Code_Snippets\set_snippet_locked($fixtureId,true,false);
    $base['expected_revision']=wrd_cs_call('get-snippet',['id'=>$fixtureId])['revision'];
    wrd_cs_reject('trash-snippet',$base,'protected');
    \Code_Snippets\set_snippet_locked($fixtureId,false,false);
    $base['expected_revision']=wrd_cs_call('get-snippet',['id'=>$fixtureId])['revision'];
    wrd_cs_assert(wrd_cs_call('trash-snippet',$base)['dry_run'],'trash preview');
    $new=wrd_cs_call('trash-snippet',$base+['dry_run'=>false])['snippet'];
    wrd_cs_assert($new['trashed'],'native trash');
    $base['expected_revision']=$new['revision'];
    wrd_cs_assert(wrd_cs_call('restore-snippet',$base)['dry_run'],'restore preview');
    $new=wrd_cs_call('restore-snippet',$base+['dry_run'=>false])['snippet'];
    wrd_cs_assert(!$new['trashed']&&!$new['active'],'native restore inactive');
    $user=get_current_user_id();
    wp_set_current_user(0);
    $denied=wp_get_ability('code-snippets/list-snippets')->execute([]);
    wp_set_current_user($user);
    wrd_cs_assert(is_wp_error($denied),'unauthenticated user denied by host');
} finally {
    // Remove only the exact fixture created in this invocation, even if a check fails.
    if($fixtureId!==null) {
        \Code_Snippets\set_snippet_locked($fixtureId,false,false);
        \Code_Snippets\deactivate_snippet($fixtureId,false);
        if(!\Code_Snippets\delete_snippet($fixtureId,false))throw new RuntimeException('Fixture cleanup failed.');
    }
}
$after=Adapter::listing(['limit'=>100]);
wrd_cs_assert(array_column($after['items'],'revision','id')===$originalRevisions,'existing snippet rows and locks unchanged');
wrd_cs_assert($after['total']===$before['total'],'fixture removed');
echo wp_json_encode(['site'=>get_option('siteurl'),'checks'=>$GLOBALS['wrd_cs_checks'],'passed'=>count($GLOBALS['wrd_cs_checks']),'fixture_removed'=>true,'existing_snippets_unchanged'=>true]);
