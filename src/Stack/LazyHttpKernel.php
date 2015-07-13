<?php

namespace Stack;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

class LazyHttpKernel implements HttpKernelInterface, TerminableInterface
{
    private $factory;
    private $app;

    public function __construct(callable $factory)
    {
        $this->factory = $factory;
    }

    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        return $this->createApp()->handle($request, $type, $catch);
    }

    private function createApp()
    {
        $this->app = $this->app ?: call_user_func($this->factory);

        return $this->app;
    }

    public function terminate(Request $request, Response $response)
    {
        if ($this->app instanceof TerminableInterface) {
            $this->app->terminate($request, $response);
        }
    }
}
