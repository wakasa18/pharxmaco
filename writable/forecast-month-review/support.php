<?php
// Local verification helpers. Export comments and quoted values are data only.
function monthSqlStatements(string $sql): array {
    $result=[];$buffer='';$quote=null;$length=strlen($sql);
    for($i=0;$i<$length;$i++){
        $ch=$sql[$i];
        if($quote!==null){$buffer.=$ch;if($ch==='\\'&&$quote!=='`'&&$i+1<$length)$buffer.=$sql[++$i];
            elseif($ch===$quote){if(($sql[$i+1]??'')===$quote)$buffer.=$sql[++$i];else $quote=null;}}
        elseif(in_array($ch,["'",'"','`'],true)){$quote=$ch;$buffer.=$ch;}
        elseif($ch==='#'||(substr($sql,$i,2)==='--'&&ctype_space($sql[$i+2]??' '))){$end=strpos($sql,"\n",$i);$i=$end===false?$length:$end;$buffer.=' ';}
        elseif(substr($sql,$i,2)==='/*'){$end=strpos($sql,'*/',$i+2);if($end===false)throw new RuntimeException('Unclosed SQL comment.');
            if(($sql[$i+2]??'')==='!'){$body=preg_replace('/^\d+\s*/','',substr($sql,$i+3,$end-$i-3));if(!preg_match('/^SET\s/i',$body)||str_contains($body,';'))throw new RuntimeException('Unexpected executable comment.');$buffer.=' '.$body.' ';}
            elseif(($sql[$i+2]??'')==='M')throw new RuntimeException('Unsupported executable comment.');$i=$end+1;}
        elseif($ch===';'){if(trim($buffer)!=='')$result[]=trim($buffer);$buffer='';}else $buffer.=$ch;
    }
    if($quote!==null)throw new RuntimeException('Unclosed SQL value.');if(trim($buffer)!=='')$result[]=trim($buffer);return $result;
}
function monthSqlSyntax(string $statement): string {
    $out='';$quote=null;
    for($i=0,$n=strlen($statement);$i<$n;$i++){$ch=$statement[$i];if($quote!==null){if($ch==='\\')$i++;elseif($ch===$quote){if(($statement[$i+1]??'')===$quote)$i++;else $quote=null;}}
        elseif($ch==="'"||$ch==='"'){$quote=$ch;$out.="''";}else $out.=$ch;}
    return $out;
}
function monthValidateExport(string $source,array $allowed): void {
    foreach(monthSqlStatements($source) as $statement){$syntax=monthSqlSyntax($statement);
        if(preg_match('/\b(?:OUTFILE|DUMPFILE|LOAD_FILE|SLEEP|BENCHMARK|GLOBAL|PERSIST|TRIGGER|PROCEDURE|FUNCTION|EVENT|DATABASE|SCHEMA)\b/i',$syntax))throw new RuntimeException('Unsafe export operation.');
        if(preg_match('/(?:`[^`]+`|\b[a-z_][a-z_0-9]*\b)\s*\.\s*(?:`[^`]+`|[a-z_][a-z_0-9]*)/i',$syntax))throw new RuntimeException('Cross-database identifier in export.');
        if(preg_match('/^(?:CREATE TABLE|ALTER TABLE|INSERT INTO)\s+`([a-z_]+)`(?=\s|\()/i',$statement,$m)){if(!in_array($m[1],$allowed,true))throw new RuntimeException('Unexpected export table.');}
        elseif(preg_match('/^SET\s+(?:SQL_MODE|time_zone|NAMES|@OLD_CHARACTER_SET_CLIENT|@OLD_CHARACTER_SET_RESULTS|@OLD_COLLATION_CONNECTION|CHARACTER_SET_CLIENT|CHARACTER_SET_RESULTS|COLLATION_CONNECTION)\b/i',$statement)){}
        elseif(!in_array(strtoupper($statement),['START TRANSACTION','COMMIT'],true))throw new RuntimeException('Unsupported export statement.');
    }
}
function monthExecute(mysqli $connection,array $statements,bool $diagnostics=false): array {
    $output=[];
    foreach($statements as $statement){$result=$connection->query($statement);if($result===false)throw new RuntimeException($connection->error);
        if($result instanceof mysqli_result){$output[]=$result->fetch_all(MYSQLI_ASSOC);$result->free();}
        if($diagnostics){$result=$connection->query('SELECT 1 AS client_diagnostic');$result->free();}}
    return $output;
}
function monthPmaStatements(string $sql): array {
    $directory='C:/UniServerZ/home/us_opt1';
    spl_autoload_register(static function(string $class)use($directory):void{$prefix='PhpMyAdmin\\SqlParser\\';if(!str_starts_with($class,$prefix))return;
        $file=$directory.'/vendor/phpmyadmin/sql-parser/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require_once $file;});
    $buffer=new \PhpMyAdmin\SqlParser\Utils\BufferedQuery($sql);$result=[];
    while(($statement=$buffer->extract(true))!==false)if(trim($statement)!=='')$result[]=trim($statement);
    return $result;
}
