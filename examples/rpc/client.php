<?php

use Amp\Loop;
use function Amp\call;
use PHPinnacle\Goridge\RPC\Client;

require __DIR__ . '/../../vendor/autoload.php';

Loop::run(function () {
    /** @var Client $rpc */
    $rpc = yield Client::connect('tcp://127.0.0.1:6666');
    $tasks = [];

    $time = microtime(true);

    for ($i = 0; $i < 10000; $i++) {
        $tasks[] = call(function () use ($rpc, $i) {
            echo yield $rpc->call("App.Hi", "Antony {$i}"), \PHP_EOL;
        });
    }

    yield $tasks;

    echo 'Done: ' . (microtime(true) - $time) . \PHP_EOL;

    $rpc->disconnect();
});
