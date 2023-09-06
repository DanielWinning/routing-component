<?php

namespace DannyXCII\RoutingComponentTests;

use DannyXCII\RoutingComponent\Router;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
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

        $router->handleRequest($path);
    }

    /**
     * @return void
     *
     * @throws MockObjectException
     */
    public function testHandleRequestDynamicMatchingRoute(): void
    {
        [$router, $testController] = $this->configure();

        $testController->expects($this->once())->method('test_4')->with('123');

        $router->handleRequest('/test/blog/123');
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

        $this->expectOutputString('404 Not Found');

        $router->handleRequest('/not-existing');
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
        ];
    }

    /**
     * @return array[]
     */
    public static function routeTestCaseProvider(): array
    {
        return [
            ['test_1', '/test'],
            ['test_2', '/test/second'],
        ];
    }
}