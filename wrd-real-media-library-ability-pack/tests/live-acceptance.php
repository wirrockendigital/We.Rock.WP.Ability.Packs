<?php
/** Scoped live acceptance with isolated folders and a disposable media file. */
if(!defined('WP_CLI') || !WP_CLI)throw new RuntimeException('WP-CLI is required.');
use WeRock\RealMediaLibraryAbilities\Adapter;
$GLOBALS['wrd_rml_checks']=[];$created=[];$attachment=null;$file=null;
/** Record only safe test labels. */
function wrd_rml_assert($ok,string $label):void {if(!$ok)throw new RuntimeException($label);$GLOBALS['wrd_rml_checks'][]=$label;}
/** Use the actual registered ability including schema and host permissions. */
function wrd_rml_call(string $op,array $args=[]):array {
    $a=wp_get_ability('real-media-library/'.$op);if(!$a)throw new RuntimeException('Missing ability');
    $r=$a->execute($args);if(is_wp_error($r))throw new RuntimeException($op.': '.$r->get_error_code());return $r['result'];
}
/** Require the intended rejection rather than an unrelated input failure. */
function wrd_rml_reject(string $op,array $args,string $suffix):void {
    $r=wp_get_ability('real-media-library/'.$op)->execute($args);
    wrd_rml_assert(is_wp_error($r)&&str_ends_with($r->get_error_code(),$suffix),'reject '.$suffix);
}
/** Supply the current whole-library revision for a new logical operation. */
function wrd_rml_args(array $args=[]):array {return $args+['expected_revision'=>Adapter::revision(),'reason'=>'Scoped acceptance test'];}
$before=Adapter::snapshot();
try {
    wrd_rml_assert(wrd_rml_call('get-status')['supported'],'reviewed target supported');
    wrd_rml_assert(isset(wrd_rml_call('list-folders')['revision']),'list folders revision');
    $create=wrd_rml_args(['parent_id'=>-1,'name'=>'WRD-RML-acceptance-'.wp_generate_uuid4()]);
    wrd_rml_assert(wrd_rml_call('create-folder',$create)['dry_run'],'create defaults to preview');
    wrd_rml_assert(Adapter::snapshot()===$before,'preview preserved complete logical state');
    $parent=wrd_rml_call('create-folder',$create+['dry_run'=>false])['folder_id'];$created[]=$parent;
    wrd_rml_reject('create-folder',$create+['dry_run'=>false],'revision');
    $child=wrd_rml_call('create-folder',wrd_rml_args(['parent_id'=>$parent,'name'=>'Child','dry_run'=>false]))['folder_id'];$created[]=$child;
    wrd_rml_assert(wrd_rml_call('get-folder',['folder_id'=>$child])['folder']['parent']===$parent,'native subfolder creation');
    wrd_rml_reject('delete-empty-folder',wrd_rml_args(['folder_id'=>$parent]),'not_empty');
    $rename=wrd_rml_args(['folder_id'=>$child,'name'=>'Renamed']);
    $rev=Adapter::revision();wrd_rml_assert(wrd_rml_call('rename-folder',$rename)['dry_run'],'rename preview');
    wrd_rml_assert(Adapter::revision()===$rev,'rename preview unchanged');
    wrd_rml_call('rename-folder',$rename+['dry_run'=>false]);
    wrd_rml_assert(wrd_rml_call('get-folder',['folder_id'=>$child])['folder']['name']==='Renamed','native rename');
    wrd_rml_reject('rename-folder',$rename+['dry_run'=>false],'revision');
    wrd_rml_reject('create-folder',wrd_rml_args(['parent_id'=>$parent,'name'=>'Renamed']),'duplicate');
    // A small text attachment provides a real file-integrity check without touching user media.
    $upload=wp_upload_bits('wrd-rml-acceptance-'.wp_generate_uuid4().'.txt',null,"WRD isolated acceptance fixture.\n");
    if($upload['error'])throw new RuntimeException('Fixture upload failed');$file=$upload['file'];
    $attachment=wp_insert_attachment(['post_title'=>'WRD RML acceptance','post_status'=>'inherit','post_mime_type'=>'text/plain'],$file,0,true);
    if(is_wp_error($attachment)){$attachment=null;throw new RuntimeException('Fixture attachment failed');}
    $hash=hash_file('sha256',$file);$originalPath=get_attached_file($attachment);$originalUrl=wp_get_attachment_url($attachment);
    $move=wrd_rml_args(['folder_id'=>$child,'attachment_ids'=>[$attachment]]);
    wrd_rml_assert(wrd_rml_call('move-attachments',$move)['dry_run'],'move preview');
    wrd_rml_call('move-attachments',$move+['dry_run'=>false]);
    wrd_rml_assert((int)wp_attachment_folder($attachment,-1)===$child,'native media movement');
    $media=wrd_rml_call('list-attachments',['folder_id'=>$child]);
    wrd_rml_assert(in_array($attachment,array_column($media['items'],'id'),true),'moved attachment listed');
    wrd_rml_reject('delete-empty-folder',wrd_rml_args(['folder_id'=>$child]),'not_empty');
    wrd_rml_assert(hash_file('sha256',$file)===$hash && get_attached_file($attachment)===$originalPath && wp_get_attachment_url($attachment)===$originalUrl,'file bytes path and URL unchanged');
    wrd_rml_call('move-attachments',wrd_rml_args(['folder_id'=>-1,'attachment_ids'=>[$attachment],'dry_run'=>false]));
    wrd_rml_assert((int)wp_attachment_folder($attachment,-1)===-1,'native return to unorganized');
    wrd_rml_reject('move-attachments',wrd_rml_args(['folder_id'=>$child,'attachment_ids'=>[2147483647]]),'attachment');
    wrd_rml_reject('delete-empty-folder',wrd_rml_args(['folder_id'=>-1]),'root');
    $delete=wrd_rml_args(['folder_id'=>$child]);
    wrd_rml_assert(wrd_rml_call('delete-empty-folder',$delete)['dry_run'],'delete preview');
    wrd_rml_call('delete-empty-folder',$delete+['dry_run'=>false]);
    wrd_rml_assert(is_wp_error(Adapter::folder($child)),'native empty-folder deletion');
    $created=array_values(array_diff($created,[$child]));
    wrd_rml_call('delete-empty-folder',wrd_rml_args(['folder_id'=>$parent,'dry_run'=>false]));$created=[];
    $user=get_current_user_id();wp_set_current_user(0);$denied=wp_get_ability('real-media-library/list-folders')->execute([]);wp_set_current_user($user);
    wrd_rml_assert(is_wp_error($denied),'valid anonymous request denied');
} finally {
    // Clean only IDs and files created in this invocation, including exceptional exits.
    if($attachment && !wp_delete_attachment($attachment,true))throw new RuntimeException('Fixture attachment cleanup failed');
    if($file && is_file($file))wp_delete_file($file);
    foreach(array_reverse($created) as $id)if(wp_rml_delete($id,false)!==true)throw new RuntimeException('Fixture folder cleanup failed');
    wp_rml_structure_reset();
}
wrd_rml_assert(Adapter::snapshot()===$before,'original folders assignments and metadata preserved');
echo wp_json_encode(['site'=>get_option('siteurl'),'checks'=>$GLOBALS['wrd_rml_checks'],'passed'=>count($GLOBALS['wrd_rml_checks']),'fixtures_removed'=>true,'original_logical_state_unchanged'=>true]);
