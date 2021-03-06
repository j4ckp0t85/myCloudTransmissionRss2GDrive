<?php

ini_set ('memory_limit','256M');

require __DIR__ . '/vendor/autoload.php';

if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName('Google Drive API PHP WDMyCloud');
    $client->setAuthConfig('/mnt/HD/HD_a2/<path_to_program_folder>/credentials.json');
    $client->setScopes('https://www.googleapis.com/auth/drive');
    
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    // Load previously authorized token from a file, if it exists.
    // The file token.json stores the user's access and refresh tokens, and is
    // created automatically when the authorization flow completes for the first
    // time.
    $tokenPath = '/mnt/HD/HD_a2/<path_to_program_folder>/token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    // If there is no previous token or it's expired.
    if ($client->isAccessTokenExpired()) {
        // Refresh the token if possible, else fetch a new one.
        // Refresh the token if possible, else fetch a new one.
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            // Check to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
        }
        // Save the token to a file.
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }
    return $client;
}

function createFolder($service,$parentFolderId,$folderName) {
	$fileMetadata = new Google_Service_Drive_DriveFile(array(
		'parents'=>array($parentFolderId),	
		'name' => $folderName,
		'mimeType' => 'application/vnd.google-apps.folder')
		);
	$file = $service->files->create($fileMetadata, array(
		'fields' => 'id'));
	//printf("Folder ID: %s\n", $file->id); 	
	return $file->id;
}

function uploadToRoot($service,$file){
	$fileMetadata = new Google_Service_Drive_DriveFile(array('name' => $file));
	$content = file_get_contents('/mnt/HD/HD_a2/<transmission_download_dir>/'.$file);
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$mime_type = finfo_file($finfo, '/mnt/HD/HD_a2/<transmission_download_dir>/'.$file);
	$file = $service->files->create($fileMetadata, array(
		'data' => $content,
		'mimeType' => $mime_type,
		'uploadType' => 'multipart',     
		'fields' => 'id'));
	//printf("File ID: %s\n", $file->id);
	unset($content);
	unset($finfo);
	unset($mime_type);
	unset($file);
}

function uploadToFolder($service,$folderId,$folderName,$file){	
	$fileMetadata = new Google_Service_Drive_DriveFile(array(
		'name' => $file,
		'parents' => array($folderId)
	));

	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	
	if($folderName===''){
		$content = file_get_contents('/mnt/HD/HD_a2/<transmission_download_dir>/'.$file);
		$mime_type = finfo_file($finfo, '/mnt/HD/HD_a2/<transmission_download_dir>/'.$file);
	}
	else {
		$content = file_get_contents('/mnt/HD/HD_a2/<transmission_download_dir>/'.$folderName.'/'.$file);
		$mime_type = finfo_file($finfo, '/mnt/HD/HD_a2/<transmission_download_dir>/'.$folderName.'/'.$file);
	}
	//$mime_type = finfo_file($finfo, '/mnt/HD/HD_a2/<transmission_download_dir>/'.$file);
	$file = $service->files->create($fileMetadata, array(
		'data' => $content,
		'mimeType' => $mime_type,
		'uploadType' => 'multipart',
		'fields' => 'id'));
	//printf("File ID: %s\n", $file->id);
	unset($content);
	unset($finfo);
	unset($mime_type);
	unset($file);
}

function uploadLargeToRoot($client,$service,$largeFile)
{    	
	 $file = new Google_Service_Drive_DriveFile();
	 $file->name=$largeFile;
	 $chunkSizeBytes = 10 * 1024 * 1024;

    // Call the API with the media upload, defer so it doesn't immediately return.
    $client->setDefer(true);
    $request = $service->files->create($file);

    // Create a media file upload to represent our upload process.
    $media = new Google_Http_MediaFileUpload(
                      $client,
                      $request,
                      mime_content_type('/mnt/HD/HD_a2/<transmission_download_dir>/'.$largeFile),
                      null,
                      true,
                      $chunkSizeBytes
    );
    $fileSize=exec('stat -c %s "'.'/mnt/HD/HD_a2/<transmission_download_dir>/'.$largeFile.'"');
    $media->setFileSize($fileSize);
    // Upload the various chunks. $status will be false until the process is
    // complete.
    $status = false;
    $handle = fopen('/mnt/HD/HD_a2/<transmission_download_dir>/'.$largeFile, "rb");
    while (!$status && !feof($handle)) {
	    			  
	    			  //$executionStartTime = microtime(true);
                      $chunk = readChunk($handle, $chunkSizeBytes);
                      $status = $media->nextChunk($chunk);
                      /*$executionEndTime = microtime(true);
					  $seconds = $executionEndTime - $executionStartTime;
					  $progress=$media->getProgress();
					  $percentageDone=round((($progress/$fileSize)*100),2);
                      $mbUploaded=((float)($progress))/1048576;
                      $ulSpeed=round(10/((float)$seconds),2);
                      printf ("(".$percentageDone."%%) uploaded ".$mbUploaded." MB at ".$ulSpeed." MB/s \n"); */
    }

    // The final value of $status will be the data from the API for the object
    // that has been uploaded.
    $result = false;
    if($status != false) {
                      $result = $status;                

    }


    fclose($handle);
    // Reset to the client to execute requests immediately in the future.
    $client->setDefer(false);	
	unlink("/tmp/torrent.lock");
	//printf("File ID: %s\n", $result->id);
	
}

