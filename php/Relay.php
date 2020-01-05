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
use Amp\Emitter;
use Amp\Iterator;
use Amp\Promise;
use Amp\Socket\ConnectContext;
use function Amp\asyncCall, Amp\call, Amp\Socket\connect;

final class Relay
{
    /**
     * @var InputStream
     */
    private $input;

    /**
     * @var OutputStream
     */
    private $output;

    /**
     * @var Buffer
     */
    private $buffer;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var Emitter
     */
    private $emitter;

    /**
     * @var Iterator
     */
    private $iterator;

    /**
     * @var callable[]
     */
    private $callbacks = [];

    /**
     * @param InputStream  $input
     * @param OutputStream $output
     */
    public function __construct(InputStream $input, OutputStream $output)
    {
        $this->input    = $input;
        $this->output   = $output;

        $this->buffer   = new Buffer();
        $this->parser   = new Parser();

        $this->emitter  = new Emitter();
        $this->iterator = $this->emitter->iterate();
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
            $clientContext = new ConnectContext;

            if ($timeout > 0) {
                $clientContext = $clientContext->withConnectTimeout($timeout);
            }

            if ($attempts > 0) {
                $clientContext = $clientContext->withMaxAttempts($attempts);
            }

            if ($noDelay) {
                $clientContext = $clientContext->withTcpNoDelay();
            }

            $socket = yield connect($uri, $clientContext);

            $self = new self($socket, $socket);
            $self->listen();

            return $self;
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
     * @return Promise<void>
     */
    public function send(Frame $frame): Promise
    {
        return $this->output->write($frame->pack($this->buffer));
    }

    /**
     * @return Promise<Frame>
     */
    public function receive(): Promise
    {
        return call(function () {
            yield $this->iterator->advance();

            return $this->iterator->getCurrent();
        });
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
     * @param int $stream
     *
     * @return void
     */
    public function cancel(int $stream): void
    {
        unset($this->callbacks[$stream]);
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
    public function listen(): void
    {
        asyncCall(function () {
            while (null !== $chunk = yield $this->input->read()) {
                $this->parser->append($chunk);

                while ($frame = $this->parser->parse()) {
                    if (isset($this->callbacks[$frame->stream])) {
                        asyncCall($this->callbacks[$frame->stream], $frame);
                    }

                    $this->emitter->emit($frame);
                }
            }
        });
    }
}
