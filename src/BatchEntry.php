<?php
/**
 * Created by PhpStorm.
 * User: minx
 * User: mm63978
 * Date: 1/25/19
 * Time: 2:23 PM
 */

namespace Drupal\ladi_book_batch ;

class BatchEntry
{

     function __construct($row, $input_dir)
     {
         
         $this->batchID = $row['batchID'] ;
         $this->collection = $row['collection'] ;
         $this->location = $row['location'] ;
         $this->userID = $row['userID'] ;
         $this->userEmail = $row['userEmail'] ;
         $this->batchType = $row['batchType'] ;
         $this->status = $row['status'] ;
         $this->asset_path = $input_dir . "/" . $this->location ;
         
         
     }

     function format_email_output(){
//        echo " formatting output \n";
//        echo " batchID ===> " .  $this->batchID  . "\n";
//        echo " collection ===> " .  $this->collection . "\n";
//        echo " location ===> " . $this->location . "\n";
//        echo " userID ===> " .  $this->userID  . "\n";
//        echo " userEmail ===> " .  $this->userEmail . "\n";
//        echo " batchType ===> " . $this->batchType  . "\n";
//        echo " status ===> " .  $this->status . "\n";
//        echo " Asset Path ===> " .  $this->asset_path . "\n";

        $msg = "Staff submitted ===> " . $this->userID  . "\n";
        $msg .= "Top level collection ===> " .  $this->collection . "\n"; 
        $msg .= "Batch ID ===> " .  $this->batchID  . "\n";

        return $msg;
     }

     public static function add_batchrow_to_batch_queue($batchID,$namespace,$collection,$location, $userID,$userEmail, $batchType){

         $connection = \Drupal\Core\Database\Database::getConnection();

         $connection->insert('batch_queue')
             ->fields([
                 'batchID' => $batchID,
                 'collection' => $collection,
                 'location' => $location,
                 'userID' => $userID,
                 'userEmail' => $userEmail,
                 'batchType' => $batchType,
                 'status' => 0,
             ])
             ->execute();
     }

     public static function format_batch_submission_output($batchID,$namespace,$collection,$location, $staff, $userEmail, $batchType){
         return "batchID == $batchID <br> Entered By Staff $staff <br> 
                    Staff Email for Reporting $userEmail <br> Collection for batch $collection <br> 
                    Location of Items for Ingest $location <br> Type of Batch for Ingest $batchType";
     }
}