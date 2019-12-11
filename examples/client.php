<?php

use Amp\Loop;
use PHPinnacle\Goridge\RPC;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(function () {
    /** @var RPC $rpc */
    $rpc = yield RPC::connect('tcp://127.0.0.1:6001');

    echo yield $rpc->call("App.Hi", "World");

    $rpc->disconnect();
});
