<?php

/*
 *
 * (c) Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Yarhon\RouteGuardBundle\Security\TestProvider;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Routing\Route;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security as SecurityAnnotation;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted as IsGrantedAnnotation;
use Yarhon\RouteGuardBundle\Annotations\ClassMethodAnnotationReaderInterface;
use Yarhon\RouteGuardBundle\Controller\ControllerMetadata;
use Yarhon\RouteGuardBundle\Routing\RequestAttributesFactory;
use Yarhon\RouteGuardBundle\Routing\RouteMetadataFactory;
use Yarhon\RouteGuardBundle\ExpressionLanguage\ExpressionDecorator;
use Yarhon\RouteGuardBundle\ExpressionLanguage\ExpressionAnalyzer;
use Yarhon\RouteGuardBundle\Security\Test\TestBag;
use Yarhon\RouteGuardBundle\Security\Test\SensioExtraTest;
use Yarhon\RouteGuardBundle\Security\Authorization\SensioSecurityExpressionVoter;
use Yarhon\RouteGuardBundle\Exception\LogicException;
use Yarhon\RouteGuardBundle\Exception\InvalidArgumentException;

/**
 * SensioExtraProvider processes Security & IsGranted annotations of Sensio FrameworkExtraBundle.
 *
 * @see https://symfony.com/doc/5.0/bundles/SensioFrameworkExtraBundle/annotations/security.html
 *
 * @author Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 */
