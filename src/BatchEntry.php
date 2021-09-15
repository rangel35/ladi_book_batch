<?php
/**
 * Created by PhpStorm.
 * User: minx
 * User: mm63978
 * Date: 1/25/19
 * Time: 2:23 PM
 */

namespace Drupal\ladi_book_batch ;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\ladi_book_batch\Email;
use Exception;

class BatchEntry
{

     function __construct($row, $input_dir)
     {
         $this->now = "Batch Date is " . date("Y-m-d") . "_" . date("h:i:sa") ;
         $this->batchID = $row['batchID'] ;
         $this->nspace = $row['namespace'] ;
         $this->collection = $row['collection'] ;
         $this->location = $row['location'] ;
         $this->userID = $row['userID'] ;
         $this->userEmail = $row['userEmail'] ;
         $this->userName = $row['userName'] ;
         $this->batchType = $row['batchType'] ;
         $this->batchLang = $row['batchLang'] ;
         $this->status = $row['status'] ;
         $this->asset_path = $input_dir . "/" . $this->location ;
         $this->email = new Email();
         
     }

     function format_email_output(){
        try {
        $msg = $this->now . "\r\n" ; 
        $msg .= "Staff submitted ===> " . $this->userName  . "\r\n";
        $msg .= "Partner Namespace ===> " .  $this->nspace . "\r\n"; 
        $msg .= "Top level collection ===> " .  $this->collection . "\r\n"; 
        $msg .= "Batch ID ===> " .  $this->batchID  . "\r\n";

        return $msg;

        } catch (Exception $e) {
            $msg = $e->getMessage();
            $this->getLogger('ladi_book_batch')->error("Batch Entry format email error: {$msg}");
            $this->email->send_error_email("Batch Entry format email error: {$msg}");
        }
     }

     public static function add_batchrow_to_batch_queue($batchID,$namespace,$collection,$location, $userID,$userEmail,$userName, $batchType, $batchLang){
        try {
         $connection = \Drupal\Core\Database\Database::getConnection();

         $connection->insert('batch_queue')
             ->fields([
                 'batchID' => $batchID,
                 'namespace' => $namespace,
                 'collection' => $collection,
                 'location' => $location,
                 'userID' => $userID,
                 'userEmail' => $userEmail,
                 'userName' => $userName,
                 'batchType' => $batchType,
                 '$batchLang' => $batchLang,
                 'status' => 0,
             ])
             ->execute();
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $this->getLogger('ladi_book_batch')->error("Batch Entry error - unable to add batch row to batch queue: {$msg}");
            $this->email->send_error_email("Batch Entry error - unable to add batch row to batch queue: {$msg}");
     }
    }
    
    public static function format_batch_info($row) {
        try {
        $btypes = array('Books with pages', 'Individual Items (single or multi)');
        $bKey = $row['batchType'] ;
        drupal_set_message(t('batchID ==  @bID.', array('@bID' => $row['batchID'])), 'status');
        drupal_set_message(t('Top Level Collection ==  @col.', array('@col' => $row['namespace'])), 'status');
        drupal_set_message(t('Entered By Staff ==  @s.', array('@s' => $row['userName'])), 'status');
        drupal_set_message(t('Staff Email for Reporting ==  @ue.', array('@ue' => $row['userEmail'])), 'status');
        drupal_set_message(t('Sub-collection for batch ==  @scol.', array('@scol' => $row['collection'])), 'status');
        drupal_set_message(t('Location of Items for Ingest ==  @loc.', array('@loc' => $row['location'])), 'status');
        drupal_set_message(t('Language of Items for Ingest ==  @lang.', array('@lang' => $row['batchLang'])), 'status');
        drupal_set_message(t('Type of Batch for Ingest ==  @bt.', array('@bt' => $btypes[$bKey])), 'status');
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $this->getLogger('ladi_book_batch')->error("Batch Entry error - unable to format batch info: {$msg}");
            $this->email->send_error_email("Batch Entry error - unable to format batch info: {$msg}");
        }
    }

    /**
     * function to update batch queue table after ingest
    */
     public static function close_batch_row($row,$input_dir){
        try {
         $now = time();
         $connection = \Drupal\Core\Database\Database::getConnection();

         $connection->update('batch_queue')
             ->fields([
                 'status' => $now,
             ])
             ->condition('batchID', $row['batchID'], '=')
             ->execute();

         //move to DONE
         $batchPath = $input_dir . "/" . $row['location']  ;
         $donePath = $input_dir . "/DONE/" . $row['location']  ;
         //echo ("$batchPath is the batchpath\n");
         //echo ("$donePath is the donePath\n");
         rename($batchPath, $donePath);

        } catch (Exception $e) {
            $msg = $e->getMessage();
            $this->getLogger('ladi_book_batch')->error("Batch Entry close_batch_row function error: {$msg}");
            $this->email->send_error_email("Batch Entry close_batch_row function error: {$msg}");
        }

     }
    
   

}
