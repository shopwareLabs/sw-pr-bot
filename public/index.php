<?php

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('Europe/Berlin');

// set environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');

$dotenv->load();

$routes = [
    '/github-callback' => \GithubBot\GithubCallbackRoute::class,
];

$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

if (array_key_exists($path, $routes)) {
    $route = new $routes[$path];

    $route->__invoke();
} else {
    http_response_code(404);
    echo '404 Not Found';
}

function dd()
{
    echo '<pre>';
    var_dump(...func_get_args());
    echo '</pre>';
    die;
}

function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? null;

    if ($value === null) {
        return $default;
    }

    return $value;
}
