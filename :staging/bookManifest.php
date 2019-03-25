<?php
/**
 * Create book page manifest from file directory
 *
* run commandline 
* php bookManifest.php [batch_directory]  //batch directory of books to add manifest
* 
*/

$batchDir = $argv[1];   // will require changing to accept command line input


//upload directory (staging)
$staging = "./ASSETS/" . $batchDir . "/" ;

$bookDir = array();  //will hold book directories
$books = scandir($staging);


//Non-processing filenames or directories for use in project
$illNames = array(".", "..", "DONE", "Thumbs.db", ".DS_Store", "Cron", "bookManifest.php") ;
 
//determine new book directories
foreach ($books as $bk)
{
    //skip specific files & directories     
    if ( (in_array($bk, $illNames)) || empty($bk) ) {
    	continue;
    } else {
		$bookDir[$bk] = $staging . $bk . "/"  ;
    }
}

if ( empty($bookDir) ) {
    exit("There are no valid book directories in staging area. \n");
} 

//book directories exist; process individual books
foreach ($bookDir as $bname => $bpath) 
{
    $manifest = $bpath . $bname . ".csv" ;
    if (($handle = fopen($manifest, "r")) !== FALSE) {
		while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
			$numFields = count($data);
			echo "$numFields fields in $manifest \n";
		}
		fclose($handle);
	}

    
    echo "manifest is $manifest \n";
    $pages = scandir($bpath);
    sort($pages) ;
//    var_dump($pages);
//    continue;
    $row = array();
    $csv = "\n";
	//$csv = "file,title,field_description\n"; //Column headers need to add additional book metadata
	
	$cnt = 0;
    foreach ($pages as $value) {
        if (in_array($value, $illNames) || (strpos($value, '.ttl') !== false) || (strpos($value, '.csv') !== false) ) { continue; }
		$cnt++;
		echo("$cnt is $value\n") ;
		if ($cnt == 1) {
			$bkcover = $value;
			continue;
		}
        $pageFile = $bpath . $value ;
        $row['file'] = $value ; 
        $path_parts = pathinfo($pageFile);
        $row['field_identifier_local'] = $path_parts['filename'] ;
        $tmp = explode('_',$row['field_identifier_local']) ;
        $pageNum = end($tmp);
        $pageNum = ltrim($pageNum, '0');
        $row['field_title'] = "Page " . $pageNum ;
        
        var_dump($row) ;
        
        $csv .= $row['file'] .',' . $row['field_identifier_local'] .',' . $row['field_title']; //Append data to csv
        $fieldFill = $numFields - 3;
        for ($a=0; $a<$fieldFill; $a++) {
        	$csv .=  "," ;
        }
        $csv .=  "\n";
    }  
    
    file_put_contents($manifest, $csv, FILE_APPEND | LOCK_EX);

    echo "Data saved to $manifest \n";
//    break;
    
}
