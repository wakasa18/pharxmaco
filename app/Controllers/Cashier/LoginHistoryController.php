<?php

namespace App\Controllers\Cashier;

use App\Controllers\BaseController;
use App\Libraries\AccountSession;
use App\Libraries\AuthenticationAudit;

class LoginHistoryController extends BaseController
{
    private const ROW_LIMIT = 1000;

    public function index()
    {
        $this->response->removeHeader('Cache-Control')->setHeader('Cache-Control', 'private, no-store, max-age=0');
        if (!AccountSession::check()) return AccountSession::rejection();
        if (session('role') !== 'cashier') {
            return $this->response->setStatusCode(403)->setBody('Cashier access required.');
        }

        $error = '';
        $dates = [];
        foreach (['date_from', 'date_to'] as $field) {
            $raw = $this->request->getGet($field) ?? '';
            $dates[$field] = is_string($raw) ? $raw : '';
            $validFormat = is_string($raw) && preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', $raw);
            $date = $validFormat ? \DateTimeImmutable::createFromFormat('!Y-m-d', $raw) : false;
            if ($raw !== '' && (!$date || $date->format('Y-m-d') !== $raw)) {
                $error = 'Enter valid dates in the From and To fields.';
                $dates[$field] = '';
            }
        }
        if ($error === '' && $dates['date_from'] !== '' && $dates['date_to'] !== '' && $dates['date_from'] > $dates['date_to']) {
            $error = 'The From date must be on or before the To date.';
        }

        $records = [];
        $truncated = false;
        if ($error === '') {
            // The account ID always comes from the validated session, never the URL or form.
            $query = db_connect()->table('activity_logs')->select('id, activity, log_time')
                ->where('user_id', (int) session('user_id'))->like('activity', 'Authentication: ', 'after');
            if ($dates['date_from'] !== '') $query->where('log_time >=', $dates['date_from'] . ' 00:00:00');
            if ($dates['date_to'] !== '') $query->where('log_time <=', $dates['date_to'] . ' 23:59:59');
            $rows = $query->orderBy('log_time', 'DESC')->orderBy('id', 'DESC')->get(self::ROW_LIMIT + 1)->getResultArray();
            $truncated = count($rows) > self::ROW_LIMIT;
            foreach (array_slice($rows, 0, self::ROW_LIMIT) as $row) {
                $details = AuthenticationAudit::details((string) $row['activity']);
                if ($details !== null) $records[] = $details + ['log_time' => $row['log_time']];
            }
        }

        return $this->response->setStatusCode(200)->setBody(view('cashier/login_history', [
            'title' => 'My Login History', 'records' => $records, 'rowLimit' => self::ROW_LIMIT,
            'tableTruncated' => $truncated, 'dateFrom' => $dates['date_from'], 'dateTo' => $dates['date_to'],
            'dateRangeError' => $error,
        ]));
    }
}
