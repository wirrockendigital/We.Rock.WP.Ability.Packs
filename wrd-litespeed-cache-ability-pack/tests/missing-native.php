<?php
/** Prove read availability does not depend on native save or purge methods. */
namespace LiteSpeed {
    class Conf {
        /** Minimal read-only native surface for the missing-write regression. */
        public static function cls(): self {return new self();}
        public function conf($key): mixed {return null;}
    }
}
namespace {
    define('ABSPATH',__DIR__);define('LSCWP_V','99.0.0');
    require __DIR__.'/../src/NativeProcess.php';
    require __DIR__.'/../src/Operations.php';
    require __DIR__.'/../src/Adapter.php';
    use WeRock\LiteSpeedAbilities\Adapter;
    if (!Adapter::available('get-settings') || Adapter::available('update-settings') || Adapter::available('purge-cache')) throw new \RuntimeException('Operation-specific availability regressed');
    echo "PASS: missing native writes preserve read availability\n";
}
