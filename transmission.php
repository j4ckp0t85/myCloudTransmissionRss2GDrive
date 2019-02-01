<?php

class Transmission
{
    private $server;
    private $user;
    private $password;
    private $session_id;

    public function __construct($server, $port, $rpcPath , $user , $password )
    {
        $this->server = $server.':'.$port.$rpcPath;
        $this->user = $user;
        $this->password = $password;
        $this->session_id = $this->getSessionId();
    }

    public function add($url, $isEncode = false, $options = array())
    {
        return $this->request('torrent-add', array_merge($options, array(
            $isEncode ? 'metainfo' : 'filename' => $url,
        )));
    }
	
    public function status()
    {
        return $this->request("session-stats");
    }
	
	public function torrentStatus($i){
		return $this->request("torrent-get", array(
            'fields' => array("percentDone","id", "name", "totalSize","uploadRatio"),
			'ids' => array($i)
        ));		
	}
	
	public function torrentRemove($i){
		return $this->request("torrent-remove", array(
			'ids' => array($i)
        ));		
	}

    public function getSessionId()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->server);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->user.':'.$this->password);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $content = curl_exec($ch);
        curl_close($ch);
        preg_match("/<code>(X-Transmission-Session-Id: .*)<\/code>/", $content, $content);
        $this->session_id = isset($content[1]) ? $content[1] : null;

        return $this->session_id;
    }

    private function request($method, $arguments = array())
    {
        $data = array(
            'method' => $method,
            'arguments' => $arguments
        );

        $header = array(
            'Content-Type: application/json',
            'Authorization: Basic '.base64_encode(sprintf("%s:%s", $this->user, $this->password)),
            $this->session_id
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->server);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->user.':'.$this->password);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $content = curl_exec($ch);
        curl_close($ch);

        if (!$content)  $content = json_encode(array('result' => 'failed'));
        return $content;

    }

    function getRssItems($rss)
    {
	    
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $items = array();
        foreach ($rss as $link) {
            curl_setopt($ch, CURLOPT_URL, $link);
            $content = curl_exec($ch);
            
            if (!$content) continue;
			
            $xml = new DOMDocument();
			
			file_put_contents('/tmp/xml.xml', $content);         //workaround for charset not supported
			$source_xml_file = file_get_contents('/tmp/xml.xml');
			
			$myXmlString = str_replace('windows-1251', 'utf-8', $source_xml_file); //force charset
			file_put_contents('/tmp/xml.xml', $myXmlString);  
			
			$xml->load('/tmp/xml.xml');  //end of workaround
			
			//alternative if charset supported: $xml->loadXML($content);
			
            $elements = $xml->getElementsByTagName('item');
            
            foreach ($elements as $item) {
	           
                $link = $item->getElementsByTagName('enclosure')->item(0) != null ?
                        $item->getElementsByTagName('enclosure')->item(0)->getAttribute('url') :
                        $item->getElementsByTagName('link')->item(0)->nodeValue;
  
                $guid = $item->getElementsByTagName('guid')->item(0) != null ?
                    $item->getElementsByTagName('guid')->item(0)->nodeValue:
                    md5($link);
          
			    if(((strpos($link,'<torrent_string_search_1>'))<>false) or ((strpos($link,'<torrent_string_search_2>'))<>false)) { //search for specific string in torrent link
				    
                	$items[] = array(
                    	'title' => $item->getElementsByTagName('title')->item(0)->nodeValue,
						'link' => $link,
						'guid' => $guid
						);
				}  
            }
        }
        curl_close($ch);

        return $items;
    }
}
?>