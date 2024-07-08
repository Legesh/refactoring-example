<?php
namespace App;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class TransactionProcessor
{
    private Client $client;
    private Logger $logger;

    public function __construct()
    {
        $this->client = new Client();
        $this->logger = new Logger('TransactionProcessor');
        $this->logger->pushHandler(new StreamHandler('app.log', Logger::WARNING));
    }

    public function process($filePath): void
    {
        $transactions = explode("\n", file_get_contents($filePath));
        foreach ($transactions as $row) {
            if (empty($row)) {
                continue;
            }
            $transaction = json_decode($row, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->warning("Invalid JSON: " . $row);
                continue;
            }

            $bin = $transaction['bin'];
            $amount = $transaction['amount'];
            $currency = $transaction['currency'];

            try {
                $binData = $this->fetchBinData($bin);
                $isEu = $this->isEu($binData->country->alpha2);
                $rate = $this->fetchExchangeRate($currency);

                if ($currency == 'EUR' || $rate == 0) {
                    $amountFixed = $amount;
                } else {
                    $amountFixed = $amount / $rate;
                }

                $commission = ceil($amountFixed * ($isEu ? 0.01 : 0.02) * 100) / 100;
                echo $commission . "\n";

            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    private function fetchBinData($bin)
    {
        $response = $this->client->get('https://lookup.binlist.net/' . $bin);
        if ($response->getStatusCode() !== 200) {
            throw new Exception('Error fetching BIN data');
        }
        return json_decode($response->getBody());
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    private function fetchExchangeRate($currency)
    {
        $response = $this->client->get('https://api.exchangeratesapi.io/latest');
        if ($response->getStatusCode() !== 200) {
            throw new Exception('Error fetching exchange rate');
        }
        $rates = json_decode($response->getBody(), true)['rates'];
        return $rates[$currency] ?? 0;
    }

    private function isEu($countryCode): bool
    {
        $euCountries = [
            'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR',
            'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PO', 'PT', 'RO',
            'SE', 'SI', 'SK'
        ];
        return in_array($countryCode, $euCountries);
    }
}
