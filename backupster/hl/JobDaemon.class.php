<?php
require_once '../config.php';
declare(ticks=1);
//A very basic job daemon that you can extend to your needs.
class JobDaemon{

    public $maxProcesses = 5;
    protected $jobsStarted = 0;
    protected $currentJobs = array();
    protected $signalQueue=array();  
    protected $parentPID;
    protected $iterations = 10000;
    protected $current_job;
  
    public function __construct(){
        echo "constructed \n";
        $this->parentPID = getmypid();
        pcntl_signal(SIGCHLD, array($this, "childSignalHandler"));
        $this->get_tempfile();
    }
    
    public function set_tempfile()
    {
        file_put_contents(sys_get_temp_dir() . '/current.job', json_encode($this->current_job));
    }
    
    public function get_tempfile()
    {
        if (is_file(sys_get_temp_dir() . '/current.job')) {
            $this->current_job = json_decode(file_get_contents(sys_get_temp_dir() . '/current.job'));
        }
    }
  
    /**
    * Run the Daemon
    */
    public function run(){
        echo "Running \n";
        for($i=0; $i<$this->iterations; $i++){
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
            
            $this->job();
            
            $exitStatus = 0; //Error code if you need to or whatever
            echo "Doing something fun in pid ".getmypid()."\n";
            exit($exitStatus);
        }
        return true;
    }
    
    public function job(){
        //placeholder for jobs
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