function uploadLargeToFolder($client,$service,$folderId,$largeFile)
{
     $file = new Google_Service_Drive_DriveFile(array(
		'name' => $largeFile,
		'parents' => array($folderId)
	 ));
	 $chunkSizeBytes = 10 * 1024 * 1024;

    // Call the API with the media upload, defer so it doesn't immediately return.
    $client->setDefer(true);
    $request = $service->files->create($file);

    // Create a media file upload to represent our upload process.
    $media = new Google_Http_MediaFileUpload(
                      $client,
                      $request,
                      mime_content_type('/mnt/HD/HD_a2/<transmission_download_dir>/'.$largeFile),
                      null,
                      true,
                      $chunkSizeBytes
    );
    $fileSize=exec('stat -c %s "'.'/mnt/HD/HD_a2/<transmission_download_dir>/'.$largeFile.'"');
   
    $media->setFileSize($fileSize);
    // Upload the various chunks. $status will be false until the process is
    // complete.
    $status = false;
    $handle = fopen('/mnt/HD/HD_a2/<transmission_download_dir>/'.$largeFile, "rb");
    while (!$status && !feof($handle)) {
	    			  //$executionStartTime = microtime(true);
                      $chunk = readChunk($handle, $chunkSizeBytes);
                      $status = $media->nextChunk($chunk);
                      /*$executionEndTime = microtime(true);
					  $seconds = $executionEndTime - $executionStartTime;
					  $progress=$media->getProgress();
					  $percentageDone=round((($progress/$fileSize)*100),2);
                      $mbUploaded=((float)($progress))/1048576;
                      $ulSpeed=round(10/((float)$seconds),2);
                      printf ("(".$percentageDone."%%) uploaded ".$mbUploaded." MB at ".$ulSpeed." MB/s \n");    */            
    }

    // The final value of $status will be the data from the API for the object
    // that has been uploaded.
    $result = false;
    if($status != false) {
       $result = $status;                
    }


    fclose($handle);
    // Reset to the client to execute requests immediately in the future.
    $client->setDefer(false);	
	unlink("/tmp/torrent.lock");
	printf("File ID: %s\n", $result->id);
}

function readChunk ($handle, $chunkSize)
{
    $byteCount = 0;
    $giantChunk = "";
    while (!feof($handle)) {
        //$chunk = fread($handle, 8192); 
        $chunk = fread($handle, 1048576); 
        $byteCount += strlen($chunk);
        $giantChunk .= $chunk;
        if ($byteCount >= $chunkSize)
        {
            return $giantChunk;
        }
    }
    return $giantChunk;
}


function listFolders ($service) {	
	$pageToken = null;
	do {
			$response = $service->files->listFiles(array(
			'q' => "mimeType='application/vnd.google-apps.folder'",
			'spaces' => 'drive',
			'pageToken' => $pageToken,
			'fields' => 'nextPageToken, files(id, name)',
		));
		foreach ($response->files as $file) {
			printf("Found file: %s (%s)\n", $file->name, $file->id);
		}

		$pageToken = $response->pageToken;
	} while ($pageToken != null);
}

function listFiles($service) {	
	// Print the names and IDs for up to 10 files.
	$optParams = array(
	  'pageSize' => 10,
	  'fields' => 'nextPageToken, files(id, name)'
	);
	$results = $service->files->listFiles($optParams);

	if (count($results->getFiles()) == 0) {
		print "No files found.\n";
	} else {
		print "Files:\n";
		foreach ($results->getFiles() as $file) {
			printf("%s (%s)\n", $file->getName(), $file->getId());
		}
	}		
}

