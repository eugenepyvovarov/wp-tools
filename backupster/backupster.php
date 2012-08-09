<?php

/**
 * @author Arashi
 * @copyright 2012
 */

class Backupster{

    private $_project_name = '';
    
    private $_ftp_user = '';
    private $_ftp_password = '';
    private $_ftp_server = '';
    private $_ftp_folder = '';
    private $_ftp_port = '';
    private $_ftp_connection = '';
    
    private $_db_user = '';
    private $_db_password = '';
    private $_db_host = '';
    private $_db_name = '';
    private $_db_tables = '';
    
    private $_backup_folder = '';
    
    private $_error = '';

    public function __construct($project_name = '', $ftp_server = '', $ftp_folder = '', $ftp_user = '', $ftp_password = '', $db_host = '', $db_name = '', $db_user = '', $db_password = '', $backup_folder = '')
    {
        $this->_project_name = $project_name;

        $this->_ftp_user = $ftp_user;
        $this->_ftp_password = $ftp_password;
        $this->_ftp_server = $ftp_server;
        $this->_ftp_port = 21;
        $this->_ftp_folder = $ftp_folder;
        
        $this->_db_user = $db_user;
        $this->_db_password = $db_password;
        $this->_db_host = $db_host;
        $this->_db_name = $db_name;
        
        if($backup_folder)
            $this->$_backup_folder = $backup_folder;
        
        $this->_set_strings();
    }
    
    public function set_param($param, $value)
    {
        $prop = '$_'.$param;
        if (isset($this->$$prop)) {
            $this->$$prop = $value;
        }
    }
    
    public function get_param($param)
    {
        $prop = '$_'.$param;
        if (isset($this->$$prop)) {
            return $this->$$prop;
        }
    }
    
    public function show_errors()
    {
       echo($this->_error);
       return;
    }
    
    private function _set_strings()
    {
        if($this->_backup_folder)
            return;
        if($this->_project_name){
            $folder = './backups/'.$this->_project_name.'_'.date('mdY');
        }else{
            $folder = './backups/job_'.date('mdY');
        }
        if(!is_dir('./backups'))
            mkdir('./backups');
        if(!is_dir($folder.'/files'))
            mkdir($folder.'/files', '777', true);
        if(!is_dir($folder.'/db'))
            mkdir($folder.'/db', '777', true);
        $this->_backup_folder = $folder;
    }
    
    /* backup the db OR just a table */
    public function backup_tables()
    {
      $host = $this->_db_host;
      $user = $this->_db_user;
      $pass = $this->_db_password;
      $name = $this->_db_name;
      
      $tables = '*';
      if($this->_db_tables)
        $tables = $this->_db_tables;
      if (!$link = mysql_connect($host,$user,$pass)) {
        $this->_error = 'Invalid db credentials';
        return;
      }
      mysql_select_db($name,$link);
      
      //get all of the tables
      if($tables == '*')
      {
        $tables = array();
        $result = mysql_query('SHOW TABLES');
        while($row = mysql_fetch_row($result))
        {
          $tables[] = $row[0];
        }
      }
      else
      {
        $tables = is_array($tables) ? $tables : explode(',',$tables);
      }
      $return = '';
      //cycle through
      foreach($tables as $table)
      {
        $result = mysql_query('SELECT * FROM '.$table);
        $num_fields = mysql_num_fields($result);
        
        $return.= 'DROP TABLE IF EXISTS '.$table.';';
        $row2 = mysql_fetch_row(mysql_query('SHOW CREATE TABLE '.$table));
        $return.= "\n\n".$row2[1].";\n\n";
        
        for ($i = 0; $i < $num_fields; $i++) 
        {
          while($row = mysql_fetch_row($result))
          {
            $return.= 'INSERT INTO '.$table.' VALUES(';
            for($j=0; $j<$num_fields; $j++) 
            {
              $row[$j] = addslashes($row[$j]);
              //$row[$j] = ereg_replace("\n","\\n",$row[$j]);
              $row[$j] = preg_replace("/\n/m","\\n",$row[$j]);
              if (isset($row[$j])) { $return.= '"'.$row[$j].'"' ; } else { $return.= '""'; }
              if ($j<($num_fields-1)) { $return.= ','; }
            }
            $return.= ");\n";
          }
        }
        $return.="\n\n\n";
      }
      
      //save file
      $handle = fopen($this->_backup_folder.'/db/db-backup-'.time().'-'.(md5(implode(',',$tables))).'.sql','w+');
      fwrite($handle,$return);
      fclose($handle);
      mysql_close($link);
    }
    
