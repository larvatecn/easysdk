<?php

declare(strict_types=1);
/**
 * This file is part of the EasySDK package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Larva\EasySDK\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Utils;
use Larva\EasySDK\Exceptions\ConnectionException;
use Larva\EasySDK\Http\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Trait HasHttpRequests.
 */
trait HasHttpRequests
{
    /**
     * @var ClientInterface
     */
    protected $httpClient;

    /**
     * The base URL for the request.
     *
     * @var string
     */
    protected $baseUri;

    /**
     * The request body format.
     *
     * @var string
     */
    protected $bodyFormat;

    /**
     * The raw body for the request.
     *
     * @var string
     */
    protected $pendingBody;

    /**
     * The pending files for the request.
     *
     * @var array
     */
    protected array $pendingFiles = [];

    /**
     * The request cookies.
     *
     * @var array
     */
    protected $cookies;

    /**
     * The transfer stats for the request.
     *
     * \GuzzleHttp\TransferStats
     */
    protected $transferStats;

    /**
     * The request options.
     *
     * @var array
     */
    protected array $options = [];

    /**
     * The middleware callables added by users that will handle requests.
     *
     * @var array
     */
    protected array $middlewares = [];

    /**
     * @var HandlerStack
     */
    protected $handlerStack;

    /**
     * @var array
     */
    protected static array $defaultOptions = [
    ];

    /**
     * Set guzzle default settings.
     *
     * @param array $defaults
     */
    public static function setDefaultOptions(array $defaults = [])
    {
        self::$defaultOptions = $defaults;
    }

    /**
     * Return current guzzle default settings.
     */
    public static function getDefaultOptions(): array
    {
        return self::$defaultOptions;
    }

    /**
     * 设置待处理请求的基本URL
     *
     * @param string $url
     * @return $this
     */
    public function baseUri(string $url)
    {
        $this->baseUri = $url;
        return $this;
    }

    /**
     * 将原始内容附加到请求中
     *
     * @param resource|string $content
     * @param string $contentType
     * @return $this
     */
    public function withBody($content, string $contentType)
    {
        $this->bodyFormat('body');
        $this->pendingBody = $content;
        $this->contentType($contentType);
        return $this;
    }

    /**
     * 设置请求是JSON
     *
     * @return $this
     */
    public function asJson()
    {
        return $this->bodyFormat(RequestOptions::JSON)->contentType('application/json');
    }

    /**
     * 设置请求为表单
     *
     * @return $this
     */
    public function asForm()
    {
        return $this->bodyFormat(RequestOptions::FORM_PARAMS)->contentType('application/x-www-form-urlencoded');
    }

    /**
     * 设置该请求是一个 Multipart 表单
     *
     * @return $this
     */
    public function asMultipart()
    {
        return $this->bodyFormat(RequestOptions::MULTIPART);
    }

    /**
     * 添加文件到请求
     *
     * @param string|array $name
     * @param string $contents
     * @param string|null $filename
     * @param array $headers
     * @return $this
     */
    public function attach($name, $contents = '', string $filename = null, array $headers = [])
    {
        if (is_array($name)) {
            foreach ($name as $file) {
                $this->attach(...$file);
            }

            return $this;
        }
        $this->asMultipart();
        $this->pendingFiles[] = array_filter([
            'name' => $name,
            'contents' => $contents,
            'headers' => $headers,
            'filename' => $filename,
        ]);
        return $this;
    }

    /**
     * Specify the body format of the request.
     *
     * @param string $format
     * @return $this
     */
    public function bodyFormat(string $format)
    {
        $this->bodyFormat = $format;
        return $this;
    }

    /**
     * 指定请求的内容类型
     *
     * @param string $contentType
     * @return $this
     */
    public function contentType(string $contentType)
    {
        return $this->withHeaders(['Content-Type' => $contentType]);
    }

    /**
     * 设置希望服务器应返回JSON
     *
     * @return $this
     */
    public function acceptJson()
    {
        return $this->accept('application/json');
    }

    /**
     * 设置希望服务器应返回的内容类型
     *
     * @param string $contentType
     * @return $this
     */
    public function accept(string $contentType)
    {
        return $this->withHeaders(['Accept' => $contentType]);
    }

