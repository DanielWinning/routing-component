<?php

namespace Luma\Tests\Unit;

use Luma\DependencyInjectionComponent\Exception\NotFoundException;
use Luma\Framework\Luma;
use Luma\HttpComponent\Request;
use Luma\HttpComponent\Response;
use Luma\HttpComponent\Uri;
use Luma\RoutingComponent\Router;
use Luma\Tests\Classes\TestHelper;
use Luma\Tests\Classes\UndefinedTestHelper;
use Luma\Tests\Controllers\TestController;
use Luma\Tests\Controllers\TestControllerWithAmbiguousDependencies;
use Luma\Tests\Controllers\TestControllerWithDependencies;
use Luma\Tests\Controllers\TestControllerWithStringDependency;
use Luma\Tests\Controllers\TestControllerWithUndefinedDependency;
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
        $_SESSION = [];
        $tmpRoutePath = sprintf('%s/%s', sys_get_temp_dir(), 'routes.yaml');
        $tmpConfigPath = sprintf('%s/%s', sys_get_temp_dir(), 'services.yaml');
        $dummyRoutes = [
            'index' => [
                'path' => '/test',
                'handler' => ['TestController', 'testMethod'],
            ]
        ];

        file_put_contents($tmpRoutePath, Yaml::dump(['routes' => $dummyRoutes]));
        file_put_contents($tmpConfigPath, '');

        try {
            $luma = new Luma(sys_get_temp_dir(), sys_get_temp_dir(), sys_get_temp_dir());
        } catch (\Exception|\Throwable $exception) {
            die($exception->getMessage());
        }

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
     * @param int $statusCode
     *
     * @return void
     *
     * @throws \ReflectionException|\Throwable
     *
     * @dataProvider routeTestCaseProvider
     */
    public function testHandleRequestMatchingRoute(string $path, mixed $return, int $statusCode = 200): void
    {
        $request = $this->buildGetRequest($this->buildUri($path));
        $response = $this->router->handleRequest($request);
        $this->assertEquals($statusCode, $response->getStatusCode());
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
     * @return void
     *
     * @throws \ReflectionException|\Throwable
     */
    public function testControllerHasStringParamType(): void
    {
        $this->router->loadRoutes([
            [
                'path' => '/',
                'handler' => [
                    TestControllerWithStringDependency::class,
                    'testMethod'
                ]
            ]
        ]);
        $this->expectNotToPerformAssertions();
        $this->router->handleRequest($this->buildGetRequest($this->buildUri('/')));
    }

    /**
     * @return void
     *
     * @throws \ReflectionException|\Throwable
     */
    public function testControllerHasUndefinedDependency(): void
    {
        $this->router->loadRoutes([
            [
                'path' => '/',
                'handler' => [
                    TestControllerWithUndefinedDependency::class,
                    'testMethod'
                ]
            ]
        ]);
        $this->expectException(\RuntimeException::class);
        $this->router->handleRequest($this->buildGetRequest($this->buildUri('/')));
    }

    /**
     * @return void
     *
     * @throws \ReflectionException|\Throwable
     */
    public function testControllerWithNonTypeHintedDependencies()
    {
        $this->router->loadRoutes([
            [
                'path' => '/',
                'handler' => [
                    TestControllerWithAmbiguousDependencies::class,
                    'index',
                ],
            ],
        ]);
        $this->expectException(\RuntimeException::class);
        $this->router->handleRequest($this->buildGetRequest($this->buildUri('/')));
    }

    /**
     * @return void
     *
     * @throws \ReflectionException|\Throwable
     */
    public function testUnauthenticatedResponseReturned()
    {
        $this->router->loadRoutes([
            [
                'path' => '/',
                'handler' => [
                    TestController::class,
                    'notAuthenticated',
                ],
            ],
        ]);

        $response = $this->router->handleRequest($this->buildGetRequest($this->buildUri('/')));

        $this->assertEquals('403 Not Allowed', $response->getBody()->getContents());
        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * @return void
     *
     * @throws \ReflectionException|\Throwable
     */
    public function testRequireUnauthenticatedRoute()
    {
        $this->router->loadRoutes([
            [
                'path' => '/',
                'handler' => [
                    TestController::class,
                    'notAuthenticatedSuccess',
                ],
            ],
        ]);

        $response = $this->router->handleRequest($this->buildGetRequest($this->buildUri('/')));

        $this->assertEquals('Success', $response->getBody()->getContents());
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @return void
     *
     * @throws \ReflectionException|\Throwable
     */
    public function testShouldReturnNotFoundResponseWithIncorrectRequestMethod(): void
    {
        $this->router->loadRoutes([
            [
                'path' => '/',
                'handler' => [
                    TestController::class,
                    'testIndex',
                ],
                'methods' => [
                    'POST'
                ],
            ],
        ]);
        $response = $this->router->handleRequest($this->buildGetRequest($this->buildUri('/')));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('404 Not Found', $response->getBody()->getContents());
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
        $map = [
            [UndefinedTestHelper::class, null],
            [TestHelper::class, new TestHelper()],
        ];
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->any())
            ->method('get')
            ->willReturnMap($map);
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
            [
                'path' => '/test-methods',
                'methods' => ['GET'],
                'handler' => [
                    TestController::class,
                    'testIndex',
                ],
            ],
            [
                'path' => '/not-authenticated',
                'methods' => ['GET'],
                'handler' => [
                    TestController::class,
                    'notAuthenticated',
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
            '/not-authenticated' => [
                '/not-authenticated',
                '403 Not Allowed',
                403
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