<?php

namespace App\Controllers;

use App\Libraries\UserPresence;
use CodeIgniter\Controller;

/** Stateless, limited-purpose capabilities; no PHP session or login renewal. */
final class Presence extends Controller
{
    public function heartbeat()
    {
        if (!$this->request->is('post')) return $this->response->setStatusCode(405)->setHeader('Allow', 'POST');
        try {
            $service = new UserPresence(db_connect());
            if (!$service->enabled()) return $this->result(503, ['success' => false]);
            $valid = $service->heartbeat($this->request->getHeaderLine('X-Pharxmaco-Presence'));
            return $this->result($valid ? 200 : 401, ['success' => $valid]);
        } catch (\Throwable $error) {
            return $this->result(503, ['success' => false]);
        }
    }

    public function users()
    {
        if (!$this->request->is('get')) return $this->response->setStatusCode(405)->setHeader('Allow', 'GET');
        try {
            $service = new UserPresence(db_connect());
            if (!$service->enabled()) return $this->result(503, ['success' => false]);
            $identity = $service->authorize($this->request->getHeaderLine('X-Pharxmaco-Presence'));
            if (!$identity) return $this->result(401, ['success' => false]);
            if ($identity['user']['role'] !== 'admin') return $this->result(403, ['success' => false]);
            return $this->result(200, ['success' => true] + $service->summary());
        } catch (\Throwable $error) {
            return $this->result(503, ['success' => false]);
        }
    }

    private function result(int $status, array $data)
    {
        return $this->response->setStatusCode($status)->setHeader('Cache-Control', 'no-store, max-age=0')->setJSON($data);
    }
}
