<?php

namespace DannyXCII\RoutingComponent;

use Psr\Container\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

class Router {
    private array $routes = [];
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param string $filename
     *
     * @return void
     */
    public function loadRoutesFromFile(string $filename): void
    {
        $loadedConfig = Yaml::parseFile($filename);

        if (!isset($loadedConfig['routes']) || !is_array($loadedConfig['routes'])) {
            throw new \RuntimeException("Invalid route configuration in YAML file: $filename");
        }

        $this->routes = $loadedConfig['routes'];
    }

    /**
     * @param string $requestUri
     *
     * @return void
     *
     * @throws \ReflectionException|\Exception|\Throwable
     */
    public function handleRequest(string $requestUri): void
    {
        foreach ($this->routes as $route) {
            if (preg_match("#^{$route['path']}$#", $requestUri, $matches)) {
                array_shift($matches); // Remove the full match
                $this->callHandler($route['handler'], $matches);
                return;
            }
        }
        // Handle 404 Not Found
        header("HTTP/1.0 404 Not Found");
        echo "404 Not Found";
    }

    /**
     * @param array $handler
     * @param array $matches
     *
     * @return void
     *
     * @throws \ReflectionException|\Exception|\Throwable
     */
    private function callHandler(array $handler, array $matches): void
    {
        if (count($handler) !== 2) {
            throw new \RuntimeException('Invalid handler format');
        }

        [$controllerClass, $methodName] = $handler;

        if (class_exists($controllerClass)) {
            $reflection = new \ReflectionClass($controllerClass);
            $constructor = $reflection->getConstructor();

            if ($constructor) {
                $dependencies = $this->resolveDependencies($constructor, $this->container);
                $controller = $reflection->newInstanceArgs($dependencies);
            } else {
                $controller = new $controllerClass();
            }

            if (method_exists($controller, $methodName)) {
                $this->invokeControllerMethod($controller, $methodName, $matches);
            }
        }
    }

    /**
     * @param \ReflectionMethod $constructor
     * @param ContainerInterface $container
     *
     * @return array
     *
     * @throws \Exception|\Throwable
     */
    private function resolveDependencies(\ReflectionMethod $constructor, ContainerInterface $container): array
    {
        $dependencies = [];
        foreach ($constructor->getParameters() as $parameter) {
            $paramType = $parameter->getType();

            if ($paramType && !$paramType->isBuiltin()) {
                $dependencyName = $paramType->getName();
                $dependency = $container->get($dependencyName);

                if (!$dependency) {
                    throw new \RuntimeException("Dependency not found: $dependencyName");
                }

                $dependencies[] = $dependency;
            } else {
                throw new \RuntimeException("Unsupported parameter type: " . $parameter->getName());
            }
        }

        return $dependencies;
    }

    /**
     * @param $controller
     * @param string $methodName
     * @param array $matches
     *
     * @return void
     */
    private function invokeControllerMethod($controller, string $methodName, array $matches): void
    {
        call_user_func_array([$controller, $methodName], $matches);
    }
}