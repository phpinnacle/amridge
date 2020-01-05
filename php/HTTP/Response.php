<?php

declare(strict_types=1);

namespace PHPinnacle\Goridge\HTTP;

class Response
{
    /**
     * @var int
     */
    public $stream;

    /**
     * @var int
     */
    public $status;

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
     * @param int    $status
     * @param string $body
     * @param array  $headers
     */
    public function __construct(int $stream, int $status = 200, string $body = '', array $headers = [])
    {
        $this->stream  = $stream;
        $this->status  = $status;
        $this->body    = $body;
        $this->headers = $headers;
    }
}
