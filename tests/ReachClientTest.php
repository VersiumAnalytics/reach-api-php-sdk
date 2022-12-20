<?php

namespace Versium\Reach\Tests;

use Generator;
use PHPUnit\Framework\TestCase;
use Versium\Reach\ReachClient;

class ReachClientTest extends TestCase {
    /**
     * Simple test helper function to create a generator from an array
     * @param array $yieldValues
     * @return Generator
     */
    protected function generate(array $yieldValues): Generator
    {
        yield from $yieldValues;
    }

    /**
     * @test
     * @group ReachClient
     */
    public function it_can_be_instantiated() {
        $this->assertInstanceOf(ReachClient::class, new ReachClient(''));
    }

    /**
     * @test
     * @group append
     */
    public function append_returns_empty_array() {
        $client = new ReachClient('');
        $this->assertEquals($this->generate([]), $client->append('', []));
    }

    /**
     * @test
     * @group append
     */
    public function append_does_not_call_createAndLimitRequests() {
        $client = $this->createPartialMock(ReachClient::class, ['createAndLimitRequests']);
        $client->expects($this->exactly(0))
            ->method('createAndLimitRequests');
        $client->append('', [])->current();
    }

    /**
     * @test
     * @group append
     */
    public function calls_createAndLimitRequests() {
        $client = $this->createPartialMock(ReachClient::class, ['createAndLimitRequests']);
        $client->expects($this->once())
            ->method('createAndLimitRequests');
        $client->append('', [[]])->current();
    }

    /**
     * @test
     * @group listgen
     */
    public function calls_sendListGenRequest() {
        $client = $this->createPartialMock(ReachClient::class, ['sendListGenRequest']);
        $client->expects($this->once())
            ->method('sendListGenRequest')
            ->willReturn(['requestErrorNum' => 1]);

        $client->listgen('', [], []);
    }
}
