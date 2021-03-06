<?php

/*
 *
 * (c) Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Yarhon\RouteGuardBundle\Security\TestResolver;

use Symfony\Component\HttpFoundation\RequestStack;
use Yarhon\RouteGuardBundle\Security\Test\TestInterface;
use Yarhon\RouteGuardBundle\Security\Test\SymfonyAccessControlTest;
use Yarhon\RouteGuardBundle\Routing\RouteContextInterface;

/**
 * @author Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 */
class SymfonyAccessControlResolver implements SymfonySecurityResolverInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(TestInterface $test)
    {
        return $test instanceof SymfonyAccessControlTest;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(TestInterface $test, RouteContextInterface $routeContext)
    {
        /* @var SymfonyAccessControlTest $test */

        return [$test->getAttributes(), $this->requestStack->getCurrentRequest()];
    }
}
