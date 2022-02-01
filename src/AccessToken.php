<?php

declare(strict_types=1);
/**
 * This file is part of the EasySDK package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Larva\EasySDK;

use GuzzleHttp\Exception\GuzzleException;
use Larva\EasySDK\Exceptions\ConnectionException;
use Larva\EasySDK\Exceptions\HttpException;
use Larva\EasySDK\Contracts\AccessTokenInterface;
use Larva\EasySDK\Exceptions\InvalidArgumentException;
use Larva\EasySDK\Exceptions\RuntimeException;
use Larva\EasySDK\Http\Response;
use Larva\EasySDK\Traits\HasHttpRequests;
use Larva\EasySDK\Traits\InteractsWithCache;
use Psr\Http\Message\RequestInterface;

/**
 * AccessToken
 */
abstract class AccessToken implements AccessTokenInterface
{
    use HasHttpRequests;
    use InteractsWithCache;

    /**
     * @var ServiceContainer
     */
    protected $app;

    /**
     * @var string
     */
    protected string $requestMethod = 'GET';

    /**
     * @var string
     */
    protected $endpointToGetToken;

    /**
     * @var string
     */
    protected $queryName;

    /**
     * @var array
     */
    protected $token;

    /**
     * @var string
     */
    protected string $tokenKey = 'access_token';

    /**
     * @var string
     */
    protected string $cachePrefix = 'easysdk.access_token.';

    /**
     * AccessToken constructor.
     *
     * @param ServiceContainer $app
     */
    public function __construct(ServiceContainer $app)
    {
        $this->app = $app;
    }

    /**
     * @return array
     *
     * @throws HttpException
     */
    public function getRefreshedToken(): array
    {
        return $this->getToken(true);
    }

    /**
     * @param bool $refresh
     * @return array
     * @throws HttpException
     */
    public function getToken(bool $refresh = false): array
    {
        $cacheKey = $this->getCacheKey();
        $cache = $this->getCache();
        if (!$refresh && $cache->has($cacheKey) && $result = $cache->get($cacheKey)) {
            return $result;
        }
        $token = $this->requestToken($this->getCredentials());
        $this->setToken($token[$this->tokenKey], $token['expires_in'] ?? 7200);
        $this->app->events->dispatch(new Events\AccessTokenRefreshed($this));
        return $token;
    }

    /**
     * 设置访问令牌
     * @param string $token
     * @param int $lifetime
     * @return AccessTokenInterface
     * @throws InvalidArgumentException|RuntimeException
     */
    public function setToken(string $token, int $lifetime = 7200): AccessTokenInterface
    {
        $this->getCache()->set($this->getCacheKey(), [
            $this->tokenKey => $token,
            'expires_in' => $lifetime,
        ], $lifetime);
        if (!$this->getCache()->has($this->getCacheKey())) {
            throw new RuntimeException('Failed to cache access token.');
        }
        return $this;
    }

    /**
     * 刷新令牌
     * @return AccessTokenInterface
     * @throws HttpException
     */
    public function refresh(): AccessTokenInterface
    {
        $this->getToken(true);
        return $this;
    }

    /**
     * 请求访问令牌
     * @param array $credentials
     * @return array
     * @throws ConnectionException|HttpException|GuzzleException
     */
    public function requestToken(array $credentials): array
    {
        $response = $this->sendRequest($credentials);
        if (empty($response->json($this->tokenKey))) {
            throw new HttpException('Request access_token fail: ' . json_encode($response->json(), JSON_UNESCAPED_UNICODE), $response->toPsrResponse());
        }
        return $response->json();
    }

    /**
     * @param RequestInterface $request
     * @param array $requestOptions
     * @return RequestInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function applyToRequest(RequestInterface $request, array $requestOptions = []): RequestInterface
    {
        parse_str($request->getUri()->getQuery(), $query);
        $query = http_build_query(array_merge($this->getQuery(), $query));
        return $request->withUri($request->getUri()->withQuery($query));
    }

    /**
     * Send http request.
     *
     * @param array $credentials
     * @return Response
     * @throws ConnectionException
     */
    protected function sendRequest(array $credentials): Response
    {
        $options = [
            ('GET' === $this->requestMethod) ? 'query' : 'json' => $credentials,
        ];
        return $this->setHttpClient($this->app['http_client'])->request($this->getEndpoint(), $this->requestMethod, $options);
    }

    /**
     * @return string
     */
    protected function getCacheKey(): string
    {
        return $this->cachePrefix . md5(json_encode($this->getCredentials()));
    }

    /**
     * The request query will be used to add to the request.
     *
     * @return array
     */
    protected function getQuery(): array
    {
        return [$this->queryName ?? $this->tokenKey => $this->getToken()[$this->tokenKey]];
    }

    /**
     * 获取令牌访问点
     * @return string
     * @throws InvalidArgumentException
     */
    public function getEndpoint(): string
    {
        if (empty($this->endpointToGetToken)) {
            throw new InvalidArgumentException('No endpoint for access token request.');
        }
        return $this->endpointToGetToken;
    }

    /**
     * @return string
     */
    public function getTokenKey(): string
    {
        return $this->tokenKey;
    }

    /**
     * Credential for get token.
     *
     * @return array
     */
    abstract protected function getCredentials(): array;
}
