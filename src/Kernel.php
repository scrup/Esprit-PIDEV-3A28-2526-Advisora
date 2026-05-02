<?php

namespace App;

use App\DependencyInjection\Compiler\DisableDoctrineDoctorNamingConventionAnalyzerPass;
use App\DependencyInjection\Compiler\DisableDoctrineDoctorTimeZoneAnalyzerPass;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function __construct(string $environment, bool $debug)
    {
        $timezone = $_SERVER['APP_TIMEZONE'] ?? $_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?: 'Africa/Lagos';

        if (!is_string($timezone)) {
            $timezone = 'Africa/Lagos';
        }

        date_default_timezone_set($timezone);

        parent::__construct($environment, $debug);
    }

    /**
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable
    {
        $contents = require $this->getProjectDir() . '/config/bundles.php';

        foreach ($contents as $class => $envs) {
            if (($envs[$this->environment] ?? $envs['all'] ?? false) !== true) {
                continue;
            }

            $bundle = new $class();

            if (!$bundle instanceof BundleInterface) {
                continue;
            }

            yield $bundle;
        }
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        if ('dev' === $this->environment) {
            $container->addCompilerPass(new DisableDoctrineDoctorTimeZoneAnalyzerPass(), PassConfig::TYPE_BEFORE_REMOVING);
            $container->addCompilerPass(new DisableDoctrineDoctorNamingConventionAnalyzerPass(), PassConfig::TYPE_BEFORE_REMOVING);
        }
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $confDir = $this->getProjectDir() . '/config';

        $loader->load($confDir . '/{packages}/*.yaml', 'glob');
        $loader->load($confDir . '/{packages}/' . $this->environment . '/*.yaml', 'glob');

        if (file_exists($confDir . '/services.yaml')) {
            $loader->load($confDir . '/services.yaml');
        }

        if (file_exists($confDir . '/services_' . $this->environment . '.yaml')) {
            $loader->load($confDir . '/services_' . $this->environment . '.yaml');
        }
    }
}
