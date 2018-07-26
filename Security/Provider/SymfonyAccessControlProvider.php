<?php

/*
 *
 * (c) Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Yarhon\LinkGuardBundle\Security\Provider;

use Symfony\Component\Routing\Route;
use Yarhon\LinkGuardBundle\Security\Http\RequestConstraint;
use Yarhon\LinkGuardBundle\Security\Http\RouteMatcher;
use Yarhon\LinkGuardBundle\Security\Http\RequestMatcher;
use Yarhon\LinkGuardBundle\Security\Authorization\Test\Arguments;
use Yarhon\LinkGuardBundle\Security\Authorization\Test\TestBag;
use Yarhon\LinkGuardBundle\Security\Http\TestBagMap;

/**
 * SymfonyAccessControlProvider processes access_control config of Symfony SecurityBundle.
 *
 * @see https://symfony.com/doc/4.1/security/access_control.html
 *
 * @author Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 */
class SymfonyAccessControlProvider implements ProviderInterface
{
    /**
     * @var array
     */
    private $rules = [];

    /**
     * SymfonyAccessControlProvider constructor.
     */
    public function __construct()
    {
    }

    /**
     * TODO: check for rules without at least one constraint parameter / at least one argument parameter
     * (and check how symfony security processes such rules).
     *
     * @param array $rule
     *
     * @throws \InvalidArgumentException (see self::normalizeRule method)
     */
    public function addRule(array $rule)
    {
        $rule = $this->normalizeRule($rule);

        $constraint = new RequestConstraint($rule['path'], $rule['host'], $rule['methods'], $rule['ips']);

        $attributes = $rule['roles'];
        if ($rule['allow_if']) {
            $expression = $rule['allow_if'];
            $attributes[] = $expression;
        }

        $arguments = new Arguments($attributes);
        $arguments->setSubjectMetadata(Arguments::SUBJECT_CONTEXT_VARIABLE, 'request');

        $this->rules[] = [$constraint, $arguments];
    }

    /**
     * {@inheritdoc}
     */
    public function getTests(Route $route)
    {
        $matches = [];

        foreach ($this->rules as $rule) {
            /** @var RequestConstraint $constraint */
            list($constraint, $arguments) = $rule;

            $routeMatcher = new RouteMatcher($constraint);
            $matchType = $routeMatcher->matches($route);

            if (RouteMatcher::MATCH_NEVER == $matchType) {
                continue;
            }

            $testBag = new TestBag([$arguments]);

            if (RouteMatcher::MATCH_POSSIBLE == $matchType) {
                $matches[] = [$testBag, new RequestMatcher($constraint)];
                continue;
            }

            if (RouteMatcher::MATCH_ALWAYS == $matchType) {
                $matches[] = [$testBag, null];
                break;
            }
        }

        if (1 == count($matches) && null === $matches[0][1]) {
            // MATCH_ALWAYS rule was found, and there were no MATCH_POSSIBLE rules found before, so
            // we don't need a TestBagMap to resolve it by Request in runtime.
            return $matches[0][0];
        } elseif (count($matches)) {
            return new TestBagMap($matches);
        }

        return null;
    }

    /**
     * Checks and normalizes config rule, according to configuration defined in
     * \Symfony\Bundle\SecurityBundle\DependencyInjection\MainConfiguration::addAccessControlSection.
     *
     * @param array $rule
     *
     * @return array $rule
     *
     * @throws \InvalidArgumentException When rule array keys doesn't correspond to configuration prototype
     *                                   (possibly, BC breaking changes in Symfony SecurityBundle)
     */
    private function normalizeRule(array $rule)
    {
        $prototype = [
            'path' => null,
            'host' => null,
            'methods' => [],
            'ips' => [],
            'roles' => [],
            'allow_if' => null,
            'requires_channel' => null,
        ];

        $diff = array_diff(array_keys($rule), array_keys($prototype));

        if (count($diff)) {
            throw new \InvalidArgumentException(sprintf('Options %s are not supported in access_control rules.',
                implode(', ', $diff)));
        }

        $rule = array_merge($prototype, $rule);

        if (is_string($rule['methods'])) {
            $rule['methods'] = preg_split('/\s*,\s*/', $rule['methods']);
        }

        if (is_string($rule['roles'])) {
            $rule['roles'] = preg_split('/\s*,\s*/', $rule['roles']);
        }

        if (is_string($rule['ips'])) {
            $rule['ips'] = [$rule['ips']];
        }

        return $rule;
    }
}
