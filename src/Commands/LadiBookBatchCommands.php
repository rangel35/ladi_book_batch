<?php

namespace Drupal\ladi_book_batch\Commands ;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\context\ContextManager;
use Drupal\context\ContextReactionPluginBase;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseNotFoundException;
use Drupal\Core\Database\Driver\mysql\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\http_client_manager\HttpClientInterface;
use Drupal\ladi_book_batch\BatchEntry;
use Drupal\ladi_book_batch\Email;
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
use Drupal\image\Entity\ImageStyle;
    
use Drupal\islandora\ContextProvider\NodeContextProvider;
use Drupal\islandora\ContextProvider\MediaContextProvider;
use Drupal\islandora\ContextProvider\FileContextProvider;
use Drupal\islandora\ContextProvider\TermContextProvider;
use Exception;


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
    use LoggerChannelTrait;

    function __construct()
    {
        $this->email = new Email();
    }

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
	
        //local hostname
        $host = 'http://localhost:8000/';
		//local upload directory
        $input_dir = '/staging/ASSETS';

        //Non-processing filenames or directories for use in project
        $illNames = array(".", "..", "Thumbs.db", ".DS_Store") ;
        
		$this->output()->writeln('Hello from inside batchIngest.');
        $this->getLogger('ladi_book_batch')->info("Batch Ingest Started");

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
                   
					$dirs = scandir($batchrow->asset_path) ;	                    
					var_dump($dirs);

                    if (empty($dirs)) {
                        $msg .= "\r\nDir == " . $batchrow->asset_path . " is empty or does not exist. Please resubmit your batch request or contact an administrator for more information \n\r" ;
                    }
                    
                    foreach($dirs as $dir) {
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
                        //trim any whitespace from around the headers
                        $headers = array_map('trim', $headers);
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
                            //trim any whitespace from around the csv entries
                            $import_row = array_map('trim', $import_row);
                            
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
                            
                            $partner = "/" . strtolower($nspace) ;
                            $partnerNode = \Drupal::service('path.alias_manager')->getPathByAlias($partner);
                            preg_match('/node\/(\d+)/', $partnerNode, $p_matches) ;
                            $partnerID = $p_matches[1];
                            
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
                                        'field_repository' => $partnerID,   
                                        'field_model' => $taxonomyID,
                                        'field_weight' => $weight,
                                        'field_namespace' => strtoupper($nspace),
                                    );                               
                                    echo "these are the autoEntries entries\n";
                                    var_dump($autoEntries);
                                    
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
                                    $path = \Drupal::service('path.alias_storage')->save($system_path, $bookalias, "und");

                                    //reads first file in array for adding media
                                    $pageFile = $files[0] ;
                                    //call to set up book in drupal databases
                                    $this->add_book_entry_to_drupal_db($connection, $bid);
                                    $mediafile = $this->add_media_file($connection, $bid, $batchrow, $pageFile, $dir);
                                    print "media ID is " . $mediafile . "\n";
                                    $this->genTN($mediafile);

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
                                        'field_repository' => $partnerID,   
                                        'field_model' => $taxonomyID,
                                        'field_namespace' => strtoupper($nspace),
                                    );                               
                                    
                                    echo "these are the autoEntries entries\n";
                                    var_dump($autoEntries);

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
                                    $path = \Drupal::service('path.alias_storage')->save($system_path, $itemAlias, "und");
                                    
                                    $pageFile = $import_row[1] ;
                                    print ("pagefile is $pageFile \n");
                                    
                                    //call to add media to objects
                                    $mediafile=$this->add_media_file($connection, $bid, $batchrow, $pageFile, $dir);
                                    print "media ID is " . $mediafile . "\n";
                                    $this->genTN($mediafile);

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
                                    
                                    $autoEntries = array( 
                                        'type'  => 'ladi_content',
                                        'uid' => $batchrow->userID,
                                        'field_member_of' => $colID,   
                                        'field_repository' => $partnerID,   
                                        'field_model' => $taxonomyID,
                                        'field_weight' => $weight,
                                        'field_namespace' => strtoupper($nspace),
                                    );                               
                                    
                                    if ( $weight > 1) {
                                        $partOf = $docId;
                                        $autoEntries["field_part_of"] = $partOf; 
                                    }

                                    echo "these are the autoEntries entries\n";
                                    var_dump($autoEntries);

                                    $csv_entries = $this->getcsventries($connection,$autoEntries,$import_row,$batchLang,$headers,$dups);
                                    echo "these are the full csv entries\n";
                                    var_dump($csv_entries);

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
                                    $path = \Drupal::service('path.alias_storage')->save($system_path, $bookalias, "und");

                                    $pageFile = array_shift($files) ;

                                    //call to add media to objects
                                    $mediafile = $this->add_media_file($connection, $bid, $batchrow, $pageFile, $dir);
                                    print "media ID is " . $mediafile . "\n";
                                    $this->genTN($mediafile);
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
                                        'field_namespace' => strtoupper($nspace),
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
                                    $path = \Drupal::service('path.alias_storage')->save($system_path, $pagealias, "und");

                                    //call to add media to page & entry to drupal book db
                                    $this->add_media_file($connection, $pageID, $batchrow, $pageFile, $dir);
                                    $this->add_page_entry_to_drupal_db($connection, $pageID, $bid, $weight) ;

                                    $urlP = $host . $pagealias;
                                    $msg .= "Page image ==> " . $pageFile . "\r\n" ; 
                                    $msg .= "Page URL ==> " . $urlP . "\r\n" ; 

                            }
							
						}
												
                    }
										
                    $batchrow->close_batch_row($row, $input_dir);
                    $this->email->batch_submission_email($batchID, $userEmail,$msg);
                }
                
			} else {
				echo "no batches queued for ingest\n";
				die;
			}
		

		} catch (PDOException $e) {
			$msg = $e->getMessage();
            echo 'Connection failed: ' . $msg;
            $this->getLogger('ladi_book_batch')->error("Batch Ingest Error: {$msg}");
            $this->email->send_error_email("Batch Ingest Error: {$msg}");
		}
		
		
	
	}
	
    //adding new book info to drupal "book" table to set up the pagination properties
    private function add_book_entry_to_drupal_db($connection, $bid)
    {
        try {
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
        } catch (Exception $e) {
			$msg = $e->getMessage();
            $this->getLogger('ladi_book_batch')->error("Failed to add Book {$bid} to Drupal DB: {$msg}");
            $this->email->send_error_email("Failed to add Book {$bid} to Drupal DB: {$msg}");
		} 
    }
    
    //adding new book page info to drupal "book" table to set up the pagination properties
    private function add_page_entry_to_drupal_db($connection, $pageID, $bid, $weight)
    {
        try {
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
        } catch (Exception $e) {
			$msg = $e->getMessage();
            $this->getLogger('ladi_book_batch')->error("Failed to add Page {$pageID} to Drupal DB: {$msg}");
            $this->email->send_error_email("Failed to add Page {$pageID} to Drupal DB: {$msg}");
		} 
    }
    
    // create metadata entries from csv input
    protected function setdefaults($connections,$node) {
        print ("Hi from setdefaults \n");
        
        $accessRights['en'] = "This electronic resource is made available by the University of Texas Libraries solely for the purposes of research, teaching and private study. Formal permission to reuse or republish this content must be obtained from the copyright holder.";
        $accessRights['es'] = "University of Texas Libraries provee accesso a este material electrónico solamente para la investigación y la enseñanza. Es necesario pedir permiso del/la autor/a para usar o publicarlo.";
        //$accessRights['pt-br'] = "";

        $rightsStmt['en'] = "All intellectual property rights are retained by the legal copyright holders. The University of Texas does not hold the copyright to the content of this file." ;
        $rightsStmt['es'] = "Todos los derechos de propriedad intelectual pertenece al/la autor/a legal. University of Texas Libraries no tiene los derechos de propriedad intelectual para este material." ;
        //$rightsStmt['pt-br'] = "" ;

        try {

            if ($node && !$node->hasTranslation('es')) {
                $entity_array = $node->toArray();
                $translated_fields = [];
                $translated_fields['field_rights_statement'] = $rightsStmt['es'];
                $translated_fields['field_access_condition'] = $accessRights['es'];

                $translated_entity_array = array_merge($entity_array, $translated_fields);
                $node->addTranslation('es', $translated_entity_array)->save();
            } elseif ($node && !$node->hasTranslation('en')) {
                $entity_array = $node->toArray();
                $translated_fields = [];
                $translated_fields['field_rights_statement'] = $rightsStmt['en'];
                $translated_fields['field_access_condition'] = $accessRights['en'];

                $translated_entity_array = array_merge($entity_array, $translated_fields);
                $node->addTranslation('en', $translated_entity_array)->save();
            }

        } catch (\Exception $e) {
            watchdog_exception('myerrorid', $e);
    }
    
    }
    // create metadata entries from csv input
    protected function getcsventries($connection,$csvEntries,$import_row,$batchLang,$headers,$dups) {
        print ("Hi from getcsventries \n");
        var_dump($import_row);
        var_dump($headers);

        try {
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
            if ( $batchLang == "pt-br" ) {
                for ($i = $start; $i <= $num; $i++) {
                    if (!empty($headers[$i]) && !empty($import_row[$i])) {
                        print ("$headers[$i] has value $import_row[$i] \n");
                        //finds taxonomy from text for country of orgin
                        $import_row[$i] = trim($import_row[$i]);                    
                        
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

                        //using TID in csv for adding taxonomies with translations
                        if ( ($headers[$i] == "field_genre") || ($headers[$i] == "field_dcterms_language") || ($headers[$i] == "field_resource_type") || ($headers[$i] == "field_subject_topic") || ($headers[$i] == "field_physical_location") || (strpos($headers[$i], 'field_geographic_') !== false) ) {
                        $import_row[$i] = (int)$import_row[$i];
                        }

                        //finds node ID from text for ladi partners
                        if ($headers[$i] == "field_repository") {
                            continue;
                        }
                        
                        if (in_array($headers[$i], $dups)) {
                            $csvEntries[$headers[$i]][] =  $import_row[$i] ;
                        } else {
                            $csvEntries[$headers[$i]] =  $import_row[$i] ; 
                        }

                    }
                }
            } else {
        for ($i = $start; $i <= $num; $i++) {
            if (!empty($headers[$i]) && !empty($import_row[$i])) {
                print ("$headers[$i] has value $import_row[$i] \n");
                //finds taxonomy from text for country of orgin
                $import_row[$i] = trim($import_row[$i]);                    

                        if (strpos($headers[$i], 'field_physical_location') !== false) {
                    $taxonomyID = $this->gettaxonomy($connection,$import_row[$i], "geo_country",$batchLang);
                    echo "the TID is " . $taxonomyID . "\n";
                    $import_row[$i] = $taxonomyID;
                }
                //finds taxonomy from text for subject geographic
                if (strpos($headers[$i], 'field_geographic_') !== false) {
                            $taxName = preg_replace('/^field_geographic_/', 'geo_', $headers[$i]);
                            $taxonomyID = $this->gettaxonomy($connection,$import_row[$i], $taxName, $batchLang);
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

                        //finds node ID from text for ladi partners
                        if ($headers[$i] == "field_repository") {
                            continue;
                        }

                        //finds taxonomy from text for ladi languages
                        if ($headers[$i] == "field_dcterms_language") {
                        $import_row[$i] = ucfirst(strtolower($import_row[$i]));
                        $taxonomyID = $this->gettaxonomy($connection,$import_row[$i], "ladi_languages", $batchLang);
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
            }
            
            
        return $csvEntries ;

        } catch (Exception $e) {
			$msg = $e->getMessage();
            $this->getLogger('ladi_book_batch')->error("Failed to get Metadata Entries from CSV File: {$msg}");
            $this->email->send_error_email("Failed to get Metadata Entries from CSV File: {$msg}");
		} 
    }
    
/**
    * format for linked agent
    * [
    *   ['target_id' => 1, 'rel_type' => 'relators:pbl'],
    *   ['target_id' => 2, 'rel_type' => 'relators:ctb'],
    * ]
*/
    protected function gettypedrelation($connection,$agent,$batchLang) {
        
        try {
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

        } catch (Exception $e) {
			$msg = $e->getMessage();
            $this->getLogger('ladi_book_batch')->error("Failed to get typed relation: {$msg}");
            $this->email->send_error_email("Failed to get typed relation: {$msg}");
		} 
    }
    
    protected function gettaxonomy($connection,$name,$vid,$batchLang) {
        try {
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
        } catch (Exception $e) {
			$msg = $e->getMessage();
            $this->getLogger('ladi_book_batch')->error("Failed to get taxonomy: {$msg}");
            $this->email->send_error_email("Failed to get taxonomy: {$msg}");
		} 
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
    try {
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

    } catch (Exception $e) {
        $msg = $e->getMessage();
        $this->getLogger('ladi_book_batch')->error("Failed to term id: {$msg}");
        $this->email->send_error_email("Failed to term id: {$msg}");
    } 
  }

    
    //add image to book page (book level is page 1)
    private function add_media_file($connection, $pageID, $batchrow, $pageFile, $dir)
    {
        try {
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

//since using fedora for publicDir check to see if can remove $date variable functions above
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

            $tid = $this->getTidByName($connection, 'Service File', 'islandora_media_use'); 
            $otherTID = $this->getTidByName($connection, 'Intermediate File', 'islandora_media_use'); 
                
            if ( (strtolower($mediaType) == "png") || (strtolower($mediaType) == "gif") || (strtolower($mediaType) == "jpg") || (strtolower($mediaType) == "jpeg") ) {
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

            } elseif ( (strtolower($mediaType) == "jp2") || (strtolower($mediaType) == "tif") || (strtolower($mediaType) == "tiff") ) {
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
                    'bundle' => 'file',
                'uid' => $batchrow->userID,
                'field_media_of' => $pageID,
                    'field_media_use' => $otherTID,
                'field_media_image' => [
                    'target_id' => $fileEnt->id(),
                ],
            ]);
            
        }

        $drupalMedia->setName($pageFname)
            ->setPublished(TRUE)
            ->save();
        
            $mediaID = $drupalMedia->id() ;
            var_dump($mediaID);
            return $mediaID;

        } catch (Exception $e) {
            $msg = $e->getMessage();
            $this->getLogger('ladi_book_batch')->error("Failed to add media file: {$msg}");
            $this->email->send_error_email("Failed to add media file: {$msg}");
        } 
    }

    /**
     * Turn url path into node id
     * @param string $item_path /CIDCA/ce8642a8-ebef-46b9-83c6-5ae153f86a6e
     * @command ladi_book_batch:getNodeID
     * @aliases lbb-get-node-id
     * @usage ladi_book_batch:getNodeID [name]
     */
   public function getNodeID($item_path) {

       $node_path = \Drupal::service('path.alias_manager')->getPathByAlias($item_path);
       preg_match('/node\/(\d+)/', $node_path, $matches);
       $nodeID = $matches[1]; (edited);
       print($nodeID);
    }
    
        
  /**
   * Generates thumbnails for all ingested objects.
   *
   */
	private function genTN($mid = NULL) {
        printf("inside genTN function--the mid is $mid\n") ;
        $thumbnailID = $this->getTidByName($connection, 'Thumbnail Image', 'islandora_media_use'); 
        $image_style_name = 'thumbnail';
        $destination_path = 'public://thumbnails/' ;
        print ("my mid is " . $mid . PHP_EOL) ;
        try {
            $connection = \Drupal\Core\Database\Database::getConnection();

            $account = \Drupal\user\Entity\User::load(1);
            $accountSwitcher = \Drupal::service('account_switcher');
            $userSession = new UserSession([
                'uid' => $account->id(),
                'name'=>$account->getUsername(),
                'roles'=>$account->getRoles()
            ]);
            $accountSwitcher->switchTo($userSession);
            $user = \Drupal::currentUser();
            var_dump($mid);
            $media = Media::load($mid);
            $fid = $media->field_media_image->target_id;
            $file = File::load($fid);

            $url = $file->getFileUri() ;
            
            //determine filename
            $filenamePieces = explode("/", $url);
            $filename = array_pop($filenamePieces);
            $filenameP2 = explode(".", $filename) ;
            $filename = array_shift($filenameP2) ;
            
             // Load the image style.
            $style = \Drupal::entityTypeManager()
              ->getStorage('image_style')
              ->load($image_style_name);

            // Get the styled image derivative.
            $destination = $destination_path . $filename . "-Thumbnail.jpg" ;
                        
            // If the derivative doesn't exist yet (as the image style may have been
            // added post launch), create it.
            if (!file_exists($destination)) {
                var_dump($url);
                var_dump($destination);
                $style->createDerivative($url, $destination);
            }
        
            $data = file_get_contents($destination);
        
            $path_parts = pathinfo($destination);
            $pageFname = $path_parts['basename'] ;
            $mediaType = $path_parts['extension'] ;

            print "destination is " . $destination . "\n";
        
            $fileEnt = file_save_data($data, $destination, FILE_EXISTS_REPLACE);
            print "Target ID " . $fileEnt->id() . "\n";   
            print "fileEnt label " . $fileEnt->label() . "\n";   
            
            $result = $connection->query("SELECT field_media_of_target_id FROM media__field_media_of WHERE entity_id=:mid", [':mid' => $mid]);    

            if ($result) {
                while ($row = $result->fetchAssoc()) {
                    $nodeID = $row['field_media_of_target_id'] ;
            
                }
            }

            $drupalMedia = Media::create([
              'bundle' => 'image',
                'uid' => 1,
                'field_media_of' => $nodeID,
                'field_media_use' => $thumbnailID,
              'field_media_image' => [
                    'target_id' => $fileEnt->id(),
                    'alt' => 'image of ' . $pageFname,
                    'title' => $filename . '-Thumbnail.jpg',
              ],
            ]);

            $drupalMedia->setName($pageFname)
                ->setPublished(TRUE)
                ->save();


        } catch (PDOException $e) {
            $msg = $e->getMessage();
            echo 'Connection failed: ' . $msg;
            $this->getLogger('ladi_book_batch')->error("Failed to get thumbnail: {$msg}");
            $this->email->send_error_email("Failed to get thumbnail: {$msg}");
        }
        
	}


  /**
   * Mass Generating thumbnails for all service files in system without thumbnails.
   *
   * @command ladi_book_batch:genTN
   * @aliases lbb-genTN
   * @usage ladi_book_batch:genTN
   */
	public function TN_findAllmid(){
        $connection = \Drupal\Core\Database\Database::getConnection();

        $account = \Drupal\user\Entity\User::load(1);
        $accountSwitcher = \Drupal::service('account_switcher');
        $userSession = new UserSession([
            'uid' => $account->id(),
            'name'=>$account->getUsername(),
            'roles'=>$account->getRoles()
        ]);
        $accountSwitcher->switchTo($userSession);

        //TODO: this returns only about a third of the nodes for ladi_content...this means that 2/3s are not assigned the model "ladi_content" and will need to be corrected at some point before switching to the next version of islandora 8  --- $result = $connection->query("select entity_id from node__field_model where bundle='ladi_content'");

        //use the table node to return all nids with type ladi_content for more accurate count
        $result1 = $connection->query("select nid from node where type='ladi_content'");
        $array1 = array();
        $count = 0;
        if ($result1) {
            
            while ($row = $result1->fetchAssoc()) {	
                $count = $count + 1;
                sleep(rand(2, 5));
                $nid= $row['nid'];

                $result2 = $connection->query("select entity_id from media__field_media_of where field_media_of_target_id=:nid", [':nid' => $nid]);
                $mediaID = $result2->fetchAssoc()['entity_id'];
                if ($result2){
                    if ($mediaID) {
                        print ("mediaId exists as: " . $mediaID . PHP_EOL) ;
                        $something = $this->genTN($mediaID);
                        if ($something) {
                            printf("Thumbnail generated for '%s' at %s\n", $row['nid'], date(DATE_ATOM));
                        }
        } else {
                        $count = $count -1 ;
                        print ("mediaID doesn't exist for node: " . $nid . PHP_EOL) ;
                    }
        }
            }
        }
        print ("\n\na total of " . $count . " thumbnails were processed\n") ;
    }

  /**
   * Generates a report for the content of a collection (mupi01, cidca01, etc)
   *
   * @param string $collection
   *   Argument provided to the drush command.
   *
   * @command ladi_book_batch:report
   * @aliases lbb-report
   * @options arr An option that takes collection .
   * @usage ladi_book_batch:report [collection]
   */
	public function report($collection = NULL) {
		if ($collection) {
            $allCols = array("cirma01" => "CIRMA", "mupi01" => "MUPI", "mupi02" => "MUPI", "cidca01" => "CIDCA", "frc01" => "AJEP", "pcn01" => "PCN", "eaacone01" => "EAACONE");
            
            if (!array_key_exists($collection, $allCols)) {
                die("not a valid collection");  // TODO: find clean exit
            }   
			$this->output()->writeln("Content report for collection ==> " . $collection);
            $filename = "content_report_" . $collection . "_" . date("Y-m-d") . "_" . date("h:i:sa") . ".csv" ;
            $csvpath = '/staging/reports/' . $filename ;
            $csvArr = array( 
                array("DrupalID", "Title", "URL Alias", "field_identifier_local", "uuid") 
            ); 
            try {
                $connection = \Drupal\Core\Database\Database::getConnection();

                $account = \Drupal\user\Entity\User::load(1);
                $accountSwitcher = \Drupal::service('account_switcher');
                $userSession = new UserSession([
                    'uid' => $account->id(),
                    'name'=>$account->getUsername(),
                    'roles'=>$account->getRoles()
                ]);
                $accountSwitcher->switchTo($userSession);

                $alias = "/" . $collection ;
                $node_path = \Drupal::service('path.alias_manager')->getPathByAlias($alias);
                preg_match('/node\/(\d+)/', $node_path, $matches) ;
                $colID = $matches[1]; 
                echo ("collection ID is " . $colID . "\n") ;

                $result = $connection->query("SELECT entity_id FROM node__field_member_of WHERE field_member_of_target_id=:cid", [
                    ':cid' => $colID,
                ]);
                
                $entARR = array();
                $entities = array();
                
                if ($result) {
                    //print "inside result if \n" ;
                    while ($row = $result->fetchAssoc()) {
                        $entARR[] = $row['entity_id'] ;
                        $entities[] = $row['entity_id'] ;
                    }
    }
    
        
                foreach ($entARR as $ent) {
                    $resultN = $connection->query("SELECT nid FROM book WHERE bid = :bid", [':bid' => $ent,]);    
                    while ($row = $resultN->fetchAssoc()) {
                        $nodId = $row['nid'] ;
                        if (!in_array($nodId, $entities)) {
                            $entities[] = $nodId ;
                        }
        
                    }
        
                }
                
                sort($entities);
                foreach ($entities as $entity_id) {

                    $node = \Drupal\node\Entity\Node::load($entity_id);
                    $path = $node->toUrl()->toString();
        
                    $title = $node->label();
                    $drupalId = $node->id();
                    $uuid = $node->uuid();
                    $url_alias = "https://ladi.lib.utexas.edu/" . $allCols[$collection] . "/" . $uuid ;
                    $field_identifier_local = $node->get('field_identifier_local')->value;  
                    $csvArr[] = array($drupalId, $title, $url_alias, $field_identifier_local, $uuid) ;
                }
                
                $output = "\n";
                
                $fp = fopen($csvpath, 'w');

                foreach ($csvArr as $fields) {
                    fputcsv($fp, $fields);
                }

                fclose($fp);
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                echo 'Connection failed: ' . $msg;
                $this->getLogger('ladi_book_batch')->error("Failed generate report: {$msg}");
                $this->email->send_error_email("Failed generate report: {$msg}");
            }
                
		} else {
            $this->output()->writeln("Collection is required for generating a report. drush lbb-report [collection]");
        }
        
        
	}
    
  /**
   * Generates a report for the terms defined in a taxonomy plus translations for cleanup
   *
   * @param string $taxonomyName
   *   Argument provided to the drush command.
   *
   * @command ladi_book_batch:taxReport
   * @aliases lbb-taxreport
   * @options arr An option that takes vocabulary machine name .
   * @usage ladi_book_batch:taxReport [vocab_name]
   */
	public function taxReport($taxonomyName = NULL) {
        if ($taxonomyName) {

            $filename = "content_report_" . $taxonomyName . "_" . date("Y-m-d") . "_" . date("h:i:sa") . ".csv" ;
            $csvpath = '/staging/reports/' . $filename ;
            $csvArr = array( 
                array("TermID", "English translation", "Spanish translation", "Portuguese translation", "Term URL") 
        );

            $vocabularies = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple();

            $vocab_array = array();
            foreach ($vocabularies as $k => $v) {
                $vocab_array[] = $k;
            }

            if (in_array($taxonomyName, $vocab_array)) {
                $vid = $taxonomyName;

                $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid);
                foreach ($terms as $term) {
                    $term_obj = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($term->tid);
                    $term_data[] = [
                        'tid' => $term->tid,
                        'tname' => $term->name,
                    ];
                }
                try {
                    $connection = \Drupal\Core\Database\Database::getConnection();
        
                    $account = \Drupal\user\Entity\User::load(1);
                    $accountSwitcher = \Drupal::service('account_switcher');
                    $userSession = new UserSession([
                        'uid' => $account->id(),
                        'name'=>$account->getUsername(),
                        'roles'=>$account->getRoles()
                    ]);
                    $accountSwitcher->switchTo($userSession);

                    $result = $connection->query("SELECT tid, langcode FROM taxonomy_term_data WHERE vid=:vid", [
                        ':vid' => $vid,
                    ]);
                
                    $term_lang = array();
                
                    if ($result) {
                        while ($row = $result->fetchAssoc()) {
                            $term_lang[$row['tid']] = $row['langcode'] ;
                        }
                    }
                } catch (PDOException $e) {
                    $msg = $e->getMessage();
                    echo 'Connection failed: ' . $msg;
                    $this->getLogger('ladi_book_batch')->error("Connection failure inside taxReport: {$msg}");
                    $this->email->send_error_email("Connection failure inside taxReport: {$msg}");
                }
                
                foreach ($term_data as $k => $t) {
                    $lang = $term_lang[$t['tid']] ;
                    $term_data[$k]['tlang'] = $lang ;
                }
                
                $tax_rep = array();
                foreach ($term_data as $k => $t) {
                    $termTid = $t['tid'] ;
                    $termLang = $t['tlang'] ;
                    $termName = $t['tname'] ;
                    $tax_rep[$termTid]['tid'] = $termTid ;
                    $tax_rep[$termTid]['tlang'] = $termLang ;
                    $tax_rep[$termTid]['tname_'.$termLang] = $termName ;
                    $tax_rep[$termTid]['url'] = "https://ladi-p01.lib.utexas.edu/taxonomy/term/" . $termTid ;
        
                    $result2 = $connection->query("SELECT tid, vid, name, langcode FROM taxonomy_term_field_data WHERE tid=:tid", [
                        ':tid' => $termTid,
                    ]);
                    
                    if ($result2) {
                        //print "inside result if \n" ;
                        while ($row = $result2->fetchAssoc()) {
                            if ($row['langcode'] == 'es') {
                                $tax_rep[$termTid]['tname_es'] = $row['name'] ;
                            }
                            if ($row['langcode'] == 'en') {
                                $tax_rep[$termTid]['tname_en'] = $row['name'] ;
                            }
                            if ($row['langcode'] == 'pt-br') {
                                $tax_rep[$termTid]['tname_pt-br'] = $row['name'] ;
                            }

                        }
                    }                    
                                        
                }
                foreach($tax_rep as $k => $t) {
                    $csvArr[] = array($t['tid'], $t['tname_en'], $t['tname_es'], $t['tname_pt-br'], $t['url']) ;
                }
                var_dump($csvArr);
                $fp = fopen($csvpath, 'w');
        
                foreach ($csvArr as $fields) {
                    fputcsv($fp, $fields);
                }

                fclose($fp);
            } else {
                $this->output()->writeln("Taxonomy name is not valid");
            }

        } else {
            $this->output()->writeln("Taxonomy name is required for generating a report. drush lbb-taxreport [taxonomy_name]");
        }
	}
        
    /**
   * 
   * @param string $collection
   *   Argument provided to the drush command.
   *
   * @command ladi_book_batch:fixIt
   * @aliases lbb-fixIt
   * @options arr An option that takes collection .
   * @usage ladi_book_batch:fixIt [collection]
*/
	public function fixIt($collection = NULL) {
        $connection = \Drupal\Core\Database\Database::getConnection();

        $account = \Drupal\user\Entity\User::load(1);
        $accountSwitcher = \Drupal::service('account_switcher');
        $userSession = new UserSession([
            'uid' => $account->id(),
            'name'=>$account->getUsername(),
            'roles'=>$account->getRoles()
        ]);
        $accountSwitcher->switchTo($userSession);
        
        $genTN = array("92431", "92432", "92433", "92434", "92435", "92436", "92437", "92438", "92439", "92440", "92441", "92442", "92443", "92444", "92445", "92446", "92447", "92448", "92449", "92450", "92451", "92452", "92453", "92454", "92455", "92456", "92457", "92458", "92459", "92460", "92461", "92462", "92463", "92464", "92465", "92466", "92467", "92468", "92469", "92470", "92471", "92472", "92473", "92474", "92475", "92476", "92477", "92478", "92479", "92480", "92481", "92482", "92483", "92484", "92485", "92486", "92487", "92488", "92489", "92490", "92491", "92492", "92493", "92494", "92495", "92496", "92497", "92498", "92499", "92500", "92501", "92502", "92503", "92504", "92505", "92506", "92507", "92508", "92509", "92510", "92511", "92512", "92513", "92514", "92515", "92516", "92517", "92518", "92519", "92520", "92521", "92522", "92523", "92524", "92525", "92526", "92527", "92528", "92529", "92530", "92531", "92532", "92533", "92534", "92535", "92536", "92537", "92538", "92539", "92540", "92541", "92542", "92543", "92544", "92545", "92546", "92547", "92548", "92549", "92550", "92551", "92552", "92553", "92554", "92555", "92556", "92557", "92558", "92559", "92560", "92561", "92562", "92563", "92564", "92565", "92566", "92567", "92568", "92569", "92570", "92571", "92572", "92573", "92574", "92575", "92576", "92577", "92578", "92579", "92580", "92581", "92582", "92583", "92584", "92585", "92586", "92587", "92588", "92589", "92590", "92591", "92592", "92593", "92594", "92595", "92596", "92597", "92598", "92599", "92600", "92601", "92602", "92603", "92604", "92605", "92606", "92607", "92608", "92609", "92610", "92611", "92612", "92613", "92614", "92615", "92616", "92617", "92618", "92619", "92620", "92621", "92622", "92623", "92624", "92625", "92626", "92627", "92628", "92629", "92630", "92631", "92632", "92633", "92634", "92635", "92636", "92637", "92638", "92639", "92640", "92641", "92642", "92643", "92644", "92645", "92646", "92647", "92648", "92649", "92650", "92651", "92652", "92653", "92654", "92655", "92656", "92657", "92658", "92659", "92660", "92661", "92662", "92663", "92664", "92665", "92666", "92667", "92668", "92669", "92670", "92671", "92672", "92673", "92674", "92675", "92676", "92677", "92678", "92679", "92680", "92681", "92682", "92683", "92684", "92685", "92686", "92687", "92688", "92689", "92690", "92691", "92692", "92693", "92694", "92695", "92696", "92697", "92698", "92699", "92700", "92701", "92702", "92703", "92704", "92705", "92706", "92707", "92708", "92709", "92710", "92711", "92712", "92713", "92714", "92715", "92716", "92717", "92718", "92719", "92720", "92721", "92722", "92723", "92724", "92725", "92726", "92727", "92728", "92729", "92730", "92731", "92732", "92733", "92734", "92735", "92736", "92737", "92738", "92739", "92740", "92741", "92742", "92743", "92744", "92745", "92746", "92747", "92748", "92749", "92750", "92751", "92752", "92753", "92754", "92755", "92756", "92757", "92758", "92759", "92760", "92761", "92762", "92763", "92764", "92765", "92766", "92767", "92768", "92769", "92770", "92771", "92772", "92773", "92774", "92775", "92776", "92777", "92778", "92779", "92780", "92781", "92782", "92783", "92784", "92785", "92786", "92787", "92788", "92789", "92790", "92791", "92792", "92793", "92794", "92795", "92796", "92797", "92798", "92799", "92800", "92801", "92802", "92803", "92804", "92805", "92806", "92807", "92808", "92809", "92810", "92811", "92812", "92813", "92814", "92815", "92816", "92817", "92818", "92819", "92820", "92821", "92822", "92823", "92824", "92825", "92826", "92827", "92828", "92829", "92830", "92831", "92832", "92833", "92834", "92835", "92836", "92837", "92838", "92839", "92840", "92841", "92842", "92843", "92844", "92845", "92846", "92847", "92848", "92849", "92850", "92851", "92852", "92853", "92854", "92855", "92856", "92857", "92858", "92859", "92860", "92866", "92867", "92868", "92869", "92870", "92871", "92872", "92873", "92874", "92875", "92876", "92877", "92878", "92879", "92880", "92881", "92882", "92883", "92884", "92885", "92886", "92887", "92888", "92889", "92890", "92904", "92909", "93322", "93325", "93328", "93332", "93337", "93342", "93345", "93348", "93351", "93354", "93358", "93362", "93365", "93369", "93375", "93383", "93388", "93398", "93401", "93404", "93407", "93410", "93413", "93416", "93419", "93572", "93656", "93667", "93679", "93707", "93713", "93735", "93970", "93974", "94012", "94118", "94199", "94219", "94517", "94525", "94734", "94735", "94736", "94737", "94738", "94739", "94740", "94741", "94742", "94743", "94744", "94745", "94746", "94747", "94748", "94749", "94750", "94751", "94752", "94753", "94754", "94755", "94756", "94757", "94758", "94759", "94760", "94761", "94762", "94763", "94764", "94765", "94766", "94767", "94768", "94769", "94770", "94771", "94772", "94773", "94774", "94775", "94776", "94777", "94778", "94779", "94780", "94781", "94782", "94783", "94784", "94785", "94786", "94787", "94788", "94789", "94790", "94791", "94792", "94793", "94794", "94795", "94796", "94797", "94798", "94799", "94800", "94801", "94802", "94803", "94804", "94805", "94806", "94807", "94808", "94809", "94810", "94811", "94812", "94813", "94814", "94815", "94816", "94817", "94818", "94819") ;
        $count = 0;
        foreach($genTN as $nid) {	
            $count = $count + 1;
            sleep(rand(2, 5));
            $result2 = $connection->query("select entity_id from media__field_media_of where field_media_of_target_id=:nid", [':nid' => $nid]);
            $mediaID = $result2->fetchAssoc()['entity_id'];
            if ($result2){
                if ($mediaID) {
                    print ("mediaId exists as: " . $mediaID . PHP_EOL) ;
                    $something = $this->genTN($mediaID);
                    printf("Thumbnail generated for '%s' at %s\n", $row['nid'], date(DATE_ATOM));
                } else {
                    $count = $count -1 ;
                    print ("mediaID doesn't exist for node: " . $nid . PHP_EOL) ;
                }
            }
        }

        print ("\n\na total of " . $count . " thumbnails were processed\n") ;
    }
 
}
