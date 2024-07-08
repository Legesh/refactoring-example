<?php

namespace App\Tests;

use App\TransactionProcessor;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use Monolog\Logger;
use Monolog\Handler\TestHandler;

class TransactionProcessorTest extends TestCase
{
    private TransactionProcessor $processor;

    protected function setUp(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['country' => ['alpha2' => 'DE']])),
            new Response(200, [], json_encode(['rates' => ['USD' => 1.2]]))
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $logger = new Logger('testLogger');
        $logger->pushHandler(new TestHandler());

        $this->processor = new TransactionProcessor($client, $logger);
    }

    public function testProcess()
    {
        $input = '{"bin":"45717360","amount":"100.00","currency":"EUR"}';
        file_put_contents('test_input.txt', $input);

        $this->processor->process('test_input.txt');

        // You can capture the output using output buffering
        $this->expectOutputString("1\n");
    }
}
