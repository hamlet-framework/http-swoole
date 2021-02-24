<?php

use Cache\Adapter\PHPArray\ArrayCachePool;
use Hamlet\Http\Applications\AbstractApplication;
use Hamlet\Http\Entities\JsonEntity;
use Hamlet\Http\Requests\Request;
use Hamlet\Http\Resources\HttpResource;
use Hamlet\Http\Responses\OKResponse;
use Hamlet\Http\Responses\Response;
use Hamlet\Http\Swoole\Bootstraps\SwooleBootstrap;
use Psr\Cache\CacheItemPoolInterface;

require_once '/vendor/autoload.php';

$application = new class extends AbstractApplication
{
    protected function findResource(Request $request): HttpResource
    {
        return new class implements HttpResource
        {
            public function getResponse(Request $request): Response
            {
                return new OKResponse(new JsonEntity('response'));
            }
        };
    }

    protected function getCache(Request $request): CacheItemPoolInterface
    {
        return new ArrayCachePool;
    }
};

SwooleBootstrap::run('0.0.0.0', 9501, $application);
