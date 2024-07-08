<?php

require 'vendor/autoload.php';

use App\CommissionCalculator;

if ($argc < 2) {
    echo "Usage: php calculate_commissions.php <input_file_path>\n";
    exit(1);
}

$filePath = $argv[1];
$calculator = new CommissionCalculator();
$calculator->calculateCommissions($filePath);