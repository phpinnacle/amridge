<?php

declare(strict_types=1);

namespace PHPinnacle\Goridge\HTTP;

use Amp\Promise;
use PHPinnacle\Goridge\Buffer;
use PHPinnacle\Goridge\Exception;
use PHPinnacle\Goridge\Frame;
use PHPinnacle\Goridge\Relay;
use function Amp\call;

class Worker
{
    /**
     * @var Relay
     */
    private $relay;

    /**
     * @param Relay $relay
     */
    private function __construct(Relay $relay)
    {
        $this->relay = $relay;
    }

    /**
     * @return Promise
     */
    public static function start(): Promise
    {
        return call(function () {
            /** @var Relay $relay */
            $relay = yield Relay::pipes();

            /** @var Frame $control */
            $control = yield $relay->receive();

            if (!$control->isControl()) {
                throw new Exception\ProtocolException("Expected control frame.");
            }

            if (!$payload = \json_decode($control->body, true)) {
                throw new Exception\JSONException("Expected JSON payload.");
            }

            if (!empty($payload['pid'])) {
                yield $relay->send(Frame::control(0, \json_encode(['pid' => \getmypid()])));
            }

            return new self($relay);
        });
    }

    /**
     * @return Promise
     */
    public function receive(): Promise
    {
        return call(function () {
            /** @var Frame $request */
            $request = yield $this->relay->receive();

            if (!$request->isRequest()) {
                throw new Exception\ProtocolException("Expected request frame.");
            }

            return $request;
        });
    }

    /**
     * @param int    $stream
     * @param string $body
     * @param int    $flags
     *
     * @return Promise
     */
    public function send(int $stream, string $body, int $flags = 0): Promise
    {
        return $this->relay->send(Frame::response($stream, $body, $flags));
    }

    /**
     * @param int    $stream
     * @param string $message
     * @param int    $code
     *
     * @return Promise
     */
    public function error(int $stream, string $message, int $code = 0): Promise
    {
        $frame = (new Buffer)
            ->appendUint32($code)
            ->appendUint32(\strlen($message))
            ->append($message)
        ;

        return $this->relay->send(Frame::error($stream, $frame->flush()));
    }

    /**
     * @return Promise
     */
    public function stop(): Promise
    {
        return $this->relay->send(Frame::control(0, \json_encode(['stop' => true])));
    }
}
