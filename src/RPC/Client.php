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

namespace PHPinnacle\Goridge\RPC;

use Amp\Failure;
use Amp\Deferred;
use Amp\Promise;
use PHPinnacle\Goridge\Buffer;
use PHPinnacle\Goridge\Exception;
use PHPinnacle\Goridge\Frame;
use PHPinnacle\Goridge\Relay;
use PHPinnacle\Goridge\Sequence;
use function Amp\call;

final class Client
{
    /**
     * @var Relay
     */
    private $connection;

    /**
     * @var Sequence
     */
    private $sequence;

    /**
     * @param Relay    $connection
     * @param Sequence $sequence
     */
    public function __construct(Relay $connection, Sequence $sequence = null)
    {
        $this->connection = $connection;
        $this->sequence   = $sequence ?: new Sequence();
    }

    /**
     * @param string $uri
     * @param int    $timeout
     * @param int    $attempts
     * @param bool   $noDelay
     *
     * @return Promise<Client>
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
     *
     * @return mixed
     */
    public function call(string $method, $payload): Promise
    {
        return call(function () use ($method, $payload) {
            $payload = json_encode($payload);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return new Failure(new Exception\JSONException(json_last_error_msg()));
            }

            $body = (new Buffer())
                ->appendUint16(\strlen($method))
                ->append($method)
                ->appendUint32(\strlen($payload))
                ->append($payload)
            ;

            $frame = Frame::request(Frame::FLAG_RAW, $this->sequence->reserve(), $body->flush());

            yield $this->connection->send($frame);

            return $this->await($frame->stream);
        });
    }

    /**
     * @return void
     */
    public function disconnect(): void
    {
        $this->connection->close();
    }

    /**
     * @param int $stream
     *
     * @return Promise
     */
    private function await(int $stream): Promise
    {
        $deferred = new Deferred;

        $this->connection->subscribe($stream, function (Frame $frame) use ($deferred) {
            if ($frame->isError()) {
                $deferred->fail(new Exception\RemoteException($frame->body));

                $this->sequence->release($frame->stream);

                return;
            }

            if (!$frame->isResponse()) {
                $deferred->fail(new Exception\RPCException());

                $this->sequence->release($frame->stream);

                return;
            }

            try {
                $deferred->resolve($frame->payload());
            } catch (Exception\GoridgeException $error) {
                $deferred->fail($error);
            }

            $this->sequence->release($frame->stream);
            $this->connection->cancel($frame->stream);
        });

        return $deferred->promise();
    }
}
