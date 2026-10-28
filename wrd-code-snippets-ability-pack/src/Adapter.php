<?php
/** Native, site-local Code Snippets operations with explicit write boundaries. */
declare(strict_types=1);
namespace WeRock\CodeSnippetsAbilities;
use Code_Snippets\Model\Snippet;
use function Code_Snippets\code_snippets;
defined('ABSPATH') || exit;

final class Adapter
{
    public const SCOPES = ['global','admin','front-end','content','head-content','body-content','footer-content','admin-css','site-css','site-head-js','site-footer-js'];

    /** Return stable errors without native exception text or snippet content. */
    public static function error(string $code, string $message): \WP_Error
    {
        return new \WP_Error('wrd_code_snippets_' . $code, $message, ['status' => 409]);
    }

    /** Check only native entry points needed by the requested operation, never its version. */
    public static function guard(string $operation = 'get-status', ?string $scope = null): ?\WP_Error
    {
        if (!defined('CODE_SNIPPETS_VERSION')) return self::error('target', 'Active Code Snippets is required.');
        if (!is_callable('Code_Snippets\\code_snippets')) return self::error('native_api', 'Native Code Snippets accessor unavailable.');
        $plugin = code_snippets();
        if (!is_object($plugin) || !is_callable([$plugin, 'current_user_can'])) return self::error('native_api', 'Native permission check unavailable.');
        if (!$plugin->current_user_can()) return self::error('permission', 'Native Code Snippets management capability is required.');
        if (is_multisite()) return self::error('multisite', 'This pack supports single-site installations.');
        if ($operation === 'get-status') return null;
        if ($operation === 'validate-code' && in_array($scope, ['global', 'admin', 'front-end'], true)
            && !method_exists('Code_Snippets\\Utils\\Validator', 'validate')) return self::error('native_api', 'Native code validator unavailable.');
        if ($operation === 'validate-code') return null;
        if (!class_exists(Snippet::class) || !isset($plugin->db) || !is_callable([$plugin->db, 'get_table_name'])) return self::error('native_api', 'Native snippet model or table accessor unavailable.');
        $functions = ['clean_snippets_cache', 'is_snippet_locked'];
        $functions[] = in_array($operation, ['list-snippets', 'create-snippet'], true) ? 'get_snippets' : 'get_snippet';
        $writer = ['create-snippet'=>'save_snippet', 'update-snippet'=>'save_snippet', 'activate-snippet'=>'activate_snippet',
            'deactivate-snippet'=>'deactivate_snippet', 'trash-snippet'=>'trash_snippet', 'restore-snippet'=>'restore_snippet'][$operation] ?? null;
        if ($writer !== null) { $functions[] = $writer; $functions[] = 'get_snippet'; }
        foreach ($functions as $function) if (!is_callable('Code_Snippets\\' . $function)) return self::error('native_api', 'Required native function unavailable: ' . $function);
        return null;
    }

    /** Build safe metadata; code and descriptions require a separate explicit read. */
    public static function project(Snippet $s): array
    {
        return ['id'=>$s->id,'name'=>$s->name,'scope'=>$s->scope,'priority'=>$s->priority,'active'=>$s->active,'trashed'=>$s->trashed,'locked'=>$s->locked,'cloud_linked'=>(bool)$s->cloud_id,'condition_id'=>$s->condition_id,'tags'=>$s->tags,'revision'=>self::revision($s)];
    }

