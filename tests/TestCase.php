<?php

/**
 * Report an exception.
 *
 * @param  \Throwable|string  $exception
 * @return void
 */
function report($exception)
{
    // Override the Laravel report() function for testing purpose!
    TestCase::$functions->report($exception);
}

class TestCase extends \PHPUnit\Framework\TestCase
{

    public static $functions;

    public function setUp(): void
    {
        parent::setUp();
        self::$functions = Mockery::mock();
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
