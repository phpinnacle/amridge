<?php

declare(strict_types=1);

namespace PHPinnacle\Goridge\HTTP;

class Request
{
    /**
     * @var int
     */
    public $stream;

    /**
     * @var string
     */
    public $uri;

    /**
     * @var string
     */
    public $method;

    /**
     * @var string
     */
    public $body;

    /**
     * @var array
     */
    public $headers = [];

    /**
     * @param int    $stream
     * @param string $method
     * @param string $uri
     * @param string $body
     * @param array  $headers
     */
    public function __construct(int $stream, string $method, string $uri, string $body, array $headers = [])
    {
        $this->stream  = $stream;
        $this->method  = $method;
        $this->uri     = $uri;
        $this->body    = $body;
        $this->headers = $headers;
    }
}
