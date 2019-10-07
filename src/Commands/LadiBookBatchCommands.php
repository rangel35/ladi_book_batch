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
use Drupal\Core\Entity\EntityInterface;
    
use Drupal\islandora\ContextProvider\NodeContextProvider;
use Drupal\islandora\ContextProvider\MediaContextProvider;
use Drupal\islandora\ContextProvider\FileContextProvider;
use Drupal\islandora\ContextProvider\TermContextProvider;

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
	
		$host = 'https://ladi-test.lib.utexas.edu';
//		$host = 'https://dams-p01.lib.utexas.edu';
//      $host = 'http://localhost:8000/';

        $input_dir = '/staging/ASSETS';

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
                    $batchLang = $row['batchLang'] ;
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
					var_dump($dirs);

                    if (empty($dirs)) {
                        $msg .= "\r\nDir == " . $batchrow->asset_path . " is empty or does not exist. Please resubmit your batch request or contact an administrator for more information \n\r" ;
                    }
                    
                    foreach($dirs as $dir) {
                        //continue;
                        if ($dir=="." || $dir=="..") {continue;}
                        $this->book_path = $batchrow->asset_path . "/" . $dir ;
                        echo " Ingest Path ===> " . $this->book_path . "\n";
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
                        //print "files \n";
                        //var_dump($files);
                        
                        if (empty($files)) {
                            $msg .= "\r\nDir == " . $dir . "in batch " . $batchID . " is empty or only contains a csv file, so skipping processing, please resubmit in a future batch. \n\r" ;
                            continue;
                        }
                        
                        //note dir name should match csv_file name
                        $msg .= "\r\n \r\n" . "working with Ingest " . $dir . "\r\n"; 
                        $csv_file = $this->book_path . "/" . $dir . ".csv";
                        
                        if (!file_exists($csv_file)) {
                            echo "The file $csv_file does not exist so book $dir not processed \n";
                            $msg .= "The file $csv_file does not exist so book $dir not processed \r\n";
                            continue;
                        }
												
                        echo "The file $csv_file exists \n";

                        $fp = fopen($csv_file, 'r');
                        $headers = fgetcsv($fp, 0, ",") ;
                        
                        var_dump($headers);
                        //dups array is to hold csv repeated headers (for multivalued fields)
                        $dups = array();
                        foreach(array_count_values($headers) as $val => $c) {
                            
                            if (strpos($c, 'rel:') !== false) {
                                continue;
                            }
                            if($c > 1) $dups[] = $val;
                        }
                        $dups[] = "field_linked_agent" ;
        				echo ("multivalued fields in this batch are as follows\n");
                        var_dump($dups);
                        //continue;
                        
                        $weight = 0;
                        $pages = array();  
                        
                        while (($import_row = fgetcsv($fp, 0, ",")) !== FALSE) {
                            //$import_row = array_map("utf8_encode", $import_row); 
                            //echo mb_detect_encoding($import_row);
							$num = count($import_row);
							++$weight ;
                            print("the weight is $weight \n");
							if(preg_match('/node\/(\d+)/', $collection, $matches)) {
                                $colID = $matches[1];                                
                            } else {
                                $alias = "/" . basename($collection) ;
                                $node_path = \Drupal::service('path.alias_manager')->getPathByAlias($alias);
                                preg_match('/node\/(\d+)/', $node_path, $matches) ;
                                $colID = $matches[1]; 
                            }
                            echo "colId is $colID\n";
                            //echo "import row follows\n";
                            //var_dump($import_row);
                            //$batchAllow = array("BOOK", "ITEM", "COMPOSITE")
                            
                            switch ($import_row[0]) {
                                case "BOOK":
                                    echo "Hi from inside a BOOK object. \n" ;
                                    //finds taxonomy from text for Islandora Models
                                    $taxonomyID = $this->getTidByName($connection,"Paged Content", "islandora_models");
                                    //add field entries derived from batch form
                                    //first add automatic fields 
                                    $autoEntries = array( 
                                        'type'  => 'ladi_content',
                                        'uid' => $batchrow->userID,
                                        'field_member_of' => $colID,   
                                        'field_model' => $taxonomyID,
                                        'field_weight' => $weight,
                                    );                               
                                    
                                    $csv_entries = $this->getcsventries($connection,$autoEntries,$import_row,$batchLang,$headers,$dups);
                                    echo "these are the csv entries\n";
                                    var_dump($csv_entries);
                                    
                                    $node = \Drupal::entityTypeManager()->getStorage('node')->create($csv_entries);
									
                                    //saves book node to drupal db
                                    $node->save();

                                    // Set the current node's language.
                                    $node->set('langcode', $batchLang);
                                    // Save the node to persist the change.
                                    $node->save();

                                    $bid = $node->id();                               
                                    echo "this is the bid: $bid \n";
                                    $bookTitle = $csv_entries['title'];
                                    echo "this is the bookTitle: $bookTitle \n";

                                    $nUUID = $node->uuid() ;
                                    echo "this is book UUID $nUUID \n";
                                    $bookalias = "/" . $nspace . "/" . $nUUID ;
                                    echo "this is book bookalias $bookalias \n";
                                    $system_path = "/node/" . $bid ; 
                                    $path = \Drupal::service('path.alias_storage')->save($system_path, $bookalias, "en");

                                    //reads first file in array for adding media
                                    $pageFile = $files[0] ;
                                    //call to set up book in drupal databases
                                    $this->add_book_entry_to_drupal_db($connection, $bid);
                                    $this->add_media_file($connection, $bid, $batchrow, $pageFile, $dir);

                                    $url = $host . $bookalias;
                                    $msg .= "Book image ==> " . $pageFile . "\r\n"; 
                                    $msg .= "Book URL ==> " . $url . "\r\n"; 

                                    break;
                                case "ITEM":
                                    echo "Hi from inside an ITEM object. \n" ;
                                    $taxonomyID = $this->getTidByName($connection,"Digital Document", "islandora_models");
                                    //add field entries derived from batch form
                                    //first add automatic fields 
                                    $autoEntries = array( 
                                        'type'  => 'ladi_content',
                                        'uid' => $batchrow->userID,
                                        'field_member_of' => $colID,   
                                        'field_model' => $taxonomyID,
                                        'field_weight' => $weight,
                                    );                               
                                    
                                    $csv_entries = $this->getcsventries($connection,$autoEntries,$import_row,$batchLang,$headers,$dups);
                                    echo "these are the csv entries\n";
                                    var_dump($csv_entries);
                                    
                                    $node = \Drupal::entityTypeManager()->getStorage('node')->create($csv_entries);
									
                                    //saves node to drupal db
                                    $node->save();

                                    // Set the current node's language.
                                    $node->set('langcode', $batchLang);
                                    // Save the node to persist the change.
                                    $node->save();

                                    $bid = $node->id();                               
                                    echo "this is the bid: $bid \n";

                                    $nUUID = $node->uuid() ;
                                    echo "this is book UUID $nUUID \n";
                                    $itemAlias = "/" . $nspace . "/" . $nUUID ;
                                    echo "this is item alias $itemAlias \n";
                                    $system_path = "/node/" . $bid ; 
                                    $path = \Drupal::service('path.alias_storage')->save($system_path, $itemAlias, "en");
                                    
                                    $pageFile = $import_row[1] ;
                                    print ("pagefile is $pageFile \n");
                                    
                                    //call to add media to objects
                                    $this->add_media_file($connection, $bid, $batchrow, $pageFile, $dir);

                                    $url = $host . $itemAlias;
                                    $msg .= "Single Object image ==> " . $pageFile . "\r\n"; 
                                    $msg .= "Single Object URL ==> " . $url . "\r\n"; 
                                    
                                    
                                    break;
                                case "COMPOSITE":
                                    echo "Hi from inside a COMPOSITE object. \n" ;
                                    //finds taxonomy from text for Islandora Models
                                    $taxonomyID = $this->getTidByName($connection,"Paged Content", "islandora_models");
                                    
                                    print ("weight is $weight \n");
                                    //add field entries derived from batch form
                                    //first add automatic fields 
                                    if ( $weight == 1) {
                                        $mem_of = $colID;
                                    } else {
                                        $mem_of = $docId;
                                    }
                                    
                                    $autoEntries = array( 
                                        'type'  => 'ladi_content',
                                        'uid' => $batchrow->userID,
                                        'field_member_of' => $mem_of,   
                                        'field_model' => $taxonomyID,
                                        'field_weight' => $weight,
                                    );                               
                                    
                                    echo "these are the autoEntries entries\n";
                                    var_dump($autoEntries);

                                    $csv_entries = $this->getcsventries($connection,$autoEntries,$import_row,$batchLang,$headers,$dups);
                                    echo "these are the csv entries\n";
                                    var_dump($csv_entries);
//exit;
                                    $node = \Drupal::entityTypeManager()->getStorage('node')->create($csv_entries);
									
                                    //save node to drupal 
                                    $node->save();

                                    // Set the current node's language.
                                    $node->set('langcode', $batchLang);
                                    // Save the node to persist the change.
                                    $node->save();

                                    $bid = $node->id();                               
                                    echo "this is bid $bid \n";
                                    if ( $weight == 1) {
                                        $docId = $bid;
                                    }

                                    $nUUID = $node->uuid() ;
                                    echo "this is item UUID $nUUID \n";
                                    $bookalias = "/" . $nspace . "/" . $nUUID ;
                                    echo "this is item bookalias $bookalias \n";
                                    $system_path = "/node/" . $bid ; 
                                    $path = \Drupal::service('path.alias_storage')->save($system_path, $bookalias, "en");

                                    $pageFile = array_shift($files) ;

                                    //call to add media to objects
                                    $this->add_media_file($connection, $bid, $batchrow, $pageFile, $dir);
                                    if ( $weight == 1) {
                                        $this->add_book_entry_to_drupal_db($connection, $docId);
                                    } else {
                                        $this->add_page_entry_to_drupal_db($connection, $bid, $docId, $weight) ;
                                    }

                                    $url = $host . $bookalias;
                                    $msg .= "Composite image ==> " . $pageFile . "\r\n"; 
                                    $msg .= "Composite URL ==> " . $url . "\r\n"; 
                                    

                                    break;
                                default:
                                    echo "Hi from inside a generic PAGE. \n" ;
                                    //finds taxonomy from text for Islandora Models
                                    $taxonomyID = $this->getTidByName($connection,"Page", "islandora_models");
                                    
                                    echo "this is weight $weight \n";
                                    $pageTitle = $bookTitle . " - " . $import_row[2] ;
                                    $pageFile = array_shift($files) ;
                                    print "book ID is " . $bid . "\n";

                                    //add field entries derived from batch form
                                    //first add generic book fields 
                                    $csv_entries = array( 
                                        'type'  => 'book',
                                        'title' => $pageTitle,
                                        'uid' => $batchrow->userID,
                                        'field_identifier_local' => $import_row[1],
                                        'field_member_of' => $bid,   
                                        'field_model' => $taxonomyID,
                                        'field_weight' => $weight,
                                    );                               
                                    
                                    echo "these are the csv entries\n";
                                    var_dump($csv_entries);

                                    $node = \Drupal::entityTypeManager()->getStorage('node')->create($csv_entries);

                                    $node->save();

                                    // Set the current node's language.
                                    $node->set('langcode', $batchLang);
                                    // Save the node to persist the change.
                                    $node->save();

                                    $pageID = $node->id();
                                    $pages[] = $pageID ;  //might need for sequencing 

                                    $nUUID = $node->uuid() ;
                                    echo "this is page UUID $nUUID \n";
                                    echo "pageID is $pageID \n";
                                    $pagealias = "/" . $nspace . "/" . $nUUID ;
                                    echo "this is page pagealias $pagealias \n";
                                    $system_path = "/node/" . $pageID ; 
                                    $path = \Drupal::service('path.alias_storage')->save($system_path, $pagealias, "en");

                                    //call to add media to page & entry to drupal book db
                                    $this->add_media_file($connection, $pageID, $batchrow, $pageFile, $dir);
                                    $this->add_page_entry_to_drupal_db($connection, $pageID, $bid, $weight) ;

                                    $urlP = $host . $pagealias;
                                    $msg .= "Page image ==> " . $pageFile . "\r\n" ; 
                                    $msg .= "Page URL ==> " . $urlP . "\r\n" ; 

                            }
							
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
    
    // create metadata entries from csv input
    protected function getcsventries($connection,$csvEntries,$import_row,$batchLang,$headers,$dups) {
        print ("Hi from getcsventries \n");
        var_dump($import_row);
        var_dump($headers);
        $num = count($headers);
        
        switch ($import_row[0]) {
            case "BOOK":
                $start = 1;
                break;
            case "ITEM":
                $start = 2;
                break;
            case "COMPOSITE":
                $start = 2;
                break;
            default:
                $start = 1;
        }
        
        for ($i = $start; $i <= $num; $i++) {
            if (!empty($headers[$i]) && !empty($import_row[$i])) {
                print ("$headers[$i] has value $import_row[$i] \n");
                //finds taxonomy from text for country of orgin
                $import_row[$i] = trim($import_row[$i]);                    
                if (strpos($headers[$i], 'field_origin_place') !== false) {
                    $taxonomyID = $this->gettaxonomy($connection,$import_row[$i], "geo_country",$batchLang);
                    echo "the TID is " . $taxonomyID . "\n";
                    $import_row[$i] = $taxonomyID;
                }
                //finds taxonomy from text for subject geographic
                if (strpos($headers[$i], 'field_geographic_') !== false) {
                    $taxonomyID = $this->gettaxonomy($connection,$import_row[$i], "geo_location",$batchLang);
                    echo "the TID is " . $taxonomyID . "\n";
                    $import_row[$i] = $taxonomyID;
                }

                //finds taxonomy from text for subjects
                if ($headers[$i] == "field_subject_topic") {
                    $taxonomyID = $this->gettaxonomy($connection,$import_row[$i], "subject",$batchLang);
                    echo "the TID is " . $taxonomyID . "\n";
                    $import_row[$i] = $taxonomyID;
                }

                //finds taxonomy from text for organizations
                if ($headers[$i] == "field_foaf_organization") {
                    $taxonomyID = $this->gettaxonomy($connection,$import_row[$i], "corporate_body",$batchLang);
                    echo "the TID is " . $taxonomyID . "\n";
                    $import_row[$i] = $taxonomyID;
                }

                //finds taxonomy from text for person
                if ($headers[$i] == "field_foaf_person") {
                    $taxonomyID = $this->gettaxonomy($connection,$import_row[$i], "person",$batchLang);
                    echo "the TID is " . $taxonomyID . "\n";
                    $import_row[$i] = $taxonomyID;
                }

                //finds taxonomy from text for resource types
                if ($headers[$i] == "field_resource_type") {                                            
                    $import_row[$i] = ucfirst(strtolower($import_row[$i]));
                    $taxonomyID = $this->gettaxonomy($connection,$import_row[$i], "resource_types",$batchLang);
                    echo "the TID is " . $taxonomyID . "\n";
                    $import_row[$i] = $taxonomyID;
                }
                //finds linked agent with roleterm
                if (strpos($headers[$i], 'rel:') !== false) {
                    $import_row[$i] = $import_row[$i] . "|" . $headers[$i] ;
                    $headers[$i] = "field_linked_agent";
                }
                if ($headers[$i] == "field_linked_agent") {                           
                    $typedrelation = $this->gettypedrelation($connection,$import_row[$i],$batchLang);
                    echo "the linked agent is \n";
                    var_dump($typedrelation);
                    $import_row[$i] = $typedrelation;
                }

                  //finds taxonomy from text for genre/format
               if ($headers[$i] == "field_genre") {
                   $import_row[$i] = ucfirst(strtolower($import_row[$i]));
                   $taxonomyID = $this->gettaxonomy($connection,$import_row[$i], "genre",$batchLang);
                   echo "the TID is " . $taxonomyID . "\n";
                   $import_row[$i] = $taxonomyID;
                }

                if (in_array($headers[$i], $dups)) {
                    $csvEntries[$headers[$i]][] =  $import_row[$i] ;
                } else {
                   $csvEntries[$headers[$i]] =  $import_row[$i] ; 
                }

            }
        }
        return $csvEntries ;
    }
    
/**
    * format for linked agent
    * [
    *   ['target_id' => 1, 'rel_type' => 'relators:pbl'],
    *   ['target_id' => 2, 'rel_type' => 'relators:ctb'],
    * ]
*/
    protected function gettypedrelation($connection,$agent,$batchLang) {
        $linkedagent = array() ;
        
        if (!empty($agent)) {
            list($name, $role) = (explode("|",$agent)); 
            $tid = $this->getTidByName($connection, $name, 'ladi_contributors'); 

            if ($tid==0) {
                $term = Term::create(array(
                    'parent' => array(),
                    'name' => $name,
                    'vid' => 'ladi_contributors',
                    'langcode' => $batchLang,
                ));
                $term->save();
            }
        }
        $tid = $this->getTidByName($connection, $name, 'ladi_contributors'); 
        $linkedagent['target_id'] = $tid;
        $linkedagent['rel_type'] = $role ;

        return $linkedagent ;
    }
    
    protected function gettaxonomy($connection,$name,$vid,$batchLang) {
        
        if (!empty($name)) {
            $tid = $this->getTidByName($connection, $name, $vid); 

            if ($tid==0) {
                $term = Term::create(array(
                    'parent' => array(),
                    'name' => $name,
                    'vid' => $vid,
                    'langcode' => $batchLang,
                ));
                $term->save();
            }
        }
        $tid = $this->getTidByName($connection, $name, $vid); 
        return $tid ;
    }
    
    
/**
   * Utility: find term by name and vid.
   * @param null $name
   *  Term name
   * @param null $vid
   *  Term vid
   * @return int
   *  Term id or 0 if none.
   * notes: https://drupal.stackexchange.com/questions/225209/load-term-by-name
   */
  protected function getTidByName($connection, $name = NULL, $vid = NULL) {
    $properties = [];
    if (!empty($name)) {
      $properties['name'] = $name;
    }
    if (!empty($vid)) {
      $properties['vid'] = $vid;
    }
    echo "the properties are\n";
    var_dump($properties);  
    $terms = \Drupal::entityManager()->getStorage('taxonomy_term')->loadByProperties($properties);
    $term = reset($terms);
    
    return !empty($term) ? $term->id() : 0;
  }

    
    //add image to book page (book level is page 1)
    private function add_media_file($connection, $pageID, $batchrow, $pageFile, $dir)
    {

        //TO DO: code to determine TID of taxonomy and code to determine file usage
        // currently hardcoded to Service File for field_media_use 
        // code to test if date directory exists if not then create
        
        $fileMedia = $batchrow->asset_path . "/" . $dir . "/" . $pageFile ;
        $data = file_get_contents($fileMedia);
        
        $path_parts = pathinfo($fileMedia);
        $pageFname = $path_parts['basename'] ;
        $mediaType = $path_parts['extension'] ;

        //TODO: add code to create new directory when month changes
        $date = date("Y-m");
        $year = date("Y");
        $mon = date("m");
        print ("the year is " . $year . " and the month is " . $mon . "\n") ;

        //$publicDir = 'public://'  . $date . "/" ;  
        $publicDir = 'fedora://' ; 
        $destination = $publicDir . $pageFile ;
           
        print "destination is " . $destination . "\n";

        $fileEnt = file_save_data($data, $destination, FILE_EXISTS_RENAME);
        print "fileMedia is " . $fileMedia . "\n";
        print "Node ID is " . $pageID . "\n";
        print "pageFile " . $pageFile . "\n";
        print "Target ID " . $fileEnt->id() . "\n";   
        print "fileEnt label " . $fileEnt->label() . "\n";   

        $tid = $this->getTidByName($connection, 'Original File', 'islandora_media_use'); 
                
        if ( (strtolower($mediaType) == "jp2") || (strtolower($mediaType) == "tif") || (strtolower($mediaType) == "tiff") ) {
            $drupalMedia = Media::create([
                'bundle' => 'file',
                'uid' => $batchrow->userID,
                'field_media_of' => $pageID,
                'field_media_use' => $tid,
                'field_media_image' => [
                    'target_id' => $fileEnt->id(),
                ],
            ]);
        } else {
            $drupalMedia = Media::create([
                'bundle' => 'image',
                'uid' => $batchrow->userID,
                'field_media_of' => $pageID,
                'field_media_use' => $tid,
                'field_media_image' => [
                    'target_id' => $fileEnt->id(),
                    'alt' => 'image of ' . $pageFname,
                    'title' => $pageFile,
                ],
            ]);
            
        }

        $drupalMedia->setName($pageFname)
            ->setPublished(TRUE)
            ->save();
        
//        $jp2=$this->addjp2($pageID, $batchrow, $fileMedia) ;
//        print "jp2 is " . $jp2 .  "\n";

//        $action = $this->runactions($pageID, $batchrow, $fileMedia) ;
//        print ("action returned $action \n") ;

    }
    
    // add jp2 if exists as intermediate file
    protected function addjp2($pageID, $batchrow, $fileMedia) {
         print "Inside addjp2 \n";
        
        //$fileMedia = $batchrow->asset_path . "/" . $dir . "/" . $pageFile ;
        //$pageFname = basename($fileMedia) ;
        //$data = file_get_contents($fileMedia);

        
        $path_parts = pathinfo($fileMedia);
        
        print "dirname is " . $path_parts['dirname'] . "\n";
        print "filename is " . $path_parts['filename'] . "\n";

        $jp2 = $path_parts['dirname'] . "/" . $path_parts['filename'] . ".jp2" ;
        
        if (file_exists($jp2)) {
            
            $date = date("Y-m");

            $publicDir = 'public://' . $date . "/" ;         
            $destination = $publicDir . $path_parts['filename'] . ".jp2"  ;
            $data = file_get_contents($jp2);
            
            print "jp2 destination is " . $destination . "\n";

            $jp2Ent = file_save_data($data, $destination, FILE_EXISTS_RENAME);
            print "jp2 is " . $jp2 . "\n";
            print "Node ID is " . $pageID . "\n";
            print "filename " . $path_parts['filename'] . "\n";
            print "Target ID " . $jp2Ent->id() . "\n";   
            print "jp2Ent label " . $jp2Ent->label() . "\n";   

            //TODO: determine taxonomy id from text name (Service File)

            $drupalMedia = Media::create([
              'bundle' => 'image',
              'uid' => $batchrow->userID,
              'field_media_of' => $pageID,
              'field_media_use' => 15,
              'field_media_image' => [
                'target_id' => $jp2Ent->id(),
                'alt' => 'image of ' . $path_parts['filename'],
                'title' => $pageFile,
              ],
            ]);

            $drupalMedia->setName($pageFname)
                ->setPublished(TRUE)
                ->save();



        } else {
            $jp2 = "does not exist" ;
        }


        return $jp2 ;
    }
    
    //run actions for thumbnail creation and indexing
    protected function runactions($pageID, $batchrow, $fileMedia) {
        print "Inside runactions \n";
        print ("the pageID is " . $pageID . "\n") ;
        
        //$pgnode = \Drupal::service('entity_type.manager')->getStorage('node')->load($pageID);
        
        $pgnode = \Drupal\node\Entity\Node::load($pageID);
        
        print ("the pageID is " . $pgnode->id() . "\n") ;
        print ("generating thumbnail \n");
                
        \Drupal\system\Entity\Action::load('image_generate_a_thumbnail_from_a_service_file')->execute([$pgnode]);

        //$pgnode = \Drupal::service('entity_type.manager')->getStorage('node')->load($pageID);
        //$action = \Drupal::service('entity_type.manager')->getStorage('action')->load('image_generate_a_thumbnail_from_a_service_file');
        //$action->execute([$pgnode]);
        
        print ("indexing to fedora \n");
                
        $action = entity_load_multiple_by_properties(
            'action', array('id' => 'index_node_in_fedora')
        );

        $action['index_node_in_fedora']->execute([$pgnode]);

        return "done" ;
        
/*        
        $action = \Drupal::service('entity_type.manager')->getStorage('action')->load('image_generate_a_thumbnail_from_a_service_file');
        
        $action->execute([$pgnode]);
*/
//    \Drupal\system\Entity\Action::load('image_generate_a_thumbnail_from_a_service_file')->execute($pgnode);
/*
        $action2 = \Drupal::service('entity_type.manager')->getStorage('action')->load('index_node_in_fedora');
        
        $action2->execute([$pgnode]);

        $action3 = \Drupal::service('entity_type.manager')->getStorage('action')->load('index_node_in_triplestore');
        
        $action3->execute([$pgnode]);
*/

    }
 
}
