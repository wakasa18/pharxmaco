<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class ReceiptInvoice extends BaseConfig
{
    // Fill with the seller's actual registration. Empty values remain unconfigured.
    public string $tin = '';
    public string $taxStatus = 'vat'; // vat or non_vat
    public float $vatRate = 12.0;

    // Use the registered series. serialEnd = 0 keeps the existing transaction reference.
    public string $serialPrefix = '';
    public int $serialStart = 1;
    public int $serialEnd = 0; // Enable only when the actual registered range is available.
    public int $serialDigits = 8;
}
