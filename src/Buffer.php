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

use PHPinnacle\Buffer\ByteBuffer;

final class Buffer extends ByteBuffer
{
    public function appendUint64LE(int $value): self
    {
        return $this->append(\pack("P", $value));
    }

    public function readUint64LE(int $offset): int
    {
        return (int) \unpack("P", $this->read(8, $offset))[1];
    }

    public function consumeUint64LE(): int
    {
        return (int) \unpack("P", $this->consume(8))[1];
    }
}
