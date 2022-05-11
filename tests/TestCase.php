<?php

class TestCase extends \PHPUnit\Framework\TestCase
{

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
