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

namespace PHPinnacle\Goridge;

class Frame
{
    const
        FLAG_RAW  = 1
    ;

    const
        OPCODE_ERROR    = 1,
        OPCODE_CONTROL  = 2,
        OPCODE_REQEUST  = 3,
        OPCODE_RESPONSE = 4
    ;

    /**
     * @var int
     */
    public $flags;

    /**
     * @var int
     */
    public $stream = 0;

    /**
     * @var int
     */
    public $opcode;

    /**
     * @var string
     */
    public $body;

    /**
     * @param int    $flags
     * @param int    $opcode
     * @param int    $stream
     * @param string $body
     */
    public function __construct(int $flags, int $opcode, int $stream, string $body)
    {
        $this->flags  = $flags;
        $this->opcode = $opcode;
        $this->stream = $stream;
        $this->body   = $body;
    }

    public static function error(int $flags, int $stream, string $body): self
    {
        return new self($flags, self::OPCODE_ERROR, $stream, $body);
    }

    public static function control(int $flags, int $stream, string $body): self
    {
        return new self($flags, self::OPCODE_CONTROL, $stream, $body);
    }

    public static function request(int $flags, int $stream, string $body): self
    {
        return new self($flags, self::OPCODE_REQEUST, $stream, $body);
    }

    public static function response(int $flags, int $stream, string $body): self
    {
        return new self($flags, self::OPCODE_RESPONSE, $stream, $body);
    }

    public function isRaw(): bool
    {
        return (bool) ($this->flags & self::FLAG_RAW);
    }

    public function isControl(): bool
    {
        return $this->opcode === self::OPCODE_CONTROL;
    }

    public function isRequest(): bool
    {
        return $this->opcode === self::OPCODE_REQEUST;
    }

    public function isResponse(): bool
    {
        return $this->opcode === self::OPCODE_RESPONSE;
    }

    public function isError(): bool
    {
        return $this->opcode === self::OPCODE_ERROR;
    }

    /**
     * @return mixed
     *
     * @throws Exception\JSONException
     */
    public function payload()
    {
        if ($this->flags & self::FLAG_RAW) {
            return $this->body;
        }

        return goridge_decode($this->body);
    }

    /**
     * @param Buffer $buffer
     *
     * @return string
     */
    public function pack(Buffer $buffer): string
    {
        return $buffer
            ->appendUint8($this->flags)
            ->appendUint8($this->opcode)
            ->appendUint16($this->stream)
            ->appendUint32(\strlen($this->body))
            ->appendUint8(1) // TODO: check bit!
            ->append($this->body)
            ->flush()
        ;
    }
}
