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

final class Parser
{
    /**
     * @var Buffer
     */
    private $buffer;

    /**
     * @param Buffer|null $buffer
     */
    public function __construct(Buffer $buffer = null)
    {
        $this->buffer = $buffer ?: new Buffer;
    }

    /**
     * @param string $chunk
     *
     * @return void
     */
    public function append(string $chunk): void
    {
        $this->buffer->append($chunk);
    }

    /**
     * @return null|Frame
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    public function parse(): ?Frame
    {
        if ($this->buffer->size() < 9) {
            return null;
        }

        $flags  = $this->buffer->readUint8(0);
        $opcode = $this->buffer->readUint8(1);
        $stream = $this->buffer->readUint16(2);
        $size   = $this->buffer->readUint32(4);
        $check  = $this->buffer->readUint8(8); // TODO: check bit!

        if ($this->buffer->size() < $size + 9) {
            return null;
        }

        $this->buffer->discard(9);

        return new Frame($flags, $opcode, $stream, $this->buffer->consume($size));
    }
}
