<?php
/**
 * This file is part of PHPinnacle/Amridge.
 *
 * (c) PHPinnacle Team <dev@phpinnacle.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace PHPinnacle\Amridge;

use function Amp\asyncCall, Amp\call, Amp\Socket\connect;
use Amp\Loop;
use Amp\Promise;
use Amp\Socket\ClientConnectContext;
use Amp\Socket\Socket;

final class Connection
{
    /**
     * @var string
     */
    private $uri;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var Buffer
     */
    private $buffer;

    /**
     * @var Socket
     */
    private $socket;

    /**
     * @var callable[]
     */
    private $callbacks = [];

    /**
     * @var int
     */
    private $lastWrite = 0;

    /**
     * @param string $uri
     */
    public function __construct(string $uri)
    {
        $this->uri    = $uri;
        $this->parser = new Parser;
        $this->buffer = new Buffer;
    }

    /**
     * Clean up
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @param Frame $frame
     *
     * @return Promise<int>
     */
    public function send(Frame $frame): Promise
    {
        $this->lastWrite = Loop::now();

        /** @noinspection PhpUnhandledExceptionInspection */
        return $this->socket->write($frame->pack($this->buffer));
    }

    /**
     * @param int      $seq
     * @param callable $callback
     */
    public function subscribe(int $seq, callable $callback): void
    {
        $this->callbacks[$seq] = $callback;
    }

    /**
     * @param int $seq
     *
     * @return void
     */
    public function cancel(int $seq): void
    {
        unset($this->callbacks[$seq]);
    }

    /**
     * @param int  $timeout
     * @param int  $maxAttempts
     * @param bool $noDelay
     *
     * @return Promise
     */
    public function open(int $timeout, int $maxAttempts, bool $noDelay): Promise
    {
        return call(function () use ($timeout, $maxAttempts, $noDelay) {
            $context = new ClientConnectContext;

            if ($maxAttempts > 0) {
                $context = $context->withMaxAttempts($maxAttempts);
            }

            if ($timeout > 0) {
                $context = $context->withConnectTimeout($timeout);
            }

            if ($noDelay) {
                $context = $context->withTcpNoDelay();
            }

            $this->socket = yield connect($this->uri, $context);

            asyncCall(function () {
                while (null !== $chunk = yield $this->socket->read()) {
                    $this->parser->append($chunk);

                    while ($frame = $this->parser->parse()) {
                        if (!isset($this->callbacks[$frame->seq])) {
                            continue 2;
                        }

                        $callback = $this->callbacks[$frame->seq];
                        unset($this->callbacks[$frame->seq]);

                        asyncCall($callback, $frame);
                    }
                }

                $this->close();
            });
        });
    }

    /**
     * @return void
     */
    public function close(): void
    {
        $this->callbacks = [];

        $this->socket->close();
    }
}
