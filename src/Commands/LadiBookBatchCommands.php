<?php

namespace Drupal\ladi_book_batch\Commands ;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\context\ContextManager;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseNotFoundException;
use Drupal\Core\Database\Driver\mysql\Connection;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\http_client_manager\HttpClientInterface;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\migrate_tools\Commands\MigrateToolsCommands;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term; 
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ladi_book_batch\BatchEntry;


/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
class LadiBookBatchCommands extends DrushCommands {
  /**
   * Echos back hello with the argument provided.
   *
   * @param string $name
   *   Argument provided to the drush command.
   *
   * @command ladi_book_batch:hello
   * @aliases lbb-hello
   * @options arr An option that takes multiple values.
   * @options msg Whether or not an extra message should be displayed to the user.
   * @usage ladi_book_batch:hello [name] --msg
   */
	public function hello($name, $options = ['msg' => FALSE]) {
		if ($options['msg']) {
			$this->output()->writeln('Hello ' . $name . '! This is your first Drush 9 command.');
		} else {
			$this->output()->writeln('Hello ' . $name . '!');
		}
	}


  /**
   * provides functionality for nightly batch ingest.
   *
   * @command ladi_book_batch:batchIngest
   * @aliases lbb-batchIngest
   * @usage drush ladi_book_batch:batchIngest
   */
	public function batchIngest() {
	   
        //change these settings or better yet add via ini file //TO DO
		$host = 'http://localhost:8000/';
		$username = 'admin';
		$password = 'islandora';
		$input_dir = '/home/vagrant/custom/staging/ASSETS';

        //Non-processing filenames or directories for use in project
        $illNames = array(".", "..", "Thumbs.db", ".DS_Store") ;
        
		$this->output()->writeln('Hello from inside batchIngest.');

		// select * from Gemini order by dateCreated ;
	
		try {
			$connection = \Drupal\Core\Database\Database::getConnection();
		
			$result = $connection->query("SELECT * FROM batch_queue WHERE status=0");
			if ($result) {
                while ($row = $result->fetchAssoc()) {
                  
                    $batchID = $row['batchID'] ;
                    $nspace = $row['namespace'] ;
                    $collection = $row['collection'] ;
                    $location = $row['location'] ;
                    $userID = $row['userID'] ;
                    $userEmail = $row['userEmail'] ;
                    $batchType = $row['batchType'] ;
                    $status = $row['status'] ;

                    $account = \Drupal\user\Entity\User::load($userID);
                    $accountSwitcher = \Drupal::service('account_switcher');
                    $userSession = new UserSession([
                        'uid' => $account->id(),
                        'name'=>$account->getUsername(),
                        'roles'=>$account->getRoles()
                    ]);
                    $accountSwitcher->switchTo($userSession);
                    
                    $batchrow = new BatchEntry($row, $input_dir);
                    echo "batchrow is" . PHP_EOL;
                    var_dump($batchrow);
                  
					$msg = $batchrow->format_email_output();
//					echo "msg is: \n" . $msg . PHP_EOL;
                   
//                    echo " Asset Path ===> " . $batchrow->asset_path . "\n";
					$dirs = scandir($batchrow->asset_path) ;					
//					var_dump($dirs);
                    if (empty($dirs)) {
                        
                        $msg .= "\r\nDir == " . $batchrow->asset_path . " is empty or does not exist. Please resubmit your batch request or contact an administrator for more information \n\r" ;
                    }
                    
                    foreach($dirs as $dir) {
                        //continue;
                        if ($dir=="." || $dir=="..") {continue;}
                        $this->book_path = $batchrow->asset_path . "/" . $dir ;
                        echo " Book Path ===> " . $this->book_path . "\n";
                        $dirFiles = scandir($this->book_path);
                        $files = array();  //will contain image files for use 
                        
                        //reads all the media files into array for later uploading
                        foreach($dirFiles as $f) {
                            if ($f=="." || $f==".." || (strpos($f,'.csv') !== FALSE) || (in_array($f, $illNames))) {
                                continue;
                            }else{
                                $files[] = $f ;
                            }                            
                        }
                        print "files \n";
                        var_dump($files);

                        if (empty($files)) {
                        
                            $msg .= "\r\nDir == " . $dir . "in batch " . $batchID . " is empty or only contains a csv file, so skipping processing, please resubmit in a future batch. \n\r" ;
                            continue;
                        }

                        
                        //note dir name should match csv_file name
                        $msg .= "\r\n \r\n" . "working with Book " . $dir . "\r\n"; 
                        $csv_file = $this->book_path . "/" . $dir . ".csv";
                        if (!file_exists($csv_file)) {
                            echo "The file $csv_file does not exist so book $dir not processed \n";
                            $msg .= "The file $csv_file does not exist so book $dir not processed \r\n";
                            continue;
                        }
												
                        echo "The file $csv_file exists \n";

                        $fp = fopen($csv_file, 'r');
                        $headers = fgetcsv($fp, 1024, ",") ;
                        
                        //dups array is to hold csv repeated headers (for multivalued fields)
                        $dups = array();
                        foreach(array_count_values($headers) as $val => $c) {
                            if($c > 1) $dups[] = $val;
                        }
        				//var_dump($dups);
                        //continue;
                        
                        $weight = 0;
                        $pages = array();  
                        
                        while (($import_row = fgetcsv($fp, 1024, ",")) !== FALSE) {
                            $import_row = array_map("utf8_encode", $import_row); 
							$num = count($import_row);
							
							if(preg_match('/node\/(\d+)/', $collection, $matches)) {
                                $colID = $matches[1];                                
                            } else {
                                $alias = "/" . basename($collection) ;
                                $node_path = \Drupal::service('path.alias_manager')->getPathByAlias($alias);
                                preg_match('/node\/(\d+)/', $node_path, $matches) ;
                                $colID = $matches[1]; 
                            }

							if ($import_row[0] == "BOOK") {
                                echo "Hi from inside BOOK\n" ;
                                                               
                                //hardcoded for testing
                                $csv_entries = array( 
                                    'type'  => 'islandora_object',
                                    'uid' => $batchrow->userID,
                                    'field_member_of' => $colID,   

                                );                               
                                //echo "this is the csv \n" ;
                                //var_dump($csv_entries);
                                //continue;

                                for ($i = 1; $i <= $num; $i++) {
                                    if (!empty($headers[$i]) && !empty($import_row[$i])) {
                                        
                                        if (in_array($headers[$i], $dups)) {
                                            $csv_entries[$headers[$i]][] =  $import_row[$i] ;
                                        } else {
                                        	$csv_entries[$headers[$i]] =  $import_row[$i] ;  
                                    }
                                          
                                    }
                                }
                                
                                $node = \Drupal::entityTypeManager()->getStorage('node')->create($csv_entries);
								                                
                                //saves book node to drupal db
				$node->save();
                                
				$bid = $node->id();                               
                                echo "this is bid $bid \n";
                                
                		$nUUID = $node->uuid() ;
                                echo "this is book UUID $nUUID \n";
                                $bookalias = "/" . $nspace . "/" . $nUUID ;
				echo "this is book bookalias $bookalias \n";
                                $system_path = "/node/" . $bid ; 
                                $path = \Drupal::service('path.alias_storage')->save($system_path, $bookalias, "en");

                                //reads next file in array for adding media
                                $pageFile = array_shift($files) ;
                                //call to set up book in drupal databases
                                $this->add_book_entry_to_drupal_db($connection, $bid);
                                $this->add_media_file($bid, $batchrow, $pageFile, $dir);
								
                                $url = $host . $bookalias;
                                $msg .= "Book image ==> " . $pageFile . "\r\n"; 
                                $msg .= "Book URL ==> " . $url . "\r\n";          
				
			} else {

                                //Create Page(s) for book with limited metadata and media file
                                //continue;
                                $weight++;
                               
                                echo "this is weight $weight \n";
                                
                                $pageFile = array_shift($files) ;
                                
				print "book ID is " . $bid . "\n";
				$node = \Drupal::entityTypeManager()->getStorage('node')->create(array(
				  'type'        => 'book',
				  'title'       => $import_row[2],
				  'uid'		=> $batchrow->userID,
				  'field_identifier_local'       => $import_row[1],
				  'field_member_of'       => $bid,
				));
				$node->save();
				$pageID = $node->id();
				$pages[] = $pageID ;

                		$nUUID = $node->uuid() ;
                                echo "this is page UUID $nUUID \n";
                                echo "pageID is $pageID \n";
                                $pagealias = "/" . $nspace . "/" . $nUUID ;
                                echo "this is page pagealias $pagealias \n";
                                $system_path = "/node/" . $pageID ; 
                                $path = \Drupal::service('path.alias_storage')->save($system_path, $pagealias, "en");
                                
                                //call to add media to page & entry to drupal book db
                                $this->add_media_file($pageID, $batchrow, $pageFile, $dir);
                                $this->add_page_entry_to_drupal_db($connection, $pageID, $bid, $weight) ;
                                
                                $urlP = $host . $pagealias;
                                $msg .= "Page image ==> " . $pageFile . "\r\n" ; 
                                $msg .= "Page URL ==> " . $urlP . "\r\n" ; 

			}
							
							//var_dump($pages);
							
		}
												
            }
										
                    //print "email message is: \n $msg \n";
                    $batchrow->close_batch_row($row, $input_dir);
                    $batchrow->batch_submission_email($batchID, $userEmail,$msg);
        }
             
	} else {
		echo "no batches queued for ingest\n";
		die;
	}
		

	} catch (PDOException $e) {
		echo 'Connection failed: ' . $e->getMessage();
	}
		


}
	
