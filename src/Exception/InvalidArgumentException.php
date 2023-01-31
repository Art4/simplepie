<?php

declare(strict_types=1);
/**
 * SimplePie
 *
 * A PHP-Based RSS and Atom Feed Framework.
 * Takes the hard work out of managing a complete RSS/Atom solution.
 *
 * Copyright (c) 2004-2022, Ryan Parman, Sam Sneddon, Ryan McCue, and contributors
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are
 * permitted provided that the following conditions are met:
 *
 *     * Redistributions of source code must retain the above copyright notice, this list of
 *       conditions and the following disclaimer.
 *
 *     * Redistributions in binary form must reproduce the above copyright notice, this list
 *       of conditions and the following disclaimer in the documentation and/or other materials
 *       provided with the distribution.
 *
 *     * Neither the name of the SimplePie Team nor the names of its contributors may be used
 *       to endorse or promote products derived from this software without specific prior
 *       written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS
 * OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS
 * AND CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
 * OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @copyright 2004-2022 Ryan Parman, Sam Sneddon, Ryan McCue
 * @author Ryan Parman
 * @author Sam Sneddon
 * @author Ryan McCue
 * @link http://simplepie.org/ SimplePie
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

namespace SimplePie\Exception;

use SimplePie\Exception;

/**
 * General SimplePie exception class
 *
 * @internal
 */
final class InvalidArgumentException extends Exception
{
    /**
     * Create a new invalid argument exception with a standardized text
     *
     * @param string $method The called method or function, that received the wrong argument
     * @param int $position The argument position in the function signature
     * @param string $name The argument name in the function signature
     * @param string $expected The expected argument type, e.g. 'string|bool' or 'callable'
     *
     * @return self
     */
    public static function create(string $method, int $position, string $name, string $expected): self
    {
        return new self(sprintf(
            '%1$s(): Argument #%2$d (%3$s) must be of type "%4$s"',
            $method,
            $position,
            $name,
            $expected
        ), 1);
    }

    /**
     * Create a deprecated autocast message with a standardized text
     *
     * @param string $method The called method or function, the received the wrong argument
     * @param int $position The argument position in the function signature
     * @param string $name The argument name in the function signature
     * @param string $expected The expected argument type, e.g. 'string|bool' or 'callable'
     * @param string $version The SimplePie version since the argument type is deprecated
     *
     * @return string the deprecation message for use in `trigger_error()`
     */
    public static function getDeprecatedAutocastMessage(string $method, int $position, string $name, string $expected, string $version): string
    {
        return sprintf(
            '%1$s(): Providing argument #%2$d (%3$s) not as type "%4$4" is deprecated since SimplePie %5$s and will not be autocasted as of SimplePie 2.0.0, please provide as type "%4$s" instead.',
            $method,
            $position,
            $name,
            $expected,
            $version
        );
    }
}
