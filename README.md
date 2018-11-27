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

概述
原生Percona版 PT-kill(Perl)工具只是单纯的KILL掉正在运行中的慢SQL，而不能作为一个监控工具使用，例如缺少邮件报警或者微信报警功能，固需要将其重构。

重构版 PT-kill(PHP)从information_schema.PROCESSLIST表中捕获正在运行中的SELECT|ALTER等DML/DDL消耗资源过多的查询，过滤它们，然后杀死它们（可选择不杀）且发邮件/微信报警给DBA和相关开发知悉，避免因慢SQL执行时间过长对数据库造成一定程度的伤害。（注：慢SQL执行完才记录到slow.log里，执行过程中不记录。）

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

以上是工具的使用方法和参数选项。
这里说下比较重要的参数：
1、--kill 如果想杀掉慢查询，那么在后面添加该选项；

2、--match-info 可以单独使用，也可以和--match-user结合一起使用；

3、--daemon 1 是开启后台守护进程，如果不添加该选择，可以用系统的crontab代替。
   该选项要和-I 10（秒）配合一起使用，即每休眠10秒监控一次。0为关闭后台守护进程。

4、--mail 为开启发送邮件报警，需先设置smtp_config.php，改成你自己的邮箱账号信息
smtp_config.php
<?php

    $content = nl2br(file_get_contents(dirname(__FILE__).'/kill.txt'));
    require_once "Smtp.class.php";

    //******************** 配置信息 ********************************
    $smtpserver = "smtp.126.com";//SMTP服务器
    $smtpserverport = 25;//SMTP服务器端口
    $smtpusermail = "chunyang_he@126.com";//SMTP服务器的用户邮箱
    $smtpemailto = 'chunyang_he@126.com';//发送给谁
    $smtpuser = "chunyang_he@126.com";//SMTP服务器的用户帐号，注：部分邮箱只需@前面的用户名
    $smtppass = "123456";//SMTP服务器的授权码
    $mailtitle = "警告！出现卡顿慢SQL，请及时优化处理！";//邮件主题
    $mailcontent = "<h1>".$content."</h1>";//邮件内容
    $mailtype = "HTML";//邮件格式（HTML/TXT）,TXT为文本邮件
    //************************ 配置信息 ****************************

?>

5、--weixin 为开启发送微信报警，需要先安装下simplejson-3.8.2.tar.gz
shell> tar zxvf simplejson-3.8.2.tar.gz
shell> cd simplejson-3.8.2
shell> python setup.py build
shell> python setup.py install

然后编辑pt-kill.php脚本
找到
$status1 = system("/usr/bin/python  wechat.py  'hcymysql' {$row['DB']}库出现卡顿慢SQL！ '{$content1}'");
将其'hcymysql'我的微信号换成你自己的即可。

微信企业号设置
移步https://www.cnblogs.com/linuxprobe/p/5717776.html 看此教程配置。

6、会在工具目录下生成kill.txt文件保存慢SQL。
shell> cat kill.txt
2018-11-27 16:41:22
用户名：root
来源IP：localhost
数据库名：hcy
执行时间：18
SQL语句：select sleep(60)

7、默认只杀连接中的慢SQL，保留会话连接，如果想把连接也杀掉，去掉QUERY
修改pt-kill.php
//$kill_sql = "KILL QUERY {$row['ID']}"; 
$kill_sql = "KILL {$row['ID']}";

具体演示请看“pt_kill演示录像.avi”

