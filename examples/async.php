<?php

use Amp\Loop;
use PHPinnacle\Amridge\RPC;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(function () {
    /** @var RPC $rpc */
    $rpc = yield RPC::connect('unix:///tmp/server.sock');
    
    $time = microtime(true);

    for ($i = 0; $i < 10; ++$i) {
        echo (yield $rpc->call("App.Hi", "RPC {$i}")), PHP_EOL;
    }

    echo microtime(true) - $time . \PHP_EOL;

    $rpc->disconnect();
});
