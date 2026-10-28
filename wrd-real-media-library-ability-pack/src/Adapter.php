<?php
/** Native RML operations with bounded inputs and whole-library revision checking. */
declare(strict_types=1);
namespace WeRock\RealMediaLibraryAbilities;
use MatthiasWeb\RealMediaLibrary\Core;
defined('ABSPATH') || exit;

final class Adapter
{
    /** Use stable redacted errors rather than forwarding vendor exception text. */
    public static function error(string $code,string $message): \WP_Error {return new \WP_Error('wrd_rml_'.$code,$message,['status'=>409]);}

    /** Check operation-specific native functions while retaining vendor permissions. */
    public static function guard(string $operation = 'get-status'): ?\WP_Error
    {
        if(!defined('RML_VERSION'))return self::error('target','Active Real Media Library is required.');
        if(!is_callable([Core::class,'getInstance']) || !is_callable(['MatthiasWeb\\RealMediaLibrary\\rest\\Service','permit']) || !is_callable('_wp_rml_root'))return self::error('native_api','Native core, permission check or root accessor unavailable.');
        $core=Core::getInstance();
        if(!is_object($core))return self::error('native_api','Native core unavailable.');
        if($operation==='create-folder'&&!is_callable([$core,'isPro']))return self::error('native_api','Native edition accessor unavailable.');
        if(is_multisite())return self::error('scope','Multisite is not supported.');
        if(is_wp_error(\MatthiasWeb\RealMediaLibrary\rest\Service::permit()))return self::error('permission','Native Real Media Library permission is required.');
        if(_wp_rml_root()!==-1)return self::error('root','A customized root requires a separately reviewed adapter.');
        // Catalog reads do not change multilingual folder mappings; writes remain blocked.
        $writers=['create-folder','rename-folder','move-attachments','delete-empty-folder'];
        if(in_array($operation,$writers,true) && (defined('ICL_SITEPRESS_VERSION') || function_exists('pll_current_language')))return self::error('scope','Multilingual folder writes require a separately reviewed adapter.');
        if($operation==='get-status')return null;
        if(!is_callable([$core,'getTableName']))return self::error('native_api','Native table accessor unavailable.');
        $writer=['create-folder'=>'wp_rml_create','rename-folder'=>'wp_rml_rename','move-attachments'=>'wp_rml_move','delete-empty-folder'=>'wp_rml_delete'][$operation]??null;
        $functions=$writer===null?[]:[$writer,'wp_rml_structure_reset','wp_rml_get_object_by_id'];
        if($operation==='move-attachments')$functions[]='wp_attachment_folder';
        foreach($functions as $function)if(!is_callable($function))return self::error('native_api','Required native function unavailable: '.$function);
        return null;
    }

    /** Resolve the native physical tables instead of assuming a WordPress prefix. */
    public static function tables(): array {return array_map(static fn($suffix)=>Core::getInstance()->getTableName($suffix),['','posts','meta']);}

    /** Read logical state without invoking count-cache or structure initialization writes. */
    public static function snapshot(): array
    {
        global $wpdb;[$folders,$posts,$meta]=self::tables();
        $rows=$wpdb->get_results("SELECT * FROM `$folders` ORDER BY id",ARRAY_A);
        foreach($rows as &$row)unset($row['cnt']);unset($row);
        return ['folders'=>$rows,'assignments'=>$wpdb->get_results("SELECT * FROM `$posts` ORDER BY attachment,isShortcut",ARRAY_A),'metadata'=>$wpdb->get_results("SELECT * FROM `$meta` ORDER BY meta_id",ARRAY_A)];
    }

    /** Include structure, ordering, ownership, restrictions, assignments and folder metadata. */
    public static function revision(): string {return hash('sha256',wp_json_encode(self::snapshot()));}

    /** Return only folder fields useful to an agent; root is a virtual folder. */
    public static function folder(int $id)
    {
        global $wpdb;[$table]=self::tables();
        if($id===-1)return ['id'=>-1,'parent'=>null,'name'=>'Unorganized','type'=>0];
        $row=$wpdb->get_row($wpdb->prepare("SELECT id,parent,name,type FROM `$table` WHERE id=%d",$id),ARRAY_A);
        if(!$row)return self::error('missing','Folder not found.');
        foreach(['id','parent','type'] as $key)$row[$key]=(int)$row[$key];
        return $row;
    }

