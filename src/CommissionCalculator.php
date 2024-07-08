<?php

namespace App;

require 'vendor/autoload.php';

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class CommissionCalculator
{
    const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR',
        'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PO', 'PT', 'RO',
        'SE', 'SI', 'SK'
    ];

    private Client $client;
    private Logger $logger;

    public function __construct(Client $client = null)
    {
        $this->client = $client ?? new Client();
        $this->logger = new Logger('TransactionProcessor');
        $this->logger->pushHandler(new StreamHandler('app.log', Logger::WARNING));
    }

    /**
     * @throws GuzzleException
     */
    public function calculateCommissions($filePath): void
    {
        $rows = explode("\n", file_get_contents($filePath));
        foreach ($rows as $row) {
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->warning("Invalid JSON: " . $row);
                continue;
            }

            if (empty($row)) {
                continue;
            }

            $transaction = json_decode($row, true);
            $bin = $transaction['bin'];
            $amount = (float)$transaction['amount'];
            $currency = $transaction['currency'];

            try {
                $countryCode = $this->fetchCountryCodeFromBin($bin);
                $isEu = $this->isEuCountry($countryCode);
                $rate = $this->fetchExchangeRate($currency);
                $amountInEur = $this->convertAmountToEur($amount, $currency, $rate);

                $commission = $this->calculateTransactionCommission($amountInEur, $isEu);
                echo number_format($commission, 2, '.', '') . "\n";
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function fetchCountryCodeFromBin($bin): ?string
    {
        $response = $this->client->get('https://lookup.binlist.net/' . $bin);
        if ($response->getStatusCode() !== 200) {
            throw new Exception('Error fetching BIN data');
        }
        $data = json_decode($response->getBody(), true);
        return $data['country']['alpha2'] ?? null;
    }

    public function isEuCountry($countryCode): bool
    {
        return in_array($countryCode, self::EU_COUNTRIES);
    }

    /**
     * @throws GuzzleException
     */
    public function fetchExchangeRate($currency): float
    {
        if ($currency == 'EUR') {
            return 1;
        }

        $response = $this->client->get('http://api.exchangeratesapi.io/latest?access_key=3b23be8e4c265b7bf519575b30ae7027');
        $data = json_decode($response->getBody(), true);
        return $data['rates'][$currency] ?? 0;
    }

    public function convertAmountToEur($amount, $currency, $rate): float
    {
        if ($currency == 'EUR') {
            return $amount;
        }

        return $rate > 0 ? $amount / $rate : 0;
    }

    public function calculateTransactionCommission($amount, $isEu): float
    {
        $rate = $isEu ? 0.01 : 0.02;
        return ceil($amount * $rate * 100) / 100;
    }
}
