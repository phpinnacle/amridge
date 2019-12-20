<?php
/**
 * High-performance PHP process supervisor and load balancer written in Go
 *
 * @author Wolfy-J
 */
declare(strict_types=1);

namespace PHPinnacle\Goridge;

use Amp\Deferred;
use Amp\Promise;
use Amp\Success;

/**
 * Accepts connection from RoadRunner server over given Goridge relay.
 *
 * Example:
 *
 * $worker = new Worker(new Goridge\StreamRelay(STDIN, STDOUT));
 * while ($task = $worker->receive($context)) {
 *      $worker->send("DONE", json_encode($context));
 * }
 */
class Worker
{
    // Send as response context to request worker termination
    const STOP = '{"stop":true}';
    const PID = '{"pid":%s}';

    /**
     * @var Relay
     */
    private $relay;

    /**
     * @var bool
     */
    private $started = false;

    /**
     * @param Relay $relay
     */
    public function __construct(Relay $relay)
    {
        $this->relay = $relay;

        $this->start();
    }

    /**
     * @return Promise<void>
     */
    public function start(): Promise
    {
        if ($this->started) {
            return new Success();
        }

        $deferred = new Deferred();

        $this->relay->subscribe(0, function (Frame $frame) use ($deferred) {
            if (!$frame->isControl()) {
                throw new Exception\FrameException("Expected control frame.");
            }

            $payload = $frame->payload();

            if (!empty($payload['pid'])) {
                $pid = new Frame(0, Frame::OPCODE_CONTROL, \sprintf(self::PID, \getmypid()));

                yield $this->relay->send($pid);
            }

            $this->started = true;

            $deferred->resolve();
        });

        return $deferred->promise();
    }

    /**
     * Receive packet of information to process, returns null when process must be stopped.
     * Might return Error to wrap error message from server.
     *
     * @return array
     */
    public function receive()
    {
        $control = $this->relay->receive();

        if (!$control->isControl()) {
            throw new Exception\RoadRunnerException("Expected control frame.");
        }

        if (!$control->isRaw() && !empty($control->body)) {
            if (!$cmd = \json_decode($control->body, true)) {
                throw new Exception\RoadRunnerException("Expected JSON payload.");
            }

            if (!empty($cmd['stop'])) {
                return null;
            }
        }

        $body = $this->relay->receive();

        return [$control->body, $body->body];
    }

    /**
     * Respond to the server with result of task execution and execution context.
     *
     * Example:
     * $worker->respond((string)$response->getBody(), json_encode($response->getHeaders()));
     *
     * @param string|null $payload
     * @param string|null $header
     */
    public function send(string $payload = '', string $header = '')
    {
        $flag = Frame::PAYLOAD_CONTROL | (empty($header) ? Frame::PAYLOAD_NONE : Frame::FLAG_RAW);

        $this->relay->send(Goridge\pack($header, $flag) . Goridge\pack($payload, Frame::FLAG_RAW));
    }

    /**
     * Respond to the server with an error. Error must be treated as TaskError and might not cause
     * worker destruction.
     *
     * Example:
     *
     * $worker->error("invalid payload");
     *
     * @param string $message
     */
    public function error(string $message)
    {
        $this->relay->send(Goridge\pack($message, Frame::PAYLOAD_CONTROL | Frame::FLAG_RAW | Frame::PAYLOAD_ERROR));
    }

    /**
     * Terminate the process. Server must automatically pass task to the next available process.
     * Worker will receive StopCommand context after calling this method.
     *
     * Attention, you MUST use continue; after invoking this method to let rr to properly
     * stop worker.
     */
    public function stop()
    {
        $this->send('', self::STOP);
    }
}
