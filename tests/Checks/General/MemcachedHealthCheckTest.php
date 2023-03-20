<?php
namespace {
    // if memcached isnt installed, create a stub for tests
    if (!class_exists('Memcached')) {
        class Memcached {
        }
    }
}

namespace Gentux\Healthz\Checks\General {

    use Mockery;
    use Gentux\Healthz\HealthCheck;
    use Memcached;

    class MemcachedHealthCheckTest extends \TestCase
    {

        /** @var \Memcached */
        protected $memcached;

        /** @var MemcachedHealthCheck */
        protected $health;

        public function setUp(): void
        {
            parent::setUp();
            $this->memcached = Mockery::mock(Memcached::class);
            $this->health = new MemcachedHealthCheck($this->memcached);
        }

        /** @test */
        public function instance_of_health_check()
        {
            $this->assertInstanceOf(HealthCheck::class, $this->health);
        }

        /** @test */
        public function add_servers()
        {
            $this->health->addServer('123.com');
            $this->health->addServer('456.com', 2222, 1);

            $expect = [
                ['123.com', 11211, 0],
                ['456.com', 2222, 1],
            ];
            $this->assertSame($expect, $this->health->servers());
        }

        /** @test */
        public function set_options()
        {
            $this->health->setOptions([1 => 'foo']);
            $this->assertSame([1 => 'foo'], $this->health->options());
        }

        /** @test */
        public function username_and_password()
        {
            $this->health->setAuth('user', 'secret');

            $this->assertSame('user', $this->health->username());
            $this->assertSame('secret', $this->health->password());
        }

        /** @test */
        public function run_builds_memcached_instance_and_tests_connection()
        {
            $this->health->addServer('123.com');
            $this->health->addServer('456.com', 2222, 1);
            $this->health->setAuth('user', 'secret');
            $this->health->setOptions(['foo' => 'bar']);

            # spy on memcached instance
            $servers = [ ['123.com', 11211, 0], ['456.com', 2222, 1] ];
            $this->memcached->shouldReceive('addServers')->with($servers)->once();
            $this->memcached->shouldReceive('setOptions')->with(['foo' => 'bar'])->once();
            $this->memcached->shouldReceive('setSaslAuthData')->with('user', 'secret')->once();
            $this->memcached->shouldReceive('set')->with('test.connection', 'success', 1)->once()->andReturn(true);

            $this->health->run();
            $this->assertTrue(true);
        }

        /**
         * @test
         */
        public function run_throws_failure_exception_if_memcached_cant_set_test_value()
        {
            $this->expectException(\Gentux\Healthz\Exceptions\HealthFailureException::class);
            $this->memcached->shouldReceive('set')->with('test.connection', 'success', 1)->once()->andReturn(false);
            $this->health->run();
        }
    }
}
