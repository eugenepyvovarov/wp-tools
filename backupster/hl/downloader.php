<?php
require_once '../config.php';
require_once 'JobDaemon.class.php';
declare(ticks=1);
//A very basic job daemon that you can extend to your needs.
class FTP_download extends JobDaemon{

    public $maxProcesses = 2;
    
    public function job()
    {
        global $config;
        global $backup_config;
        
        
        if (!$link = mysql_connect($config['mysql_host'],$config['mysql_user'],$config['mysql_password'])) {
                $this->_error = 'Invalid db credentials';
                exit('Database connection failed');
        }
        mysql_select_db($config['mysql_database'],$link);
        
        $processID = getmypid();
            
        $result = mysql_query("UPDATE `jobs` SET locked_by = $processID WHERE status = 'new' AND  locked_by IS NULL LIMIT 1;", $link);
        
        if($ftp_connection = ftp_connect($backup_config['_ftp_server'], $backup_config['_ftp_port'])){
            $ftp_login  = ftp_login($ftp_connection, $backup_config['_ftp_user'], $backup_config['_ftp_password']);
            ftp_pasv($ftp_connection, true);
            
            $result = mysql_query("SELECT * FROM `jobs` WHERE locked_by = $processID LIMIT 1", $link);
            $file = mysql_fetch_assoc($result);

            $backup_folder = $this->current_job['folder'];
            
   	        if (!(is_dir($backup_folder.'/files/')))
               mkdir($backup_folder.'/files/','777',true);
   	        if (!(is_dir($backup_folder.'/files'.dirname($file['file']))))
               mkdir($backup_folder.'/files'.dirname($file['file']),'777',true);
               
            if($res = ftp_get($ftp_connection, $backup_folder.'/files'.$file['file'], $file['file'], FTP_BINARY)){
                $result = mysql_query("UPDATE `jobs` SET locked_by = NULL, status = 'in progress' WHERE locked_by = $processID LIMIT 1;", $link);
            }else{
                $result = mysql_query("UPDATE `jobs` SET locked_by = NULL WHERE locked_by = $processID LIMIT 1;", $link);
            }

            ftp_close($ftp_connection);

            
            mysql_close($link);
        }else{
            $result = mysql_query("UPDATE `jobs` SET locked_by = NULL WHERE locked_by = $processID LIMIT 1;", $link);
            mysql_close($link);
            exit('ftp connection failed');
        }
    }
}

$demon = new FTP_download();
$demon->run();