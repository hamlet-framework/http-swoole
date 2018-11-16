<?php

namespace Hamlet\Http\Swoole\Writers;

use Exception;
use Hamlet\Http\Writers\ResponseWriter;
use Psr\Http\Message\ServerRequestInterface;
use SessionHandlerInterface;
use swoole_http_response;

class SwooleResponseWriter implements ResponseWriter
{
    /** @var swoole_http_response */
    private $response;

    /** @var SessionHandlerInterface|null */
    private $sessionHandler;

    public function __construct(swoole_http_response $response, ?SessionHandlerInterface $sessionHandler)
    {
        $this->response = $response;
        $this->sessionHandler = $sessionHandler;
    }

    /**
     * @param int $code
     * @param string|null $line
     * @suppress PhanParamTooManyInternal
     */
    public function status(int $code, string $line = null)
    {
        $this->response->status((string) $code, $line);
    }

    public function header(string $key, string $value)
    {
        // @bug not sure but for whatever reason swoole dislikes this header
        if (strtolower($key) == 'content-length') {
            return;
        }
        $this->response->header($key, $value);
    }

    public function writeAndEnd(string $payload)
    {
        $this->response->end($payload);
    }

    public function end()
    {
        $this->response->end();
    }

    /**
     * @param string $name
     * @param string $value
     * @param int $expires
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httpOnly
     */
    public function cookie(string $name, string $value, int $expires, string $path, string $domain = '', bool $secure = false, bool $httpOnly = false)
    {
        $this->response->cookie($name, $value, $expires, $path, $domain, $secure, $httpOnly);
    }

    /**
     * @param ServerRequestInterface $request
     * @param array $sessionParams
     * @return void
     * @throws Exception
     */
    public function session(ServerRequestInterface $request, array $sessionParams)
    {
        if ($this->sessionHandler === null) {
            return;
        }

        $sessionName = session_name();
        $cookies = $request->getCookieParams();

        if (isset($cookies[$sessionName])) {
            $sessionId = (string) $cookies[$sessionName];
        } else {
            $params = session_get_cookie_params();
            $sessionId = \bin2hex(\random_bytes(8));

            $lifeTime = $params['lifetime'] ? time() + ((int) $params['lifetime']) : 0;
            $this->cookie($sessionName, $sessionId, $lifeTime, (string) $params['path'], (string) $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }

        $this->sessionHandler->write($sessionId, serialize($sessionParams));
    }
}
