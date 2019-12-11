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

final class Sequence
{
    private const MAX = 65535;

    /**
     * @var int
     */
    private $max;

    /**
     * @var int
     */
    private $next = 0;

    /**
     * @var \SplStack
     */
    private $stack;

    /**
     * @param int $max
     */
    public function __construct(int $max = self::MAX)
    {
        $this->max   = $max;
        $this->stack = new \SplStack();
    }

    /**
     * @return int
     */
    public function reserve(): int
    {
        if (!$this->stack->isEmpty()) {
            return $this->stack->pop();
        }

        $next = ++$this->next;

        return $next === self::MAX ? $this->next = 0 : $next;
    }

    /**
     * @param int $id
     */
    public function release(int $id): void
    {
        $this->stack->push($id);
    }
}
