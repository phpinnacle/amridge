<?php

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
        asyncCall(function (Request $request) use ($client) {
            $response = Response::ok($request->stream, 'Hello');

            yield $client->response($response);
        }, $request);
    }
});
