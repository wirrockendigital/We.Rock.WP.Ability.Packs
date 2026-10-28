<?php
/** Scoped authenticated WP-CLI acceptance; never install this executable test on the website. */
if(!defined('WP_CLI') || !WP_CLI)throw new RuntimeException('WP-CLI required.');
use WeRock\RealCookieBannerAbilities\Adapter;
$GLOBALS['wrd_rcb_checks']=[];$fixtures=[];
/** Assert only safe labels so failure output never contains native content. */
function wrd_rcb_assert($condition,string $label):void {
    if(!$condition)throw new RuntimeException($label);
    $GLOBALS['wrd_rcb_checks'][]=$label;
}
/** Exercise the real WP_Ability schema, permission and execution path. */
function wrd_rcb_call(string $op,array $args=[]):array {
    $a=wp_get_ability('real-cookie-banner/'.$op);
    if(!$a)throw new RuntimeException('Ability missing');
    $r=$a->execute($args);
    if(is_wp_error($r))throw new RuntimeException($op.': '.$r->get_error_code());
    return $r['result'];
}
/** Verify expected denials through the registered host. */
function wrd_rcb_reject(string $op,array $args,string $suffix):void {
    $r=wp_get_ability('real-cookie-banner/'.$op)->execute($args);
    wrd_rcb_assert(is_wp_error($r)&&str_ends_with($r->get_error_code(),$suffix),'reject '.$suffix);
}
$before=[];foreach(['service','blocker'] as $kind)$before[$kind]=Adapter::catalog($kind)['revision'];
$settings=wrd_rcb_call('get-settings');$groups=wrd_rcb_call('list-groups');
$notices=\DevOwl\RealCookieBanner\Core::getInstance()->getNotices();
$checklist=[];foreach(['add-cookie','add-blocker'] as $key)$checklist[$key]=$notices->isChecklistItem($key);
try {
    wrd_rcb_assert(wrd_rcb_call('get-status')['supported'],'reviewed target supported');
    foreach(['service','blocker'] as $kind) {
        $schema=wrd_rcb_call('get-edit-schema',['kind'=>$kind]);
        wrd_rcb_assert(isset($schema['changes_schema']['properties']['meta']),'editable schema '.$kind);
        $list=wrd_rcb_call('list-items',['kind'=>$kind]);
        $changes=['title'=>'WRD RCB acceptance fixture','description'=>'Temporary unpublished acceptance fixture.'];
        if($kind==='service')$changes['meta']=['provider'=>'WRD test','codeOptIn'=>'/* Deliberately no executable statements. */'];
        else {$changes['service_ids']=[$fixtures['service']];$changes['meta']=['rules'=>'https://example.invalid/wrd-test/*','isVisual'=>false];}
        $create=['kind'=>$kind,'expected_revision'=>$list['catalog_revision'],'reason'=>'Scoped acceptance test','changes'=>$changes];
        wrd_rcb_assert(wrd_rcb_call('create-draft',$create)['dry_run'],'create preview '.$kind);
        wrd_rcb_assert(Adapter::catalog($kind)['revision']===$list['catalog_revision'],'create preview unchanged '.$kind);
        $new=wrd_rcb_call('create-draft',$create+['dry_run'=>false])['item'];$id=$new['id'];$fixtures[$kind]=$id;
        wrd_rcb_assert($new['status']==='draft','created unpublished '.$kind);
        wrd_rcb_reject('create-draft',$create+['dry_run'=>false],'revision');
        $item=wrd_rcb_call('get-item',['kind'=>$kind,'id'=>$id]);
        wrd_rcb_assert(!isset($item['meta']),'metadata values omitted '.$kind);
        $details=wrd_rcb_call('get-item',['kind'=>$kind,'id'=>$id,'include_details'=>true]);
        wrd_rcb_assert($details['description']===$changes['description'],'native description preserved '.$kind);
        if($kind==='service')wrd_rcb_assert($details['meta']['codeOptIn']===$changes['meta']['codeOptIn'],'native code preserved without execution');
        else wrd_rcb_assert($details['service_ids']===[$fixtures['service']],'native service references');
        $base=['kind'=>$kind,'id'=>$id,'expected_revision'=>$item['revision'],'reason'=>'Scoped acceptance test'];
        $update=$base+['changes'=>['title'=>'WRD RCB acceptance updated']];
        wrd_rcb_assert(wrd_rcb_call('update-draft',$update)['dry_run'],'update preview '.$kind);
        wrd_rcb_assert(Adapter::revision($id)===$item['revision'],'update preview unchanged '.$kind);
        $new=wrd_rcb_call('update-draft',$update+['dry_run'=>false])['item'];
        wrd_rcb_assert($new['title']==='WRD RCB acceptance updated','native update '.$kind);
        wrd_rcb_reject('update-draft',$update+['dry_run'=>false],'revision');
        $base['expected_revision']=$new['revision'];
        if($kind==='service')wrd_rcb_reject('update-draft',$base+['changes'=>['groups'=>[2147483647]]],'reference');
        else wrd_rcb_reject('update-draft',$base+['changes'=>['service_ids'=>[2147483647]]],'reference');
        // A private fixture checks the non-draft guard without ever publishing visitor-facing data.
        wp_update_post(['ID'=>$id,'post_status'=>'private']);
        $private=$base;$private['expected_revision']=Adapter::revision($id);
        wrd_rcb_reject('update-draft',$private+['changes'=>['title'=>'Must reject']],'state');
        wp_update_post(['ID'=>$id,'post_status'=>'draft']);
        $base['expected_revision']=Adapter::revision($id);
        wrd_rcb_assert(wrd_rcb_call('trash-draft',$base)['dry_run'],'trash preview '.$kind);
        $trashed=wrd_rcb_call('trash-draft',$base+['dry_run'=>false])['item'];
        wrd_rcb_assert($trashed['status']==='trash','native trash '.$kind);
        $base['expected_revision']=$trashed['revision'];
        wrd_rcb_assert(wrd_rcb_call('restore-draft',$base)['dry_run'],'restore preview '.$kind);
        $restored=wrd_rcb_call('restore-draft',$base+['dry_run'=>false])['item'];
        wrd_rcb_assert($restored['status']==='draft','native restore remains unpublished '.$kind);
    }
    $user=get_current_user_id();wp_set_current_user(0);
    $r=wp_get_ability('real-cookie-banner/get-settings')->execute([]);
    wp_set_current_user($user);
    wrd_rcb_assert(is_wp_error($r),'anonymous user rejected');
} finally {
    // Delete only fixtures created by this invocation, removing the blocker before its service.
    foreach(array_reverse($fixtures) as $id)if(!wp_delete_post($id,true))throw new RuntimeException('Fixture cleanup failed.');
    foreach($checklist as $key=>$state)$notices->setChecklistItem($key,$state);
}
foreach(['service','blocker'] as $kind)wrd_rcb_assert(Adapter::catalog($kind)['revision']===$before[$kind],'original catalog unchanged '.$kind);
wrd_rcb_assert(wrd_rcb_call('get-settings')===$settings,'selected settings unchanged');
wrd_rcb_assert(wrd_rcb_call('list-groups')===$groups,'groups unchanged');
foreach($checklist as $key=>$state)wrd_rcb_assert($notices->isChecklistItem($key)===$state,'checklist restored '.$key);
echo wp_json_encode(['site'=>get_option('siteurl'),'passed'=>count($GLOBALS['wrd_rcb_checks']),'checks'=>$GLOBALS['wrd_rcb_checks'],'fixtures_removed'=>true,'published_nothing'=>true]);
