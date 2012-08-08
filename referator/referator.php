<?php

/**
 * @author Arashi
 * @copyright 2012
 */
ini_set( 'max_execution_time', 240 );
ini_set( 'memory_limit', '128M' );

class Referator{
    private $_wp_version = '';
    private $_wp_language = '';
    public $_wp_files = array();
    private $_wp_installs_list = array();
    public $error = '';
    public $download_url = '';
    public $package_name = "";
    public $package_path = "";
    public $source_files_path = "";
    public $result_hashe_file = "";
    
    public function __construct($ver, $lang = ''){
        if (isset($lang))
            $this->_wp_language = $lang;
        if (isset($ver))
            $this->_wp_version = $ver;
            
        $this->build_strings();
    }

    public function get_package(){
        if (file_exists($this->package_path))
            return;
        if ($this->download_url){
            if($file = @file_get_contents($this->download_url)){
                file_put_contents($this->package_path, $file);
            }else{
                $this->error = 'Can\'t find file';
            }
        }else{
            $this->error = 'Incorrect url';
        }  
    }
    
    
    public function build_strings(){
        if ($this->_wp_language) {
            if (strlen($this->_wp_language) > 2) {
                $prefix = substr($this->_wp_language,0,stripos($this->_wp_language,'_'));
            }else{
                $prefix = $this->_wp_language;
            }
            $this->download_url = "http://{$prefix}.wordpress.org/wordpress-{$this->_wp_version}-{$this->_wp_language}.zip";
            $this->package_name = "wordpress-{$this->_wp_version}-{$this->_wp_language}.zip";
            $this->package_path = "./tmp/{$this->package_name}";
            $this->source_files_path = "./tmp/{$this->_wp_version}/{$this->_wp_language}";
            $this->result_hashe_file = "./hashes/hashes-{$this->_wp_version}-{$this->_wp_language}.php";
        }elseif($this->_wp_version){
            $url = "http://wordpress.org/wordpress-{$this->_wp_version}-no-content.zip";
            if (is_file($url)) {
                $this->download_url = $url;
            }else{
                $url = "http://wordpress.org/wordpress-{$this->_wp_version}.zip";
                $this->download_url = $url;
            }
            $this->package_name = "wordpress-{$this->_wp_version}.zip";
            $this->package_path = "./tmp/{$this->package_name}";
            $this->source_files_path = "./tmp/{$this->_wp_version}";
            $this->result_hashe_file = "./hashes/hashes-{$this->_wp_version}.php";
        }else{
            $this->error = 'No params passed';
        }
    }
    
    public function remove_content_folder_from_zip(){
        if(file_exists($this->source_files_path.DIRECTORY_SEPARATOR."wordpress"))
            return;
        $zip = new ZipArchive;
        if ($zip->open($this->package_path) === TRUE) {
            for($i = 0; $i < $zip->numFiles; $i++)
            {
                $chk = strpos($zip->getNameIndex($i), 'wp-content');
                if ($chk !== false) {
                    $zip->deleteIndex($i);
                }
            } 
            //$zip->deleteName('wordpress/wp-content/');
            $zip->close();
        } else {
    			$this->error = 'Can\'t open archive';
        }
    }
    
    public function unpack_package(){
        if(file_exists($this->source_files_path.DIRECTORY_SEPARATOR."wordpress"))
            return;
        $zip = new ZipArchive;
        if ($zip->open($this->package_path) === TRUE) {
            if(!$zip->extractTo($this->source_files_path))
                $this->error = 'Can\'t extract archive';   
            $zip->close();
        } else {
    			$this->error = 'Can\'t open archive';
        }
    }
    
    public function recurse_directory($dir) {
		if ( $handle = @opendir($dir) ) {
			while ( false !== ( $file = readdir( $handle ) ) ) {
				if ( $file != '.' && $file != '..' ) {
					$file = $dir . '/' . $file;
					if ( is_dir( $file ) ) {
						$this->recurse_directory( $file );
					} elseif ( is_file( $file ) ) {
                        $this->_wp_files[str_replace($this->source_files_path.DIRECTORY_SEPARATOR."wordpress".'/', '', $file )] = md5_file($file);
					}
				}
			}
			closedir( $handle );
		}
	}
    
    public function write_hashes(){
	        $out = '';
            $out .= '<?php'."\n";
            $out .= '$filehashes = array('."\n";
            foreach ($this->_wp_files as $file => $hashe) {
               $out.= "'$file' => '$hashe',\n";
            }
            $out .= ');'."\n";
            $out .= '?>'."\n";
            file_put_contents($this->result_hashe_file, $out);
            
   }
   
   function rrmdir($dir) {
        foreach(glob($dir . '/*') as $file) {
            if(is_dir($file)){
                $this->rrmdir($file);
                rmdir($file);
            }else
                unlink($file);
        }
    }
    
    function cleanup() {
        $this->rrmdir($referator->source_files_path);
        unlink($this->package_path);
    }
    
    public function run() {
        unset($filehashes);
        if (is_file($this->result_hashe_file)) {
            include_once($this->result_hashe_file);
            echo json_encode(array('state' => 'ok', 'filehashes' => $filehashes));
            exit;
        }else{
            if (!$this->_wp_version) {
                echo json_encode(array('state' => 'error', 'error' => 'invalid param'));
                exit;
            }
            $this->get_package();
            $this->remove_content_folder_from_zip();
            $this->unpack_package();
            $this->recurse_directory($this->source_files_path.DIRECTORY_SEPARATOR."wordpress");
            $this->write_hashes();
            include_once($this->result_hashe_file);
            if ($this->error) {
                echo json_encode(array('state' => 'error', 'error' => $this->error));
            }else{
                echo json_encode(array('state' => 'ok', 'filehashes' => $filehashes));
            }
            $this->cleanup();
            exit;
        }
    }
}

$referator = new Referator($_GET['ver'], $_GET['lang']);
$referator->run();
?>