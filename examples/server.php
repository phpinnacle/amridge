<?php

use Amp\Loop;
use PHPinnacle\Goridge\RPC\Server;

require __DIR__ . '/../vendor/autoload.php';

class App
{
    public static function Hi(string $name): string
    {
        return sprintf('Hello %s!', $name);
    }
}

Loop::run(function () {
    $rpc = Server::listen('tcp://0.0.0.0:6666');
    $rpc->register('App.Hi', ['App', 'Hi']);

    yield $rpc->serve();
});
