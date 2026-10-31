<?php
/** Native maintenance catalog, redacted state and replay-safe operation receipts. */
declare(strict_types=1);
namespace WeRock\LiteSpeedAbilities;
defined('ABSPATH') || exit;
final class Operations {
    public const DB_TYPES=['revision','orphaned_post_meta','auto_draft','trash_post','spam_comment','trash_comment','trackback-pingback','expired_transient','all_transients','optimize_tables'];
    private const COMPONENTS=['image'=>'Img_Optm','crawler'=>'Crawler','cloud'=>'Cloud','ccss'=>'CSS','ucss'=>'UCSS','lqip'=>'Placeholder','vpi'=>'VPI','optimax'=>'Optimax','cache'=>'Purge'];
    /** Map each public action to one reviewed native method and fixed arguments. */
    public static function catalog(): array {
        $items=[
            'purge-all'=>['cache','Purge','purge_all',true,[],'Invalidates page/CSS/JS/local-resource caches, shared object/OPcache and configured Cloudflare cache.'],
            'purge-object'=>['cache','Purge','purge_all_object',true,[],'Flushes the configured object cache; other sites sharing the backend may be affected.'],
            'purge-opcache'=>['cache','Purge','purge_all_opcache',false,[],'Flushes shared PHP opcode cache; other sites using this PHP pool may be affected.'],
            'purge-feeds'=>['cache','Purge','purge_feeds',false,[],'Invalidates native feed cache tags.'],
            'purge-private'=>['cache','Purge','add_private_all',true,[],'Invalidates private page cache tags.'],
            'request-images'=>['image','Img_Optm','new_req',false,[],'Sends eligible image URLs to QUIC.cloud and can consume quota; native throttling applies.'],
            'pull-images'=>['image','Img_Optm','start_async',true,[],'Requests an asynchronous download of optimized images; files and metadata may change.'],
            'clean-image-queue'=>['image','Img_Optm','clean',false,[],'Clears unfinished native image optimization rows and may rewind the image cursor.'],
            'restore-originals'=>['image','Img_Optm','batch_switch',false,[],'Switches eligible optimized images back to retained originals using native WP-CLI.'],
            'use-optimized'=>['image','Img_Optm','batch_switch',false,[],'Switches eligible images to retained optimized versions using native WP-CLI.'],
            'start-crawler'=>['crawler','Crawler','start_async',true,[],'Requests asynchronous crawling and can increase server load.'],
            'reset-crawler'=>['crawler','Crawler','reset_pos',false,[],'Resets crawler progress; future crawling starts again.'],
            'refresh-crawler-map'=>['crawler','Crawler_Map','gen',false,[true],'Fetches the configured sitemap and rebuilds the crawler map.'],
            'empty-crawler-map'=>['crawler','Crawler_Map','empty_map',false,[],'Removes the native crawler URL map, not WordPress content.'],
            'clear-crawler-blocklist'=>['crawler','Crawler_Map','blacklist_empty',false,[],'Removes the crawler blocklist; affected URLs may be crawled again.'],
            'sync-cloud-usage'=>['cloud','Cloud','sync_usage',false,[],'Contacts QUIC.cloud to refresh local service usage and quota.'],
            'clear-cloud-nodes'=>['cloud','Cloud','clear_cloud',false,[],'Clears cached QUIC.cloud node selections so native services can reconnect.'],
        ];
        foreach (['ccss'=>'CSS','ucss'=>'UCSS','lqip'=>'Placeholder','vpi'=>'VPI','optimax'=>'Optimax'] as $type=>$class) {
            $items['process-'.$type]=[$type,$class,'cron',true,[false],'Processes one native queue pass; may transmit page/image data to QUIC.cloud and consume quota.'];
            $items['clear-'.$type.'-queue']=[$type,$class,'clear_q',false,[$type,true],'Clears waiting jobs for this service without deleting published content.'];
        }
        foreach (['img_optm','ccss','ucss','lqip','vpi','optimax'] as $service) {
            $items['redetect-'.$service]=['cloud','Cloud','detect_cloud',false,[$service,true],'Contacts QUIC.cloud to select a service node.'];
        }
        return $items;
    }
    /** Keep all method checks public and operation-specific; never bypass native visibility. */
    private static function method(string $class,string $method): bool {
        $class='LiteSpeed\\'.$class;
        return method_exists($class,$method) && (new \ReflectionMethod($class,$method))->isPublic();
    }
    /** Expose the action catalog together with its actual native prerequisites. */
    public static function catalog_status(): array {
        $result=[];
        foreach (self::catalog() as $action=>[$component,$class,$method,$static,$args,$effects]) {
            $available=defined('LSCWP_V') && !is_multisite() && self::method($class,$method) && self::method(self::COMPONENTS[$component],'get_summary');
            if (in_array($action,['restore-originals','use-optimized'],true)) $available=$available && NativeProcess::binary()!==null;
            $result[$action]=['component'=>$component,'available'=>$available,'effects'=>$effects,'availability_note'=>'Native method availability; service activation, quota, queue contents and server permissions are checked separately.','requires_shared_cache_acknowledgement'=>in_array($action,['purge-all','purge-object','purge-opcache'],true)];
        }
        return $result;
    }
    /** Enumerate the extended tools without granting unknown operations a default path. */
    public static function handles(string $op): bool {
        return in_array($op,['get-maintenance-status','get-component-status','get-database-status','cleanup-database','run-maintenance','get-operation-receipt','list-crawler-map','set-crawler-active'],true);
    }
    /** Reads remain usable if an unrelated writer or optional CLI binary is missing. */
    public static function available(string $op): bool {
        return match($op) {
            'get-database-status','cleanup-database'=>self::method('DB_Optm','db_count') && ($op!=='cleanup-database'||(self::method('DB_Optm','handler_clean_db_cli')&&NativeProcess::binary()!==null)),
            'list-crawler-map'=>self::method('Crawler_Map','list_map')&&self::method('Crawler_Map','list_blacklist'),
            'set-crawler-active'=>self::method('Crawler','list_crawlers')&&self::method('Crawler','is_active')&&self::method('Crawler','toggle_activeness'),
            default=>true,
        };
    }
    /** Compare the site origin before allowing sitemap-driven fetches. */
    public static function local_https_url(string $url): bool {
        $p=wp_parse_url($url);$home=wp_parse_url(home_url('/'));
        return is_array($p)&&($p['scheme']??'')==='https'&&strtolower($p['host']??'')===strtolower($home['host']??'')&&($p['port']??443)===($home['port']??443)&&!isset($p['user'])&&!isset($p['pass'])&&!isset($p['fragment'])&&!preg_match('/[\x00-\x20\x7f<>\\\\]/',$url);
    }
    /** LiteSpeed accepts multiple sitemap URLs separated by newlines in one native setting. */
    public static function sitemap_urls_valid(string $value): bool {
        $lines=preg_split('/\r\n|\r|\n/',$value);
        if (count($lines)>20) return false;
        foreach ($lines as $line) if (trim($line)!=='' && !self::local_https_url(trim($line))) return false;
        return true;
    }
    /** Hash internal state without returning credentials, paths, queue URLs or cloud tokens. */
    public static function revision(mixed $data): string {
        return hash_hmac('sha256',wp_json_encode([home_url('/'),get_current_blog_id(),$data]),wp_salt('auth'));
    }
    /** Read native summaries; only explicit numeric counters are safe to return. */
    public static function component_state(string $component): array {
        $short=self::COMPONENTS[$component];$class='LiteSpeed\\'.$short;
        if (!self::method($short,'get_summary')) return ['component'=>$component,'available'=>false,'revision'=>self::revision([$component,false])];
        $summary=$class::get_summary();$summary=is_array($summary)?$summary:[];
        $counts=[];
        foreach (['next_post_id','is_running','last_pull','last_request','last_start_time','last_update_time','last_pos','last_count','last_crawled','list_size','curr_crawler','done','total_reduced','total_optm','last_full_time_cost','switch_failed','destroy_failed'] as $key) {
            if (isset($summary[$key])&&is_numeric($summary[$key])) $counts[$key]=0+$summary[$key];
        }
        $raw=[$component,$summary];$result=['component'=>$component,'available'=>true,'counters'=>(object)$counts];
        if (in_array($component,['ccss','ucss','lqip','vpi','optimax'],true)&&self::method($short,'load_queue')) {
            $queue=$class::cls()->load_queue($component);$result['queued']=count((array)$queue);$raw[]=$queue;
        }
        if ($component==='image'&&self::method($short,'img_count')) {
            $native=$class::cls()->img_count();$counts=[];
            foreach ((array)$native as $key=>$value) if (is_numeric($value)) $counts[$key]=0+$value;
            $result['images']=(object)$counts;$raw[]=$native;
        }
        if ($component==='crawler'&&self::method($short,'list_crawlers')&&self::method($short,'is_active')) {
            $items=[];
            foreach (array_keys($class::cls()->list_crawlers()) as $id) $items[]=['id'=>(int)$id,'active'=>(bool)$class::cls()->is_active($id)];
            $result['crawlers']=$items;$raw[]=$items;
        }
        if ($component==='cloud') {
            $result['activated']=!empty($summary['sk_b64'])&&!empty($summary['qc_activated']);
            $usage=[];
            foreach (['img_optm','ccss','ucss','lqip','vpi','optimax','cdn'] as $service) {
                $safe=[];
                foreach (['quota','used','balance','pag_bal','daily_quota','daily_used','monthly_quota','monthly_used'] as $key) {
                    $v=$summary['usage.'.$service][$key]??null;
                    if (is_numeric($v)) $safe[$key]=0+$v;
                }
                if ($safe) $usage[$service]=$safe;
            }
            $result['usage']=(object)$usage;
        }
        global $wpdb;
        // Include media metadata and crawler rows in optimistic state without exposing their contents.
        if ($component==='image') {
            $rows=$wpdb->get_results("SELECT meta_id,post_id,meta_key,SHA2(meta_value,256) AS digest FROM {$wpdb->postmeta} WHERE meta_key IN ('_wp_attachment_metadata','_wp_attached_file') ORDER BY meta_id",ARRAY_A);
            if ($wpdb->last_error) throw new \RuntimeException('Media state read failed');
            $raw[]=$rows;
        }
        if ($component==='crawler' && self::method('Data','tb') && self::method('Data','tb_exist')) {
            $data=\LiteSpeed\Data::cls();
            foreach (['crawler','crawler_blacklist'] as $table) {
                if (!$data->tb_exist($table)) continue;
                $name=$data->tb($table);
                if (!preg_match('/^[a-zA-Z0-9_]+$/',$name)) throw new \RuntimeException('Invalid native table');
                $rows=$wpdb->get_results("SELECT id,SHA2(url,256) AS url_hash,res,mtime FROM `$name` ORDER BY id",ARRAY_A);
                if ($wpdb->last_error) throw new \RuntimeException('Crawler state read failed');
                $raw[]=$rows;
            }
        }
        // Include all persisted configuration in the revision, but expose none of its private values.
        global $wpdb;
        $config=$wpdb->get_results($wpdb->prepare("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name",$wpdb->esc_like('litespeed.conf.').'%'),ARRAY_A);
        if ($wpdb->last_error) throw new \RuntimeException('State read failed');
        $result['revision']=self::revision([$raw,$config]);
        return $result;
    }
    /** Count native cleanup candidates and hash relevant data to detect changed preview state. */
    public static function database_state(): array {
        $native=\LiteSpeed\DB_Optm::cls();$counts=[];
        foreach (self::DB_TYPES as $type) $counts[$type]=$native->db_count($type);
        global $wpdb;
        $queries=[
            "SELECT * FROM {$wpdb->posts} WHERE post_type='revision' OR post_status IN ('auto-draft','trash') ORDER BY ID",
            "SELECT m.* FROM {$wpdb->postmeta} m LEFT JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE p.ID IS NULL OR p.post_type='revision' OR p.post_status IN ('auto-draft','trash') ORDER BY m.meta_id",
            "SELECT * FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash') OR comment_type IN ('trackback','pingback') ORDER BY comment_ID",
            "SELECT m.* FROM {$wpdb->commentmeta} m JOIN {$wpdb->comments} c ON c.comment_ID=m.comment_id WHERE c.comment_approved IN ('spam','trash') OR c.comment_type IN ('trackback','pingback') ORDER BY m.meta_id",
            $wpdb->prepare("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s ORDER BY option_name",$wpdb->esc_like('_transient_').'%', $wpdb->esc_like('litespeed.conf.').'%'),
        ];
        $digests=[];
        foreach ($queries as $sql) {
            $rows=$wpdb->get_results($sql,ARRAY_A);
            if ($wpdb->last_error||!is_array($rows)) throw new \RuntimeException('Database preview read failed');
            $digests[]=self::revision($rows);
        }
        return ['counts'=>$counts,'revision'=>self::revision([$counts,$digests]),'cli_available'=>NativeProcess::binary()!==null,'note'=>'Native retention settings affect revision cleanup. Concurrent native writers are not locked by this pack.'];
    }
    /** Scope receipts to this site and retain only fingerprints and bounded outcomes. */
    private static function receipt_key(string $id): string {return 'wrd_lsc_receipt_'.hash('sha256',$id);}
    /** Dispatch validated read operations or guarded state-changing actions. */
    public static function run(string $op,array $args): array|\WP_Error {
        if ($op==='get-maintenance-status') return ['actions'=>self::catalog_status(),'database_types'=>self::DB_TYPES,'database_cleanup_available'=>self::available('cleanup-database')];
        if ($op==='get-component-status') return self::component_state($args['component']);
        if ($op==='get-database-status') return self::database_state();
        if ($op==='get-operation-receipt') {
            $receipt=get_option(self::receipt_key($args['request_id']),null);
            return ['found'=>is_array($receipt),'receipt'=>$receipt];
        }
        if ($op==='list-crawler-map') {
            $map=\LiteSpeed\Crawler_Map::cls();$method=$args['kind']==='blocked'?'list_blacklist':'list_map';
            $rows=$map->$method($args['limit']??25,$args['offset']??0);$items=[];
            foreach ((array)$rows as $row) {
                // Do not return native URL query strings, cookies or rejection diagnostics.
                $items[]=['id'=>(int)($row['id']??0),'path'=>wp_parse_url((string)($row['url']??''),PHP_URL_PATH)?:'/'];
            }
            return ['items'=>$items,'revision'=>self::component_state('crawler')['revision']];
        }
        if (trim($args['reason'])==='') return Adapter::error('reason','A meaningful reason is required.');
        $action=$op==='cleanup-database'?'database:'.$args['kind']:($op==='set-crawler-active'?'set-crawler-active':$args['action']);
        if ($op==='run-maintenance' && !(self::catalog_status()[$action]['available']??false)) return Adapter::error('unavailable','Required native methods or CLI are unavailable.');
        $component=$op==='cleanup-database'?'database':($op==='set-crawler-active'?'crawler':self::catalog()[$action][0]);
        // One lock covers maintenance writers and receipts; native UI/cron remain independent.
        global $wpdb;
        $lock='wrd_lsc_maint_'.substr(hash('sha256',DB_NAME.':'.$wpdb->prefix),0,36);
        if ((string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)',$lock))!=='1') return Adapter::error('busy','Another maintenance operation is running.');
        try {
            $key=self::receipt_key($args['request_id']);
            $identity=$args;unset($identity['dry_run'],$identity['reason'],$identity['confirm_action'],$identity['backup_reference'],$identity['acknowledge_shared_cache']);
            $fingerprint=self::revision([$op,$identity]);$receipt=get_option($key,null);
            if (is_array($receipt)) {
                if (!hash_equals($receipt['fingerprint'],$fingerprint)) return Adapter::error('request','This request_id belongs to another request.');
                return ['replayed'=>true,'receipt'=>$receipt];
            }
            $state=$component==='database'?self::database_state():self::component_state($component);
            if (empty($state['revision'])||!hash_equals($state['revision'],$args['expected_revision'])) return Adapter::error('revision','State changed; read and preview again.');
            if ($op==='set-crawler-active') {
                $ids=array_column($state['crawlers']??[],'id');
                if (!in_array($args['crawler_id'],$ids,true)) return Adapter::error('input','Unknown zero-based crawler ID.');
            }
            if ($action==='refresh-crawler-map') {
                $url=\LiteSpeed\Conf::cls()->conf('crawler-sitemap');
                if ($url && !self::sitemap_urls_valid((string)$url)) return Adapter::error('url','Configure a same-origin HTTPS crawler sitemap first.');
            }
            $effects=$op==='cleanup-database'?'Permanently deletes the selected native cleanup candidates or optimizes tables. A verified backup is required.':($op==='set-crawler-active'?'Changes one native crawler active flag.':self::catalog()[$action][5]);
            $cloud_required=$action==='request-images'||$action==='sync-cloud-usage'||str_starts_with($action,'redetect-')||str_starts_with($action,'process-');
            $prerequisite=null;
            if ($cloud_required) {
                $cloud=self::component_state('cloud');
                if (empty($cloud['activated'])) $prerequisite='Activate QUIC.cloud through the native administrator UI first.';
            }
            if ($action==='request-images' && !\LiteSpeed\Conf::cls()->conf('img_optm-ori') && !\LiteSpeed\Conf::cls()->conf('img_optm-webp')) $prerequisite='Enable original or next-generation image optimization before requesting images.';
            $preview=['prerequisite_issue'=>$prerequisite,'dry_run'=>$args['dry_run']??true,'action'=>$action,'effects'=>$effects,'state'=>$state,'required_confirmation'=>$action];
            if ($args['dry_run']??true) return $preview+['requested'=>false];
            if ($prerequisite!==null) return Adapter::error('prerequisite',$prerequisite);
            if (($args['confirm_action']??'')!==$action) return Adapter::error('acknowledgement','confirm_action must exactly match the preview action.');
            if (($component==='database'||in_array($action,['restore-originals','use-optimized'],true)) && trim($args['backup_reference']??'')==='') return Adapter::error('backup','Supply a reference to a verified backup before applying.');
            if (in_array($action,['purge-all','purge-object','purge-opcache'],true)&&!($args['acknowledge_shared_cache']??false)) return Adapter::error('scope','Shared cache impact requires acknowledge_shared_cache=true.');
            $receipt=['action'=>$action,'fingerprint'=>$fingerprint,'state'=>'started','started_at'=>time()];
            if (!add_option($key,$receipt,'',false)) return Adapter::error('busy','Could not reserve this request_id; reconcile its receipt.');
            try {
                // Suppress native notices and provider payloads from the authenticated tool output.
                ob_start();
                try {
                    if ($component==='database') $native=NativeProcess::run('database',['kind'=>$args['kind'],'revision'=>$args['expected_revision']]);
                    elseif (in_array($action,['restore-originals','use-optimized'],true)) $native=NativeProcess::run($action,['revision'=>$args['expected_revision']]);
                    elseif ($op==='set-crawler-active') {
                        $crawler=\LiteSpeed\Crawler::cls();
                        if ((bool)$crawler->is_active($args['crawler_id'])!==$args['active']) $crawler->toggle_activeness($args['crawler_id']);
                        $native=(bool)$crawler->is_active($args['crawler_id'])===$args['active'];
                    } else {
                        [, $short,$method,$static,$params]=self::catalog()[$action];$class='LiteSpeed\\'.$short;
                        $native=$static?$class::$method(...$params):$class::cls()->$method(...$params);
                    }
                } finally {ob_end_clean();}
                if ($native===false||is_wp_error($native)) throw new \RuntimeException('Native operation unconfirmed');
                $receipt['state']='native_returned';$receipt['finished_at']=time();
            } catch (\Throwable $e) {
                $receipt['state']='needs_reconciliation';$receipt['finished_at']=time();
            }
            if (!update_option($key,$receipt,false)) return Adapter::error('receipt','Receipt update was not confirmed; native effects may already have occurred. Re-read the receipt and state.');
            return ['requested'=>true,'receipt'=>$receipt,'verification'=>'A native return is not proof that background jobs, CDN changes or cache eviction completed. Re-read component state and verify externally before any new request.'];
        } finally {$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }
}
