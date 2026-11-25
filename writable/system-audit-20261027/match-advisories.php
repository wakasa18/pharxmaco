<?php
require 'phar://' . getcwd() . '/composer.phar/vendor/autoload.php';
$lock=json_decode(file_get_contents('composer.lock'), true, 512, JSON_THROW_ON_ERROR);
$packages=array_column($lock['packages'], 'version', 'name');
$packages['codeigniter4/framework']='4.7.0';
$raw=preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents('writable/system-audit-20261027/packagist-advisories.json'));
$data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
$matches=[];
foreach($packages as $name=>$version) foreach($data['advisories'][$name]??[] as $item) {
 if (Composer\Semver\Semver::satisfies($version,$item['affectedVersions'])) $matches[]=['package'=>$name,'installed'=>$version,'advisory'=>$item['advisoryId'],'title'=>$item['title'],'affected'=>$item['affectedVersions'],'link'=>$item['link'],'severity'=>$item['severity']??null,'reported'=>$item['reportedAt']??null];
}
file_put_contents('writable/system-audit-20261027/dependency-matches.json',json_encode(['packages_checked'=>$packages,'matches'=>$matches],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
echo json_encode($matches,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
