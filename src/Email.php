<?php

namespace Drupal\ladi_book_batch;

class Email {

    function __construct()
    {
        $this->subject = "LADI Book Batch Error";
        $this->devEmail = "EMAIL";
    }

    public static function send_error_email($message)
    {
        mail ($this->devEmail, $this->subject, $message);
    }

    public static function batch_submission_email($batchID, $userEmail,$message)
    {
        $subject = "LADI batch submission: " . $batchID;
        $message .= "\n.";
        
        print "email subject is: \n $subject \n";
        print "email to is: \n $userEmail \n";
        print "email message is: \n $message \n";
        
        $to = $userEmail;
                 
        $header = "From: EMAIL \n";  // change to appropriate from email
        // $header .= "MIME-Version: 1.0 \n";
        // $header .= "Content-type: text/text \n";
        
        $retval = mail ($to,$subject,$message,$header);
        
        if( $retval == true ) {
           echo "Message sent successfully...\n";
        }else {
           echo "Message could not be sent...\n";
        } 
    }

}
