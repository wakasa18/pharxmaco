<?php
// Run explicitly with the local PHP executable. Copies into an isolated database; never tests writes on the source.
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
use App\Libraries\CashierForeignKeys;
use App\Libraries\CashierShift;
use App\Libraries\DatabaseBackupService;

$settings = config(\Config\Database::class)->default;
if (!in_array($settings['hostname'], ['localhost','127.0.0.1','::1'], true)) throw new RuntimeException('Local MySQL required.');
$schema = 'phx_cashier_fk_review_' . getmypid();
if (!preg_match('/^phx_cashier_fk_review_[0-9]+$/D', $schema) || $schema === $settings['database']) throw new RuntimeException('Unsafe scratch name.');
$source = \Config\Database::connect($settings, false); $source->initialize();
$source->query('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
$connections = new ReflectionProperty(\CodeIgniter\Database\Config::class, 'instances'); $oldConnections = $connections->getValue();
$dir = WRITEPATH . 'cashier-fk-review'; if (!is_dir($dir)) mkdir($dir, 0777, true);
$checks = []; $assert = static function (bool $ok, string $name) use (&$checks): void { if (!$ok) throw new RuntimeException($name); $checks[] = $name; };
$quote = static fn ($name) => '`' . str_replace('`','``',$name) . '`';
$run = static function ($db, string $sql): array {
    $results = [];
    if (!$db->connID->multi_query($sql)) throw new RuntimeException('SQL import failed.');
    do {
        if ($result = $db->connID->store_result()) { $results = array_merge($results, $result->fetch_all(MYSQLI_ASSOC)); $result->free(); }
        if (!$db->connID->more_results()) break;
        if (!$db->connID->next_result()) throw new RuntimeException('SQL import continuation failed.');
    } while (true);
    $db->resetDataCache(); return $results;
};
$snapshot = static function ($db) use ($quote): array {
    $db->resetDataCache();
    $result = [];
    foreach ($db->listTables() as $table) {
        $rows = $db->query('SELECT * FROM ' . $quote($table))->getResultArray();
        $encoded = [];
        foreach ($rows as $row) { foreach ($row as &$value) if ($value !== null) $value = (string) $value; unset($value); $encoded[] = json_encode($row, JSON_THROW_ON_ERROR); }
        sort($encoded, SORT_STRING); $result[$table] = ['rows'=>count($rows), 'sha256'=>hash('sha256', implode("\n",$encoded))];
    }
    ksort($result); return $result;
};
$fkCount = static fn ($db) => (int)$db->query("SELECT COUNT(*) n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND (TABLE_NAME LIKE 'cashier\_%' OR TABLE_NAME='user_presence_sessions')")->getRowArray()['n'];
$rejected = static function (callable $work, string $contains): bool { try { $work(); } catch (Throwable $e) { if (str_contains($e->getMessage(), $contains)) return true; throw $e; } return false; };
try {
    $db = \Config\Database::connect(array_replace($settings,['database'=>$schema]),false); $db->initialize();
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach ($source->listTables() as $table) {
        $ddl = $source->query('SHOW CREATE TABLE ' . $quote($table))->getRowArray()['Create Table'];
        $db->query($ddl);
        $db->query('INSERT INTO ' . $quote($table) . ' SELECT * FROM ' . $quote($settings['database']) . '.' . $quote($table));
    }
    $db->query('SET FOREIGN_KEY_CHECKS=1');
    $connections->setValue(null,['tests'=>$db]);
    $import = file_get_contents(ROOTPATH . 'database/upgrades/2026-10-21-cashier-foreign-keys.sql');
    $assert($import === CashierForeignKeys::importSql(), 'Committed phpMyAdmin SQL matches migration definitions');
    // Allow rerunning this integration check after applying 000017 locally.
    foreach ($db->query("SELECT TABLE_NAME,CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND (TABLE_NAME LIKE 'cashier\_%' OR TABLE_NAME='user_presence_sessions')")->getResultArray() as $key) {
        $db->query('ALTER TABLE ' . $quote($key['TABLE_NAME']) . ' DROP FOREIGN KEY ' . $quote($key['CONSTRAINT_NAME']));
    }
    $db->table('migrations')->where('version','2026_10_21_000017')->where('namespace','App')->where('group','default')->delete();
    $db->query('ALTER TABLE cashier_events MODIFY sale_id BIGINT UNSIGNED NULL DEFAULT NULL');
    $event = $db->table('cashier_events')->get(1)->getRowArray();
    if (!$event) throw new RuntimeException('Local fixture needs one cashier event.');
    $db->table('cashier_events')->where('id',$event['id'])->update(['user_id'=>2147483000]);
    $bad = $snapshot($db);
    $assert($rejected(fn()=>CashierForeignKeys::apply($db),'preflight refused'), 'Native migration refuses orphan references before DDL');
    $failedImport = $run($db,$import);
    $assert($fkCount($db)===0 && $snapshot($db)===$bad && str_contains(json_encode($failedImport),'NOT COMPLETE'), 'phpMyAdmin preflight refusal preserves every row and adds no constraints/registration');
    $db->table('cashier_events')->where('id',$event['id'])->update(['user_id'=>$event['user_id']]);
    $db->query('ALTER TABLE cashier_drawers MODIFY branch_id BIGINT NOT NULL');
    $assert($rejected(fn()=>CashierForeignKeys::apply($db),'preflight refused') && $fkCount($db)===0, 'Mismatched branch key type is refused before DDL');
    $db->query('ALTER TABLE cashier_drawers MODIFY branch_id INT NOT NULL');
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    $assert($rejected(fn()=>CashierForeignKeys::apply($db),'preflight refused') && $fkCount($db)===0, 'Upgrade refuses disabled foreign-key checks');
    $db->query('SET FOREIGN_KEY_CHECKS=1');
    $db->query('ALTER TABLE cashier_login_sessions ADD CONSTRAINT wrong_delete_policy FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
    $assert($rejected(fn()=>CashierForeignKeys::apply($db),'preflight refused') && $fkCount($db)===1, 'Conflicting cascade policy is refused without replacing existing constraints');
    $db->query('ALTER TABLE cashier_login_sessions DROP FOREIGN KEY wrong_delete_policy');
    $db->query('ALTER TABLE cashier_login_sessions ADD CONSTRAINT existing_login_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT');
    $before = $snapshot($db); $oldMigrations = $db->table('migrations')->orderBy('id')->get()->getResultArray();
    $run($db,$import); $run($db,$import);
    require_once APPPATH . 'Database/Migrations/2026_10_21_000017_enforce_cashier_foreign_keys.php';
    $migration = new \App\Database\Migrations\EnforceCashierForeignKeys(new \CodeIgniter\Database\MySQLi\Forge($db));
    $migration->up(); $migration->up();
    $after = $snapshot($db); unset($before['migrations'],$after['migrations']);
    $assert($before===$after && $fkCount($db)===24, 'Repeated SQL and native migrations preserve all existing rows and enforce 24 links without duplicate FKs');
    $assert((int)$db->table('migrations')->where('version','2026_10_21_000017')->countAllResults()===1
        && array_slice($db->table('migrations')->orderBy('id')->get()->getResultArray(),0,count($oldMigrations))===$oldMigrations, 'Migration registration occurs once and prior registrations are preserved');
    $saleType = $db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sales' AND COLUMN_NAME='id'")->getRowArray()['COLUMN_TYPE'];
    $eventType = $db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cashier_events' AND COLUMN_NAME='sale_id'")->getRowArray()['COLUMN_TYPE'];
    $assert($saleType===$eventType, 'Cashier sale reference type and signedness match sales.id');

    $finishSql=str_replace('u315645644_pharxmacotest',$schema,file_get_contents(ROOTPATH.'database/upgrades/2026-10-21-finish-cashier-foreign-keys.sql'));
    $statusSql=str_replace('u315645644_pharxmacotest',$schema,file_get_contents(ROOTPATH.'database/upgrades/2026-10-21-cashier-upgrade-status.sql'));
    $db->table('migrations')->where('version','2026_10_21_000017')->where('namespace','App')->where('group','default')->delete();
    $beforeFinish=$snapshot($db);$finishResult=$run($db,$finishSql);$afterFinish=$snapshot($db);
    unset($beforeFinish['migrations'],$afterFinish['migrations']);
    $assert($beforeFinish===$afterFinish && str_contains(json_encode($finishResult),'Upgrade complete')
        && (int)$db->table('migrations')->where('version','2026_10_21_000017')->countAllResults()===1,
        'Completion-only import verifies 24 FKs and adds only missing migration registration');
    $finished=$snapshot($db);$run($db,$finishSql);
    $assert($finished===$snapshot($db),'Repeated completion-only import changes no records');
    $db->query('USE information_schema');$statusResult=$run($db,$statusSql);$finishResult=$run($db,$finishSql);$db->query('USE `'.$schema.'`');
    $assert((int)$statusResult[0]['migration_000017']===1 && (int)$statusResult[0]['cashier_foreign_keys']===24
        && str_contains(json_encode($finishResult),'Upgrade complete') && $finished===$snapshot($db),
        'Status and completion target the right schema even when information_schema is selected');
    $db->query('ALTER TABLE cashier_drawers DROP FOREIGN KEY fk_phx_drawer_last_shift');
    $db->table('migrations')->where('version','2026_10_21_000017')->where('namespace','App')->where('group','default')->delete();
    $incomplete=$snapshot($db);$finishResult=$run($db,$finishSql);
    $assert($incomplete===$snapshot($db) && str_contains(json_encode($finishResult),'NOT COMPLETE') && $fkCount($db)===23,
        'Completion refuses 23 FKs without altering tables or registering migration');
    CashierForeignKeys::apply($db);$run($db,$finishSql);
    $assert($fkCount($db)===24 && (int)$db->table('migrations')->where('version','2026_10_21_000017')->countAllResults()===1,
        'Optimized migration resumes one missing constraint and completion then succeeds');
    $db->query('ALTER TABLE cashier_login_sessions DROP FOREIGN KEY existing_login_fk');
    $db->query('ALTER TABLE cashier_login_sessions ADD CONSTRAINT wrong_completion_policy FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE');
    $finishResult=$run($db,$finishSql);
    $assert($fkCount($db)===24 && str_contains(json_encode($finishResult),'NOT COMPLETE'),
        'Completion checks FK definitions and refuses a 24-count schema containing a cascade policy');
    $db->query('ALTER TABLE cashier_login_sessions DROP FOREIGN KEY wrong_completion_policy');
    CashierForeignKeys::apply($db);$run($db,$finishSql);

    $branch = (int)$db->table('branches')->get(1)->getRowArray()['id'];
    $template = $db->table('users')->where('role','admin')->where('status','active')->get(1)->getRowArray(); unset($template['id']);
    $newUser = static function (string $username, string $role) use ($db,$template,$branch): int {
        $row = array_replace($template,['username'=>$username,'full_name'=>'FK Review User','role'=>$role,'branch_id'=>$branch]);
        $db->table('users')->insert($row); return (int)$db->insertID();
    };
    $admin=$newUser('fk-review-admin','admin'); $cashier1=$newUser('fk-review-cashier1','cashier'); $cashier2=$newUser('fk-review-cashier2','cashier');
    $db->table('cashier_drawers')->insert(['branch_id'=>$branch,'drawer_name'=>'FK review drawer','status'=>'active']); $drawer=(int)$db->insertID();
    $service=new CashierShift($db);
    $shift1=$service->open($cashier1,$drawer,'100',str_repeat('c',32));
    $service->cashMovement($cashier1,$branch,'cash_in','20','Review cash addition',str_repeat('d',32));
    $sale=$db->table('sales')->get(1)->getRowArray();unset($sale['id']);
    $sale=array_replace($sale,['invoice_no'=>'FK-REVIEW-SALE','user_id'=>$cashier1,'branch_id'=>$branch,'checkout_token_hash'=>null,'status'=>'completed']);
    $db->table('sales')->insert($sale);$saleId=(int)$db->insertID();
    $db->transBegin();$active=$service->requireActive($cashier1,$branch);$service->recordSale($active,$saleId);$db->transCommit();
    $summary=$service->summary($service->activeFor($cashier1));$expected=number_format($summary['expected_cash'],2,'.','');
    $service->close($shift1,$cashier1,$expected,'50','Handover test',$summary['revision'],$cashier2);
    $shift2=$service->open($cashier2,$drawer,'50',str_repeat('e',32));
    $summary2=$service->summary($service->activeFor($cashier2));
    $service->close($shift2,$cashier2,'50','50','Close test',$summary2['revision'],null);
    $assert((int)$db->table('cashier_shifts')->where('id',$shift1)->get()->getRowArray()['handover_accepted_shift_id']===$shift2, 'Real MySQL open, sale, cash movement, close and handover workflows work with enforced FKs');
    $db->table('cashier_shift_reviews')->insert(['shift_id'=>$shift1,'reviewed_by'=>$admin,'review_note'=>'Reviewed']);
    $eventId=(int)$db->table('cashier_events')->where('sale_id',$saleId)->get()->getRowArray()['id'];
    $db->table('cashier_event_reviews')->insert(['event_id'=>$eventId,'reviewed_by'=>$admin,'review_note'=>'Reviewed']);
    $db->table('cashier_held_sales')->insert(['hold_key'=>str_repeat('f',64),'user_id'=>$cashier1,'branch_id'=>$branch,'cart_json'=>'{}']);
    $db->table('cashier_login_sessions')->insert(['user_id'=>$cashier1,'token_hash'=>str_repeat('a',64),'account_fingerprint'=>str_repeat('b',64)]);
    $db->table('user_presence_sessions')->insert(['token_hash'=>str_repeat('f',64),'user_id'=>$cashier1,'account_fingerprint'=>str_repeat('b',64),'created_at'=>time(),'last_seen_at'=>time(),'expires_at'=>time()+7200]);
    foreach(CashierForeignKeys::definitions() as [, $table,$column]) {
        $pre=$snapshot($db);
        $assert($rejected(fn()=>$db->query("UPDATE `$table` SET `$column`=2147483000 LIMIT 1"),'foreign key constraint fails') && $snapshot($db)===$pre, "$table.$column rejects a nonexistent parent without modifying records");
    }
    $assert($rejected(fn()=>$db->query('DELETE FROM sales WHERE id='.$saleId),'foreign key constraint fails'), 'Sale referenced by cashier history cannot be deleted');
    $assert($rejected(fn()=>$db->query('DELETE FROM cashier_events WHERE id='.$eventId),'foreign key constraint fails'), 'Reviewed financial event cannot be cascade-deleted');
    $assert($rejected(fn()=>$db->query('DELETE FROM users WHERE id='.$cashier1),'foreign key constraint fails'), 'Referenced cashier account cannot be deleted');
    $db->table('cashier_events')->where('id',$eventId)->update(['shift_id'=>null]);
    $db->table('cashier_events')->where('id',$eventId)->update(['shift_id'=>$shift1]);
    $assert(true,'Optional null shift references remain supported');

    // Test the generic restore validator on a composite FK as well as the cashier links.
    $db->query('CREATE TABLE fk_review_parent (a INT, b INT, PRIMARY KEY(a,b)) ENGINE=InnoDB');
    $db->query('CREATE TABLE fk_review_child (id INT PRIMARY KEY, a INT NULL, b INT NULL, CONSTRAINT fk_review_composite FOREIGN KEY(a,b) REFERENCES fk_review_parent(a,b)) ENGINE=InnoDB');
    $db->query('INSERT INTO fk_review_parent VALUES(1,2)'); $db->query('INSERT INTO fk_review_child VALUES(1,1,2),(2,NULL,999)');
    $backupService=new DatabaseBackupService($db);
    $backupDir=$dir.'/backups';if(!is_dir($backupDir))mkdir($backupDir,0777,true);
    (new ReflectionProperty($backupService,'backupDirectory'))->setValue($backupService,$backupDir.'/');
    $backup=$backupService->createBackup('fk-review');$goodSnapshot=$snapshot($db);
    $db->table('users')->where('id',$admin)->update(['full_name'=>'Changed after backup']);
    $backupService->restoreBackup($backup['path']);
    $assert($goodSnapshot===$snapshot($db),'Backup round-trip preserves circular handovers, all FKs and composite nullable references');
    $lines=file($backup['path']);$changed=false;
    foreach($lines as $i=>$line){$record=json_decode($line,true);if(($record['table']??'')==='cashier_events'){ $record['row']['user_id']=2147483000;$lines[$i]=json_encode($record)."\n";$changed=true;break;}}
    if(!$changed)throw new RuntimeException('Missing corrupt-backup fixture');
    $badBackup=$backupDir.'/invalid-reference.pmbak';file_put_contents($badBackup,implode('',$lines));
    $assert($rejected(fn()=>$backupService->restoreBackup($badBackup),'invalid foreign-key reference') && $goodSnapshot===$snapshot($db)
        && (int)$db->query('SELECT @@FOREIGN_KEY_CHECKS n')->getRowArray()['n']===1,'Restore rejects invalid cashier references and rolls back all rows with FK checks restored');
    $lines=file($backup['path']);
    foreach($lines as $i=>$line){$record=json_decode($line,true);if(($record['table']??'')==='fk_review_child' && (int)$record['row']['id']===1){$record['row']['b']=999;$lines[$i]=json_encode($record)."\n";break;}}
    file_put_contents($badBackup,implode('',$lines));
    $assert($rejected(fn()=>$backupService->restoreBackup($badBackup),'invalid foreign-key reference') && $goodSnapshot===$snapshot($db),'Restore rejects invalid composite references without treating partial NULLs as orphans');

    $preReset=$snapshot($db);$run($db,file_get_contents(ROOTPATH.'database/upgrades/2026-10-16-clear-cashier-shifts.sql'));
    $postReset=$snapshot($db);
    foreach(['cashier_shifts','cashier_shift_actions','cashier_shift_reviews','cashier_drawers','cashier_events'] as $table)unset($preReset[$table],$postReset[$table]);
    $assert($preReset===$postReset && $db->table('cashier_shifts')->countAllResults()===0
        && $db->table('cashier_events')->where('shift_id IS NOT NULL',null,false)->countAllResults()===0,'Explicit reset clears circular handovers under FK enforcement while preserving sales and unrelated records');
    $run($db,file_get_contents(ROOTPATH.'database/upgrades/2026-10-16-clear-cashier-shifts.sql'));
    $assert($fkCount($db)===24,'Repeated explicit shift reset retains all 24 constraints');
    $result=['checks'=>$checks,'scratch_database_removed'=>true,'application_records_changed'=>false];
    file_put_contents($dir.'/mysql-results.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
    echo json_encode(['passed'=>count($checks),'application_records_changed'=>false],JSON_PRETTY_PRINT),PHP_EOL;
} catch (Throwable $error) {
    if (isset($db)) {
        $last=(string)$db->getLastQuery();
        if(str_starts_with($last,'SET @phx_fk_'))file_put_contents($dir.'/failed-metadata-query.sql',$last.";\n");
    }
    file_put_contents($dir.'/mysql-failure.json',json_encode(['passed_before_failure'=>$checks,'error'=>$error->getMessage()],JSON_PRETTY_PRINT)."\n");
    fwrite(STDERR,$error->getMessage()."\n"); $failed=true;
} finally {
    $connections->setValue(null,$oldConnections);
    if(isset($db))$db->close();
    $source->query('DROP DATABASE `'.$schema.'`'); $source->close();
}
if(!empty($failed)) exit(1);
