<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * Most rows a list page sends to its client-side DataTable. Long-growing lists
     * (products, logs, sales) are cut here so the page stays fast; the view shows a notice.
     */
    protected const TABLE_ROW_LIMIT = 3000;

    /**
     * @param array<int, mixed> $rows rows fetched with findAll(self::TABLE_ROW_LIMIT + 1)
     * @return array{0: array<int, mixed>, 1: bool} the rows to show and whether more existed
     */
    protected function capTableRows(array $rows): array
    {
        $truncated = count($rows) > self::TABLE_ROW_LIMIT;

        return [$truncated ? array_slice($rows, 0, self::TABLE_ROW_LIMIT) : $rows, $truncated];
    }

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');
    }
}
