<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class CashierFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!\App\Libraries\AccountSession::check()) {
            return \App\Libraries\AccountSession::rejection();
        }

        $role = session()->get('role');

        if (!in_array($role, ['admin', 'cashier'])) {
            return redirect()->to(site_url('login'))->with('error', 'Access denied.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
