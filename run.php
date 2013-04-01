<?php

/**
 * Multithread parser runner
 * 
 * @uses pcntl_fork, nohup, exec
 * 
 * @usage nohup php run.php 5 &
*/

/**
 * Defining constants
*/
DEFINE('MEMORY_LIMIT',  10 * 1024 /*10 MB*/);
DEFINE('TIME_LIMIT',    6 * 60 * 60 /*6 Hours*/);
DEFINE('LOGS_DIR',      dirname(__FILE__) . DIRECTORY_SEPARATOR . 'logs');
$time_start = microtime(1);

/**
 * Retargeting IO
*/
fclose(STDIN);
fclose(STDOUT);
fclose(STDERR);
$STDIN  = fopen('/dev/null', 'r');
$STDOUT = fopen(LOGS_DIR.'/application.log', 'ab');
$STDERR = fopen(LOGS_DIR.'/error.log', 'ab');
ini_set('error_log', LOGS_DIR.'/error.log');

/**
 * Getting accounts info
*/

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.php');

/**
 * Setting Delay
*/

$sleep  = ! empty($argv[1]) ? $argv[1] : 1;
declare(ticks=1);
/*
 * 
 * Без этой директивы PHP не будет перехватывать сигналы
 * PHP >= 5.3.0 вместо declare(ticks = 1) надо бы использовать pcntl_signal_dispatch()
 *
 * #pcntl_signal(SIGTERM, "_process_callback");
 * #pcntl_signal(SIGCHLD, "_process_callback");
 * function _process_callback($signo)
 * {
 * 
 * }
 */ 


echo "Service started" . PHP_EOL;
while(TRUE)
{
    foreach($config->accounts as $k => $account)
    {
        unset($output);
        exec("ps aux | grep '{$account['imap']['login']}' | grep -v grep", $output);
        
        if(count($output) < 1)
        {
            echo PHP_EOL;
            echo "Child: {$account['imap']['login']}".PHP_EOL;
            
            $pid = pcntl_fork();
            if ($pid == -1)
            {
                error_log('Could not launch new job, exiting');
            } 
            elseif ($pid)
            {
                /**
                 * Parent code execution
                */
                echo "PID: $pid".PHP_EOL;
            } 
            else
            {
                /**
                 * Child code execution (running parser)
                */
                $run_cmd_arr    = array();
                $run_cmd_arr[]  = 'php parser.php';
                $run_cmd_arr[]  = $account['imap']['host'];
                $run_cmd_arr[]  = $account['imap']['login'];
                $run_cmd_arr[]  = $account['imap']['password'];
                $run_cmd_arr[]  = '>>' . LOGS_DIR . DIRECTORY_SEPARATOR . $account['imap']['login'] . '.txt';
                $run_cmd_arr[]  = '2>>' . LOGS_DIR . DIRECTORY_SEPARATOR . $account['imap']['login'] . '.txt';
                
                $run_cmd_str    = implode(' ', $run_cmd_arr);
                exec($run_cmd_str);
                exit;
            }
        }
        else
        {
            echo "Exists: {$account['imap']['login']}".PHP_EOL;
        }
    }
    
    /**
     * Waiting all processes die
    */
    /*
    foreach($config->accounts as $k => $account)
    {
        pcntl_wait($status);
    }
    */
    
    /**
     * Counting metrics
    */
    $memory_usage   = round(memory_get_usage(TRUE) / 1024) . 'KB';
    #echo 'Memory usage: ' . $memory_usage . '/' . MEMORY_LIMIT . 'KB'. PHP_EOL;
    
    $time_now       = microtime(1);
    $time_elapsed   = round($time_now-$time_start,2);
    #echo 'Time elapsed: ' . $time_elapsed . '/' . TIME_LIMIT . PHP_EOL;
    
    if($memory_usage > MEMORY_LIMIT || $time_elapsed > TIME_LIMIT)
    {
        echo 'Memory usage: ' . $memory_usage . '/' . MEMORY_LIMIT . 'KB'. PHP_EOL;
        echo 'Time elapsed: ' . $time_elapsed . '/' . TIME_LIMIT . PHP_EOL;
        
        exec('nohup php ' . __FILE__ . ' ' . $sleep . '&', $output);
        echo implode(PHP_EOL, $output) . PHP_EOL;
        break;
        exit;
    }
    
    sleep($sleep);
}

?>