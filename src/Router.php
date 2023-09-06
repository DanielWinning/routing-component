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
            $pattern = $this->generateRoutePattern($route['path']);

            if (preg_match($pattern, $requestUri, $matches)) {
                array_shift($matches);
                array_pop($matches);
                $this->callHandler($route['handler'], $matches);
                return;
            }
        }

        header("HTTP/1.1 404 Not Found");
        echo "404 Not Found";
    }

    /**
     * @param string $routePath
     *
     * @return string
     */
    private function generateRoutePattern(string $routePath): string
    {
        $routePath = preg_replace_callback('/{([^}]+)}/', function ($matches) {
            return '(?P<' . $matches[1] . '>[^/]+)';
        }, $routePath);

        return "#^{$routePath}$#";
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
                $controller = $this->createControllerInstance($controllerClass);
            }

            if (method_exists($controller, $methodName)) {
                $this->invokeControllerMethod($controller, $methodName, $matches);
            }
        }
    }

    /**
     * @param mixed $controller
     *
     * @return mixed
     */
    public function createControllerInstance(mixed $controller): object
    {
        if (is_string($controller)) {
            return new $controller();
        }

        return $controller;
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

    /**
     * @return array
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * @param array $routes
     *
     * @return void
     */
    public function loadRoutes(array $routes): void
    {
        $this->routes = $routes;
    }
}