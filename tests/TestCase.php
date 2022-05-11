<?php

namespace Gentux\Healthz;

class TestCase extends \PHPUnit\Framework\TestCase
{

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
