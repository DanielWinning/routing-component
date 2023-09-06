<?php

namespace DannyXCII\tests;

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

    public function testLoadRoutesFromFile()
    {
        $router = new Router($this->container);

        $yamlFile = 'sample_routes.yaml';
        $yamlContent = Yaml::dump(['routes' => [['path' => '/test', 'handler' => ['TestController', 'testMethod']]]]);
        file_put_contents($yamlFile, $yamlContent);

        $router->loadRoutesFromFile($yamlFile);

        $this->assertEquals([['path' => '/test', 'handler' => ['TestController', 'testMethod']]], $router->getRoutes());

        unlink($yamlFile);
    }
}