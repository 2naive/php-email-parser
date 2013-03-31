<?php

require_once('MimeMailParser.class.php');

/**
 * class IMAP
 *
 * @todo Garbage collector for stored files
 * 
 * @var string $host Mail server hostname
*/
class IMAP
{
    protected $epp;
    
    protected $host;
    protected $email;
    protected $pwd;
    
    protected $port             = 993;
    protected $protocol         = 'imap';
    protected $box              = 'INBOX';
    protected $retry_n          = 3;
    
    protected $path_tmp         = '/tmp';
    protected $filename_raw_msg = 'message.raw.txt';
    protected $filename_txt_msg = 'message.txt';
    protected $filename_html_msg= 'message.html';
    
    private $_connection_string;
    private $_connection_string_s;

    private $_callback_function;
    private $_ssl               = TRUE;
    private $_ssl_no_validate   = FALSE;
    
    private $_connection;
    private $_mailbox_info      = array();
    private $_all_mailboxes     = array();
    
    public static $echo_on      = TRUE;
    public static $log_type     = 1;
    public static $log_dest     = 'to.naive@gmail.com';
    public static $die_on_error = TRUE;
    
    
    /**
     * __construct()
     * @param $argv
     *
     * @return void
    */
    public function __construct($argv, $function_name)
    {
        /**
         * Recieving args
        */
        if( ! is_array($argv) || empty($argv[1]) || empty($argv[2]) || empty($argv[3]))
        {
            self::_error_log('Host, email and password MUST BE passed as ARGV', var_export($argv,1));
        }
        /**
         * TODO: Validation needed here
        */
        if(preg_match('#:[\d]{1,6}#', $argv[1]))
        {
            $this->host = substr($argv[1], 0, strpos($argv[1], ':') );
            $this->port = (int)substr($argv[1], strpos($argv[1], ':') +1 );
        }
        else
        {
            $this->host     =   $argv[1];
        }
        $this->email    =   $argv[2];
        $this->pwd      =   $argv[3];
        $this->box      =   ! empty($argv[4]) ? (string) $argv[4] : $this->box;
        $this->epp      =   ! empty($argv[5]) ? (int)    $argv[5] : $this->epp;
        $this->protocol =   ! empty($argv[6]) ?          $argv[6] : $this->protocol;
        if( ! empty($argv[7]))
            $this->_ssl     =   ($argv[7] == "NOSSL") ? FALSE         : $this->_ssl;
        
        $this->port     =   ( ! $this->_ssl && $this->port == 993) ? NULL : $this->port;
        
        $this->_connection_string    = '{';
        $this->_connection_string   .= $this->host;
        $this->_connection_string   .= $this->port      ? ':'.$this->port       : '';
        $this->_connection_string   .= $this->protocol  ? '/'.$this->protocol   : '';
        $this->_connection_string   .= $this->_ssl      ? '/ssl'                : '';
        $this->_connection_string   .= '}';
        
        $this->_connection_string_s  = $this->_connection_string;
        $this->_connection_string   .= $this->box;

        $this->_callback_function = $function_name;
    }
    
    public function get($name)
    {
        return $this->{$name};
    }
    
    public function set($name, $val)
    {
        $this->{$name} = $val;
    }
    
    /**
     * Establishing connection
    */
    public function connect($retry_n = 3, $options = NULL)
    {
        $this->retry_n  = $retry_n;
        $this->_connection = imap_open($this->_connection_string, $this->email, $this->pwd, $options, $this->retry_n)
            or self::_error_log('Unable connect to IMAP account', imap_last_error());
        self::_echo("Connected to: {$this->email}");
        
        /**
         * Check if chosen mailbox (e.g. INBOX) exists
        */
        $this->_all_mailboxes = $this->getMailboxesList();
        if( ! in_array($this->_connection_string, $this->_all_mailboxes) )
        {
            self::_error_log('Not valid mailbox', $this->box);
        }
        
        return TRUE;
    }
    
    public function is_connected()
    {
        if(imap_ping($this->_connection))
        {
            return TRUE;
        }
        else
        {
            return $this->connect($this->retry_n);
        }
    }
    
    public function disconnect($options = CL_EXPUNGE)
    {
        imap_close($this->_connection, CL_EXPUNGE)
            or self::_error_log('Unable to close IMAP connection', imap_last_error());
        echo self::_echo("Connection closed");
    }
    
    public function getMailboxesList()
    {
        if($this->is_connected())
        {
            return imap_list($this->_connection, $this->_connection_string_s , "*");
        }
        else
        {
            self::_error_log('Couldn`t get mailboxes list', 'No connection');
        }
    }
    
    public function getMailboxInfo()
    {
        if($this->is_connected())
        {
            
            $this->_mailbox_info = imap_mailboxmsginfo($this->_connection)
                or self::_error_log('Unable to get mailbox info', imap_last_error());
                
            return $this->_mailbox_info;
        }
        else
        {
            self::_error_log('Couldn`t get mailboxes list', 'No connection');
        }
    }
    
    public function searchMessages($criteria)
    {
        if($this->is_connected())
        {
            $emails = imap_search($this->_connection, $criteria);
            return $emails;
        }
        else
        {
            self::_error_log('Couldn`t search messages', 'No connection');
        }
    }
    
