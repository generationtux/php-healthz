<?php
namespace Gentux\Healthz;

use Exception;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\ArrayLoader as TwigArrayLoader;
use Gentux\Healthz\Support\Stack;
use Gentux\Healthz\Exceptions\HealthWarningException;

/**
 * Collection of health checks to run.
 *
 * @package Gentux\Healthz
 */
class Healthz
{

    use Stack {
        Stack::push as stackPush;
    }

    /**
     * @param HealthCheck[] $healthChecks
     */
    public function __construct($healthChecks = [])
    {
        $this->items = $healthChecks;
    }

    /**
     * Push new health check onto the stack
     *
     * @param HealthCheck $healthCheck
     *
     * @return $this
     */
    public function push(HealthCheck $healthCheck): self
    {
        return $this->stackPush($healthCheck);
    }

    /**
     * Run the health checks in the stack
     *
     * @return ResultStack
     */
    public function run(): ResultStack
    {
        $results = [];

        foreach($this->all() as $check) {
            $resultCode = HealthResult::RESULT_SUCCESS;

            try {
                $check->run();
            } catch (Exception $e) {
                $check->setStatus($e->getMessage());
                $resultCode = $e instanceof HealthWarningException ? HealthResult::RESULT_WARNING : HealthResult::RESULT_FAILURE;
            }

            $results[] = new HealthResult($resultCode, $check);
        }

        return new ResultStack($results);
    }

    /**
     * Generate the HTML view for the health checks
     *
     * NOTE: this will run the health checks if a result stack is not passed in
     *
     * @param ResultStack $results
     *
     * @return string
     */
    public function html(ResultStack $results = null): string
    {
        if ($results === null) {
            $results = $this->run();
        }

        $loader = new TwigArrayLoader([
            'healthz' => file_get_contents(__DIR__ . '/../templates/healthz.html'),
        ]);
        $twig = new TwigEnvironment($loader);

        return $twig->render('healthz', ['results' => $results->all()]);
    }
}
