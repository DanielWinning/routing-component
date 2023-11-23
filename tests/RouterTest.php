<?php

namespace DannyXCII\RoutingComponentTests;

use DannyXCII\HttpComponent\Request;
use DannyXCII\HttpComponent\Response;
use DannyXCII\HttpComponent\StreamBuilder;
use DannyXCII\HttpComponent\URI;
use DannyXCII\RoutingComponent\Router;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use PHPUnit\Framework\MockObject\MockObject;
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
    private MockObject $testController;

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
        [$this->router, $this->testController] = $this->configure();
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
     * @param string $methodName
     * @param string $path
     * @param mixed $return
     *
     * @return void
     *
     * @throws \ReflectionException|\Throwable
     *
     * @dataProvider routeTestCaseProvider
     */
    public function testHandleRequestMatchingRoute(string $methodName, string $path, mixed $return): void
    {
        $this->testController->expects($this->once())
            ->method($methodName)
            ->with([])
            ->willReturn($return);

        $request = $this->buildGetRequest($this->buildUri($path));
        $response = $this->router->handleRequest($request);
        $this->assertEquals(200, $response->getStatusCode());
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

        $this->assertEquals(
            404,
            $response->getStatusCode()
        );
    }

    /**
     * @param string $path
     * @param string $method
     * @param array $args
     *
     * @return void
     *
     * @throws \ReflectionException|\Throwable
     *
     * @dataProvider dynamicRouteTestCaseProvider
     */
    public function testHandleRequestDynamicMatchingRoute(string $path, string $method, array $args, mixed $return): void
    {
        $this->testController->expects($this->once())
            ->method($method)
            ->with(...$args)
            ->willReturn($return);
        $response = $this->router->handleRequest($this->buildGetRequest($this->buildUri($path)));
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @return void
     *
     * @throws \ReflectionException|\Throwable
     */
    public function testHandleRequestWithQueryString(): void
    {
        $this->testController->expects($this->once())
            ->method('test_1')
            ->with([])
            ->willReturn(TestController::STRING_RETURN);
        $response = $this->router->handleRequest(
            $this->buildGetRequest($this->buildUri('/test', 'var=1'))
        );
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @return void
     *
     * @throws \ReflectionException|\Throwable
     */
    public function testHandleRequestNoMatchingRoute(): void
    {
        $this->testController->expects($this->never())
            ->method($this->anything());
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
        $this->testController->expects($this->once())
            ->method('test_7')->with([])
            ->willReturn(null);
        $response = $this->router->handleRequest($this->buildGetRequest($this->buildUri('/test_7')));
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
                'path' => '/test_6',
                'handler' => [
                    TestController::class,
                    'test_6',
                ],
            ],
            [
                'path' => '/test_7',
                'handler' => [
                    TestController::class,
                    'test_7',
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
                'test_index',
                '/',
                TestController::STRING_RETURN,
                ],
            '/test' => [
                'test_1',
                '/test',
                TestController::STRING_RETURN,
                ],
            '/test/second' => [
                'test_2',
                '/test/second',
                TestController::STRING_RETURN,
            ],
            '/test/' => [
                'test_1',
                '/test/',
                TestController::STRING_RETURN,
            ],
            '/test_6' => [
                'test_6',
                '/test_6',
                (new Response())->withStatus(200)->withBody(StreamBuilder::build('Test response')),
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
                'method' => 'test_4',
                'args' => ['123'],
                'return' => '123',
            ],
            '/test/blog/{category}/{id}' => [
                'path' => '/test/blog/recipes/1',
                'method' => 'test_5',
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