    public function parseMessages($emails, $limit = 0, $path = FALSE, $leave_unseen = FALSE)
    {
        $this->path_tmp = $path ? $path : '/tmp';
        $result         = array();
        $_k             = 0;
        $limit          = $this->epp ? $this->epp : $limit;
        
        foreach($emails as $i)
        {
            $_k++;
            if ($_k > $limit && $limit) break;
            
            /**
             * Pinging connection
            */
            $this->is_connected();
            
            /**
             * Getting headers information
            */
            $header = imap_headerinfo($this->_connection, $i)
                or self::_error_log("Unable read $i message header information", imap_last_error());
            
            /**
             * Forming message object
            */
            $message        = new stdClass();
            $message->id    = $header->message_id;
            $message->date  = $header->date;
            
            /**
             * Saving message locally
            */
            $user_folder    = $this->path_tmp . DIRECTORY_SEPARATOR . $this->email;

            if( ! file_exists($user_folder))
            {
                mkdir($user_folder)
                    or self::_error_log("Unable create user directory", $user_folder);
            }
            
            if( ! is_writable($user_folder))
            {
                self::_error_log("User directory not writable", $user_folder);
            }
            
            $message_folder  = $user_folder . DIRECTORY_SEPARATOR . $message->id;
            if( ! file_exists($message_folder))
            {
                mkdir($message_folder)
                        or self::_error_log("Unable create message directory", $message_folder);
            }
            
            $message_file_tmp= $message_folder . DIRECTORY_SEPARATOR . $this->filename_raw_msg;
            imap_savebody($this->_connection, $message_file_tmp, $i)
                or self::_error_log("Unable to save message $message_file_tmp", imap_last_error());
            
            /**
             * Initializing MMParser extended MimeMailParser
            */
            
            $Parser                 = new MimeMailParser();
            $Parser->setPath($message_file_tmp);
            
            /**
             * Getting message information
             * Converting using message encoding
             * TODO: attachment enc?
            */
            $message->headers       = $Parser->getHeaders();
            $message->attachments   = $Parser->getAttachments();
            
            $message->to        = iconv_mime_decode($Parser->getHeader('to'), 0, "UTF-8");
            $message->from      = iconv_mime_decode($Parser->getHeader('from'), 0, "UTF-8");
            $message->subject   = iconv_mime_decode($Parser->getHeader('subject'), 0, "UTF-8");
            $message->text      = iconv($Parser->getMessageEncoding('text'), "UTF-8", $Parser->getMessageBody('text'));
            $message->html      = iconv($Parser->getMessageEncoding('html'), "UTF-8", $Parser->getMessageBody('html'));
            
            
            /**
             * Saving text/html originals
            */
            if ($fp = fopen($message_folder . DIRECTORY_SEPARATOR . $this->filename_txt_msg, 'w'))
            {
                fwrite($fp, $message->text);
                fclose($fp);
            }
            else
            {
                self::_error_log("Unable to save txt message", $message_folder);
            }
            
            if ($fp = fopen($message_folder . DIRECTORY_SEPARATOR . $this->filename_html_msg, 'w'))
            {
                fwrite($fp, $message->html);
                fclose($fp);
            }
            else
            {
                self::_error_log("Unable to save txt message", $message_folder);
            }
            
            /**
             * Saving attachments
             * TIP: you may save it to different folder
            */
            foreach($message->attachments as $k => $attachment)
            {
                /**
                 * Fixing encoding
                */
                foreach ($attachment as $name => $value)
                {
                    $attachment->{$name}   =   iconv_mime_decode($value, 0, "UTF-8");
                }
                
                $_local_file    = $message_folder . DIRECTORY_SEPARATOR . $attachment->filename;
                if ($fp = fopen($_local_file, 'w'))
                {
                    while($bytes = $attachment->read())
                    {
                        fwrite($fp, $bytes);
                    }
                    fclose($fp);
                    
                    $message->attachments[$k]->local_file = $_local_file;
                }
                else
                {
                    self::_error_log("Unable to save attachment {$attachment->filename}", $message_folder);
                }
            }
            
            if ($this->_callback_function)
            {
                if (is_callable($this->_callback_function))
                {
                    call_user_func($this->_callback_function, $message);
                }
                else
                {
                    self::_error_log("Unable call user function", $this->callback);
                }
            }
            
            if($leave_unseen)
            {
                imap_clearflag_full($this->_connection, $i, '\\Seen')
                    or self::_error_log("Unable mark $i message as Unseen", imap_last_error());
            }
            
            $result[$i] = $message;
        }
        
        return $result;
    }
    

    
    /**
     * Loggin function
     *
     * @param $str  Info string
     * @param $err  Error string
     * @param $type 0 - system  , 1 - email , 2 - file
     * @param $dest 0 -         , 1 - email , 2 - file
     *
     * @return void
    */
    public static function _error_log($str, $err, $type = 0, $dest = NULL)
    {
        $err            = is_array($err) ? var_export($err, 1) : $err;
        
        self::_echo($str . ': ' . $err);
        
        $error_log_str  = $str . ': ' . $err . "\r\n";
        $error_log_str .= var_export(debug_backtrace(), 1);
        error_log($error_log_str, self::$log_type, self::$log_dest);
        
        if(self::$die_on_error)
            die();
    }
    
    /**
     * Echo function
    */
    public static function _echo($str)
    {
        if(self::$echo_on)
        {
            $str = is_scalar($str) ? $str : var_export($str, 1);
            echo $str;
            echo "\r\n";
        }
    }
    
}