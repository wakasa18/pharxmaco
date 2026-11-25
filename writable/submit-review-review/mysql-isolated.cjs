const fs=require('node:fs'),path=require('node:path'),net=require('node:net'),{spawn}=require('node:child_process');
const root=path.resolve(process.cwd()),dir=path.resolve(__dirname,'mysql-scratch-'+Date.now());
const binary='C:/UniServerZ/core/mysql/bin/mysqld_z.exe',basedir='C:/UniServerZ/core/mysql';
const php='C:/UniServerZ/core/php83/php.exe',delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
let server,port;
function run(command,args,env=process.env){return new Promise((resolve,reject)=>{const p=spawn(command,args,{cwd:root,env,windowsHide:true,stdio:['ignore','pipe','pipe']});let out='',err='';p.stdout.on('data',chunk=>out+=chunk);p.stderr.on('data',chunk=>err+=chunk);p.on('error',reject);p.on('exit',code=>code===0?resolve(out):reject(Error(command+' failed: '+err+' '+out)));});}
(async()=>{
 fs.mkdirSync(dir,{recursive:false});
 await run(binary,['--no-defaults','--initialize-insecure','--basedir='+basedir,'--datadir='+dir,'--log-error='+path.join(dir,'init.log')]);
 fs.writeFileSync(path.join(dir,'init-users.sql'), "CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY '';\nGRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;\n");
 const probe=net.createServer();await new Promise(resolve=>probe.listen(0,'127.0.0.1',resolve));port=probe.address().port;await new Promise(resolve=>probe.close(resolve));
 server=spawn(binary,['--no-defaults','--basedir='+basedir,'--datadir='+dir,'--port='+port,'--bind-address=127.0.0.1','--mysqlx=0','--skip-log-bin','--skip-name-resolve','--init-file='+path.join(dir,'init-users.sql'),'--log-error='+path.join(dir,'server.log')],{cwd:root,windowsHide:true,stdio:'ignore'});
 let ready=false;for(let i=0;i<300;i++){if(server.exitCode!==null)throw Error('Isolated MySQL exited: '+fs.readFileSync(path.join(dir,'server.log'),'utf8'));if(fs.existsSync(path.join(dir,'server.log'))&&fs.readFileSync(path.join(dir,'server.log'),'utf8').includes('ready for connections')){ready=true;break;}await delay(100);}
 if(!ready)throw Error('Isolated MySQL startup timed out.');
 const result=await run(php,['writable/cashier-session-review/mysql-check.php'],{...process.env,PHX_LOGIN_REVIEW_MYSQL_PORT:String(port)});
 process.stdout.write(result);
})().catch(error=>{console.error(error.message);process.exitCode=1;}).finally(async()=>{
 if(server&&server.exitCode===null&&server.signalCode===null){try{await run('C:/UniServerZ/core/mysql/bin/mysqladmin.exe',['--no-defaults','--protocol=tcp','--host=127.0.0.1','--port='+port,'--user=root','shutdown']);}catch{server.kill();}for(let i=0;i<100&&server.exitCode===null&&server.signalCode===null;i++)await delay(100);}
 // Only this run's verified workspace directory is removed after its server has stopped.
 const target=path.resolve(dir),workspace=root+path.sep;
 const portClosed=!port||await new Promise(resolve=>{const socket=net.createConnection({host:'127.0.0.1',port});let done=false;const finish=value=>{if(done)return;done=true;socket.destroy();resolve(value);};socket.once('connect',()=>finish(false));socket.once('error',()=>finish(true));socket.setTimeout(500,()=>finish(false));});
 if(target.startsWith(workspace)&&path.basename(target).startsWith('mysql-scratch-')&&portClosed&&(!server||server.exitCode!==null||server.signalCode!==null))fs.rmSync(target,{recursive:true,force:true});
 else if(!portClosed){console.error('Temporary MySQL is still listening; its data directory was retained.');process.exitCode=1;}
});
