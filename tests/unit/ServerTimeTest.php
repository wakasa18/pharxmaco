<?php

use App\Controllers\ServerTime;
use CodeIgniter\Test\CIUnitTestCase;

final class ServerTimeTest extends CIUnitTestCase
{
    private function controller(string $method): ServerTime
    {
        \Config\Services::resetSingle('response'); // Each controller request starts with a fresh HTTP response.
        $request = new \CodeIgniter\HTTP\IncomingRequest(new \Config\App(), new \CodeIgniter\HTTP\SiteURI(new \Config\App()), null, new \CodeIgniter\HTTP\UserAgent());
        $request->setMethod($method);
        $controller = new ServerTime();
        $controller->initController($request, service('response'), service('logger'));
        return $controller;
    }

    public function testAnonymousClockSampleUsesServerEpochAndCannotBeCached(): void
    {
        $before = (int) floor(microtime(true) * 1000);
        $response = $this->controller('GET')->index();
        $after = (int) ceil(microtime(true) * 1000);
        $data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertIsInt($data['server_time_ms']);
        $this->assertGreaterThanOrEqual($before, $data['server_time_ms']);
        $this->assertLessThanOrEqual($after, $data['server_time_ms']);
        $this->assertSame('Asia/Manila', $data['timezone']);
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));
        $this->assertSame('no-cache', $response->getHeaderLine('Pragma'));
        $this->assertSame('0', $response->getHeaderLine('Expires'));
    }

    public function testClockRouteIsGetOnlyAndDoesNotUseAccountOrSessionFilters(): void
    {
        $routes = service('routes');
        $routes->loadRoutes();
        $this->assertArrayHasKey('server-time', $routes->getRoutes('GET'));
        $this->assertArrayNotHasKey('server-time', $routes->getRoutes('POST'));
        $this->assertSame(['server-time', 'presence/heartbeat', 'presence/users'], config('Filters')->globals['before']['csrf']['except']);
        // Check the resolved global filters, not just the declared exception.
        $filters = service('filters');
        $filters->reset();
        $filters->initialize('server-time');
        $this->assertNotContains(\CodeIgniter\Filters\CSRF::class, array_column($filters->getFiltersClass()['before'], 0));
        $this->assertNotContains(\App\Filters\AuthFilter::class, array_column($filters->getFiltersClass()['before'], 0));
        $filters->reset();
        $filters->initialize('logout');
        $this->assertContains(\CodeIgniter\Filters\CSRF::class, array_column($filters->getFiltersClass()['before'], 0));
        $this->assertSame(405, $this->controller('POST')->index()->getStatusCode());
        $this->assertSame('GET', service('response')->getHeaderLine('Allow'));
    }
}
