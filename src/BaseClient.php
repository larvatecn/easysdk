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
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Larva\EasySDK\Contracts\AccessTokenInterface;
use Larva\EasySDK\Exceptions\ConnectionException;
use Larva\EasySDK\Http\Response;
use Larva\EasySDK\Traits\HasHttpRequests;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LogLevel;

/**
 * Class BaseClient.
 *
 * @author overtrue <i@overtrue.me>
 */
class BaseClient
{
    use HasHttpRequests {
        request as performRequest;
    }

    /**
     * @var ServiceContainer
     */
    protected $app;

    /**
     * @var AccessTokenInterface|null
     */
    protected $accessToken = null;

    /**
     * BaseClient constructor.
     *
     * @param ServiceContainer $app
     * @param AccessTokenInterface|null $accessToken
     */
    public function __construct(ServiceContainer $app, AccessTokenInterface $accessToken = null)
    {
        $this->app = $app;
        $this->accessToken = $accessToken ?? $this->app['access_token'];
        $this->withUserAgent('GuzzleHttp/7 EasySDK/1.0');
        $this->asJson();
        $this->acceptJson();
    }

    /**
     * Issue a GET request to the given URL.
     *
     * @param string $url
     * @param array|string|null $query
     * @return Response
     * @throws ConnectionException
     * @throws GuzzleException
     */
    public function get(string $url, $query = null): Response
    {
        return $this->request($url, 'GET', [
            'query' => $query,
        ]);
    }

    /**
     * Issue a HEAD request to the given URL.
     *
     * @param string $url
     * @param mixed $query
     * @return Response
     * @throws ConnectionException
     * @throws GuzzleException
     */
    public function head(string $url, $query = null): Response
    {
        return $this->request($url, 'HEAD', [
            'query' => $query,
        ]);
    }

    /**
     * Issue a POST request to the given URL.
     *
     * @param string $url
     * @param mixed $data
     * @return Response
     * @throws ConnectionException
     * @throws GuzzleException
     */
    public function post(string $url, array $data = []): Response
    {
        return $this->request($url, 'POST', [
            $this->bodyFormat => $data,
        ]);
    }

    /**
     * Issue a PATCH request to the given URL.
     *
     * @param string $url
     * @param mixed $data
     * @return Response
     * @throws ConnectionException
     * @throws GuzzleException
     */
    public function patch(string $url, $data = []): Response
    {
        return $this->request($url, 'PATCH', [
            $this->bodyFormat => $data,
        ]);
    }

    /**
     * Issue a PUT request to the given URL.
     *
     * @param string $url
     * @param mixed $data
     * @return Response
     * @throws ConnectionException
     * @throws GuzzleException
     */
    public function put(string $url, $data = []): Response
    {
        return $this->request($url, 'PUT', [
            $this->bodyFormat => $data,
        ]);
    }

    /**
     * Issue a DELETE request to the given URL.
     *
     * @param string $url
     * @param mixed $data
     * @return Response
     * @throws ConnectionException
     * @throws GuzzleException
     */
    public function delete(string $url, $data = []): Response
    {
        return $this->request($url, 'DELETE', empty($data) ? [] : [
            $this->bodyFormat => $data,
        ]);
    }

    /**
     * @return AccessTokenInterface
     */
    public function getAccessToken(): AccessTokenInterface
    {
        return $this->accessToken;
    }

    /**
     * @param AccessTokenInterface $accessToken
     *
     * @return $this
     */
    public function setAccessToken(AccessTokenInterface $accessToken)
    {
        $this->accessToken = $accessToken;
        return $this;
    }

    /**
     * @param string $url
     * @param string $method
     * @param array $options
     * @return Response
     * @throws GuzzleException|ConnectionException
     */
    public function request(string $url, string $method = 'GET', array $options = []): Response
    {
        if (empty($this->middlewares)) {
            $this->registerHttpMiddlewares();
        }
        $response = $this->performRequest($url, $method, $options);
        $this->app->events->dispatch(new Events\HttpResponseCreated($response));
        return $response;
    }

    /**
     * Register Guzzle middlewares.
     */
    protected function registerHttpMiddlewares()
    {
        // retry
        $this->withMiddleware($this->retryMiddleware(), 'retry');
        // access token
        $this->withMiddleware($this->accessTokenMiddleware(), 'access_token');
        // log
        $this->withMiddleware($this->logMiddleware(), 'log');
    }

    /**
     * Attache access token to request query.
     *
     * @return \Closure
     */
    protected function accessTokenMiddleware()
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                if ($this->accessToken) {
                    $request = $this->accessToken->applyToRequest($request, $options);
                }
                return $handler($request, $options);
            };
        };
    }

    /**
     * Log the request.
     *
     * @return \Closure
     */
    protected function logMiddleware()
    {
        $formatter = new MessageFormatter($this->app['config']['http.log_template'] ?? MessageFormatter::DEBUG);
        return Middleware::log($this->app['logger'], $formatter, LogLevel::DEBUG);
    }

    /**
     * Return retry middleware.
     *
     * @return \Closure
     */
    protected function retryMiddleware()
    {
        return Middleware::retry(
            function ($retries, RequestInterface $request, ResponseInterface $response = null) {
                // Limit the number of retries to 2
                if ($retries < $this->app->config->get('http.max_retries', 1) && $response && $body = $response->getBody()) {
                    // Retry on server errors
                    if (in_array(abs($response->getStatusCode()), [400, 401], true)) {
                        $this->accessToken->refresh();
                        $this->app['logger']->debug('Retrying with refreshed access token.');
                        return true;
                    }
                }
                return false;
            },
            function () {
                return abs($this->app->config->get('http.retry_delay', 500));
            }
        );
    }
}
