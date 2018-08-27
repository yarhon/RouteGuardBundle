<?php

/*
 *
 * (c) Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Yarhon\RouteGuardBundle\Security\Http;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * @author Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 */
class RequestContextMatcher
{
    /**
     * @param RequestContext             $requestContext
     * @param RequestConstraintInterface $constraint
     *
     * @return bool
     */
    public function matches(RequestContext $requestContext, RequestConstraintInterface $constraint)
    {
        if ($constraint->getMethods() && !in_array($requestContext->getMethod(), $constraint->getMethods(), true)) {
            return false;
        }

        if ($constraint->getIps() && !IpUtils::checkIp($requestContext->getClientIp(), $constraint->getIps())) {
            return false;
        }

        if (null !== $constraint->getHostPattern() && !preg_match('{'.$constraint->getHostPattern().'}i', $requestContext->getHost())) {
            return false;
        }

        if (null !== $constraint->getPathPattern() && !preg_match('{'.$constraint->getPathPattern().'}', rawurldecode($requestContext->getPathInfo()))) {
            return false;
        }

        return true;
    }
}