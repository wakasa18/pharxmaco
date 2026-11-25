<?php
// Explicitly authorized local aggregate repair. Branch quantities are the source of truth.
require dirname(__DIR__,2).'/system/Test/bootstrap.php';
$settings=config(\Config\Database::class)->default;
if (($settings['database']??'')!=='pharxmaco_drugstore'
    || !in_array($settings['hostname']??'',['localhost','127.0.0.1','::1'],true)) throw new RuntimeException('Known local database required.');
$db=\Config\Database::connect('default');
$now=new DateTimeImmutable('now',new DateTimeZone('Asia/Manila'));
$db->transException(true);$db->transBegin();
try {
    $actor=$db->query("SELECT id FROM users WHERE role='admin' AND status='active' ORDER BY id LIMIT 1")->getRowArray();
    if (!$actor) throw new RuntimeException('An active administrator is required for the audit trail.');
    $branchRows=$db->query('SELECT id,product_id,branch_id,stock FROM branch_products ORDER BY id FOR UPDATE')->getResultArray();
    $products=$db->query('SELECT * FROM products ORDER BY id FOR UPDATE')->getResultArray();
    $totals=[];foreach ($branchRows as $row) $totals[$row['product_id']]=($totals[$row['product_id']]??0)+(int)$row['stock'];
    $changes=[];$before=[];
    foreach ($products as $row) {
        $total=$totals[$row['id']]??0;
        if ((int)$row['stock']===$total) continue;
        $before[]=$row;
        $changes[]=['product_id'=>(int)$row['id'],'before'=>(int)$row['stock'],'after'=>$total];
    }
    if ($changes) {
        $backupDir=WRITEPATH.'backups/';if (!is_dir($backupDir)&&!mkdir($backupDir,0770,true)) throw new RuntimeException('Backup directory unavailable.');
        $backup=$backupDir.'stock-reconciliation-'.$now->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
        $payload=['database'=>$settings['database'],'created_at'=>$now->format('c'),'products_before'=>$before,'branch_stock'=>$branchRows,'changes'=>$changes];
        $json=json_encode($payload,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
        if (file_put_contents($backup,$json,LOCK_EX)!==strlen($json)) throw new RuntimeException('Backup could not be saved.');
        foreach ($changes as $change) {
            if (!$db->table('products')->where('id',$change['product_id'])->set('stock',$change['after'])->set('updated_at','updated_at',false)->update()) throw new RuntimeException('Stock repair failed.');
        }
        $audit='2026-10-16 authorized master-stock reconciliation from existing branch balances. Branch stock and product timestamps preserved. '
            .json_encode(['backup'=>basename($backup),'sha256'=>hash_file('sha256',$backup),'changes'=>$changes],JSON_THROW_ON_ERROR);
        if (!$db->table('activity_logs')->insert(['user_id'=>$actor['id'],'activity'=>$audit,'log_time'=>$now->format('Y-m-d H:i:s')])) throw new RuntimeException('Audit could not be saved.');
    }
    $remaining=(int)$db->query('SELECT COUNT(*) n FROM products p LEFT JOIN (SELECT product_id,SUM(stock) qty FROM branch_products GROUP BY product_id)b ON b.product_id=p.id WHERE p.stock<>COALESCE(b.qty,0)')->getRowArray()['n'];
    if ($remaining || !$db->transStatus()) throw new RuntimeException('Reconciliation verification failed.');
    $db->transCommit();
    $result=['reconciled'=>count($changes),'remaining_mismatches'=>$remaining,'changes'=>$changes,'backup'=>$backup??null];
    file_put_contents(__DIR__.'/stock-reconciliation-result.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {$db->transRollback();throw $error;}
