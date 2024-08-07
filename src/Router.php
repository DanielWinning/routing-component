<?php

namespace Luma\RoutingComponent;

use Luma\Framework\Luma;
use Luma\Framework\Messages\FlashMessage;
use Luma\HttpComponent\Response;
use Luma\HttpComponent\Stream;
use Luma\RoutingComponent\Attribute\AbstractRouteProtectionAttribute;
use Luma\RoutingComponent\Attribute\RequireAuthentication;
use Luma\RoutingComponent\Attribute\RequirePermissions;
use Luma\RoutingComponent\Attribute\RequireRoles;
use Luma\RoutingComponent\Attribute\RequireUnauthenticated;
use Luma\SecurityComponent\Authentication\Interface\UserInterface;
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
     * @throws \RuntimeException
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
     * @return ResponseInterface
     *
     * @throws \ReflectionException|\Exception|\Throwable
     */
    public function handleRequest(RequestInterface $request): ResponseInterface
    {
        $requestPath = strtok($request->getUri()->getPath(), '?');
        $requestPath = $requestPath === '/' ? $requestPath : rtrim($requestPath, '/');

        foreach ($this->routes as $route) {
            if (array_key_exists('methods', $route) && !in_array($request->getMethod(), $route['methods'])) {
                continue;
            }

            $pattern = $this->generateRoutePattern($route['path']);

            if (preg_match($pattern, $requestPath, $matches)) {
                array_shift($matches);
                $matches = array_filter($matches, function($val, $key) {
                    return !is_numeric($key);
                }, ARRAY_FILTER_USE_BOTH);

                return $this->callHandler($route['handler'], $matches, $request);
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
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     *
     * @throws \ReflectionException|\Exception|\Throwable
     */
    private function callHandler(array $handler, array $matches, RequestInterface $request): ResponseInterface
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
                return $this->invokeControllerMethod($controller, $methodName, $matches, $request);
            }
        }

        return $this->notFoundResponse();
    }

    /**
     * @return ResponseInterface
     */
    private function notFoundResponse(): ResponseInterface
    {
        return $this->badResponse('404 Not Found', 404, 'Not Found');
    }

    /**
     * @return Response
     */
    private function notAllowedResponse(): Response
    {
        return $this->badResponse('403 Not Allowed', 403, 'Not Allowed');
    }

    /**
     * @param string $displayText
     * @param int $code
     * @param string $reasonText
     *
     * @return Response
     */
    private function badResponse(string $displayText, int $code, string $reasonText): Response
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->write($displayText);
        $stream->rewind();

        return new Response(
            $code,
            $reasonText,
            [
                'Content-Type' => 'text/html',
            ],
            $stream
        );
    }

    /**
     * @param string $controller
     *
     * @return mixed
     */
    public function createControllerInstance(string $controller): object
    {
        return new $controller();
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

            if ($paramType) {
                $dependencyName = $paramType->getName();

                try {
                    $dependency = $paramType->isBuiltin() ? $dependencyName : $container->get($dependencyName);

                    if (!$dependency) throw new \Exception();

                    $dependencies[] = $dependency;
                } catch (\Exception $exception) {
                    throw new \RuntimeException(
                        sprintf('Dependency not found: %s', $dependencyName),
                        0,
                        $exception
                    );
                }
            } else {
                throw new \RuntimeException(sprintf('Unsupported parameter type: %s', $parameter->getName()));
            }
        }

        return $dependencies;
    }

    /**
     * @param $controller
     * @param string $methodName
     * @param array $matches
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     *
     * @throws \ReflectionException
     */
    private function invokeControllerMethod($controller, string $methodName, array $matches, RequestInterface $request): ResponseInterface
    {
        $notAllowedResponse = $this->handleRouteProtectionAttributes($controller, $methodName);

        if ($notAllowedResponse instanceof ResponseInterface) {
            return $notAllowedResponse;
        }

        if ($this->methodHasRequestParameter($controller, $methodName)) {
            array_unshift($matches, $request);
        }

        $result = call_user_func_array([$controller, $methodName], $matches);

        if ($result instanceof ResponseInterface) {
            return $result;
        }

        if (is_string($result)) {
            $stream = new Stream(fopen('php://temp', 'r+'));
            $stream->write($result);
            $stream->rewind();

            return new Response(
                200,
                'OK',
                [
                    'Content-Type' => [
                        'text/html',
                        'application/json',
                    ],
                ],
                $stream
            );
        }

        return $this->notFoundResponse();
    }

    /**
     * @param string|null $redirectPath
     * @param string|null $message
     *
     * @return void
     */
    private function checkForRedirect(?string $redirectPath, ?string $message): void
    {
        if ($message) {
            $_SESSION['messages']['info'][] = new FlashMessage($message);
        }

        if ($redirectPath) {
            header('Location: ' . $redirectPath);
            exit;
        }
    }

    /**
     * @param $controller
     * @param string $methodName
     *
     * @return bool
     *
     * @throws \ReflectionException
     */
    private function methodHasRequestParameter($controller, string $methodName): bool
    {
        $reflectionMethod = new \ReflectionMethod($controller, $methodName);

        return (bool) count(array_filter(array_map(function (\ReflectionParameter $parameter) {
            $parameterType = $parameter->getType();

            if (!$parameterType instanceof \ReflectionNamedType) {
                return false;
            }

            if (!class_exists($parameterType->getName())) {
                return false;
            }

            $interfaces = class_implements($parameterType->getName());

            if (!is_array($interfaces)) {
                return false;
            }

            return array_key_exists(RequestInterface::class, $interfaces);
        }, $reflectionMethod->getParameters())));
    }

    /**
     * @param $controller
     * @param string $methodName
     *
     * @return Response|null
     *
     * @throws \ReflectionException
     */
    private function handleRouteProtectionAttributes($controller, string $methodName): ?Response
    {
        $reflectionMethod = new \ReflectionMethod($controller, $methodName);
        $authenticatedUser = Luma::getLoggedInUser();

        if ($requireAuthentication = $this->processRequireAuthenticationAttribute($reflectionMethod, $authenticatedUser)) {
            return $requireAuthentication;
        }

        if ($requireUnauthenticated = $this->processRequireUnauthenticatedAttribute($reflectionMethod, $authenticatedUser)) {
            return $requireUnauthenticated;
        }

        if ($requireRoles = $this->processRequireRolesAttribute($reflectionMethod, $authenticatedUser)) {
            return $requireRoles;
        }

        if ($requirePermissions = $this->processRequirePermissionsAttribute($reflectionMethod, $authenticatedUser)) {
            return $requirePermissions;
        }

        return null;
    }

    /**
     * @param \ReflectionMethod $reflectionMethod
     * @param UserInterface|null $authenticatedUser
     *
     * @return Response|null
     */
    private function processRequireAuthenticationAttribute(\ReflectionMethod $reflectionMethod, ?UserInterface $authenticatedUser): ?Response
    {
        $requireAuthenticationAttribute = $reflectionMethod->getAttributes(RequireAuthentication::class);

        if (!empty($requireAuthenticationAttribute)) {
            if (!$authenticatedUser) {
                $this->checkForRedirect(
                    ...$this->getRedirectionAttributeArguments($requireAuthenticationAttribute[0])
                );

                return $this->notAllowedResponse();
            }
        }

        return null;
    }

    /**
     * @param \ReflectionMethod $reflectionMethod
     * @param UserInterface|null $authenticatedUser
     *
     * @return Response|null
     */
    private function processRequireUnauthenticatedAttribute(\ReflectionMethod $reflectionMethod, ?UserInterface $authenticatedUser): ?Response
    {
        $requireUnauthenticatedAttribute = $reflectionMethod->getAttributes(RequireUnauthenticated::class);

        if (!empty($requireUnauthenticatedAttribute)) {
            if ($authenticatedUser) {
                $this->checkForRedirect(
                    ...$this->getRedirectionAttributeArguments($requireUnauthenticatedAttribute[0])
                );

                return $this->notAllowedResponse();
            }
        }

        return null;
    }

    /**
     * @param \ReflectionMethod $reflectionMethod
     * @param UserInterface|null $authenticatedUser
     *
     * @return Response|null
     */
    private function processRequireRolesAttribute(\ReflectionMethod $reflectionMethod, ?UserInterface $authenticatedUser): ?Response
    {
        $requireRolesAttribute = $reflectionMethod->getAttributes(RequireRoles::class);

        if (!empty($requireRolesAttribute)) {
            [$redirectPath, $message] = $this->getRedirectionAttributeArguments($requireRolesAttribute[0]);

            if (!$authenticatedUser) {
                $this->checkForRedirect($redirectPath, $message);

                return $this->notAllowedResponse();
            }

            $roles = $requireRolesAttribute[0]->getArguments()[RequireRoles::ROLES_KEY];

            foreach ($roles as $role) {
                if (!$authenticatedUser->hasRole($role)) {
                    $this->checkForRedirect($redirectPath, $message);

                    return $this->notAllowedResponse();
                }
            }
        }

        return null;
    }

    /**
     * @param \ReflectionMethod $reflectionMethod
     * @param UserInterface|null $authenticatedUser
     *
     * @return Response|null
     */
    public function processRequirePermissionsAttribute(\ReflectionMethod $reflectionMethod, ?UserInterface $authenticatedUser): ?Response
    {
        $requirePermissionsAttribute = $reflectionMethod->getAttributes(RequirePermissions::class);

        if (!empty($requirePermissionsAttribute)) {
            [$redirectPath, $message] = $this->getRedirectionAttributeArguments($requirePermissionsAttribute[0]);

            if (!$authenticatedUser) {
                $this->checkForRedirect($redirectPath, $message);

                return $this->notAllowedResponse();
            }

            $permissions = $requirePermissionsAttribute[0]->getArguments()[RequirePermissions::PERMISSIONS_KEY];

            foreach ($authenticatedUser->getRoles() as $role) {
                foreach ($permissions as $permission) {
                    if (!$role->hasPermission($permission)) {
                        $this->checkForRedirect($redirectPath, $message);

                        return $this->notAllowedResponse();
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param \ReflectionAttribute $attribute
     *
     * @return array
     */
    private function getRedirectionAttributeArguments(\ReflectionAttribute $attribute): array
    {
        return [
            $attribute->getArguments()[AbstractRouteProtectionAttribute::REDIRECT_PATH_KEY] ?? null,
            $attribute->getArguments()[AbstractRouteProtectionAttribute::MESSAGE_KEY] ?? null,
        ];
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