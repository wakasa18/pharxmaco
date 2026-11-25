<?php
require dirname(__DIR__,2).'/system/Test/bootstrap.php';
$settings=config(\Config\Database::class)->default;
if (!in_array($settings['hostname'],['localhost','127.0.0.1','::1'],true) || $settings['database']!=='pharxmaco_drugstore') throw new RuntimeException('Known local database required.');
$db=\Config\Database::connect('default');
echo json_encode(['users_schema'=>$db->query('SHOW CREATE TABLE users')->getRowArray()['Create Table'],
    'branches'=>$db->table('branches')->select('id, branch_name, status')->orderBy('id')->get()->getResultArray(),
    'existing_demo_accounts'=>$db->table('users')->select('id, username, role, branch_id, status')->whereIn('username',['demo_admin','demo_cashier1','demo_cashier2'])->get()->getResultArray()],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
