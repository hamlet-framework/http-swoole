<?php

namespace Hamlet\Http\Swoole\Bootstraps;

use Hamlet\Http\Applications\AbstractApplication;
use Hamlet\Http\Swoole\Requests\SwooleRequest;
use Hamlet\Http\Swoole\Writers\SwooleResponseWriter;
use swoole_http_request;
use swoole_http_response;
use swoole_http_server;

final class SwooleBootstrap
{
    private function __construct()
    {
    }

    /**
     * @param string $host
     * @param int $port
     * @param AbstractApplication $application
     * @return void
     */
    public static function run(string $host, int $port, AbstractApplication $application)
    {
        $server = new swoole_http_server($host, $port, SWOOLE_BASE);
        $workers = (int) shell_exec('grep -c processor /proc/cpuinfo');
        $server->set([
            'worker_num' => $workers
        ]);

        $server->on('request', function (swoole_http_request $swooleRequest, swoole_http_response $swooleResponse) use ($application) {
            $request  = new SwooleRequest($swooleRequest);
            $writer   = new SwooleResponseWriter($swooleResponse);
            $response = $application->run($request);

            $application->output($request, $response, $writer);
        });
        $server->start();
    }
}
