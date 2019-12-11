<?php
/**
 * This file is part of PHPinnacle/Goridge.
 *
 * (c) PHPinnacle Team <dev@phpinnacle.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace PHPinnacle\Goridge;

use Amp\Failure;
use Amp\Deferred;
use Amp\Promise;
use function Amp\call;

final class RPC
{
    /**
     * @var Relay
     */
    private $connection;

    /**
     * @param Relay $connection
     */
    public function __construct(Relay $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param string $uri
     * @param int    $timeout
     * @param int    $attempts
     * @param bool   $noDelay
     *
     * @return Promise<RPC>
     */
    public static function connect(string $uri, int $timeout = 0, int $attempts = 0, bool $noDelay = false): Promise
    {
        return call(function () use ($uri, $timeout, $attempts, $noDelay) {
            $connection = yield Relay::connect($uri, $timeout, $attempts, $noDelay);

            return new self($connection);
        });
    }

    /**
     * @param string $method
     * @param mixed  $payload
     * @param bool   $raw
     *
     * @return mixed
     */
    public function call(string $method, $payload, bool $raw = false): Promise
    {
        return call(function () use ($method, $payload, $raw) {
            $flags = $raw ? Frame::PAYLOAD_RAW : 0;
            $body  = $raw ? $payload : json_encode($payload);

            if (!$raw && json_last_error() !== JSON_ERROR_NONE) {
                return new Failure(new Exception\JSONException(json_last_error_msg()));
            }

            $frame = new Frame($flags, Frame::OPCODE_REQEUST, $method . $body);

            $deferred = new Deferred;

            $this->connection->subscribe($frame->stream, static function (Frame $frame) use ($deferred) {
                if ($frame->opcode === Frame::OPCODE_ERROR) {
                    $deferred->fail(new Exception\RemoteException($frame->body));

                    return;
                }

                if ($frame->opcode !== Frame::OPCODE_RESPONSE) {
                    $deferred->fail(new Exception\RPCException());

                    return;
                }

                $response = $frame->body;

                if (!$frame->flags & Frame::PAYLOAD_RAW) {
                    $response = json_decode($frame->body, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $deferred->fail(new Exception\JSONException(json_last_error_msg()));

                        return;
                    }
                }

                $deferred->resolve($response);
            });

            yield $this->connection->send($frame);

            return $deferred->promise();
        });
    }

    /**
     * @return void
     */
    public function disconnect(): void
    {
        $this->connection->close();
    }
}
