<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** Compatibility entry point for previously configured schedules; no forecasts are written. */
class GenerateReorderForecast extends BaseCommand
{
    protected $group = 'Forecast';
    protected $name = 'forecast:reorder';
    protected $description = 'Retired reorder forecast command; performs no work.';
    protected $usage = 'forecast:reorder';

    public function run(array $params)
    {
        CLI::write('Reorder forecasting has been removed. Remove forecast:reorder from any configured schedule. No records were changed.');
    }
}
