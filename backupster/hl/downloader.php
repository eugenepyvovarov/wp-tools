<?php
require_once '../config.php';
declare(ticks=1);
//A very basic job daemon that you can extend to your needs.
class JobDaemon{

    public $maxProcesses = 2;
    protected $jobsStarted = 0;
    protected $currentJobs = array();
    protected $signalQueue=array();  
    protected $parentPID;
  
    public function __construct(){
        echo "constructed \n";
        $this->parentPID = getmypid();
        pcntl_signal(SIGCHLD, array($this, "childSignalHandler"));
    }
  
    /**
    * Run the Daemon
    */
    public function run(){
        echo "Running \n";
        for($i=0; $i<20; $i++){
            $jobID = rand(0,10000000000000);

            while(count($this->currentJobs) >= $this->maxProcesses){
               echo "Maximum children allowed, waiting...\n";
               sleep(1);
            }

            $launched = $this->launchJob($jobID);
        }
      
        //Wait for child processes to finish before exiting here
        while(count($this->currentJobs)){
            echo "Waiting for current jobs to finish... \n";
            sleep(1);
        }
    }
  
    /**
    * Launch a job from the job queue
    */
    protected function launchJob($jobID){
        $pid = pcntl_fork();
        if($pid == -1){
            //Problem launching the job
            error_log('Could not launch new job, exiting');
            return false;
        }
        else if ($pid){
            // Parent process
            // Sometimes you can receive a signal to the childSignalHandler function before this code executes if
            // the child script executes quickly enough!
            //
            $this->currentJobs[$pid] = $jobID;
          
            // In the event that a signal for this pid was caught before we get here, it will be in our signalQueue array
            // So let's go ahead and process it now as if we'd just received the signal
            if(isset($this->signalQueue[$pid])){
                echo "found $pid in the signal queue, processing it now \n";
                $this->childSignalHandler(SIGCHLD, $pid, $this->signalQueue[$pid]);
                unset($this->signalQueue[$pid]);
            }
        }
        else{
            //Forked child, do your deeds....
            
            global $config;
            global $backup_config;
            
            if (!$link = mysql_connect($config['mysql_host'],$config['mysql_user'],$config['mysql_password'])) {
                    $this->_error = 'Invalid db credentials';
                    exit(1);
            }
            mysql_select_db($config['mysql_database'],$link);
            
            $posixProcessID = posix_getpid();
                
            $result = mysql_query("UPDATE `jobs` SET locked_by = $posixProcessID WHERE status = 'new' AND  locked_by IS NULL LIMIT 1;", $link);
            
            if($ftp_connection = ftp_connect($backup_config['_ftp_server'], $backup_config['_ftp_port'])){
                $ftp_login  = ftp_login($ftp_connection, $backup_config['_ftp_user'], $backup_config['_ftp_password']);
                ftp_pasv($ftp_connection, true);
                
                $result = mysql_query("SELECT * FROM `jobs` WHERE locked_by = $posixProcessID LIMIT 1", $link);
                $file = mysql_fetch_assoc($result);

                $backup_folder = realpath('../backups/');
                
       	        if (!(is_dir($backup_folder.'/job_'.date('mdY').'/files/')))
	               mkdir($backup_folder.'/job_'.date('mdY').'/files/','777',true);
       	        if (!(is_dir($backup_folder.'/job_'.date('mdY').'/files'.dirname($file['file']))))
	               mkdir($backup_folder.'/job_'.date('mdY').'/files/'.dirname($file['file']),'777',true);
                   
   	            $res = ftp_get($ftp_connection, $backup_folder.'/job_'.date('mdY').'/files'.$file['file'], $file['file'], FTP_BINARY);
                
                ftp_close($ftp_connection);

                $result = mysql_query("UPDATE `jobs` SET locked_by = NULL, status = 'in progress' WHERE locked_by = $posixProcessID LIMIT 1;", $link);
                
                mysql_close($link);
            }else{
                mysql_close($link);
                exit(1);
            }
            
            
            //
            $exitStatus = 0; //Error code if you need to or whatever
            echo "Doing something fun in pid ".getmypid()."\n";
            exit($exitStatus);
        }
        return true;
    }
  
    public function childSignalHandler($signo, $pid=null, $status=null){
      
        //If no pid is provided, that means we're getting the signal from the system.  Let's figure out
        //which child process ended
        if(!$pid){
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
        }
      
        //Make sure we get all of the exited children
        while($pid > 0){
            if($pid && isset($this->currentJobs[$pid])){
                $exitCode = pcntl_wexitstatus($status);
                if($exitCode != 0){
                    echo "$pid exited with status ".$exitCode."\n";
                }
                unset($this->currentJobs[$pid]);
            }
            else if($pid){
                //Oh no, our job has finished before this parent process could even note that it had been launched!
                //Let's make note of it and handle it when the parent process is ready for it
                echo "..... Adding $pid to the signal queue ..... \n";
                $this->signalQueue[$pid] = $status;
            }
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
        }
        return true;
    }
}

$demon = new JobDaemon();
$demon->run();