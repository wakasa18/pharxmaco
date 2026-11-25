<?php

namespace App\Controllers;

use CodeIgniter\Controller;

/** A read-only clock sample; polling must not renew authenticated sessions. */
final class ServerTime extends Controller
{
    public function index()
    {
        if (!$this->request->is('get')) {
            return $this->response->setStatusCode(405)->setHeader('Allow', 'GET');
        }

        return $this->response
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('Expires', '0')
            ->setJSON([
                'server_time_ms' => (int) floor(microtime(true) * 1000),
                'timezone' => 'Asia/Manila',
            ]);
    }
}
