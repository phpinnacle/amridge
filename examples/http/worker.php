<?php

use Amp\Delayed;
use Amp\Loop;
use PHPinnacle\Goridge\HTTP\Client;
use PHPinnacle\Goridge\HTTP\Request;
use PHPinnacle\Goridge\HTTP\Response;
use function Amp\asyncCall;

require __DIR__ . '/../../vendor/autoload.php';

Loop::run(function () {
    /** @var Client $client */
    $client = yield Client::connect();

    while ($request = yield $client->request()) {
        asyncCall(function (Client $client, Request $request) {
            yield new Delayed(3000);

            yield $client->response(new Response($request->stream, 200, sprintf('Hello from %s!', \getmypid())));
        }, $client, $request);
    }
});
