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
        PAYLOAD_RAW  = 1
    ;

    const
        OPCODE_ERROR    = 1,
        OPCODE_REQEUST  = 2,
        OPCODE_RESPONSE = 4,
        OPCODE_CONTROL  = 8
    ;

    /**
     * @var int
     */
    public $flags;

    /**
     * @var int
     */
    public $opcode;

    /**
     * @var string
     */
    public $body;

    /**
     * @var int
     */
    public $stream = 1;

    /**
     * @param int    $flags
     * @param int    $opcode
     * @param string $body
     */
    public function __construct(int $flags, int $opcode, string $body)
    {
        $this->flags  = $flags;
        $this->opcode = $opcode;
        $this->body   = $body;
    }

    /**
     * @param Buffer $buffer
     *
     * @return string
     */
    public function pack(Buffer $buffer) :string
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