    /**
     * 添加 Headers 到请求
     *
     * @param array $headers
     * @return $this
     */
    public function withHeaders(array $headers)
    {
        $this->options = array_merge_recursive($this->options, [
            RequestOptions::HEADERS => $headers,
        ]);
        return $this;
    }

    /**
     * 设置请求的基本身份验证用户名和密码。
     *
     * @param string $username
     * @param string $password
     * @return $this
     */
    public function withBasicAuth(string $username, string $password)
    {
        $this->options[RequestOptions::AUTH] = [$username, $password];
        return $this;
    }

    /**
     * 设置请求的摘要身份验证用户名和密码。
     *
     * @param string $username
     * @param string $password
     * @return $this
     */
    public function withDigestAuth(string $username, string $password)
    {
        $this->options[RequestOptions::AUTH] = [$username, $password, 'digest'];
        return $this;
    }

    /**
     * 设置请求的授权令牌。
     *
     * @param string $token
     * @param string $type
     * @return $this
     */
    public function withToken(string $token, string $type = 'Bearer')
    {
        $this->options[RequestOptions::HEADERS]['Authorization'] = trim($type . ' ' . $token);
        return $this;
    }

    /**
     * 设置请求的授权。
     *
     * @param string $token
     * @return $this
     */
    public function withAuthorization(string $token)
    {
        $this->options[RequestOptions::HEADERS]['Authorization'] = trim($token);
        return $this;
    }

    /**
     * 设置请求UA
     *
     * @param string $userAgent
     * @return $this
     */
    public function withUserAgent(string $userAgent)
    {
        return $this->withHeaders(['User-Agent' => $userAgent]);
    }

    /**
     * 设置 http Referer
     *
     * @param string $referer
     * @return $this
     */
    public function withReferer(string $referer)
    {
        return $this->withHeaders(['Referer' => $referer]);
    }

    /**
     * 设置 http Origin
     *
     * @param string $origin
     * @return $this
     */
    public function withOrigin(string $origin)
    {
        return $this->withHeaders(['Origin' => $origin]);
    }

    /**
     * 设置请求Cookie
     *
     * @param array $cookies
     * @param string $domain
     * @return $this
     */
    public function withCookies(array $cookies, string $domain)
    {
        $this->options = array_merge_recursive($this->options, [
            RequestOptions::COOKIES => CookieJar::fromArray($cookies, $domain),
        ]);
        return $this;
    }

    /**
     * 设置请求仅使用 IPV4
     *
     * @return $this
     */
    public function withOnlyIPv4()
    {
        $this->options[RequestOptions::FORCE_IP_RESOLVE] = 'v4';
        return $this;
    }

    /**
     * 设置请求仅使用 IPV6
     *
     * @return $this
     */
    public function withOnlyIPv6()
    {
        $this->options[RequestOptions::FORCE_IP_RESOLVE] = 'v6';
        return $this;
    }

    /**
     * 设置请求为不跟随重定向
     *
     * @return $this
     */
    public function withoutRedirecting()
    {
        $this->options[RequestOptions::ALLOW_REDIRECTS] = false;
        return $this;
    }

    /**
     * 设置请求不验证证书有效性
     *
     * @return $this
     */
    public function withoutVerifying()
    {
        $this->options[RequestOptions::VERIFY] = false;
        return $this;
    }

    /**
     * Specify the path where the body of the response should be stored.
     *
     * @param string|resource $to
     * @return $this
     */
    public function sink($to)
    {
        $this->options[RequestOptions::SINK] = $to;
        return $this;
    }

    /**
     * 设置请求的超时时间
     *
     * @param int $seconds
     * @return $this
     */
    public function timeout(int $seconds)
    {
        $this->options[RequestOptions::TIMEOUT] = $seconds;
        return $this;
    }

    /**
     * 合并设置到客户端
     *
     * @param array $options
     * @return $this
     */
    public function withOptions(array $options)
    {
        $this->options = array_merge_recursive($this->options, $options);
        return $this;
    }

    /**
     * Add new middleware the client handler stack.
     *
     * @param callable $middleware
     * @param string|null $name
     * @return $this
     */
    public function withMiddleware(callable $middleware, string $name = null)
    {
        if (!is_null($name)) {
            $this->middlewares[$name] = $middleware;
        } else {
            $this->middlewares[] = $middleware;
        }
        return $this;
    }