    //adding new book info to drupal "book" table to set up the pagination properties
    private function add_book_entry_to_drupal_db($connection, $bid)
    {
        $connection->insert('book')
            ->fields([
                'nid' => $bid,
                'bid' => $bid,
                'pid' => 0,         //might be generalized to allow for books to be members of collections
                'has_children' => 1,
                'weight' => 0,
                'depth' => 1,
                'p1' => $bid,
            ])
            ->execute();
    }
    
    //adding new book page info to drupal "book" table to set up the pagination properties
    private function add_page_entry_to_drupal_db($connection, $pageID, $bid, $weight)
    {
        $connection->insert('book')
            ->fields([
                'nid' => $pageID,
                'bid' => $bid,
                'pid' => $bid,
                'has_children' => 0,
                'weight' => $weight,
                'depth' => 2,
                'p1' => $bid,
                'p2' => $pageID,
            ])
            ->execute();
    }



//add image to book page (book level is page 1)

    private function add_media_file($pageID, $batchrow, $pageFile, $dir)
    {

        //TO DO: code to determine TID of taxonomy and code to determine file usage
        // currently hardcoded to Service File for field_media_use 
        // code to test if date directory exists if not then create
        
//        print "batchrow \n";
//        var_dump($batchrow);
        $fileMedia = $batchrow->asset_path . "/" . $dir . "/" . $pageFile ;
        $pageFname = basename($fileMedia) ;
        $data = file_get_contents($fileMedia);
        
        //TODO: add code to create new directory when month changes
//        $date = date("Y-m");
//        $publicDir = 'public://' . $date . "/" ;
        
        $publicDir = 'public://' ;        
        $destination = $publicDir . $pageFile ;
        print "destination is " . $destination . "\n";

        $fileEnt = file_save_data($data, $destination, FILE_EXISTS_RENAME);
        print "fileMedia is " . $fileMedia . "\n";
        print "Node ID is " . $pageID . "\n";
        print "pageFile " . $pageFile . "\n";
        print "Target ID " . $fileEnt->id() . "\n";   
        print "fileEnt label " . $fileEnt->label() . "\n";   

        //TODO: determine taxonomy id from text name (Service File)
        //also determine bundle from mimetype/extension
        
        $drupalMedia = Media::create([
          'bundle' => 'image',
          'uid' => $batchrow->userID,
          'field_media_of' => $pageID,
          'field_media_use' => 18,
          'field_media_image' => [
            'target_id' => $fileEnt->id(),
            'alt' => 'image of ' . $pageFile,
            'title' => $pageFile,
          ],
        ]);

        $drupalMedia->setName($pageFname)
            ->setPublished(TRUE)
            ->save();								
        
    }
    
    
    
}
