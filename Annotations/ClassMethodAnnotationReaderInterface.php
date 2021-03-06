<?php

/*
 *
 * (c) Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Yarhon\RouteGuardBundle\Annotations;

/**
 * @author Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 */
interface ClassMethodAnnotationReaderInterface
{
    /**
     * @param string $class
     * @param string $method
     * @param array  $annotationClasses
     *
     * @return array Parsed annotations
     *
     * @throws \ReflectionException
     */
    public function read($class, $method, array $annotationClasses);
}
