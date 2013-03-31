<?php

    require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'IMAP.class.php');
    
    define('MESSAGES_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'data');
    
    /**
     * @todo Text file attach bugfix
     * 
     * @tested on Gmail, Yandex.Mail, others
     *
     * @encoding UTF-8
     * 
     * @uses exec, iconv
     * @uses pecl                   http://php.net/manual/en/install.pecl.phpize.php
     * @uses mbstring               http://www.php.net/manual/en/mbstring.installation.php
     * @uses MailParse              http://pecl.php.net/package/mailparse
     * @uses PHP Mime Mail Parser   https://code.google.com/p/php-mime-mail-parser/wiki/RequirementsAndInstallation
     * @uses MMParser               Encoding fix for MimeMailParser.class + extra fixes
     * 
     * @installation (Debian)
     *      apt-get install php5-cli
     *      apt-get install php5-dev
     *      apt-get install php5-pear
     *      pecl install mailparse
     *      
     *      vi /etc/php5/cli/php.ini
     *      > extension=mailparse.so
     *
     *      wget https://php-mime-mail-parser.googlecode.com/svn/trunk/MimeMailParser.class.php
     *      wget https://php-mime-mail-parser.googlecode.com/svn/trunk/attachment.class.php
     *      
     * 
     * @usage php parser.php imap.yandex.ru mail@yandex.ru password INBOX 10 imap NOSSL >log.txt 2>log.txt
    */
    
    /**
     * Callback function that is called when message recieved
     *
     * 
    */
    function _callback($message)
    {
        IMAP::_echo($message->id);
        IMAP::_echo("\t".$message->date);
        IMAP::_echo("\t".$message->from);
        IMAP::_echo("\t".$message->to);
        IMAP::_echo("\t".$message->subject);
        #IMAP::_echo($message);
    }
    
    
    /**
     * Echo time
    */
    $_now           = date("Y-m-d H:i:s");
    IMAP::_echo("");
    IMAP::_echo($_now);
    
    /**
     * Initializing IMAP instance
    */
    $IMAP           = new IMAP($argv, '_callback');

    /**
     * Connecting via IMAP (or POP3/NNTP)
    */
    $IMAP->connect(5);

    /**
     * Getting information about folder
    */
    $mailbox_info   = $IMAP->getMailboxInfo();
    //IMAP::_echo($mailbox_info);
    
    if( $mailbox_info->Unread > 0)
    {
        IMAP::_echo("Unread messages: {$mailbox_info->Unread}");
        
        /**
         * Serching for UNSEEN mails
         *
         * Other search matching messages criteria:
         * ALL                  - return all messages matching the rest of the criteria
         * ANSWERED             - with the \\ANSWERED flag set
         * BCC "string"         - with "string" in the Bcc: field
         * BEFORE "date"        - with Date: before "date"
         * BODY "string"        - with "string" in the body of the message
         * CC "string"          - with "string" in the Cc: field
         * DELETED              - deleted messages
         * FLAGGED              - with the \\FLAGGED (sometimes referred to as Important or Urgent) flag set
         * FROM "string"        - with "string" in the From: field
         * KEYWORD "string"     - with "string" as a keyword
         * NEW                  - new messages
         * OLD                  - old messages
         * ON "date"            - with Date: matching "date"
         * RECENT               - with the \\RECENT flag set
         * SEEN                 - that have been read (the \\SEEN flag is set)
         * SINCE "date"         - with Date: after "date"
         * SUBJECT "string"     - with "string" in the Subject:
         * TEXT "string"        - with text "string"
         * TO "string"          - with "string" in the To:
         * UNANSWERED           - that have not been answered
         * UNDELETED            - that are not deleted
         * UNFLAGGED            - that are not flagged
         * UNKEYWORD "string"   - that do not have the keyword "string"
         * UNSEEN               - which have not been read yet
        */
        $emails = $IMAP->searchMessages('UNSEEN');
        
        if(is_array($emails))
        {
            $messages = $IMAP->parseMessages($emails, 10, MESSAGES_DIR);
        }
        else
        {
            IMAP::_error_log('Searched messages not found', $emails);
        }
    }
    else
    {
        IMAP::_echo("No new mail");
    }
    
    /**
     * Closing IMAP connection applying new markers
    */
    $IMAP->disconnect();
?>