    /** Read media IDs, titles and MIME types without exposing file paths or URLs. */
    public static function attachments(array $a): array
    {
        global $wpdb;[, $table]=self::tables();$id=$a['folder_id'];
        $where=$wpdb->prepare("p.post_type='attachment' AND COALESCE(r.fid,-1)=%d",$id);
        $join="FROM {$wpdb->posts} p LEFT JOIN `$table` r ON r.attachment=p.ID AND r.isShortcut=0 WHERE $where";
        $rows=$wpdb->get_results($wpdb->prepare("SELECT p.ID AS id,p.post_title AS title,p.post_mime_type AS mime_type $join ORDER BY p.ID LIMIT %d OFFSET %d",$a['limit']??25,$a['offset']??0),ARRAY_A);
        foreach($rows as &$r)$r['id']=(int)$r['id'];unset($r);
        return ['items'=>$rows,'total'=>(int)$wpdb->get_var("SELECT COUNT(*) $join"),'revision'=>self::revision()];
    }

    /** Dispatch bounded reads and writes. */
    public static function run(string $op,array $a)
    {
        if($op==='get-status') {
            $ready=self::guard()===null;
            return ['version'=>defined('RML_VERSION')?RML_VERSION:null,'source_version'=>'4.23.7','source_version_matches'=>defined('RML_VERSION')&&RML_VERSION==='4.23.7','version_policy'=>'informational','supported'=>$ready,'writes_supported'=>self::guard('create-folder')===null,'pro'=>$ready&&is_callable([Core::getInstance(),'isPro'])?Core::getInstance()->isPro():null];
        }
        if($e=self::guard($op))return $e;
        if($op==='list-folders') {
            $rows=self::snapshot()['folders'];
            return ['items'=>array_map(static fn($r)=>self::folder((int)$r['id']),array_slice($rows,$a['offset']??0,$a['limit']??25)),'total'=>count($rows),'root_id'=>-1,'revision'=>self::revision()];
        }
        if(in_array($op,['get-folder','list-attachments'],true)) {
            $f=self::folder($a['folder_id']);if(is_wp_error($f))return $f;
            return $op==='get-folder'?['folder'=>$f,'revision'=>self::revision()]:self::attachments($a);
        }
        return self::write($op,$a);
    }

