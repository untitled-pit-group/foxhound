<?php declare(strict_types=1);
namespace Tests;
class RpcDispatcherTest extends TestCase
{
    private function assertJsonRpcError(int $code): void
    {
        $status = $this->response->getStatusCode();
        $this->assertTrue(400 <= $status && $status < 600);
        $this->assertStringStartsWith(
            'application/json',
            $this->response->headers->get('Content-Type'),
        );

        $content = json_decode($this->response->getContent(), true);
        $this->assertEquals('2.0', $content['jsonrpc'] ?? null);
        $error = $content['error'] ?? null;
        $this->assertNotNull($error, "Response had error: " . json_encode($error));
        $this->assertEquals($code, $error['code']);
    }
    private function assertNotError(): void
    {
        $status = $this->response->getStatusCode();
        $this->assertTrue($status < 300,
            "Response did not have success status (got {$status})");
    }
    private function assertNoContent(): void
    {
        $this->assertEquals(204, $this->response->getStatusCode());
    }

    private function getRegistry(): \App\Rpc\Registry
    {
        return $this->app->get(\App\Rpc\Registry::class);
    }

    private function getRpcResponse()
    {
        return json_decode($this->response->getContent(), true);
    }

    public function test_rpc_dispatcher_input_errors(): void
    {
        $this->post('/rpc');
        $this->assertJsonRpcError(-32700);

        $this->json('POST', '/rpc', ['invalid' => 'request']);
        $this->assertJsonRpcError(-32600);

        $this->json('POST', '/rpc', ['jsonrpc' => '2.0']);
        $this->assertNoContent();
        $this->json('POST', '/rpc', ['jsonrpc' => '2.0', 'id' => 'a']);
        $this->assertJsonRpcError(-32600);

        $this->json('POST', '/rpc', ['jsonrpc' => '2.0', 'id' => 'a',
                                     'method' => 'test.nonexistent',
                                     'params' => ['sequential', 'params']]);
        $this->assertJsonRpcError(-32600);

        $this->json('POST', '/rpc', ['jsonrpc' => '2.0', 'id' => 'a',
                                     'method' => 'test.nonexistent',
                                     'params' => []]);
        $this->assertJsonRpcError(-32601);
    }

    public function test_rpc_dispatcher_calls_method(): void
    {
        $this->getRegistry()->register('test.it_works', function (array $p) {
            return 'It Works!';
        });
        $this->json('POST', '/rpc', ['jsonrpc' => '2.0', 'id' => 'a',
                                     'method' => 'test.it_works',
                                     'params' => []]);
        $this->assertNotError();
        $this->assertEquals('It Works!', $this->getRpcResponse()['result']);
    }
}
