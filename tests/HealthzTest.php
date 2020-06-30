<?php
namespace Gentux\Healthz;

use Mockery;
use Exception;
use Gentux\Healthz\Exceptions\HealthWarningException;

class HealthzTest extends \TestCase
{

    /** @var HealthCheck | Mockery\Mock */
    protected $check1;

    /** @var HealthCheck | Mockery\Mock */
    protected $check2;

    /** @var HealthCheck | Mockery\Mock */
    protected $check3;

    /** @var Healthz */
    protected $healthz;

    public function setUp(): void
    {
        parent::setUp();
        $this->check1 = Mockery::mock(HealthCheck::class);
        $this->check2 = Mockery::mock(HealthCheck::class);
        $this->check3 = Mockery::mock(HealthCheck::class);

        $this->healthz = new Healthz([$this->check1, $this->check2]);
    }

    /** @test */
    public function get_set_of_health_checks()
    {
        $result = $this->healthz->all();
        $this->assertCount(2, $result);
        $this->assertSame($this->check1, $result[0]);
        $this->assertSame($this->check2, $result[1]);
    }

    /** @test */
    public function push_new_health_checks_onto_the_stack()
    {
        $result = $this->healthz->push($this->check3);
        $this->assertSame($this->healthz, $result);

        $this->assertCount(3, $this->healthz->all());
    }

    /** @test */
    public function run_health_checks_and_return_result_stack()
    {
        $this->healthz->push($this->check3);

        # success
        $this->check1->shouldReceive('run');

        # warning
        $this->check2->shouldReceive('run')->andThrow(new HealthWarningException('warning'));
        $this->check2->shouldReceive('setStatus')->with('warning')->once();

        # failure
        $this->check3->shouldReceive('run')->andThrow(new Exception('failure'));
        $this->check3->shouldReceive('setStatus')->with('failure')->once();

        $result = $this->healthz->run();
        $this->assertInstanceOf(ResultStack::class, $result);
        $this->assertTrue($result->all()[0]->passed());
        $this->assertTrue($result->all()[1]->warned());
        $this->assertTrue($result->all()[2]->failed());
    }
}
