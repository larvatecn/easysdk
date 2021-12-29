<?php

declare(strict_types=1);
/**
 * This file is part of the EasySDK package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Larva\EasySDK\Events;

use Larva\EasySDK\Http\Response;

/**
 * Class HttpResponseCreated.
 *
 * @author mingyoung <mingyoungcheung@gmail.com>
 */
class HttpResponseCreated
{
    /**
     * @var ResponseInterface
     */
    public $response;

    /**
     * @param Response $response
     */
    public function __construct(Response $response)
    {
        $this->response = $response;
    }
}