    /** Cover the entire native row and lock state, including unexposed fields. */
    public static function revision(Snippet $s): string
    {
        global $wpdb;
        $table = code_snippets()->db->get_table_name(false);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$table` WHERE id=%d", $s->id), ARRAY_A);
        return hash('sha256', wp_json_encode([$row, \Code_Snippets\is_snippet_locked($s->id, false)]));
    }

    /** Clear native caches before a fresh single-record read. */
    public static function get(int $id): ?Snippet
    {
        \Code_Snippets\clean_snippets_cache(code_snippets()->db->get_table_name(false));
        $s = \Code_Snippets\get_snippet($id, false);
        return $s && $s->id === $id ? $s : null;
    }

    /** Provide a catalog revision for create requests and safe paged discovery. */
    public static function listing(array $args): array
    {
        \Code_Snippets\clean_snippets_cache(code_snippets()->db->get_table_name(false));
        $snippets = \Code_Snippets\get_snippets([], false);
        usort($snippets, static fn($a,$b) => $a->id <=> $b->id);
        $rows = array_map([self::class,'project'], $snippets);
        return ['items'=>array_slice($rows,$args['offset']??0,$args['limit']??25),'total'=>count($rows),'catalog_revision'=>hash('sha256',wp_json_encode(array_column($rows,'revision')))];
    }

    /** Parse PHP without evaluation and then run the vendor's identifier validator. */
    public static function validate(string $code, string $scope): array
    {
        if (!in_array($scope,['global','admin','front-end'],true)) return ['valid'=>null,'executed'=>false,'validation'=>'PHP parser not applicable; review HTML/CSS/JavaScript separately.'];
        // Non-PHP snippets remain usable when only the PHP validator is unavailable.
        if (!method_exists('Code_Snippets\\Utils\\Validator', 'validate')) return ['valid'=>false,'executed'=>false,'validation'=>'Native PHP validator unavailable.'];
        try {
            token_get_all("<?php\n" . $code, TOKEN_PARSE);
            $error = (new \Code_Snippets\Utils\Validator($code))->validate();
            return ['valid'=>!$error,'executed'=>false,'line'=>$error ? (int)($error['line']??0) : null];
        } catch (\ParseError $e) {
            return ['valid'=>false,'executed'=>false,'line'=>$e->getLine()];
        }
    }

    /** Dispatch reads and mutation previews using the same validation rules. */
    public static function run(string $operation, array $a)
    {
        if ($operation === 'get-status') return ['version'=>defined('CODE_SNIPPETS_VERSION')?CODE_SNIPPETS_VERSION:null,'source_version'=>'4.0.0','source_version_matches'=>defined('CODE_SNIPPETS_VERSION')&&CODE_SNIPPETS_VERSION==='4.0.0','version_policy'=>'informational','supported'=>self::guard()===null,'safe_mode'=>defined('CODE_SNIPPETS_SAFE_MODE')&&(bool)CODE_SNIPPETS_SAFE_MODE,'scopes'=>self::SCOPES];
        if ($error=self::guard($operation,$a['scope']??null)) return $error;
        if ($operation==='list-snippets') return self::listing($a);
        if ($operation==='validate-code') return self::validate($a['code'],$a['scope']);
        if ($operation==='get-snippet') {
            $s=self::get($a['id']);
            if (!$s) return self::error('missing','Snippet not found.');
            $out=self::project($s);
            if ($a['include_code']??false) {$out['code']=$s->code;$out['description']=$s->desc;}
            return $out;
        }
        return self::write($operation,$a);
    }

    /** Serialize pack writes and lock native rows against concurrent database edits. */
    private static function write(string $operation, array $a)
    {
        global $wpdb;
        $table=code_snippets()->db->get_table_name(false);
        $engine=$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table));
        if ($engine!=='InnoDB') return self::error('storage','InnoDB is required for revision-checked writes.');
        $lock='wrd_cs_' . hash('sha256',DB_NAME . ':' . $table);
        $lock=substr($lock,0,64);
        if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))!==1) return self::error('busy','Another pack write is in progress.');
        $committed=false;
        try {
            if ($wpdb->query('START TRANSACTION')===false) throw new \RuntimeException();
            // A complete ordered range lock also serializes catalog-based creation.
            if ($wpdb->query("SELECT id FROM `$table` ORDER BY id FOR UPDATE")===false) throw new \RuntimeException();
            $s=$operation==='create-snippet' ? new Snippet(['network'=>false]) : self::get($a['id']);
            if (!$s) return self::error('missing','Snippet not found.');
            $revision=$operation==='create-snippet' ? self::listing([])['catalog_revision'] : self::revision($s);
            if (!hash_equals($revision,$a['expected_revision'])) return self::error('revision','State changed. Read the current revision before retrying.');
            if ($s->locked || $s->cloud_id || $s->condition_id || !in_array($s->scope,self::SCOPES,true)) return self::error('protected','Locked, cloud-linked, conditional or unsupported snippets require native administration.');
            if (in_array($operation,['create-snippet','update-snippet'],true)) {
                if ($s->active || $s->trashed) return self::error('state','Deactivate or restore the snippet before editing.');
                foreach ($a['changes'] as $field=>$value) $s->{$field==='description'?'desc':$field}=$value;
                $s->active=false;
                $validation=self::validate($s->code,$s->scope);
                if ($validation['valid']===false) return self::error('code','PHP validation failed; use validate-code for the line number.');
            } elseif ($operation==='activate-snippet') {
                if ($s->trashed) return self::error('state','Restore the snippet before activation.');
                if (($a['acknowledge_execution']??false)!==true) return self::error('acknowledgement','Activation enables code execution; acknowledge_execution must be true.');
                if (defined('CODE_SNIPPETS_SAFE_MODE') && CODE_SNIPPETS_SAFE_MODE) return self::error('safe_mode','Activation is blocked while global safe mode is enabled.');
                if (self::validate($s->code,$s->scope)['valid']===false) return self::error('code','PHP validation failed.');
            } elseif ($operation==='trash-snippet' && $s->active) return self::error('state','Deactivate before trashing.');
            elseif ($operation==='deactivate-snippet' && $s->trashed) return self::error('state','Snippet is trashed.');
            $before=self::project($s);
            if ($a['dry_run']??true) return ['dry_run'=>true,'operation'=>$operation,'snippet'=>$before,'changed_fields'=>array_keys($a['changes']??[])];
            // Native operations retain cache, asset revision and file mirroring hooks.
            $ok=true;
            switch ($operation) {
                case 'create-snippet': case 'update-snippet': $saved=\Code_Snippets\save_snippet($s);$ok=$saved && $saved->id>0;if($ok)$s=$saved;break;
                case 'activate-snippet': if(!$s->active)$ok=\Code_Snippets\activate_snippet($s->id,false) instanceof Snippet;break;
                case 'deactivate-snippet': if($s->active)$ok=\Code_Snippets\deactivate_snippet($s->id,false) instanceof Snippet;break;
                case 'trash-snippet': if(!$s->trashed)$ok=\Code_Snippets\trash_snippet($s->id,false);break;
                case 'restore-snippet': if($s->trashed)$ok=\Code_Snippets\restore_snippet($s->id,false);break;
                default: throw new \RuntimeException();
            }
            if (!$ok || $wpdb->last_error) throw new \RuntimeException();
            if ($wpdb->query('COMMIT')===false) throw new \RuntimeException();
            $committed=true;
            $fresh=self::get($s->id);
            if (!$fresh) throw new \RuntimeException();
            return ['dry_run'=>false,'operation'=>$operation,'snippet'=>self::project($fresh)];
        } finally {
            // Native hooks may have filesystem effects; callers must reconcile uncertain failures.
            if (!$committed) $wpdb->query('ROLLBACK');
            \Code_Snippets\clean_snippets_cache($table);
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));
        }
    }
}
