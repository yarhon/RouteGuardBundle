<?php

/*
 *
 * (c) Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Yarhon\RouteGuardBundle\Security\TestProvider;

use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Routing\Route;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactoryInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security as SecurityAnnotation;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted as IsGrantedAnnotation;
use Yarhon\RouteGuardBundle\Annotations\ClassMethodAnnotationReaderInterface;
use Yarhon\RouteGuardBundle\Security\Sensio\VariableResolver;
use Yarhon\RouteGuardBundle\Security\Sensio\ExpressionDecorator;
use Yarhon\RouteGuardBundle\Controller\ControllerMetadata;
use Yarhon\RouteGuardBundle\Routing\RouteMetadata;
use Yarhon\RouteGuardBundle\Security\Test\TestBag;
use Yarhon\RouteGuardBundle\Security\Test\TestArguments;
use Yarhon\RouteGuardBundle\Security\Authorization\SensioSecurityExpressionVoter;
use Yarhon\RouteGuardBundle\Exception\LogicException;
use Yarhon\RouteGuardBundle\Exception\InvalidArgumentException;

/**
 * SensioSecurityProvider processes Security & IsGranted annotations of Sensio FrameworkExtraBundle.
 *
 * @see https://symfony.com/doc/5.0/bundles/SensioFrameworkExtraBundle/annotations/security.html
 *
 * @author Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 */
class SensioSecurityProvider implements TestProviderInterface
{
    use LoggerAwareTrait;

    /**
     * @var ClassMethodAnnotationReaderInterface
     */
    private $reader;

    /**
     * @var VariableResolver
     */
    private $variableResolver;

    /**
     * @var ExpressionLanguage
     */
    private $expressionLanguage;

    /**
     * @var ArgumentMetadataFactoryInterface
     */
    private $argumentMetadataFactory;

    /**
     * SensioSecurityProvider constructor.
     *
     * @param ClassMethodAnnotationReaderInterface  $reader
     * @param VariableResolver                      $variableResolver
     * @param ArgumentMetadataFactoryInterface|null $argumentMetadataFactory
     */
    public function __construct(ClassMethodAnnotationReaderInterface $reader, VariableResolver $variableResolver, ArgumentMetadataFactoryInterface $argumentMetadataFactory = null)
    {
        $this->reader = $reader;
        $this->variableResolver = $variableResolver;

        $this->argumentMetadataFactory = $argumentMetadataFactory ?: new ArgumentMetadataFactory();
    }

    /**
     * @param ExpressionLanguage $expressionLanguage
     */
    public function setExpressionLanguage(ExpressionLanguage $expressionLanguage)
    {
        $this->expressionLanguage = $expressionLanguage;
    }

    /**
     * {@inheritdoc}
     */
    public function onBuild()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getTests(Route $route, $controllerName = null)
    {
        if (!$controllerName) {
            return null;
        }

        list($class, $method) = explode('::', $controllerName);

        $arguments = $this->argumentMetadataFactory->createArgumentMetadata([$class, $method]);
        $routeMetadata = new RouteMetadata($route, $controllerName);
        $controllerMetadata = new ControllerMetadata($arguments);
        $variableNames = $this->variableResolver->getVariableNames($routeMetadata, $controllerMetadata);

        $annotations = $this->reader->read($class, $method, [SecurityAnnotation::class, IsGrantedAnnotation::class]);

        $tests = [];

        foreach ($annotations as $annotation) {
            $attributes = [];
            $subjectName = null;

            if ($annotation instanceof SecurityAnnotation) {
                if (!$this->expressionLanguage) {
                    throw new LogicException('Cannot create expression because ExpressionLanguage is not provided.');
                }

                $expression = $annotation->getExpression();

                try {
                    // At first try to create expression without any variable names to save time during expression resolving
                    $expression = $this->createExpression($expression);
                } catch (InvalidArgumentException $e) {
                    $expression = $this->createExpression($expression, $variableNames);
                }

                $attributes[] = $expression;
            } elseif ($annotation instanceof IsGrantedAnnotation) {
                // Despite of the name, $annotation->getAttributes() is a string (annotation value)
                $attributes[] = $annotation->getAttributes();

                $subjectName = $annotation->getSubject();

                if ($subjectName && !in_array($subjectName, $variableNames)) {
                    throw new InvalidArgumentException(sprintf('Unknown subject variable "%s". Known variables: "%s".', $subjectName, implode('", "', $variableNames)));
                }
            }

            $arguments = new TestArguments($attributes);

            if ($subjectName) {
                $arguments->setMetadata($subjectName);
            }
            $tests[] = $arguments;
        }

        if (!count($tests)) {
            return null;
        }

        $testBag = new TestBag($tests);
        $testBag->setMetadata([$routeMetadata, $controllerMetadata]);

        return $testBag;
    }

    /**
     * @param string $expression
     * @param array  $variableNames
     *
     * @return ExpressionDecorator
     *
     * @throws InvalidArgumentException
     */
    private function createExpression($expression, array $variableNames = [])
    {
        $voterVariableNames = SensioSecurityExpressionVoter::getVariableNames();
        $namesToParse = array_merge($voterVariableNames, $variableNames);

        // TODO: warning if some variable names overlaps with SensioSecurityExpressionVoter variables

        try {
            $parsed = $this->expressionLanguage->parse($expression, $namesToParse);
        } catch (SyntaxError $e) {
            throw new InvalidArgumentException(sprintf('Cannot parse expression "%s" with following variables: "%s".', $expression, implode('", "', $namesToParse)), 0, $e);
        }

        $expression = new ExpressionDecorator($parsed, $variableNames);

        return $expression;
    }
}