class SensioExtraProvider implements ProviderInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var ClassMethodAnnotationReaderInterface
     */
    private $annotationReader;

    /**
     * @var ExpressionLanguage
     */
    private $expressionLanguage;

    /**
     * @var ExpressionAnalyzer
     */
    private $expressionAnalyzer;

    /**
     * @var RequestAttributesFactory
     */
    private $requestAttributesFactory;

    /**
     * @var RouteMetadataFactory
     */
    private $routeMetadataFactory;

    /**
     * @var array
     */
    private $tests = [];

    /**
     * @param ClassMethodAnnotationReaderInterface $annotationReader
     * @param RequestAttributesFactory             $requestAttributesFactory
     * @param RouteMetadataFactory                 $routeMetadataFactory
     */
    public function __construct(ClassMethodAnnotationReaderInterface $annotationReader, RequestAttributesFactory $requestAttributesFactory, RouteMetadataFactory $routeMetadataFactory)
    {
        $this->annotationReader = $annotationReader;
        $this->requestAttributesFactory = $requestAttributesFactory;
        $this->routeMetadataFactory = $routeMetadataFactory;
    }

    /**
     * @param ExpressionLanguage $expressionLanguage
     */
    public function setExpressionLanguage(ExpressionLanguage $expressionLanguage)
    {
        $this->expressionLanguage = $expressionLanguage;
    }

    /**
     * @param ExpressionAnalyzer $expressionAnalyzer
     */
    public function setExpressionAnalyzer($expressionAnalyzer)
    {
        $this->expressionAnalyzer = $expressionAnalyzer;
    }

    /**
     * {@inheritdoc}
     */
    public function getTests($routeName, Route $route, ControllerMetadata $controllerMetadata = null)
    {
        if (!$controllerMetadata) {
            return null;
        }

        $annotations = $this->annotationReader->read($controllerMetadata->getClass(), $controllerMetadata->getMethod(),
            [SecurityAnnotation::class, IsGrantedAnnotation::class]
        );

        if (!count($annotations)) {
            return null;
        }

        $controllerArguments = array_keys($controllerMetadata->getArguments());
        $requestAttributes = $this->getRequestAttributeNames($route);
        $requestAttributes = array_diff($requestAttributes, $controllerArguments);
        $allowedVariables = array_merge($controllerArguments, $requestAttributes);

        $tests = [];

        foreach ($annotations as $annotation) {
            $subjectName = null;

            if ($annotation instanceof SecurityAnnotation) {
                $expression = $this->processSecurityAnnotation($annotation, $allowedVariables);
                $attributes = [$expression];
                $usedVariables = $expression->getVariableNames();
            } elseif ($annotation instanceof IsGrantedAnnotation) {
                list($role, $subjectName) = $this->processIsGrantedAnnotation($annotation, $allowedVariables);
                $attributes = [$role];
                $usedVariables = $subjectName ? [$subjectName] : [];
            }

            if (count($usedVariables)) {
                $test = new SensioExtraTest($attributes, $subjectName);

                $usedRequestAttributes = array_values(array_intersect($usedVariables, $requestAttributes));

                if (count($usedRequestAttributes)) {
                    $test->setMetadata('request_attributes', $usedRequestAttributes);
                }
            } else {
                $uniqueKey = $this->getTestAttributesUniqueKey($attributes);

                if (!isset($this->tests[$uniqueKey])) {
                    $this->tests[$uniqueKey] = new SensioExtraTest($attributes);
                }

                $test = $this->tests[$uniqueKey];
            }

            $tests[] = $test;
        }

        return new TestBag($tests);
    }

    /**
     * @param SecurityAnnotation $annotation
     * @param array              $allowedVariables
     *
     * @return ExpressionDecorator
     */
    private function processSecurityAnnotation(SecurityAnnotation $annotation, array $allowedVariables)
    {
        if (!$this->expressionLanguage) {
            throw new LogicException('Cannot create expression because ExpressionLanguage is not provided.');
        }

        $expression = $annotation->getExpression();

        return $this->createExpression($expression, $allowedVariables);
    }

    /**
     * @param IsGrantedAnnotation $annotation
     * @param array               $allowedVariables
     *
     * @return array
     */
    private function processIsGrantedAnnotation(IsGrantedAnnotation $annotation, array $allowedVariables)
    {
        // Despite of the name, $annotation->getAttributes() is a string (annotation value)
        $role = $annotation->getAttributes();

        $subjectName = $annotation->getSubject() ?: null;

        if (null !== $subjectName && !in_array($subjectName, $allowedVariables, true)) {
            throw new InvalidArgumentException(sprintf('Unknown subject variable "%s". Allowed variables: "%s".', $subjectName, implode('", "', $allowedVariables)));
        }

        return [$role, $subjectName];
    }

    /**
     * @param Route $route
     *
     * @return string[]
     */
    private function getRequestAttributeNames(Route $route)
    {
        $routeMetadata = $this->routeMetadataFactory->createMetadata($route);

        return $this->requestAttributesFactory->getAttributeNames($routeMetadata);
    }

    /**
     * @param string $expression
     * @param array  $allowedVariables
     *
     * @return ExpressionDecorator
     *
     * @throws InvalidArgumentException
     */
    private function createExpression($expression, array $allowedVariables)
    {
        $voterVariableNames = SensioSecurityExpressionVoter::getVariableNames();
        $namesToParse = array_merge($voterVariableNames, $allowedVariables);

        try {
            if (!$this->expressionAnalyzer) {
                try {
                    // At first try to create expression without any variable names to save time during expression resolving
                    $parsed = $this->expressionLanguage->parse($expression, $voterVariableNames);
                    $usedVariables = [];
                } catch (SyntaxError $e) {
                    $parsed = $this->expressionLanguage->parse($expression, $namesToParse);
                    $usedVariables = $allowedVariables;
                }
            } else {
                $parsed = $this->expressionLanguage->parse($expression, $namesToParse);
                $usedVariables = $this->expressionAnalyzer->getUsedVariables($parsed);
                $usedVariables = array_values(array_diff($usedVariables, $voterVariableNames));
            }
        } catch (SyntaxError $e) {
            throw new InvalidArgumentException(sprintf('Cannot parse expression "%s" with following variables: "%s".', $expression, implode('", "', $namesToParse)), 0, $e);
        }

        return new ExpressionDecorator($parsed, $usedVariables);
    }

    /**
     * @param array $attributes
     *
     * @return string
     */
    private function getTestAttributesUniqueKey(array $attributes)
    {
        $roles = $attributes;

        $expressions = array_filter($attributes, function ($attribute) {
            return $attribute instanceof ExpressionDecorator;
        });

        $roles = array_diff($roles, $expressions);

        $expressions = array_map(function ($expression) {
            return (string) $expression;
        }, $expressions);

        $roles = array_unique($roles);
        sort($roles);

        return implode('#', array_merge($roles, $expressions));
    }
}