    /** Serialize pack operations and lock native state before the optimistic revision check. */
    private static function write(string $op,array $a)
    {
        global $wpdb;$tables=self::tables();[$folders,$posts]=$tables;
        foreach($tables as $table)if($wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table))!=='InnoDB')return self::error('storage','Native tables must use InnoDB.');
        $lock=substr('wrd_rml_'.hash('sha256',DB_NAME.':'.$folders),0,64);
        if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))!==1)return self::error('busy','Another pack operation is running.');
        $committed=false;
        try {
            if($wpdb->query('START TRANSACTION')===false)throw new \RuntimeException();
            foreach($tables as $table)if($wpdb->query("SELECT * FROM `$table` FOR UPDATE")===false)throw new \RuntimeException();
            if(!hash_equals(self::revision(),$a['expected_revision']))return self::error('revision','Library changed. Read a fresh revision before retrying.');
            wp_rml_structure_reset();
            $id=$a['folder_id']??$a['parent_id']??-1;$f=self::folder($id);
            if(is_wp_error($f))return $f;
            $object=wp_rml_get_object_by_id($id);
            if(!$object || $f['type']!==0)return self::error('type','This write surface supports regular folders and the unorganized root.');
            // Object-level contracts are checked after resolving the actual native folder.
            if(!is_callable([$object,'getRestrictions']) || (isset($a['name'])&&!is_callable([$object,'isValidName'])))return self::error('native_api','Native folder validation methods unavailable.');
            if($object->getRestrictions())return self::error('restricted','Restricted folders require native administration.');
            if(in_array($op,['rename-folder','delete-empty-folder'],true)&&$id===-1)return self::error('root','The virtual root cannot be renamed or deleted.');
            if(isset($a['name'])) {
                if(!$object->isValidName($a['name']))return self::error('name','Invalid folder name.');
                $parent=$op==='create-folder'?$id:$f['parent'];
                $duplicate=$wpdb->get_var($wpdb->prepare("SELECT id FROM `$folders` WHERE parent=%d AND name=%s AND id<>%d",$parent,$a['name'],$op==='rename-folder'?$id:0));
                if($duplicate)return self::error('duplicate','A sibling with this name already exists.');
                if($op==='create-folder' && $parent!==-1 && !Core::getInstance()->isPro())return self::error('edition','Subfolders require the native Pro edition.');
            }
            if($op==='delete-empty-folder') {
                if($wpdb->get_var($wpdb->prepare("SELECT id FROM `$folders` WHERE parent=%d LIMIT 1",$id)) || $wpdb->get_var($wpdb->prepare("SELECT attachment FROM `$posts` WHERE fid=%d LIMIT 1",$id)))return self::error('not_empty','Only folders without children or assignments can be removed.');
            }
            if($op==='move-attachments')foreach($a['attachment_ids'] as $attachment) {
                // Hold attachment rows against concurrent deletion while moving their virtual assignment.
                $row=$wpdb->get_row($wpdb->prepare("SELECT post_type FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE",$attachment));
                if(!$row || $row->post_type!=='attachment' || !current_user_can('edit_post',$attachment))return self::error('attachment','Every selected attachment must exist and be editable.');
                if($wpdb->get_var($wpdb->prepare("SELECT attachment FROM `$posts` WHERE attachment=%d AND isShortcut<>0 LIMIT 1",$attachment)))return self::error('shortcut','Shortcut assignments require native administration.');
                $source=wp_rml_get_object_by_id((int)wp_attachment_folder($attachment,-1));
                if($source&&!is_callable([$source,'getRestrictions']))return self::error('native_api','Native source-folder restriction check unavailable.');
                if(!$source || $source->getRestrictions())return self::error('restricted','A source folder is restricted.');
            }
            if($a['dry_run']??true)return ['dry_run'=>true,'operation'=>$op,'folder_id'=>$id,'attachment_ids'=>$a['attachment_ids']??[],'native_hooks_rechecked_on_execution'=>true];
            // Never suppress the plugin's validation and lifecycle hooks.
            switch($op) {
                case 'create-folder':$result=wp_rml_create($a['name'],$id,0,[],false,false);if(!is_int($result)||$result<1)throw new \RuntimeException();$id=$result;break;
                case 'rename-folder':$result=$f['name']===$a['name']?true:wp_rml_rename($a['name'],$id,false);if($result!==true)throw new \RuntimeException();break;
                case 'delete-empty-folder':if(wp_rml_delete($id,false)!==true)throw new \RuntimeException();break;
                case 'move-attachments':if(wp_rml_move($id,$a['attachment_ids'],false,false)!==true)throw new \RuntimeException();break;
                default:throw new \RuntimeException();
            }
            wp_rml_structure_reset();
            if($wpdb->last_error)throw new \RuntimeException();
            if($op==='move-attachments')foreach($a['attachment_ids'] as $attachment)if((int)wp_attachment_folder($attachment,-1)!==$id)throw new \RuntimeException();
            if(in_array($op,['create-folder','rename-folder'],true)) { $fresh=self::folder($id);if(is_wp_error($fresh)||$fresh['name']!==$a['name'])throw new \RuntimeException(); }
            if($op==='delete-empty-folder'&&!is_wp_error(self::folder($id)))throw new \RuntimeException();
            if($wpdb->query('COMMIT')===false)throw new \RuntimeException();$committed=true;
            return ['dry_run'=>false,'folder_id'=>$id,'revision'=>self::revision()];
        } finally {
            // External hook effects are not transactional; reconcile an uncertain failure before retrying.
            if(!$committed)$wpdb->query('ROLLBACK');
            try {wp_rml_structure_reset();}
            finally {$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
        }
    }
}
