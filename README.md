Note: for study purpose only, tested on a my cloud mirror Gen2. Piracy is not encouraged.

This script is written in php and is based on

    Transmission rpc (the client is available to install from the My Cloud Dashboard)
  
    Google Drive php REST api
  
It fetches RSS from one or more RSS sources, add torrents to the Transmission client running on the nas and upload finished torrent to Google Drive user space (root or a specific folder).

Resume supported. 

Approach used: for small files (max 256M) load direcly in memory and upload, for bigger files use chunks approach.

Usage. 

1) Enable Google Drive API from Google dev. console

2) Create the credentials.json file (as specified in the quickstart document) download and place it in the root folder of this project 

3) Connect using an ssh client to the nas as root user and type
                 
        php rss.php
  
The first time the script will generate an url: copy it and paste in a browser. Login with your Google account and allow the app for permissions. A code will be generated, copy it and paste in the terminal. A new file will be created to grant access to google drive space (token.json)

The script can be launched as a cron job (search on the net how to do it).