function uploadLargeToFolderResumable ($client,$service,$folderId,$folderName,$fileName) 
{
	
	
	$file = new Google_Service_Drive_DriveFile(array(
		'name' => $fileName,
		'parents' => array($folderId)		
	));
	
	
	$client->setDefer(true);
	$chunkSize = 10 * 1024 * 1024;
	
	if($folderName===''){
		$fileSize=exec('stat -c %s "'.'/mnt/HD/HD_a2/<transmission_download_dir>/'.$fileName.'"');
		$mimeType = mime_content_type('/mnt/HD/HD_a2/<transmission_download_dir>/'.$fileName);
	}
	else {
		$fileSize=exec('stat -c %s "'.'/mnt/HD/HD_a2/<transmission_download_dir>/'.$folderName.'/'.$fileName.'"');
		$mimeType = mime_content_type('/mnt/HD/HD_a2/<transmission_download_dir>/'.$folderName.'/'.$fileName);
	}
	
	/*
	$mimeType=mime_content_type('/mnt/HD/HD_a2/<transmission_download_dir>/'.$fileName);
	$fileSize=exec('stat -c %s "'.'/mnt/HD/HD_a2/<transmission_download_dir>/'.$fileName.'"');*/
	
	$request = $service->files->create(
	    $file,
	    [
	        "mimeType" => $mimeType,
	        "uploadType" => "resumable",
	        'fields' => 'id'
	    ]
	);
	
	$media = new Google_Http_MediaFileUpload($client, $request, $mimeType, null, true, $chunkSize);
    $media->setFileSize($fileSize);
	$media->setChunkSize($chunkSize);
	
	$resumeUri=$media->getResumeUri();
	
	$upload=array(
		'name' => $fileName,
		'resumeUri' => $resumeUri,
		'folder'=>base64_encode($folderName),
		'folderId'=>$folderId
	);
	
	file_put_contents("resumableupload.json",json_encode($upload));
	
	try {
		$status = false;
		if($folderName===''){
			$handle = fopen('/mnt/HD/HD_a2/<transmission_download_dir>/'.$fileName, "rb");
		}
		else {
			$handle = fopen('/mnt/HD/HD_a2/<transmission_download_dir>/'.$folderName.'/'.$fileName, "rb");
		}
		$k = 1;
		while (!$status && !feof($handle)) {
		    $chunk = fread($handle, $chunkSize);
		    try {
		        $status = $media->nextChunk($chunk);
		        //print("byte ".$media->getProgress(). " \n");
		    } catch (Exception $e) {
		        //echo 'error - ' . $e->getMessage();
		    }
		}
		$result = false;
		if ($status != false) {
		    $result = $status;
			unlink($resumableJsonPath.'resumableupload.json');
			unlink('/tmp/torrent.lock');
		}
		fclose($handle);
		$client->setDefer(false);
		} catch (Exception $e) {
         //print('GoogleDrive error: ' . $e->getMessage());
         unlink('/tmp/torrent.lock');
     }

}

function resumeToFolder ($client,$service) {
	
	$resumableJsonPath='/mnt/HD/HD_a2/<path_to_program_folder>/';
	
	$uploadData=json_decode(file_get_contents($resumableJsonPath.'resumableupload.json')); //fetch data from local json file

	
	$fileName=$uploadData->name;
	$resumeUri=$uploadData->resumeUri;
	$folderName=base64_decode($uploadData->folder);
	$folderId=$uploadData->folderId;
	
	$file = new Google_Service_Drive_DriveFile(array(
		'name' => $fileName,
		'parents' => array($folderId)
	));
	

	$client->setDefer(true);
	$chunkSize = 10 * 1024 * 1024;
	
	if($folderName===''){
		$fileSize=exec('stat -c %s "'.'/mnt/HD/HD_a2/<transmission_download_dir>/'.$fileName.'"');
		$mimeType = mime_content_type('/mnt/HD/HD_a2/<transmission_download_dir>/'.$fileName);
	}
	else {
		$fileSize=exec('stat -c %s "'.'/mnt/HD/HD_a2/<transmission_download_dir>/'.$folderName.'/'.$fileName.'"');
		$mimeType = mime_content_type('/mnt/HD/HD_a2/<transmission_download_dir>/'.$folderName.'/'.$fileName);
	}
	
	/*
	$mimeType=mime_content_type('/mnt/HD/HD_a2/<transmission_download_dir>/'.$fileName);
	$fileSize=exec('stat -c %s "'.'/mnt/HD/HD_a2/<transmission_download_dir>/'.$fileName.'"');
	*/
		
	$request = $service->files->create(
	    $file,
	    [
	        "mimeType" => $mimeType,
	        "uploadType" => "resumable",
	        'fields' => 'id'
	    ]
	);
	

	
	$media = new Google_Http_MediaFileUpload($client, $request, $mimeType, null, true, $chunkSize);

	$media->setFileSize($fileSize);
	$media->setChunkSize($chunkSize);
	
	$upStatus=$media->resume($resumeUri); 
	
	if($upStatus==false){ //false = upload incomplete
		try {
			$status = false;
	
			if($folderName===''){
				$handle = fopen('/mnt/HD/HD_a2/<transmission_download_dir>/'.$fileName, "rb");
			}
			else {
				$handle = fopen('/mnt/HD/HD_a2/<transmission_download_dir>/'.$folderName.'/'.$fileName, "rb");
			}
			fseek($handle, $media->getProgress());   
			
			while (!$status && !feof($handle)) {
		   	 $chunk = fread($handle, $chunkSize);
		   	 
		   	 try {
		        	$status = $media->nextChunk($chunk);
					//print("byte ".$media->getProgress(). " \n");
		    	} catch (Exception $e) {
		        	//echo 'error - ' . $e->getMessage();
		    	}
				
			}
			$result = false;
			if ($status != false) {
		    	$result = $status;
				unlink($resumableJsonPath.'resumableupload.json');
				unlink('/tmp/torrent.lock');
			}
			fclose($handle);
			$client->setDefer(false);
			if($folderName!==''){
					uploadFolder($client,$service,$folderId,$folderName);
			}
				
		}
		catch (Exception $e) {
         	//print('GoogleDrive error: ' . $e->getMessage());
		 	unlink('/tmp/torrent.lock');
     		}
	}
	else {
		unlink($resumableJsonPath.'resumableupload.json'); //upload of this file was already completed
	}	
	
}




