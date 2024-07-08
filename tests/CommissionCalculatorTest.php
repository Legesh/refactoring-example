<?php

namespace App\Tests;

use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use App\CommissionCalculator;

class CommissionCalculatorTest extends TestCase
{
    private $calculator;
    private $mock;

    protected function setUp(): void
    {
        $this->mock = new MockHandler();
        $handlerStack = HandlerStack::create($this->mock);
        $client = new Client(['handler' => $handlerStack]);

        $this->calculator = new CommissionCalculator($client);
    }

    /**
     * @throws GuzzleException
     */
    public function testCalculateCommissions()
    {
        $this->mock->append(new Response(200, [], json_encode(['country' => ['alpha2' => 'LT']])));
        $this->mock->append(new Response(200, [], json_encode(['rates' => ['EUR' => 1]])));

        $inputFileContent = '{"bin":"45717360","amount":"100.00","currency":"EUR"}' . "\n";
        $inputFilePath = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($inputFilePath, $inputFileContent);

        ob_start();
        $this->calculator->calculateCommissions($inputFilePath);
        $output = ob_get_clean();

        $this->assertEquals("1.00\n", $output);

        unlink($inputFilePath);
    }

    /**
     * @throws GuzzleException
     */
    public function testFetchCountryCodeFromBin()
    {
        $responseBody = json_encode(['country' => ['alpha2' => 'LT']]);
        $this->mock->append(new Response(200, [], $responseBody));

        $countryCode = $this->calculator->fetchCountryCodeFromBin('45717360');
        $this->assertEquals('LT', $countryCode);
    }

    public function testIsEuCountry()
    {
        $this->assertTrue($this->calculator->isEuCountry('LT'));
        $this->assertFalse($this->calculator->isEuCountry('US'));
    }

    /**
     * @throws GuzzleException
     */
    public function testFetchExchangeRate()
    {
        $responseBody = json_encode(['rates' => ['USD' => 1.2]]);
        $this->mock->append(new Response(200, [], $responseBody));

        $rate = $this->calculator->fetchExchangeRate('USD');
        $this->assertEquals(1.2, $rate);

        $rate = $this->calculator->fetchExchangeRate('EUR');
        $this->assertEquals(1, $rate);
    }

    public function testConvertAmountToEur()
    {
        $amountInEur = $this->calculator->convertAmountToEur(120, 'USD', 1.2);
        $this->assertEquals(100, $amountInEur);

        $amountInEur = $this->calculator->convertAmountToEur(100, 'EUR', 1);
        $this->assertEquals(100, $amountInEur);
    }

    public function testCalculateTransactionCommission()
    {
        $commission = $this->calculator->calculateTransactionCommission(100, true);
        $this->assertEquals(1.00, $commission);

        $commission = $this->calculator->calculateTransactionCommission(100, false);
        $this->assertEquals(2.00, $commission);
    }
}

