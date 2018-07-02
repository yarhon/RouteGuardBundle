<?php

/*
 *
 * (c) Yaroslav Honcharuk <yaroslav.xs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NeonLight\SecureLinksBundle\Tests\Twig\NodeVisitor;

use Twig_Error_Syntax as SyntaxError;   // Workaround for PhpStorm to recognise type hints. Namespaced name: Twig\Error\SyntaxError
use Twig\Node\Node;
use Twig\Node\PrintNode;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\NameExpression;
use NeonLight\SecureLinksBundle\Tests\Twig\AbstractNodeTest;
use NeonLight\SecureLinksBundle\Twig\Node\RouteIfGrantedNode;
use NeonLight\SecureLinksBundle\Twig\Node\RouteIfGrantedExpression;

class DiscoverRoutingFunctionNodeVisitorTest extends AbstractNodeTest
{
    private static $referenceVarName = 'route_reference';

    /**
     * @dataProvider discoverDataProvider
     */
    public function testDiscover($source, $expected)
    {
        $node = $this->parse($source);

        $this->assertEquals($expected, $node);
    }

    public function discoverDataProvider()
    {
        $nameExpression = new NameExpression(self::$referenceVarName, 0);
        $nameExpression->setAttribute('always_defined', true);

        return [
            [
                // path function test
                '{% routeifgranted discover %}{{ path("secure1") }}{% endrouteifgranted %}',
                new RouteIfGrantedNode(
                    (new RouteIfGrantedExpression(
                        new Node([
                            new ConstantExpression('secure1', 0)
                        ])
                    ))->setFunctionName('path'),
                    new PrintNode($nameExpression, 0)
                )
            ],

            [
                // url function test
                '{% routeifgranted discover %}{{ url("secure1") }}{% endrouteifgranted %}',
                new RouteIfGrantedNode(
                    (new RouteIfGrantedExpression(
                        new Node([
                            new ConstantExpression('secure1', 0)
                        ])
                    ))->setFunctionName('url'),
                    new PrintNode($nameExpression, 0)
                )
            ],

            [
                // relative parameter test
                '{% routeifgranted discover %}{{ path("secure1", {}, true) }}{% endrouteifgranted %}',
                new RouteIfGrantedNode(
                    (new RouteIfGrantedExpression(
                        new Node([
                            new ConstantExpression('secure1', 0),
                            new ArrayExpression([], 0),
                            new ConstantExpression('GET', 0),
                        ])
                    ))->setFunctionName('path')->setRelative(true),
                    new PrintNode($nameExpression, 0)
                )
            ],
        ];
    }

    /**
     * @dataProvider discoverExceptionDataProvider
     */
    public function testDiscoverException($source, $expected)
    {
        $this->expectException($expected[0]);
        if (isset($expected[1])) {
            $this->expectExceptionMessage($expected[1]);
        }

        $this->parse($source);
    }

    public function discoverExceptionDataProvider()
    {
        return [
            [
                // without any routing function
                '{% routeifgranted discover %}test{% endrouteifgranted %}',
                [SyntaxError::class, '"routeifgranted" tag with discover option must contain one url() or path() call.']
            ],
            [
                // with 2 routing functions
                '{% routeifgranted discover %}{{ url("secure1") }}{{ url("secure2") }}{% endrouteifgranted %}',
                [SyntaxError::class, '"routeifgranted" tag with discover option must contain only one url() or path() call.']
            ],
        ];
    }
}
