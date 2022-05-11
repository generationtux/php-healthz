![Build Test Status](https://github.com/generationtux/php-healthz/actions/workflows/test.yml/badge.svg?event=push)
[![Code Climate](https://codeclimate.com/github/generationtux/php-healthz/badges/gpa.svg)](https://codeclimate.com/github/generationtux/php-healthz)
[![Test Coverage](https://codeclimate.com/github/generationtux/php-healthz/badges/coverage.svg)](https://codeclimate.com/github/generationtux/php-healthz/coverage)

# PHP Healthz

Health checking for PHP apps with built-in support for Laravel.

<img src="https://s3.amazonaws.com/gentux-dev/docs/health-check-screenshot.png">

Get an easy overview of the health of your app! Implement a health check endpoint for load balancers, or your own sanity :) Comes with an optional UI and set of pre-configured checks you can use, and is extensible
to add custom health checks to the stack as well.

- [Setup and usage](#setup)
     - [Laravel](#laravel)
     - [General PHP](#general-php)
- [Available checks and config](#check-configuration)
     - [HTTP](#http-check)
     - [Memcached](#memcached-check)
     - [Debug](#debug-check)
     - [Env)](#env-check)
     - [Database (Laravel)](#laravel-database)
     - [Queue (Laravel)](#laravel-queue)
- [Creating custom checks](#custom-checks)

---

## Setup

```sh
$ composer require generationtux/healthz
```

### Laravel < 5.4

(the following should work with Lumen as well, with minor differences)

**Add the service provider that will register the default health checks and routes**

```php
// config/app.php
'providers' => [
    Illuminate...,
    Gentux\Healthz\Support\HealthzServiceProvider::class,
]
```

You should be able to visit `/healthz/ui` to see the default Laravel health checks, or run `php artisan healthz` to get a CLI view.

To add basic auth to the UI page, set the `HEALTHZ_USERNAME` and `HEALTHZ_PASSWORD` environment variables.
Even if the UI has basic auth, the simplified `/healthz` endpoint will always be available to respond with a simple `ok` or `fail` for load balancers and other automated checks to hit.

**In order to customize the health checks, simply register `Gentux\Healthz\Healthz` in your app service provider (probably `app/Providers/AppServiceProvider.php`) to build a custom Healthz instance.**

```php
use Gentux\Healthz\Healthz;
use Illuminate\Support\ServiceProvider;
use Gentux\Healthz\Checks\General\EnvHealthCheck;
use Gentux\Healthz\Checks\Laravel\DatabaseHealthCheck;

class AppServiceProvider extends ServiceProvider
{
        public function register()
        {
                $this->app->bind(Healthz::class, function () {
                        $env = new EnvHealthCheck();
                        $db = new DatabaseHealthCheck();
                        $db->setConnection("non-default");

                        return new Healthz([$env, $db]);
                });
        }
}
```

[See more about configuring available checks](#check-configuration)

---

### General PHP

**Build an instance of the health check**

```php
<?php
use Gentux\Healthz\Healthz;
use Gentux\Healthz\Checks\General\MemcachedHealthCheck;

$memcached = (new MemcachedHealthCheck())->addServer("127.0.0.1");
$healthz = new Healthz([$memcached]);
```

**Run the checks and review results**

```php
// @var $results Gentux\Healthz\ResultStack
$results = $healthz->run();

if ($results->hasFailures()) {
        // oh no
}

if ($results->hasWarnings()) {
        // hmm
}

foreach ($results->all() as $result) {
        // @var $result Gentux\Healthz\HealthResult
        if ($result->passed() || $result->warned() || $result->failed()) {
                echo "it did one of those things at least";
        }

        echo "{$result->title()}: {$result->status()} ({$result->description()})";
}
```

**Get the UI view**

```php
$html = $healthz->html();
```

---

## Check configuration

- [HTTP](#http-check)
- [Memcached](#memcached-check)
- [Debug](#debug-check)
- [Env](#env-check)
- [Database (Laravel)](#laravel-database)
- [Queue (Laravel)](#laravel-queue)

#### HTTP

<a name="http-check"></a>
Create a new [Guzzle Request](http://docs.guzzlephp.org/en/latest/psr7.html) to pass to the constuctor of the HTTP health check.

```php
use GuzzleHTTP\PSR7\Request;
use Gentux\Healthz\Checks\General\HttpHealthCheck;

$request = new Request("GET", "http://example.com");
$httpCheck = new HttpHealthCheck($request);
```

You can optionally pass the expected response status code (defaults to `200`), as well as Guzzle client options.

```php
$httpCheck = new HttpHealthCheck($request, 204, [
        "base_url" => "http://example.com",
]);
```

#### Memcached

<a name="memcached-check"></a>
Create a new Memcached health check and use the methods `addServer` and `setOptions`.

```php
use Gentux\Healthz\Checks\General\MemcachedHealthCheck;

$memcachedCheck = new MemcachedHealthCheck();
$memcachedCheck->addServer($server, $port = 11211, $weight = 0);
$memcachedCheck->setOptions([]);
```

_See [Memcached setOptions](http://php.net/manual/en/memcached.setoptions.php) for option information._

#### Debug

<a name="debug-check"></a>
Set the environment variable to check if the app is running in debug. If this check fails, it emits a warning.

```php
use Gentux\Healthz\Checks\General\DebugHealthCheck;

$debugCheck = new DebugHealthCheck("APP_DEBUG");
```

In this case, if `APP_DEBUG` == `'true'` then this check will emit a warning.

#### Env

<a name="env-check"></a>
Provide an environment variable name to check for the apps environment. If the provided env name is found the check will always be successful and simply output the name. If no environment variable is set the check will emit a warning.

```php
use Gentux\Healthz\Checks\General\EnvHealthCheck;

$envCheck = new EnvHealthCheck("APP_ENV");
```

#### Database (Laravel)

<a name="laravel-database"></a>
This will use Laravel's built in database service to verify connectivity. You may optionally set a connection name to use (will use the default if not provided).

```php
use Gentux\Healthz\Checks\Laravel\DatabaseHealthCheck;

$dbCheck = new DatabaseHealthCheck();
$dbCheck->setConnection("my_conn"); // optional
```

#### Queue (Laravel)

<a name="laravel-queue"></a>
The queue health check currently supports `sync` and `sqs` queues. You may optionally set the queue name to use (will use the default if not specified).

```php
use Gentux\Healthz\Checks\Laravel\QueueHealthCheck;

$queueCheck = new QueueHealthCheck();
$queueCheck->setName("my_queue"); // optional
```

---

## Custom checks

_Note: Checks may have one of 3 statuses (`success`, `warning`, or `failure`). Any combination of success and warning and the stack as a whole will be considered to be successful.
Any single failure, however, will consider the stack to be failed._

To create a custom health check, you should extend `Gentux\Healthz\HealthCheck` and implement the one abstract method `run()`.

```php
<?php

use Gentux\Healthz\HealthCheck;

class MyCustomCheck extends HealthCheck
{
        /** @var string Optionally set a title, otherwise the class name will be used */
        protected $title = "";

        /** @var string Optionally set a description, just to provide more info on the UI */
        protected $description = "";

        public function run()
        {
                // any exception that is thrown will consider the check unhealthy
        }
}
```

If no exception is thrown, the check will be presumed to have been successful. Otherwise, the exception's message will be used as the `status` of the failed check.

```php
public function run()
{
    throw new Exception('Heres why the check failed.');
}
```

If you would like the check to show a `warning` instead of a full failure, throw an instance of `Gentux\Healthz\Exceptions\HealthWarningException`.

```php
use Gentux\Healthz\Exceptions\HealthWarningException;

public function run()
{
    throw new HealthWarningException("The check didn't fail, but here ye be warned.");
}
```

## Contributing

### What you need

- [docker & docker-compose](https://docs.docker.com/compose/install/)
- a fork of this repo

### Bringing up the development environment

```sh
docker-compose up -d
```

### Exec into the container

```sh
docker-compose exec app bash
```

### Composer install

```sh
composer install
```

### Running the tests

```sh
./vendor/bin/phpunit
```

### Finally

Make your changes and add any needed tests around said changes.
Then open a pull request into the generationtux repository.
