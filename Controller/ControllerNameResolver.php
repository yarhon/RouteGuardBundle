<?php

/*
 *
 * (c) Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Yarhon\LinkGuardBundle\Controller;

use Yarhon\LinkGuardBundle\Exception\InvalidArgumentException;

/**
 * @author Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 */
class ControllerNameResolver implements ControllerNameResolverInterface
{
    /**
     * @see \Symfony\Component\HttpKernel\Controller\ControllerResolver::getController For possible $controller forms
     *
     * {@inheritdoc}
     */
    public function resolve($controller)
    {
        if (is_array($controller) && isset($controller[0]) && isset($controller[1])) {
            if (is_string($controller[0])) {
                return $this->resolveClass($controller[0]).'::'.$controller[1];
            } elseif (is_object(($controller[0]))) {
                return get_class($controller[0]).'::'.$controller[1];
            }
        }

        if (is_object($controller)) {
            return get_class($controller).'::__invoke';
        }

        if (is_string($controller)) {
            if (function_exists($controller)) {
                // TODO: how to deal with this case?
                return false;
            }

            if (false === strpos($controller, '::')) {
                return $this->resolveClass($controller).'::__invoke';
            }

            list($class, $method) = explode('::', $controller);

            return $this->resolveClass($class).'::'.$method;
        }

        throw new InvalidArgumentException('Unable to resolve controller name, the controller is not callable.');
    }

    protected function resolveClass($class)
    {
        return $class;
    }
}
