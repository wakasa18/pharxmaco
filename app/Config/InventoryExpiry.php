<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/** Calendar-year range for entering new or changed product expiry dates. */
class InventoryExpiry extends BaseConfig
{
    /**
     * New or changed dates extend through 31 December of the current Philippine year
     * plus this value. Supported range: 1 through 100; invalid values use 10.
     * The same limit applies to every role; there is no per-save override.
     * Can also be set in the host's .env: inventoryexpiry.yearsAhead = 10
     */
    public int $yearsAhead = 10;
}
