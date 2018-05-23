<?php
namespace Atrauzzi\LaravelSerializer;

use Atrauzzi\LaravelSerializer\MetaData\MetadataDriver;
use Illuminate\Config\Repository;
use Illuminate\Support\ServiceProvider as Base;
//
use Illuminate\Foundation\Application;
use JMS\Serializer\Builder\CallbackDriverFactory;
use JMS\Serializer\Builder\DriverFactoryInterface;
use JMS\Serializer\SerializerBuilder;
use Doctrine\Common\Annotations\Reader;

class JMSSerializerServiceProvider extends Base
{
    const VERSION = '1.2';

    public function register()
    {
        $this->app->singleton('JMS\Serializer\Builder\DriverFactoryInterface', function (Application $app) {
            return new CallbackDriverFactory(
            // Note: Because we're using mappings from the L4 configuration system, there's no
            // real use for $metadataDirs and $reader.
                function (array $metadataDirs, Reader $reader) use ($app) {
                    return $app->make(MetadataDriver::class);
                }
            );
        });

        $this->app->singleton('JMS\Serializer\Serializer', function (Application $app) {

            /** @var \Illuminate\Config\Repository $config */
            $config = $app->make(Repository::class);

            return SerializerBuilder
                ::create()
                ->setCacheDir(storage_path('cache/serializer'))
                ->setDebug($config->get('app.debug'))
                ->setMetadataDriverFactory($app->make(DriverFactoryInterface::class))
                ->build();

        });

    }

    public function boot()
    {
        $configPath = __DIR__ . '/../../config/serializer.php';
        $this->publishes([$configPath => config_path('serializer.php')]);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['serializer'];
    }

}

