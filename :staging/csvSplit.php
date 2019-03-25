<?php
/**
* script to split csv into multiple one book files
* 
* run commandline 
* php csvSplit.php [file_to_split].csv  //csv is name of file to be split
* 
*/

$inputFile = $argv[1];   // will require changing to accept command line input
$tstamp = date("Ymd");
$outputDir = "splitFiles/$tstamp/";   

if ( !file_exists($outputDir) )  {

	if (!mkdir($outputDir, 0777, true)) {
		die('Failed to create folders...');
	}

}

$row = 1;

if (($handle = fopen($inputFile, "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
        $num = count($data);
//        echo "$num fields in line $row \n";
        
        if ($row == 1) {
        	$headers = $data;
        	var_dump($headers);
        } else {
			$headerLine = "";
			$dataLine = "";
			$filename = $outputDir . $data[0] . ".csv";
			for ($i = 0; $i < $num; $i++) {
				if (!(empty($data[$i]))) {
					$headerLine .= ($headers[$i] . ',') ;
					$dataLine .= ('"' . $data[$i] . '",') ;
				}
			}
			$headerLine = rtrim($headerLine, ",");
			$dataLine = rtrim($dataLine, ",");

//			echo "$filename: \n $headerLine: $dataLine\n\n";
			$fp = fopen($filename, 'w');
			fwrite($fp, "file,");
			fwrite($fp, $headerLine);
			fwrite($fp, "\n");
			fwrite($fp, "BOOK,");
			fwrite($fp, $dataLine);
			fclose($fp);			
			
        }
		
		$row++;
       //break;
    }
    fclose($handle);
}

?>
