<?php

/**
 * @author Arashi
 * @copyright 2012
 */

require_once('../config.php');

class FTP_read
{
    
    protected $current_job;
    protected $ftp_connection;
    protected $link;
    
    public function __construct()
    {
        if (is_file(sys_get_temp_dir() . '/current.job')) {
            $this->current_job = json_decode(file_get_contents(sys_get_temp_dir() . '/current.job'));
        }
    }
    
    public function set_tempfile()
    {
        file_put_contents(sys_get_temp_dir() . '/current.job', json_encode($this->current_job));
    }
    
    public function read_ftp()
    {
        global $config;
        global $backup_config;
        
        if($this->ftp_connection = ftp_connect($backup_config['_ftp_server'], $backup_config['_ftp_port'])){
            $ftp_login  = ftp_login($this->ftp_connection, $backup_config['_ftp_user'], $backup_config['_ftp_password']);
            ftp_pasv($this->ftp_connection, true);
            
            
            if (!$this->link = mysql_connect($config['mysql_host'],$config['mysql_user'],$config['mysql_password']))
                exit('Invalid db credentials');
            
            mysql_select_db($config['mysql_database'],$this->link);
            
            
            $this->_read($this->_ftp_folder);
            
            
            ftp_close($this->ftp_connection);
            
            $this->current_job['state'] = 'readed';
            
            $this->set_tempfile();
            
            mysql_close($this->link);
        }else{
            $this->_error = 'Can\'t connect to ftp server';
            return;
        } 
    }
   
    private function _read($remote_dir = ".")
    {
	    if ($remote_dir != ".") {
	        if (ftp_chdir($this->ftp_connection, $remote_dir) == false) {
	            $this->_error = "Change Dir Failed: $remote_dir<br />\r\n";
	            return;
	        }
	    }
         
	    $contents = ftp_nlist($this->ftp_connection, ".");
	    foreach ($contents as $file) {
        
	        if ($file == '.' || $file == '..')
	            continue;
 
	        if (@ftp_chdir($this->ftp_connection, $remote_dir.'/'.$file)) {
	            ftp_chdir ($this->ftp_connection, "..");
	            $this->_read($remote_dir.'/'.$file);
	        }
	        else{
                $result = mysql_query("INSERT INTO `backupster`.`jobs` (`id` ,`status`,`file`) VALUES (NULL , 'new', '".mysql_real_escape_string($file, $this->link)."');", $this->link);
            }
        }
	    ftp_chdir ($this->ftp_connection, "..");
	}
}

?>