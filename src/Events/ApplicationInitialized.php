<?php

declare(strict_types=1);

namespace Larva\EasySDK\Events;

use Larva\EasySDK\ServiceContainer;

class ApplicationInitialized
{
    /**
     * @var ServiceContainer
     */
    public $app;

    /**
     * @param ServiceContainer $app
     */
    public function __construct(ServiceContainer $app)
    {
        $this->app = $app;
    }
}