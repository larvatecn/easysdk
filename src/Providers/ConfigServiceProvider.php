<?php

declare(strict_types=1);

namespace Larva\EasySDK\Providers;

use Larva\EasySDK\Config;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * Class ConfigServiceProvider.
 *
 * @author overtrue <i@overtrue.me>
 */
class ConfigServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $pimple A container instance
     */
    public function register(Container $pimple)
    {
        !isset($pimple['config']) && $pimple['config'] = function ($app) {
            return new Config($app->getConfig());
        };
    }
}