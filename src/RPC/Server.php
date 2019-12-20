<?php
/**
 * This file is part of PHPinnacle/Goridge.
 *
 * (c) PHPinnacle Team <dev@phpinnacle.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PHPinnacle\Goridge\RPC;

use Amp\Promise;
use Amp\Socket\ResourceSocket;
use Amp\Socket\Server as Listener;
use PHPinnacle\Ensign\Dispatcher;
use PHPinnacle\Ensign\HandlerRegistry;
use PHPinnacle\Goridge\Buffer;
use PHPinnacle\Goridge\Exception;
use PHPinnacle\Goridge\Frame;
use PHPinnacle\Goridge\Relay;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use function Amp\asyncCall;
use function Amp\call;

class Server
{
    /**
     * @var string
     */
    private $listener;

    /**
     * @var Buffer
     */
    private $buffer;

    /**
     * @var HandlerRegistry
     */
    private $registry;

    /**
     * @var Dispatcher
     */
    private $dispatcher;

    /**
     * @param Listener        $listener
     * @param HandlerRegistry $registry
     */
    public function __construct(Listener $listener, HandlerRegistry $registry = null)
    {
        $this->listener   = $listener;
        $this->buffer     = new Buffer();
        $this->registry   = $registry ?: new HandlerRegistry();
        $this->dispatcher = new Dispatcher($this->registry);
    }

    /**
     * @param string $uri
     *
     * @return self
     * @throws Exception\RPCException
     */
    public static function listen(string $uri): self
    {
        try {
            $listener = Listener::listen($uri);
        } catch (\Throwable $error) {
            throw new Exception\RPCException($error->getMessage());
        }

        return new self($listener);
    }

    /**
     * @param string   $method
     * @param callable $handler
     *
     * @return self
     */
    public function register(string $method, callable $handler): self
    {
        $this->registry->add($method, $handler);

        return $this;
    }

    /**
     * @param LoggerInterface $logger
     *
     * @return Promise
     */
    public function serve(LoggerInterface $logger = null): Promise
    {
        $logger = $logger ?: new NullLogger();

        return call(function () use ($logger) {
            /** @var ResourceSocket $socket */
            while ($socket = yield $this->listener->accept()) {
                $relay = new Relay($socket, $socket);
                $relay->listen();

                $logger->info(sprintf('New connection: %s', $socket->getRemoteAddress()));

                asyncCall(function () use ($relay) {
                    /** @var Frame $request */
                    while ($request = yield $relay->receive()) {
                        if (!$request->isRequest()) {
                            continue;
                        }

                        $this->buffer->append($request->body);

                        $method  = $this->buffer->consume($this->buffer->consumeUint16());
                        $payload = $this->buffer->consume($this->buffer->consumeUint32());

                        $params = (array) \json_decode($payload, true);

                        if (\json_last_error() !== JSON_ERROR_NONE) {
                            continue; // TODO!!!
                        }

                        try {
                            $result = yield $this->dispatcher->dispatch($method, ...$params);
                            $response = Frame::response(0, $request->stream, \json_encode($result));
                        } catch (\Throwable $error) {
                            $response = Frame::error(0, $request->stream, $error->getMessage());
                        }

                        yield $relay->send($response);
                    }

                    $relay->close();
                });
            }

            $this->listener->close();
        });
    }
}
