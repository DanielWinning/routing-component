# Luma | Routing Component

<div>
<!-- Version Badge -->
<img src="https://img.shields.io/badge/Version-1.6.2-blue" alt="Version 1.6.2">
<!-- PHP Coverage Badge -->
<img src="https://img.shields.io/badge/PHP Coverage-77.49%25-orange" alt="PHP Coverage 77.49%">
<!-- License Badge -->
<img src="https://img.shields.io/badge/License-GPL--3.0--or--later-34ad9b" alt="License GPL--3.0--or--later">
</div>

The Routing Component is a lightweight and flexible PHP package for handling routing in your web application. It 
provides a simple way to define routes and execute controller actions based on incoming HTTP requests. This component 
is designed to be easily integrated into your PHP projects and works seamlessly with PSR-11 compliant dependency 
injection containers.

## Installation
You can install this package using Composer:

```bash
composer require lumax/routing-component
```

## Usage

### Basic Usage

#### Create a Router Instance

Create an instance of the `Router` class, passing a dependency injection container that implements the 
`Psr\Container\ContainerInterface`:

```php
use Luma\RoutingComponent\Router;
use Psr\Container\ContainerInterface;

$router = new Router($container);
```

#### Load Routes:

Load your application's routes from a YAML configuration file:

```php
$router->loadRoutesFromFile('routes.yaml');
```

Example YAML configuration (`routes.yaml`):

```yaml
routes:
  index:
    path: /
    handler: [App\Controllers\HomeController, index]
  user_index:
    path: /user/{id}
    handler: [App\Controllers\UserController, show]
```

Alternatively, you can load your routes from an array if you'd prefer:

```php
$routes = [
    [
        'path' => '/',
        'handler' => [
            'App\\Controllers\\HomeController',
            'index',
        ]       
    ],
    [
        'path' => '/user/{id}',
        'handler' => [
            'App\\Controllers\\UserController',
            'show',
        ]       
    ],
];

$router->loadRoutes($routes);
```
  
#### Handle Requests:

In your application's entry point (e.g., `index.php`), call the `handleRequest` method to handle incoming HTTP requests:

```php
$router->handleRequest($request);
```

The `handleRequest` method expects an instance of `Psr\Http\Message\RequestInterface`. This Routing Component requires my
HTTP Component, therefore `Request` and `Response` classes are already provided.

The router will match the request URI to the defined routes and execute the corresponding controller action.

### Controller Actions

Controller actions are defined as arrays containing the controller class name and the method name:

```yaml
['App\Controllers\HomeController', 'index']
```

### Dependencies
The `Router` class is designed to work seamlessly with dependency injection containers. You can inject dependencies 
into your controller actions through constructor injection. When a controller is instantiated, the router will automatically 
resolve and inject its dependencies from the container. Requires a PSR-11 compliant `ContainerInterface` instance.

### Error Handling
The router handles 404 Not Found errors for unhandled routes. If no matching route is found, it will respond with a 
404 status code.

### License
This package is open-source software licensed under the [GNU General Public License, version 3.0 (GPL-3.0)](https://opensource.org/licenses/GPL-3.0).