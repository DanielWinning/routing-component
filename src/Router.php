<?php

namespace DannyXCII\RoutingComponent;

use DannyXCII\HttpComponent\Response;
use DannyXCII\HttpComponent\Stream;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
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
     * @param RequestInterface $request
     *
     * @return void
     *
     * @throws \ReflectionException|\Exception|\Throwable
     */
    public function handleRequest(RequestInterface $request): ResponseInterface
    {
        $requestPath = rtrim(strtok($request->getUri()->getPath(), '?'), '/');

        foreach ($this->routes as $route) {
            $pattern = $this->generateRoutePattern($route['path']);

            if (preg_match($pattern, $requestPath, $matches)) {
                array_shift($matches);
                array_pop($matches);
                return $this->callHandler($route['handler'], $matches);
            }
        }

        return $this->notFoundResponse();
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
     * @return ResponseInterface
     *
     * @throws \ReflectionException|\Exception|\Throwable
     */
    private function callHandler(array $handler, array $matches): ResponseInterface
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
                return $this->invokeControllerMethod($controller, $methodName, $matches);
            }
        }

        return $this->notFoundResponse();
    }

    /**
     * @return ResponseInterface
     */
    private function notFoundResponse(): ResponseInterface
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->write('404 Not Found');
        $stream->rewind();

        return new Response(
            404,
            'Not Found',
            [
                'Content-Type' => 'text/html',
            ],
            $stream
        );
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
     * @return ResponseInterface
     */
    private function invokeControllerMethod($controller, string $methodName, array $matches): ResponseInterface
    {
        $result = call_user_func_array([$controller, $methodName], $matches);

        if ($result instanceof ResponseInterface) {
            return $result;
        }

        if (is_string($result)) {
            return new Response(
                200,
                'OK',
                [
                    'Content-Type' => 'text/html',
                ]
            );
        }

        return $this->notFoundResponse();
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