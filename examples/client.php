<?php
use Spiral\Goridge;

require __DIR__ . '/../vendor/autoload.php';

$rpc = new Goridge\RPC(
  new Goridge\SocketRelay("127.0.0.1", 6001)
);

echo $rpc->call("App.Hi", "Connect") . \PHP_EOL;

$time = microtime(true);

for ($i = 0; $i < 100; ++$i) {
   echo $rpc->call("App.Hi", "RPC {$i}") . \PHP_EOL;
}

echo microtime(true) - $time . \PHP_EOL;
