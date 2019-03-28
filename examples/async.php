<?php

use Amp\Loop;
use PHPinnacle\Amridge\RPC;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(function () {
    /** @var RPC $rpc */
    $rpc = yield RPC::connect('tcp://127.0.0.1:6001');
    
    $time = microtime(true);

    $promises = [];

    for ($i = 0; $i < 100; ++$i) {
        $promises[] = $rpc->call("App.Hi", "RPC {$i}");
    }

    $r = yield $promises;

    foreach ($r as $v) {
        echo $v . \PHP_EOL;
    }

    echo microtime(true) - $time . \PHP_EOL;

    $rpc->disconnect();
});
