<?php

declare(strict_types=1);

namespace PHPinnacle\Goridge\HTTP;

use Amp\Promise;
use PHPinnacle\Goridge\Buffer;
use PHPinnacle\Goridge\Frame;
use function Amp\call;

class Client
{
    /**
     * @var Worker
     */
    private $worker;

    /**
     * @var Buffer
     */
    private $buffer;

    /**
     * @param Worker $worker
     */
    private function __construct(Worker $worker)
    {
        $this->worker = $worker;
        $this->buffer = new Buffer();
    }

    /**
     * @return Promise
     */
    public static function connect(): Promise
    {
        return call(function () {
            $worker = yield Worker::start();

            return new self($worker);
        });
    }

    /**
     * @return Promise<Request>
     */
    public function request(): Promise
    {
        return call(function () {
            /** @var Frame $request */
            $request = yield $this->worker->receive();

            $this->buffer->append($request->body);

            $method = $this->buffer->consume($this->buffer->consumeUint8());
            $uri    = $this->buffer->consume($this->buffer->consumeUint16());

            $headers = [];
            $count   = $this->buffer->consumeUint16();

            for ($i = 0; $i < $count; $i++) {
                $field = $this->buffer->consume($this->buffer->consumeUint16());

                $headers[$field] = $this->buffer->consume($this->buffer->consumeUint16());
            }

            $body = $this->buffer->consume($this->buffer->consumeUint32());

            return new Request($request->stream, $method, $uri, $body, $headers);
        });
    }

    /**
     * @param Response $response
     *
     * @return Promise
     */
    public function response(Response $response): Promise
    {
        $frame = (new Buffer)
            ->appendUint16($response->status)
            ->appendUint32(\strlen($response->body))
            ->append($response->body)
            ->appendUint16(\count($response->headers))
        ;

        foreach ($response->headers as $field => $value) {
            $frame
                ->appendUint16(\strlen($field))
                ->append($field)
                ->appendUint16(\strlen($value))
                ->append($value)
            ;
        }

        return $this->worker->send($response->stream, $frame->flush());
    }
}
