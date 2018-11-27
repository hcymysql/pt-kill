<?php

/**
 * Percona PT-kill重构版(PHP)
 * https://github.com/hcymysql/pt-kill
 *
 * UPDATE:
 * Modified by: hcymysql 2018/11/27
 * 1、增加慢SQL邮件报警功能
 * 2、增加慢SQL微信报警功能
 *
 * 环境准备: 
 * shell> yum install -y php-process php php-mysql
 *  
 */

ini_set('date.timezone','Asia/Shanghai');
error_reporting(7);

function Usage(){
echo "\e[38;5;11m
Usage:
  Options:
  -u  username
  -p  password
  -h  host ip
  -P  port
  -B  busytime time seconds 设置慢SQL执行时间触发报警
  -I  interval time seconds 设置守护进程下间隔监测时间
  --kill 如果想杀掉慢查询，加上该选项。
  --match-info 匹配杀掉SELECT|INSERT|UPDATE语句
  --match-user 匹配杀掉的用户
  --daemon 1开启后台守护进程，0关闭后台守护进程
  --mail 开启发送邮件报警
  --weixin 开启发送微信报警
  --help  Help

Example :
   前台运行
   shell> php pt-kill.php -u admin -p 123456 -h 10.10.159.31 -P 3306 -B 10  --match-info='select|alter' --match-user='dev' --kill --mail --weixin

   后台运行
   shell> php pt-kill.php -u admin -p 123456 -h 10.10.159.31 -P 3306 -B 10  -I 15 --match-info='select|alter' --match-user='dev' --kill --mail --weixin --daemon 1
   	   
   关闭后台运行
   shell> php pt-kill.php --daemon 0
\e[0m" .PHP_EOL;
}

$shortopts  = "u:";
$shortopts .= "p:";
$shortopts .= "h:";
$shortopts .= "P:";
$shortopts .= "B:";
$shortopts .= "I:";

$longopts  = array(
    "kill",
    "match-info::",
    "match-user::",
    "daemon:",
    "mail",
    "weixin",
    "help",
);

$options = getopt($shortopts, $longopts);

if(empty($options) || isset($options['help'])){
	Usage();
        exit;
}

if(!isset($options['daemon'])){
	Slowsql();
	exit;
}
else{
	if($options['daemon'] != 0){
		if(!isset($options['I'])){
			die("请设置间隔时间 -I 5（单位秒）"."\n");
		}
	}
	$deamon = new Daemon($options['I']);
        $deamon->run($options['daemon']);
}

function Slowsql(){

global $options;

$con = mysqli_connect("{$options['h']}","{$options['u']}","{$options['p']}","information_schema","{$options['P']}") 
		      or die("数据库链接错误"."\n".mysqli_error($con));
mysqli_query($con,"set names utf8");

if(isset($options['match-info'])){
	$get_match_info = "SELECT ID,USER,HOST,DB,TIME,COMMAND,STATE,INFO FROM information_schema.PROCESSLIST 
					   WHERE TIME >= '{$options['B']}' AND INFO REGEXP '{$options['match-info']}'";
	$result = mysqli_query($con,$get_match_info);
}
else if(isset($options['match-user'])){
        echo "\e[38;5;196m没有匹配到SELECT|INSERT|UPDATE|DELETE等DML/DDL语句，退出主程序。\e[0m"."\n"; exit;
}
else if(isset($options['match-info']) && isset($options['match-user'])){
	$get_match_user_info = "SELECT ID,USER,HOST,DB,TIME,COMMAND,STATE,INFO FROM information_schema.PROCESSLIST 
					        WHERE TIME >= '{$options['B']}' AND (INFO REGEXP '{$options['match-info']}'
					        AND USER REGEXP '{$options['u']}')";
	$result = mysqli_query($con,$get_match_user_info);
}
else{ echo "\e[38;5;196m没有匹配到SELECT|INSERT|UPDATE|DELETE等DML/DDL语句，退出主程序。\e[0m"."\n\n"; exit;}

$rowcount=mysqli_num_rows($result);
if ($rowcount == 0){
        if(file_exists(dirname(__FILE__)."/kill.txt")){
			rename(dirname(__FILE__)."/kill.txt","kill_".date('Y-m-d_H:i:s')."_history.txt");
			//file_put_contents(dirname(__FILE__).'/kill.txt',"");
        }
	echo "\e[38;5;10m".date('Y-m-d H:i:s')."	未检测出当前执行中的卡顿慢SQL。\e[0m" .PHP_EOL;
}
else{
	echo "\n";
	echo "\e[38;5;196m".date('Y-m-d H:i:s')."	警告！出现卡顿慢SQL，请及时排查问题。\e[0m" .PHP_EOL;
	while($row = mysqli_fetch_array($result)){
		file_put_contents(dirname(__FILE__).'/kill.txt', date('Y-m-d H:i:s')."\n".
		"用户名：".$row['USER']."\n".
		"来源IP：".$row['HOST']."\n".
		"数据库名：".$row['DB']."\n".
		"执行时间：".$row['TIME']."\n".
		"SQL语句：".$row['INFO']."\n".
		"\n", FILE_APPEND);
		
		if(isset($options['kill'])){
			echo "\e[38;5;11m自动杀死执行时间超过{$options['B']}秒的慢SQL\e[0m" .PHP_EOL;
			$kill_sql = "KILL QUERY {$row['ID']}"; //默认只杀连接中的慢SQL，保留会话连接，如果想把连接也杀掉，去掉QUERY
			//$kill_sql = "KILL {$row['ID']}"; 
			mysqli_query($con,$kill_sql);
		}	

    //调用发邮件对象
    if(isset($options['mail'])){
    $content = nl2br(file_get_contents(dirname(__FILE__).'/kill.txt'));
    require "smtp_config.php";
    $smtp = new Smtp($smtpserver,$smtpserverport,true,$smtpuser,$smtppass);//这里面的一个true是表示使用身份验证,否则不使用身份验证.
    $smtp->debug = false;//是否显示发送的调试信息
    $status = $smtp->sendmail($smtpemailto, $smtpusermail, $mailtitle, $mailcontent, $mailtype);

    if($status==""){
        echo "对不起，邮件发送失败！请检查邮箱填写是否有误。";
    }else{
    	echo "恭喜！邮件发送成功！！" .PHP_EOL;}
    }

    //调用发微信wechat.py脚本
    if(isset($options['weixin'])){
    $content1 = file_get_contents(dirname(__FILE__).'/kill.txt');
    $status1 = system("/usr/bin/python  wechat.py  'hcymysql' {$row['DB']}库出现卡顿慢SQL！ '{$content1}'");

    if($status1==""){
        echo "对不起，微信发送失败！请检查wechat.py脚本设置是否有误。";
    }else{
        echo "恭喜！微信发送成功！！" .PHP_EOL;}
    }
    }
    }	
}

class Daemon {
    private $pidfile;
    private $sleep_time;

    function __construct($st) {
        $this->pidfile = dirname(__FILE__).'/pt-kill.pid';
	$this->sleep_time = $st;
    }
 
    private function startDeamon() {
        if (file_exists($this->pidfile)) {
            echo "The file $this->pidfile exists." . PHP_EOL;
            exit();
       }
   
       $pid = pcntl_fork();
       if ($pid == -1) {
            die('could not fork\n');
       } else if ($pid) {
           echo 'start ok' . PHP_EOL;
           exit($pid);
       } else {
           file_put_contents($this->pidfile, getmypid());
           return getmypid();
        }
    }
 
    private function start(){
        $pid = $this->startDeamon();
        while (true) {
	    Slowsql();
            sleep($this->sleep_time);
        }
    }
 
    private function stop(){
        if (file_exists($this->pidfile)) {
           $pid = file_get_contents($this->pidfile);
           posix_kill($pid, 9); 
           unlink($this->pidfile);
        }
    }
 
    public function run($param) {
        if($param == 1) {
            $this->start();
        }else if($param == 0) {
            $this->stop();
	    echo "pt-kill.php后台守护进程已停止。". PHP_EOL;
        }
	else{
            echo 'daemon传参错误，请输入0关闭后台进程，1开启后台线程。'. PHP_EOL;
        }
    }
 
}

?>

