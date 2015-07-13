<?php

namespace Stack;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\TerminableInterface;

class LazyHttpKernelTest extends \PHPUnit_Framework_TestCase
{
    /** @test */
    public function handleShouldInvokeFactory()
    {
        $factory = function () {
            return $this->createHelloKernel();
        };

        $kernel = new LazyHttpKernel($factory);

        $request = Request::create('/');
        $response = $kernel->handle($request);
        $this->assertEquals(new Response('Hello World!'), $response);
    }

    /** @test */
    public function handleShouldInvokeFactoryJustOnce()
    {
        $factoryCalled = 0;

        $factory = function () use (&$factoryCalled) {
            $factoryCalled++;
            return $this->createHelloKernel();
        };

        $kernel = new LazyHttpKernel($factory);

        $request = Request::create('/');
        $response = $kernel->handle($request);

        $this->assertSame(1, $factoryCalled);
    }

    /** @test */
    public function handleShouldReuseCreatedApp()
    {
        $factoryCalled = 0;

        $factory = function () use (&$factoryCalled) {
            $factoryCalled++;
            return $this->createHelloKernel();
        };

        $kernel = new LazyHttpKernel($factory);

        $request = Request::create('/');
        $response = $kernel->handle($request);
        $response = $kernel->handle($request);

        $this->assertSame(1, $factoryCalled);
    }

    public function testWithUrlMap()
    {
        $fooFactoryCalled = 0;
        $barFactoryCalled = 0;

        $foo = new LazyHttpKernel(function () use (&$fooFactoryCalled) {
            $fooFactoryCalled++;
            return $this->createKernel('foo');
        });

        $bar = new LazyHttpKernel(function () use (&$barFactoryCalled) {
            $barFactoryCalled++;
            return $this->createKernel('bar');
        });

        $app = $this->createHelloKernel();
        $kernel = new UrlMap($app, [
            '/foo' => $foo,
            '/bar' => $bar,
        ]);

        $request = Request::create('/foo');
        $response = $kernel->handle($request);

        $this->assertSame(1, $fooFactoryCalled);
        $this->assertSame(0, $barFactoryCalled);
    }

    public function testShortcutFunction()
    {
        $kernel = lazy(function () {
            return $this->createHelloKernel();
        });

        $this->assertInstanceOf('Stack\LazyHttpKernel', $kernel);
        $this->assertSame('Hello World!', $kernel->handle(Request::create('/'))->getContent());
    }

    /** @test */
    public function shouldBeTerminable()
    {
        $app = $this->createHelloTerminableKernel();
        $kernel = lazy(function() use ($app) {
            return $app;
        });

        $request = Request::create('/');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        $this->assertTrue($app->isTerminated());
    }

    private function createHelloTerminableKernel()
    {
        return new CallableAndTerminableHttpKernel(function(Request $request) {
            return new Response('Hello World!');
        });
    }

    private function createHelloKernel()
    {
        return $this->createKernel('Hello World!');
    }

    private function createKernel($body)
    {
        return new CallableHttpKernel(function (Request $request) use ($body) {
            return new Response($body);
        });
    }
}

class CallableAndTerminableHttpKernel extends CallableHttpKernel implements TerminableInterface
{
    /**
     * @var bool
     */
    private $terminated = false;

    /**
     * Terminates a request/response cycle.
     *
     * Should be called after sending the response and before shutting down the kernel.
     *
     * @param Request $request A Request instance
     * @param Response $response A Response instance
     *
     * @api
     */
    public function terminate(Request $request, Response $response)
    {
        $this->terminated = true;
    }

    public function isTerminated()
    {
        return $this->terminated;
    }
}