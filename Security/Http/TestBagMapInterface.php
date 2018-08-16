<?php

/*
 *
 * (c) Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Yarhon\RouteGuardBundle\Security\Http;

use Yarhon\RouteGuardBundle\Security\Test\TestBagInterface;

/**
 * @author Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 */
interface TestBagMapInterface extends \IteratorAggregate
{
    /**
     * @param TestBagInterface           $testBag
     * @param RequestContextMatcher|null $requestContextMatcher
     */
    public function add(TestBagInterface $testBag, RequestContextMatcher $requestContextMatcher = null);

    /**
     * @param RequestContext $requestContext
     *
     * @return TestBagInterface
     */
    public function resolve(RequestContext $requestContext);
}
