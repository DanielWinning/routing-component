<?php

namespace DannyXCII\RoutingComponentTests;

use DannyXCII\HttpComponent\Request;
use DannyXCII\HttpComponent\URI;
use DannyXCII\RoutingComponent\Router;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\Yaml\Yaml;

class RouterTest extends TestCase
{
    private ContainerInterface $container;

    /**
     * @return void
     *
     * @throws MockObjectException
     */
    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
    }

    /**
     * @return void
     */
    public function testLoadRoutesFromFile(): void
    {
        $router = new Router($this->container);

        $yamlFile = 'sample_routes.yaml';
        $yamlContent = Yaml::dump(['routes' => [['path' => '/test', 'handler' => ['TestController', 'testMethod']]]]);
        file_put_contents($yamlFile, $yamlContent);

        $router->loadRoutesFromFile($yamlFile);

        $this->assertEquals([['path' => '/test', 'handler' => ['TestController', 'testMethod']]], $router->getRoutes());

        unlink($yamlFile);
    }

    /**
     * @param string $methodName
     * @param string $path
     *
     * @dataProvider routeTestCaseProvider
     *
     * @return void
     *
     * @throws MockObjectException
     */
    public function testHandleRequestMatchingRoute(string $methodName, string $path): void
    {
        [$router, $testController] = $this->configure();

        $testController->expects($this->once())
            ->method($methodName)
            ->with([]);

        $uri = $this->buildUri($path, '');

        $router->handleRequest($this->buildGetRequest($uri));
    }

    /**
     * @param string $path
     * @param string $method
     * @param array $args
     *
     * @dataProvider dynamicRouteTestCaseProvider
     *
     * @return void
     *
     * @throws MockObjectException
     */
    public function testHandleRequestDynamicMatchingRoute(string $path, string $method, array $args): void
    {
        [$router, $testController] = $this->configure();

        $testController->expects($this->once())->method($method)->with(...$args);

        $uri = $this->buildUri($path, '');

        $router->handleRequest($this->buildGetRequest($uri));
    }

    /**
     * @return void
     *
     * @throws MockObjectException
     */
    public function testHandleRequestWithQueryString(): void
    {
        [$router, $testController] = $this->configure();

        $testController->expects($this->once())
            ->method('test_1')
            ->with([]);

        $uri = $this->buildUri('/test', 'var=1');

        $router->handleRequest($this->buildGetRequest($uri));
    }

    /**
     * @return void
     *
     * @throws MockObjectException
     */
    public function testHandleRequestNoMatchingRoute(): void
    {
        [$router, $testController] = $this->configure();

        $testController->expects($this->never())
            ->method($this->anything());

        $uri = $this->buildUri('/not-existing', '');
        $response = $router->handleRequest($this->buildGetRequest($uri));

        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * @param string $path
     * @param string $query
     *
     * @return UriInterface
     */
    private function buildUri(string $path, string $query): UriInterface
    {
        return new URI('https', 'localhost', $path, $query);
    }

    /**
     * @param UriInterface $uri
     *
     * @return RequestInterface
     */
    private function buildGetRequest(UriInterface $uri): RequestInterface
    {
        return new Request('GET', $uri, ['Content-Type' => 'text/html']);
    }

    /**
     * @return array
     *
     * @throws MockObjectException
     */
    private function configure(): array
    {
        $router = $this->getMockBuilder(Router::class)
            ->setConstructorArgs([$this->container])
            ->onlyMethods(['createControllerInstance'])
            ->getMock();

        $router->loadRoutes($this->getTestRoutes());
        $testController = $this->createMock(TestController::class);

        $router->expects($this->any())
            ->method('createControllerInstance')
            ->with(TestController::class)
            ->willReturn($testController);

        return [$router, $testController];
    }

    /**
     * @return array[]
     */
    private function getTestRoutes(): array
    {
        return [
            [
                'path' => '/',
                'handler' => [
                    TestController::class,
                    'test_index',
                ],
            ],
            [
                'path' => '/test',
                'handler' => [
                    TestController::class,
                    'test_1',
                ],
            ],
            [
                'path' => '/test/second',
                'handler' => [
                    TestController::class,
                    'test_2',
                ],
            ],
            [
                'path' => '/test/blog',
                'handler' => [
                    TestController::class,
                    'test_3',
                ]
            ],
            [
                'path' => '/test/blog/{id}',
                'handler' => [
                    TestController::class,
                    'test_4',
                ]
            ],
            [
                'path' => '/test/blog/{category}/{id}',
                'handler' => [
                    TestController::class,
                    'test_5',
                ],
            ],
        ];
    }

    /**
     * @return array[]
     */
    public static function routeTestCaseProvider(): array
    {
        return [
            '/' => ['test_index', '/'],
            '/test' => ['test_1', '/test'],
            '/test/second' => ['test_2', '/test/second'],
            '/test/' => ['test_1', '/test/'],
        ];
    }

    public static function dynamicRouteTestCaseProvider(): array
    {
        return [
            '/test/blog/{id}' => [
                'path' => '/test/blog/123',
                'method' => 'test_4',
                'args' => ['123'],
            ],
            '/test/blog/{category}/{id}' => [
                'path' => '/test/blog/recipes/1',
                'method' => 'test_5',
                'args' => ['recipes', '1'],
            ],
        ];
    }
}