    public function backup_files()
    {
        if($this->_ftp_connection = ftp_connect($this->_ftp_server, $this->_ftp_port)){
            $ftp_login  = ftp_login($this->_ftp_connection, $this->_ftp_user, $this->_ftp_password);
            ftp_pasv($this->_ftp_connection, true);
            
            $this->_download($this->_ftp_folder);
            ftp_close($this->_ftp_connection);
        }else{
            $this->_error = 'Can\'t connect to ftp server';
            return;
        }
    }
    
    /* backup the entire folder from ftp */    
    private function _download($remote_dir = ".")
    {
	    if ($remote_dir != ".") {
	        if (ftp_chdir($this->_ftp_connection, $remote_dir) == false) {
	            $this->_error = "Change Dir Failed: $remote_dir<br />\r\n";
	            return;
	        }
	        if (!(is_dir($this->_backup_folder.'/files/'.$remote_dir)))
	            mkdir($this->_backup_folder.'/files/'.$remote_dir,'777',true);
	    }
 
	    $contents = ftp_nlist($this->_ftp_connection, ".");
	    foreach ($contents as $file) {
        
	        if ($file == '.' || $file == '..')
	            continue;
 
	        if (@ftp_chdir($this->_ftp_connection, $remote_dir.'/'.$file)) {
	            ftp_chdir ($this->_ftp_connection, "..");
	            $this->_download($remote_dir.'/'.$file);
	        }
	        else
	            ftp_get($this->_ftp_connection, "{$this->_backup_folder}/files$remote_dir/$file", $remote_dir.'/'.$file, FTP_BINARY);
            echo('<br/>');
        }
	    ftp_chdir ($this->_ftp_connection, "..");
	}
    
    private function _zip($source, $destination)
    {
        if (!extension_loaded('zip') || !file_exists($source)) {
            return false;
        }
    
        $zip = new ZipArchive();
        if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
            return false;
        }
    
        $source = str_replace('\\', '/', realpath($source));
    
        if (is_dir($source) === true)
        {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
    
            foreach ($files as $file)
            {
                $file = str_replace('\\', '/', realpath($file));
    
                if (is_dir($file) === true)
                {
                    $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                }
                else if (is_file($file) === true)
                {
                    $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                }
            }
        }
        else if (is_file($source) === true)
        {
            $zip->addFromString(basename($source), file_get_contents($source));
        }
    
        return $zip->close();
    }
    
    private function _rrmdir($dir) {
        foreach(glob($dir . '/*') as $file) {
            if(is_dir($file)){
                $this->_rrmdir($file);
                rmdir($file);
            }else
                unlink($file);
        }
    }
    
    public function cleanup() {
        $this->_rrmdir($this->_backup_folder);
        unlink($this->_backup_folder.'.zip');
    }
    
    public function run()
    {
        if ((strlen($this->_db_host) < 2) && (strlen($this->_db_user) < 2) && (strlen($this->_db_name) < 2)) {
            $this->_error = 'Database credentials incorrect';
            return;
        }
        if ((strlen($this->_ftp_server) < 2) && (strlen($this->_ftp_user) < 2)) {
            $this->_error = 'Database credentials incorrect';
            return;
        }
        $this->backup_tables();
        $this->backup_files();
        
        $this->_zip($this->_backup_folder, $this->_backup_folder.'.zip');
        // Upload the file with an alternative filename
        require_once('./lib/dropbox/bootstrap.php');
        if(filesize($this->_backup_folder.'.zip') <= 157286400)
            $put = $dropbox->putFile($this->_backup_folder.'.zip');
        else
            $put = $dropbox->chunkedUpload($this->_backup_folder.'.zip');
        // Dump the output
        var_dump($put);
//        $this->cleanup();
    }
}

?>