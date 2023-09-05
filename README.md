# Routing Component Package

The Routing Component is a lightweight and flexible PHP package for handling routing in your web application. It 
provides a simple way to define routes and execute controller actions based on incoming HTTP requests. This component 
is designed to be easily integrated into your PHP projects and works seamlessly with PSR-11 compliant dependency 
injection containers.

## Installation
You can install this package using Composer:

```bash
composer require dannyxcii/routing-component
```

## Usage

### Basic Usage

#### Create a Router Instance

Create an instance of the Router class, passing a dependency injection container that implements the Psr\Container\ContainerInterface:

```php
use DannyXCII\RoutingComponent\Router;
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
  
#### Handle Requests:

In your application's entry point (e.g., index.php), call the handleRequest method to handle incoming HTTP requests:

```php
$requestUri = $_SERVER['REQUEST_URI'];
$router->handleRequest($requestUri);
```

The router will match the request URI to the defined routes and execute the corresponding controller action.

### Controller Actions

Controller actions are defined as arrays containing the controller class name and the method name:

```php
['App\Controllers\HomeController', 'index']
```

### Dependencies
The `Router` class can work seamlessly with dependency injection containers. You can inject dependencies into your 
controller actions through constructor injection. When a controller is instantiated, the router will automatically 
resolve and inject its dependencies from the container.

### Error Handling
The router handles 404 Not Found errors for unhandled routes. If no matching route is found, it will respond with a 
404 status code.

### License
This package is open-source software licensed under the [GNU General Public License, version 3.0 (GPL-3.0)](https://opensource.org/licenses/GPL-3.0).