function searchFile($service,$folderId,$searchString){
	$pageToken = null;
	$result=array();
	do {
		$response = $service->files->listFiles(array(
			'q' => "'$folderId' in parents and name contains '$searchString' and trashed = false",
			'spaces' => 'drive',
			'pageToken' => $pageToken,
			'fields' => 'nextPageToken, files(id, name, parents)',
		));
		foreach ($response->files as $file) {
			array_push($result,$file->name, $file->id,$file->parents);
		}

		$pageToken = $repsonse->pageToken;
	} while ($pageToken != null);
	return $result;
}

function searchFolder($service,$searchString){
	$pageToken = null;
	$result=array();
	do {
		$response = $service->files->listFiles(array(
			'q' => "mimeType = 'application/vnd.google-apps.folder' and name contains '$searchString' and trashed = false",
			'spaces' => 'drive',
			'pageToken' => $pageToken,
			'fields' => 'nextPageToken, files(id, name, parents)',
		));
		foreach ($response->files as $file) {
			
			array_push($result,$file->name, $file->id, $file->parents);  //0 = folder name, 1=folder id, 2=folder parent id
		}

		$pageToken = $repsonse->pageToken;
	} while ($pageToken != null);
	return $result;
}

function uploadFolder($client,$service,$parentFolderId,$folderName) {
	
	$realFolder=explode("/",$folderName);
	$search=searchFolder($service,$realFolder[sizeof($realFolder)-1]); //if there is a subfolder with the same name of the parent folder, subfolder name will be skipped but all the content will be uploaded
	if((sizeof($search)!==0)) //0 = folder name, 1=folder id, 2=folder parent id
		$folderId=$search[1];
	else
		$folderId=createFolder($service,$parentFolderId,$folderName);
	$files=array_diff(scandir('/mnt/HD/HD_a2/<transmission_download_dir>/'.$folderName),array('.','..'));
	//var_dump($files);
	foreach($files as $file) {
		
		$mimeType=mime_content_type('/mnt/HD/HD_a2/<transmission_download_dir>/'.$folderName.'/'.$file);
		$fileSize=exec('stat -c %s "'.'/mnt/HD/HD_a2/<transmission_download_dir>/'.$folderName.'/'.$file.'"');
		//print($mimeType." ".$fileSize." \n");
		
		//verifica mimetype, se cartella faccio il giro ricorsivo
		//se file verifico la dimensione e chiamo la relativa funzione
		
		if($mimeType==='directory'){
				$parentFolderId=$folderId;
				
				$searchTwo=searchFolder($service,$file);
				
				if(sizeof($searchTwo)===0){ //folder already present? if no, first create it
					$folderIdTwo=createFolder($service,$parentFolderId,$file);
				}
				else{ //folder already created			
						$folderIdTwo=$searchTwo[1];
					}
				
				
				$completePath=$folderName.'/'.$file;
				
				uploadFolder($client,$service,$folderIdTwo,$completePath);
				
		}
		else { //this is a file
			    if(sizeof(searchFile($service,$folderId,$file))===0){ //file already uploaded? if no, upload it
					if($fileSize<=268435456)	{	//upload call for small file
							uploadToFolder($service,$folderId,$folderName,$file);
					}
					else{ //upload call for big file
						file_put_contents("/tmp/torrent.lock",'1'); //avoid other uploads until this one is finished
						uploadLargeToFolderResumable($client,$service,$folderId,$folderName,$file); //start a new big file resumable upload
					}
				}
			}
		
	}
	
	
}

?>