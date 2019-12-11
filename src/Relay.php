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

use Amp\ByteStream\InputStream;
use Amp\ByteStream\OutputStream;
use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\Deferred;
use Amp\Loop;
use Amp\Promise;
use Amp\Socket\ClientConnectContext;
use function Amp\asyncCall, Amp\call, Amp\Socket\connect;

final class Relay
{
    const WRITE_ROUNDS = 64;

    /**
     * @var InputStream
     */
    private $input;

    /**
     * @var OutputStream
     */
    private $output;

    /**
     * @var Sequence
     */
    private $sequence;

    /**
     * @var Buffer
     */
    private $buffer;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var \SplQueue
     */
    private $queue;

    /**
     * @var bool
     */
    private $processing = false;

    /**
     * @var callable[]
     */
    private $callbacks = [];

    /**
     * @var int
     */
    private $lastWrite = 0;

    /**
     * @param InputStream  $input
     * @param OutputStream $output
     */
    public function __construct(InputStream $input, OutputStream $output)
    {
        $this->input    = $input;
        $this->output   = $output;
        $this->sequence = new Sequence();
        $this->buffer   = new Buffer();
        $this->parser   = new Parser();
        $this->queue    = new \SplQueue();
    }

    /**
     * @param string $uri
     * @param int    $timeout
     * @param int    $attempts
     * @param bool   $noDelay
     *
     * @return Promise<self>
     */
    public static function connect(string $uri, int $timeout = 0, int $attempts = 0, bool $noDelay = false): Promise
    {
        return call(function () use ($uri, $timeout, $attempts, $noDelay) {
            $clientContext = new ClientConnectContext;

            if ($attempts > 0) {
                $clientContext = $clientContext->withMaxAttempts($attempts);
            }

            if ($timeout > 0) {
                $clientContext = $clientContext->withConnectTimeout($timeout);
            }

            if ($noDelay) {
                $clientContext = $clientContext->withTcpNoDelay();
            }

            $socket = yield connect($uri, $clientContext);

            $self = new self($socket, $socket);
            $self->listen();

            return $this;
        });
    }

    /**
     * @return Promise<self>
     */
    public static function pipes(): Promise
    {
        return call(function () {
            $input  = new ResourceInputStream(\STDIN);
            $output = new ResourceOutputStream(\STDOUT);

            $self = new self($input, $output);
            $self->listen();

            return $self;
        });
    }

    /**
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @param Frame $frame
     *
     * @return Promise
     */
    public function send(Frame $frame): Promise
    {
        $frame->stream = $this->sequence->reserve();

        $deferred = new Deferred;

        $this->subscribe($frame->stream, function (Frame $frame) use ($deferred) {
            $this->sequence->release($frame->stream);

            $deferred->resolve($frame);
        });

        $this->queue->enqueue($frame->pack($this->buffer));

        if ($this->processing === false) {
            $this->processing = true;

            Loop::defer(function () {
                $this->write();
            });
        }

        return $deferred->promise();
    }

    /**
     * @param int      $stream
     * @param callable $handler
     *
     * @return void
     */
    public function subscribe(int $stream, callable $handler): void
    {
        $this->callbacks[$stream] = $handler;
    }

    /**
     * @return void
     */
    public function close(): void
    {
        if ($this->input !== null) {
            $this->input->close();
        }

        if ($this->output !== null) {
            $this->output->close();
        }

        $this->callbacks = [];
    }

    /**
     * @return void
     */
    private function write(): void
    {
        asyncCall(function () {
            $done = 0;
            $data = '';

            while (!$this->queue->isEmpty()) {
                $data .= $this->queue->dequeue();

                ++$done;

                if ($done % self::WRITE_ROUNDS === 0) {
                    Loop::defer(function () {
                        $this->write();
                    });

                    break;
                }
            }

            yield $this->output->write($data);

            $this->lastWrite  = Loop::now();
            $this->processing = false;
        });
    }

    /**
     * @return void
     */
    private function listen(): void
    {
        asyncCall(function () {
            while (null !== $chunk = yield $this->input->read()) {
                $this->parser->append($chunk);

                while ($frame = $this->parser->parse()) {
                    if (!isset($this->callbacks[$frame->stream])) {
                        continue 2;
                    }

                    asyncCall($this->callbacks[$frame->stream], $frame);

                    unset($this->callbacks[$frame->stream]);
                }
            }
        });
    }
}
