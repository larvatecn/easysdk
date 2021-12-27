<?php

declare(strict_types=1);

namespace Larva\EasySDK\Contracts;

use Psr\Http\Message\RequestInterface;

/**
 * Interface AuthorizerAccessToken.
 *
 * @author overtrue <i@overtrue.me>
 */
interface AccessTokenInterface
{
    /**
     * @return array
     */
    public function getToken(): array;

    /**
     * @return AccessTokenInterface
     */
    public function refresh(): self;

    /**
     * @param RequestInterface $request
     * @param array $requestOptions
     * @return RequestInterface
     */
    public function applyToRequest(RequestInterface $request, array $requestOptions = []): RequestInterface;
}
