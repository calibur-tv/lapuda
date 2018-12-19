<?php

namespace Tests\Unit;

use App\Http\Middleware\ShowDelete;
use Illuminate\Http\Request;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ShowDeleteMiddlewareTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testExample()
    {
        $this->assertTrue(true);
    }

    public function testHashAndTimeIsOk()
    {
        $time = time();
        $request = new Request();
        $request->merge([
            'time' => $time,
            'hash' => md5('force-eye-' . $time),
        ]);

        $middleware = new ShowDelete();
        $middleware->handle($request, function ($request) {
            $this->assertEquals($request->get('showDelete'), 1);
        });
    }

    public function testNoHash()
    {
        $request = new Request();

        $middleware = new ShowDelete();
        $middleware->handle($request, function ($request) {
            $this->assertEquals($request->get('showDelete'), 0);
        });
    }

    public function testHashAndTimeIsNotOk()
    {
        $time = time();
        $request = new Request();
        $request->merge([
            'time' => $time,
            'hash' => md5('force-eye1-' . $time),
        ]);

        $middleware = new ShowDelete();
        $middleware->handle($request, function ($request) {
            $this->assertEquals($request->get('showDelete'), 0);
        });
    }
}
