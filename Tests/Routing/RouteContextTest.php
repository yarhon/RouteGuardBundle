<?php

/*
 *
 * (c) Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Yarhon\RouteGuardBundle\Tests\Routing;

use PHPUnit\Framework\TestCase;
use Yarhon\RouteGuardBundle\Routing\RouteContext;

/**
 * @author Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 */
class RouteContextTest extends TestCase
{
    public function testConstructDefaultValues()
    {
        $context = new RouteContext('route1');

        $this->assertSame('route1', $context->getName());
        $this->assertSame([], $context->getParameters());
        $this->assertSame('GET', $context->getMethod());
    }

    public function testConstructAllValues()
    {
        $context = new RouteContext('route1', ['q' => 1], 'POST');

        $this->assertSame('route1', $context->getName());
        $this->assertSame(['q' => 1], $context->getParameters());
        $this->assertSame('POST', $context->getMethod());
    }
}
