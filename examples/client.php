<?php

use Spiral\Goridge\RPC;
use Spiral\Goridge\SocketRelay;

require __DIR__ . '/../vendor/autoload.php';

$rpc = new RPC(new SocketRelay("/tmp/server.sock", null, SocketRelay::SOCK_UNIX));

echo $rpc->call("App.Hi", "Connect") . \PHP_EOL;

$time = microtime(true);

for ($i = 0; $i < 10; ++$i) {
   echo $rpc->call("App.Hi", "RPC {$i}") . \PHP_EOL;
}

echo microtime(true) - $time . \PHP_EOL;
