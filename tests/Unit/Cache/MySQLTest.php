<?php

// SPDX-FileCopyrightText: 2004-2023 Ryan Parman, Sam Sneddon, Ryan McCue
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace SimplePie\Tests\Unit\Cache;

use PHPUnit\Framework\TestCase;

class MySQLTest extends TestCase
{
    public function testNamespacedClassExists()
    {
        $this->assertTrue(class_exists('SimplePie\Cache\MySQL'));
    }

    public function testClassExists()
    {
        $this->assertTrue(class_exists('SimplePie_Cache_MySQL'));
    }
}
