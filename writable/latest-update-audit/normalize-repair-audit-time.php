<?php
// Align only this repair's own audit timestamp with the application's timezone.
require dirname(__DIR__,2).'/system/Test/bootstrap.php';
$settings=config(\Config\Database::class)->default;
if (($settings['database']??'')!=='pharxmaco_drugstore'||!in_array($settings['hostname']??'',['localhost','127.0.0.1','::1'],true)) throw new RuntimeException('Known local database required.');
$result=json_decode(file_get_contents(__DIR__.'/stock-reconciliation-result.json'),true,512,JSON_THROW_ON_ERROR);
$backup=json_decode(file_get_contents($result['backup']),true,512,JSON_THROW_ON_ERROR);
$date=new DateTimeImmutable($backup['created_at']);
$db=\Config\Database::connect('default');$db->transException(true);$db->transBegin();
try {
    $rows=$db->query('SELECT id,activity,log_time FROM activity_logs WHERE activity LIKE ? FOR UPDATE',['%'.basename($result['backup']).'%'])->getResultArray();
    if (count($rows)!==1||!str_contains($rows[0]['activity'],hash_file('sha256',$result['backup']))) throw new RuntimeException('Unique matching repair audit required.');
    $target=$date->setTimezone(new DateTimeZone('Asia/Manila'))->format('Y-m-d H:i:s');
    if ($rows[0]['log_time']!==$target) {
        if ($rows[0]['log_time']!==$date->format('Y-m-d H:i:s')) throw new RuntimeException('Unexpected repair timestamp.');
        if (!$db->table('activity_logs')->where('id',$rows[0]['id'])->update(['log_time'=>$target])) throw new RuntimeException('Audit timestamp correction failed.');
    }
    if (!$db->transStatus()||!$db->transCommit()) throw new RuntimeException('Audit timestamp commit failed.');
    echo "Repair audit timestamp verified in Asia/Manila.\n";
} catch (Throwable $error) {$db->transRollback();throw $error;}
