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
