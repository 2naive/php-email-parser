<?php

    $config             = new stdClass();
    $config->accounts   = array(
        'shoko2sms@greensms.ru' => array(
            'imap'  =>  array(
                'host'      => 'imap.yandex.ru',
                'login'     => 'email@stupid.su',
                'password'  => 'stupid'
            ),
            'sms'  =>  array(
                'type'      => 'devino',
                'login'     => 'login',
                'password'  => 'password'
            )
            
        ),
        
        'kp.greensms@gmail.com' => array(
            'imap'  =>  array(
                'host'      => 'imap.gmail.com',
                'login'     => 'email@stupid.su',
                'password'  => 'stupid'
            ),
            'sms'  =>  array(
                'type'      => 'devino',
                'login'     => 'login',
                'password'  => 'password'
            )
            
        ),
    );

?>