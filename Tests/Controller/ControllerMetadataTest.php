<?php

/*
 *
 * (c) Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Yarhon\RouteGuardBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Yarhon\RouteGuardBundle\Controller\ControllerMetadata;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Yarhon\RouteGuardBundle\Exception\InvalidArgumentException;

/**
 * @author Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 */
class ControllerMetadataTest extends TestCase
{
    private $arguments;

    private $controllerMetadata;

    public function setUp()
    {
        $this->arguments = [
            new ArgumentMetadata('arg1', 'int', false, false, null),
            new ArgumentMetadata('arg2', 'string', false, false, null),
        ];

        $this->controllerMetadata = new ControllerMetadata('class::method', $this->arguments);
    }

    public function testGetName()
    {
        $this->assertSame('class::method', $this->controllerMetadata->getName());
    }

    public function testAll()
    {
        $this->assertSame($this->arguments, $this->controllerMetadata->all());
    }

    public function testKeys()
    {
        $this->assertSame(['arg1', 'arg2'], $this->controllerMetadata->keys());
    }

    public function testHas()
    {
        $this->assertTrue($this->controllerMetadata->has('arg1'));
        $this->assertFalse($this->controllerMetadata->has('arg3'));
    }

    public function testGet()
    {
        $this->assertSame($this->arguments[0], $this->controllerMetadata->get('arg1'));
    }

    public function testGetException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid argument name: "arg3"');

        $this->controllerMetadata->get('arg3');
    }
}
