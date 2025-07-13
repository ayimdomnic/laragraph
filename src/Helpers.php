<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph;

use Closure;

/**
 * This file is part of the Laragraph package.
 *
 * (c) Odhiambo Dormnic <ayimdomnic@gmail.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Helpers
{
    /**
     * Apply a callback to a value or each value in an array.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function applyEach(Closure $callback, $valueOrValues)
    {
        if (is_array($valueOrValues)) {
            return array_map($callback, $valueOrValues);
        }

        if ($valueOrValues instanceof \Traversable) {
            return iterator_to_array(
                new \CallbackFilterIterator(
                    new \ArrayIterator($valueOrValues),
                    $callback
                )
            );
        }

        return $callback($valueOrValues);
    }

}