    /**
     * Send the request to the given URL.
     *
     * @param string $url
     * @param string $method
     * @param array $options
     * @return Response
     * @throws ConnectionException|GuzzleException
     */
    public function request(string $url, string $method, array $options = []): Response
    {
        $options = $this->mergeOptions([
            RequestOptions::ON_STATS => function ($transferStats) {
                $this->transferStats = $transferStats;
            },
        ], $options, ['handler' => $this->getHandlerStack()]);
        if (property_exists($this, 'baseUri') && !is_null($this->baseUri)) {
            $options['base_uri'] = $this->baseUri;
        }
        if (isset($options[$this->bodyFormat])) {
            if ($this->bodyFormat === RequestOptions::MULTIPART) {
                $options[$this->bodyFormat] = $this->parseMultipartBodyFormat($options[$this->bodyFormat]);
                unset($options[RequestOptions::HEADERS]['Content-Type']);//去除无用的
            } elseif ($this->bodyFormat === RequestOptions::BODY) {
                $options[$this->bodyFormat] = $this->pendingBody;
            }
            if (is_array($options[$this->bodyFormat])) {
                $options[$this->bodyFormat] = array_merge($options[$this->bodyFormat], $this->pendingFiles);
            }
        } else {
            $options[$this->bodyFormat] = $this->pendingBody;
        }

        [$this->pendingBody, $this->pendingFiles] = [null, []];
        try {
            $response = new Response($this->getHttpClient()->request(strtoupper($method), $url, $options));
            $response->cookies = $this->cookies;
            $response->transferStats = $this->transferStats;
            return $response;
        } catch (ConnectException $e) {
            throw new ConnectionException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Parse multi-part form data.
     *
     * @param array $data
     * @return array|array[]
     */
    protected function parseMultipartBodyFormat(array $data): array
    {
        return array_map(function ($value, $key) {
            return is_array($value) ? $value : ['name' => $key, 'contents' => $value];
        }, $data, array_keys($data));
    }

    /**
     * Merge the given options with the current request options.
     *
     * @param array $options
     * @return array
     */
    public function mergeOptions(...$options): array
    {
        return array_merge_recursive(self::$defaultOptions, $this->options, ...$options);
    }

    /**
     * Set GuzzleHttp\Client.
     *
     * @param ClientInterface $httpClient
     * @return $this
     */
    public function setHttpClient(ClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    /**
     * Return GuzzleHttp\ClientInterface instance.
     *
     * @return ClientInterface
     */
    public function getHttpClient(): ClientInterface
    {
        if (!($this->httpClient instanceof ClientInterface)) {
            if (property_exists($this, 'app') && $this->app['http_client']) {
                $this->httpClient = $this->app['http_client'];
            } else {
                $this->httpClient = new Client([
                    'handler' => HandlerStack::create($this->getGuzzleHandler()),
                    'cookies' => true,
                ]);
            }
        }
        return $this->httpClient;
    }

    /**
     * Return all middlewares.
     *
     * @return array
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * @param HandlerStack $handlerStack
     *
     * @return $this
     */
    public function setHandlerStack(HandlerStack $handlerStack)
    {
        $this->handlerStack = $handlerStack;
        return $this;
    }

    /**
     * Build a handler stack.
     *
     * @return HandlerStack
     */
    public function getHandlerStack(): HandlerStack
    {
        if ($this->handlerStack) {
            return $this->handlerStack;
        }
        $this->handlerStack = HandlerStack::create($this->getGuzzleHandler());
        $this->handlerStack->push(function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                $this->cookies = $options['cookies'];
                return $handler($request, $options);
            };
        });
        foreach ($this->middlewares as $name => $middleware) {
            $this->handlerStack->push($middleware, $name);
        }
        return $this->handlerStack;
    }

    /**
     * Get guzzle handler.
     *
     * @return callable
     */
    protected function getGuzzleHandler()
    {
        if (property_exists($this, 'app') && isset($this->app['guzzle_handler'])) {
            return is_string($handler = $this->app->raw('guzzle_handler')) ? new $handler() : $handler;
        }
        return Utils::chooseHandler();
    }
}
