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
    private string $temporaryRoutesFilepath;
    private Router $router;

    /**
     * @return void
     *
     * @throws MockObjectException
     */
    protected function setUp(): void
    {
        $this->temporaryRoutesFilepath = sprintf('%s/%s', sys_get_temp_dir(), 'routes.yaml');

        if (file_exists($this->temporaryRoutesFilepath)) {
            unlink($this->temporaryRoutesFilepath);
        }

        $this->container = $this->createMock(ContainerInterface::class);
        $this->router = $this->configure();
    }

    /**
     * @param mixed $routesData
     *
     * @return void
     *
     * @dataProvider getRouteFileInfo
     */
    public function testLoadRoutesFromFile(mixed $routesData): void
    {
        $router = new Router($this->container);
        $routesConfig = fopen($this->temporaryRoutesFilepath, 'w');

        if ($routesConfig !== false) {
            fwrite($routesConfig, is_array($routesData) ? Yaml::dump(['routes' => $routesData]) : $routesData);
            fclose($routesConfig);

            if (is_array($routesData)) {
                $router->loadRoutesFromFile($this->temporaryRoutesFilepath);
                $this->assertSame($routesData, $router->getRoutes());
            } else {
                $this->expectException(\RuntimeException::class);
                $router->loadRoutesFromFile($this->temporaryRoutesFilepath);
            }
        } else {
            $this->fail('Unable to open file for writing.');
        }
    }

    /**
     * @param string $path
     * @param mixed $return
     *
     * @return void
     *
     * @throws \ReflectionException|\Throwable
     *
     * @dataProvider routeTestCaseProvider
     */
    public function testHandleRequestMatchingRoute(string $path, mixed $return): void
    {
        $request = $this->buildGetRequest($this->buildUri($path));
        $response = $this->router->handleRequest($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($return, $response->getBody()->getContents());
    }

    /**
     * @return void
     *
     * @throws \ReflectionException|\Throwable
     */
    public function testHandleRequestWithInvalidHandlerDefinition(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->router->handleRequest($this->buildGetRequest($this->buildUri('/invalid-handler')));
    }

    /**
     * @return void
     *
     * @throws \ReflectionException|\Throwable
     */
    public function testItReturnsNotFoundResponseWhenControllerDoesNotExist(): void
    {
        $uri = $this->buildUri('/not-existing-controller');
        $response = $this->router->handleRequest($this->buildGetRequest($uri));

        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * @param string $path
     * @param array $args
     * @param mixed $return
     *
     * @return void
     *
     * @throws \ReflectionException|\Throwable
     *
     * @dataProvider dynamicRouteTestCaseProvider
     */
    public function testHandleRequestDynamicMatchingRoute(string $path, array $args, mixed $return): void
    {
        $response = $this->router->handleRequest($this->buildGetRequest($this->buildUri($path)));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($return, $response->getBody()->getContents());
    }

    /**
     * @return void
     *
     * @throws \ReflectionException|\Throwable
     */
    public function testHandleRequestWithQueryString(): void
    {
        $response = $this->router->handleRequest(
            $this->buildGetRequest($this->buildUri('/test', 'var=1'))
        );
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(TestController::STRING_RETURN, $response->getBody()->getContents());
    }

    /**
     * @return void
     *
     * @throws \ReflectionException|\Throwable
     */
    public function testHandleRequestNoMatchingRoute(): void
    {
        $response = $this->router->handleRequest($this->buildGetRequest($this->buildUri('/not-existing')));
        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * @return void
     *
     * @throws \ReflectionException|\Throwable
     */
    public function testHandleRequestMatchingInvalidControllerMethod(): void
    {
        $response = $this->router->handleRequest($this->buildGetRequest($this->buildUri('/test_return_invalid_response')));
        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * @param string $path
     * @param string $query
     *
     * @return UriInterface
     */
    private function buildUri(string $path, string $query = ''): UriInterface
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
     * @return Router
     *
     * @throws MockObjectException
     */
    private function configure(): Router
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->any())
            ->method('get')
            ->with('DannyXCII\RoutingComponentTests\TestHelper')
            ->willReturn(new TestHelper());
        $router = new Router($container);
        $router->loadRoutes($this->getTestRoutes());

        return $router;
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
                    'testIndex',
                ],
            ],
            [
                'path' => '/test',
                'handler' => [
                    TestController::class,
                    'testIndex',
                ],
            ],
            [
                'path' => '/test/second',
                'handler' => [
                    TestController::class,
                    'testIndex',
                ],
            ],
            [
                'path' => '/test/blog',
                'handler' => [
                    TestController::class,
                    'testIndex',
                ]
            ],
            [
                'path' => '/test/blog/{id}',
                'handler' => [
                    TestController::class,
                    'testParams',
                ]
            ],
            [
                'path' => '/test/blog/{category}/{id}',
                'handler' => [
                    TestController::class,
                    'testMultipleParams',
                ],
            ],
            [
                'path' => '/invalid-handler',
                'handler' => [
                    TestController::class,
                ],
            ],
            [
                'path' => '/not-existing-controller',
                'handler' => [
                    'NotExistingController',
                    'methodNotExists',
                ],
            ],
            [
                'path' => '/test_return_response_class',
                'handler' => [
                    TestController::class,
                    'testReturnResponseClass',
                ],
            ],
            [
                'path' => '/test_return_invalid_response',
                'handler' => [
                    TestController::class,
                    'testReturnInvalidResponse',
                ],
            ],
            [
                'path' => '/depends',
                'handler' => [
                    TestControllerWithDependencies::class,
                    'testMethodWithDependantController',
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
            '/' => [
                '/',
                TestController::STRING_RETURN,
            ],
            '/test' => [
                '/test',
                TestController::STRING_RETURN,
            ],
            '/test/second' => [
                '/test/second',
                TestController::STRING_RETURN,
            ],
            '/test/' => [
                '/test/',
                TestController::STRING_RETURN,
            ],
            '/test_return_response_class' => [
                '/test_return_response_class',
                'Test response',
            ],
            '/depends' => [
                '/depends',
                TestController::STRING_RETURN,
            ],
        ];
    }

    /**
     * @return array[]
     */
    public static function dynamicRouteTestCaseProvider(): array
    {
        return [
            '/test/blog/{id}' => [
                'path' => '/test/blog/123',
                'args' => ['123'],
                'return' => '123',
            ],
            '/test/blog/{category}/{id}' => [
                'path' => '/test/blog/recipes/1',
                'args' => ['recipes', '1'],
                'return' => 'Category: recipes | Post ID: 1',
            ],
        ];
    }

    /**
     * @return array
     */
    public static function getRouteFileInfo(): array
    {
        return [
            'invalid' => ['This is my routes file!'],
            'valid' => [
                [
                    'path' => '/test',
                    'handler' => ['TestController', 'testMethod'],
                ]
            ],
        ];
    }
}