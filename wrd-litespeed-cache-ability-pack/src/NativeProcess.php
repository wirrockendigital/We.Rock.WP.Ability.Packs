<?php
/** Narrow isolated WP-CLI transport for native routines that require a CLI context. */
declare(strict_types=1);
namespace WeRock\LiteSpeedAbilities;
defined('ABSPATH') || exit;
final class NativeProcess {
    /** Discover only established absolute executable locations; callers cannot select programs. */
    public static function binary(): ?string {
        if (!function_exists('proc_open') || !function_exists('proc_get_status')) return null;
        foreach (['/usr/local/bin/wp','/usr/bin/wp','/opt/homebrew/bin/wp'] as $path) {
            if (is_file($path) && is_executable($path)) return $path;
        }
        return null;
    }
    /** Run only reviewed dispatch keys, with request values on stdin and bounded output/time. */
    public static function run(string $action, array $input): array|\WP_Error {
        if (!in_array($action,['database','restore-originals','use-optimized'],true)) return Adapter::error('input','Unknown native process action.');
        $binary=self::binary();
        if (!$binary) return Adapter::error('unavailable','Native WP-CLI execution is unavailable.');
        // This fixed program verifies the site and current user again in the child process.
        $program= <<<'CODE'
$p=json_decode(stream_get_contents(STDIN),true);
if(!is_array($p)||home_url('/')!==$p['site']||!current_user_can('manage_options')||is_multisite()){exit(10);}
if($p['action']==='database'){
 $state=\WeRock\LiteSpeedAbilities\Operations::database_state();
 if(!hash_equals($state['revision'],$p['revision'])){exit(11);}
 if(!in_array($p['kind'],\WeRock\LiteSpeedAbilities\Operations::DB_TYPES,true)){exit(12);}
 $r=\LiteSpeed\DB_Optm::cls()->handler_clean_db_cli($p['kind']);
 if($r===false){exit(13);}
}else{
 if(!in_array($p['action'],['restore-originals','use-optimized'],true)){exit(14);}
 $state=\WeRock\LiteSpeedAbilities\Operations::component_state('image');
 if(!hash_equals($state['revision'],$p['revision'])){exit(15);}
 \LiteSpeed\Img_Optm::cls()->batch_switch($p['action']==='restore-originals'?'batch_switch_ori':'batch_switch_optm');
 if((int)\LiteSpeed\Img_Optm::get_summary('switch_failed')>0){exit(16);}
}
echo "\nWRD_NATIVE_FINISHED\n";
CODE;
        $argv=[$binary,'--path='.ABSPATH,'--url='.home_url('/'),'--user='.(string)get_current_user_id(),'--no-color','eval',$program];
        $pipes=[];$process=proc_open($argv,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,ABSPATH);
        if (!is_resource($process)) return Adapter::error('process','Native process could not start.');
        $finished=false;$code=-1;$output='';$started=microtime(true);
        try {
            fwrite($pipes[0],wp_json_encode($input+['site'=>home_url('/'),'action'=>$action]));fclose($pipes[0]);
            stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
            while (true) {
                // Discard provider diagnostics; retain only a small tail for the completion marker.
                $output=substr($output.stream_get_contents($pipes[1]),-4096);stream_get_contents($pipes[2]);
                $status=proc_get_status($process);
                if (!$status['running']) {$code=$status['exitcode'];$finished=true;break;}
                if (microtime(true)-$started>90) {proc_terminate($process,9);break;}
                usleep(50000);
            }
            $output=substr($output.stream_get_contents($pipes[1]),-4096);
        } finally {
            foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
            proc_close($process);
        }
        if (!$finished || $code!==0 || !str_contains($output,'WRD_NATIVE_FINISHED')) return Adapter::error('native','Native process did not confirm completion. Inspect state and the receipt before retrying; changes may be partial.');
        return ['native_returned'=>true];
    }
}
