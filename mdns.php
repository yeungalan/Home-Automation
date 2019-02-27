<?php
$x = new mDNS();
$x->query("");

$response = [];
for($i=0;$i<=10;$i++){
	$info = [];
	$data = $x->read();
	print_r($x->prase($data));
	//print_r($data);
	sleep(1);
}


class mDNS{
	private $socket;
	public function __construct() {
		$this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
		socket_set_option($this->socket, IPPROTO_IP, MCAST_JOIN_GROUP, array('group' => '224.0.0.251', 'interface' => 0));
		socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 1, "usec" => 0));
		socket_bind($this->socket, "0.0.0.0", 5353);
    }
	
	public function prase($data){
		$info = [];
		$info["TransactionID"] = dechex($data[0]).dechex($data[1]);
		$info["Flags"] = dechex($data[2]).dechex($data[3]);
		$info["Questions"] = dechex($data[4]).dechex($data[5]);
		$info["AnswerRRs"] = dechex($data[6]).dechex($data[7]);
		$info["AuthorityRRs"] = dechex($data[8]).dechex($data[9]);
		$info["AdditionalRRs"] = dechex($data[10]).dechex($data[11]);
		
		$CurrPos = 12;
		if($info["Questions"] > 0){
			//TODO: add recuvisvly
			$arr = [];
			$i = 0 ;
			while($i < $info["Questions"]){
				array_push($arr,$this->parseQuestion($CurrPos,$data));
				$CurrPos = $arr[$i]["NextFunctionReadFrom"] ;
				$i = $i + 1;
			}
			$info["Query"] = $arr;
		}
		if($info["AnswerRRs"] > 0){
			//TODO: add recuvisvly
			$arr = [];
			$i = 0 ;
			while($i < $info["AnswerRRs"]){
				array_push($arr,$this->parseAnswer($CurrPos,$data));
				$CurrPos = $arr[$i]["NextFunctionReadFrom"] ;
				$i = $i + 1;
			}
			$info["Answer"] = $arr;
		}
		if($info["AdditionalRRs"] > 0){
			//TODO: add recuvisvly
			$arr = [];
			$i = 0 ;
			while($i < $info["AdditionalRRs"]){
				array_push($arr,$this->parseAdditional($CurrPos,$data));
				$CurrPos = $arr[$i]["NextFunctionReadFrom"] ;
				$i = $i + 1;
			}
			$info["Additional"] = $arr;
		}
		return $info;
	}
	
	public function parseAdditional($pos,$data){
		//$pos = real Position on packet
		//$currentStrPosition = relative position on packet, which calc start = 0
		
		//for calc the _tcp_google_cast.local
		$info = [];
		$currentStrPosition = $pos + 2;
		
		$info["Type"] = hexdec(dechex($data[$currentStrPosition]).dechex($data[$currentStrPosition + 1]));
		$currentStrPosition = $currentStrPosition + 2;
		
		$info["Class"] = hexdec(dechex($data[$currentStrPosition]).dechex($data[$currentStrPosition + 1]));
		$currentStrPosition = $currentStrPosition + 2;
		
		$info["TTL"] = hexdec(dechex($data[$currentStrPosition]).dechex($data[$currentStrPosition + 1]).dechex($data[$currentStrPosition + 2]).dechex($data[$currentStrPosition + 3]));
		$currentStrPosition = $currentStrPosition + 4;
		
		$info["DataLength"] = hexdec(dechex($data[$currentStrPosition]).dechex($data[$currentStrPosition + 1]));
		$currentStrPosition = $currentStrPosition + 2;
		
		// 16 = TXT
		// 33 = SRV
		// 01 = A
		if($info["Type"] == 16){
			$TXTArr = [];
			$BeforeIterationStrPosition = $currentStrPosition;
			while($currentStrPosition < $BeforeIterationStrPosition + $info["DataLength"]){
				$currentTXTLength = $data[$currentStrPosition];
				$tmp = "";
				for($i = $currentStrPosition + 1;$i <= $currentStrPosition + $currentTXTLength;$i++){
					$tmp = $tmp.chr($data[$i]);
				}
				array_push($TXTArr,array("value" => $tmp,"length" => $currentTXTLength));
				$currentStrPosition = $currentStrPosition + $currentTXTLength + 1;
			}
			$info["Record"]["TXT"] = $TXTArr;
		}else if($info["Type"] == 33){
			$info["Record"]["SRV"]["Priority"] = hexdec(dechex($data[$currentStrPosition]).dechex($data[$currentStrPosition + 1]));
			$currentStrPosition = $currentStrPosition + 2;
			
			$info["Record"]["SRV"]["Weight"] = hexdec(dechex($data[$currentStrPosition]).dechex($data[$currentStrPosition + 1]));
			$currentStrPosition = $currentStrPosition + 2;
			
			$info["Record"]["SRV"]["Port"] = hexdec(dechex($data[$currentStrPosition]).dechex($data[$currentStrPosition + 1]));
			$currentStrPosition = $currentStrPosition + 2;
			
			for($i = $currentStrPosition + 1;$i <= $currentStrPosition + $info["DataLength"] - 9;$i++){
				if($data[$i]== 5 || $data[$i] == 4){
					$info["Record"]["SRV"]["Target"] = $info["Record"]["SRV"]["Target"].".";
				}else{
					$info["Record"]["SRV"]["Target"] = $info["Record"]["SRV"]["Target"].chr($data[$i]);
				}
			}
			$info["Record"]["SRV"]["Target"] = $info["Record"]["SRV"]["Target"].".local";
			$currentStrPosition = $currentStrPosition + $info["DataLength"] - 6;
		}else if($info["Type"] == 1){
			$info["Record"]["A"]["IP"] = $data[$currentStrPosition].".".$data[$currentStrPosition + 1].".".$data[$currentStrPosition + 2].".".$data[$currentStrPosition + 3];
			$currentStrPosition = $currentStrPosition + $info["DataLength"];
		}else if($info["Type"] == 12){
			for($i = $currentStrPosition + 1;$i < $currentStrPosition + $info["DataLength"] - 1;$i++){
			if($data[$i]== 5 || $data[$i] == 4){
				$info["DomainName"] = $info["DomainName"].".";
			}else if($data[$i] > 31 && $data[$i] < 127){
				$info["DomainName"] = $info["DomainName"].chr($data[$i]);
			}
		}
		}else{
			$currentStrPosition = $currentStrPosition + $info["DataLength"];
		}
		$info["NextFunctionReadFrom"] = $currentStrPosition;
		return $info;
	}
	
	public function parseAnswer($pos,$data){
		//$pos = real Position on packet
		//$currentStrPosition = relative position on packet, which calc start = 0
		
		//for calc the _tcp_google_cast.local
		$info = [];
		$currentStrPosition = $pos; 
		$flag = false;
		for($i = $currentStrPosition;$i <= sizeof($data);$i++){
			if($flag == false){
				if($data[$i] == 5){ // .
					if($data[$i + 1] == 108){ // l
						if($data[$i + 2] == 111){ // o
							if($data[$i + 3] == 99){ // c
								if($data[$i + 4] == 97){ // a
									if($data[$i + 5] == 108){ // l
										if($data[$i + 6] == 0){ // terminator
											$currentStrPosition = $i + 6;
											$flag = true;
										}
									}
								}
							}
						}
					}
				}
			}
		}
		for($i = $pos;$i < $currentStrPosition;$i++){
			if($data[$i]== 5 || $data[$i] == 4){
				$info["Name"] = $info["Name"].".";
			}else{
				$info["Name"] = $info["Name"].chr($data[$i]);
			}
		}
		$currentStrPosition = $currentStrPosition + 1;
		
		$info["Type"] = hexdec(dechex($data[$currentStrPosition]).dechex($data[$currentStrPosition + 1]));
		$currentStrPosition = $currentStrPosition + 2;
		
		$info["Class"] = hexdec(dechex($data[$currentStrPosition]).dechex($data[$currentStrPosition + 1]));
		$currentStrPosition = $currentStrPosition + 2;
		
		$info["TTL"] = hexdec(dechex($data[$currentStrPosition]).dechex($data[$currentStrPosition + 1]).dechex($data[$currentStrPosition + 2]).dechex($data[$currentStrPosition + 3]));
		$currentStrPosition = $currentStrPosition + 4;
		
		$info["DataLength"] = hexdec(dechex($data[$currentStrPosition]).dechex($data[$currentStrPosition + 1]));
		$currentStrPosition = $currentStrPosition + 2;
		
		// 16 = TXT
		// 33 = SRV
		// 01 = A
		if($info["Type"] == 16){
			$TXTArr = [];
			$BeforeIterationStrPosition = $currentStrPosition;
			while($currentStrPosition < $BeforeIterationStrPosition + $info["DataLength"]){
				$currentTXTLength = $data[$currentStrPosition];
				$tmp = "";
				for($i = $currentStrPosition + 1;$i <= $currentStrPosition + $currentTXTLength;$i++){
					$tmp = $tmp.chr($data[$i]);
				}
				array_push($TXTArr,array("value" => $tmp,"length" => $currentTXTLength));
				$currentStrPosition = $currentStrPosition + $currentTXTLength + 1;
			}
			$info["Record"]["TXT"] = $TXTArr;
		}else if($info["Type"] == 33){
			$info["Record"]["SRV"]["StartLength"] = $currentStrPosition;
			
			$info["Record"]["SRV"]["Priority"] = hexdec(dechex($data[$currentStrPosition]).dechex($data[$currentStrPosition + 1]));
			$currentStrPosition = $currentStrPosition + 2;
			
			$info["Record"]["SRV"]["Weight"] = hexdec(dechex($data[$currentStrPosition]).dechex($data[$currentStrPosition + 1]));
			$currentStrPosition = $currentStrPosition + 2;
			
			$info["Record"]["SRV"]["Port"] = hexdec(dechex($data[$currentStrPosition]).dechex($data[$currentStrPosition + 1]));
			$currentStrPosition = $currentStrPosition + 2;
			
			$info["Record"]["SRV"]["Middle"] = $currentStrPosition;
			for($i = $currentStrPosition + 1;$i <= $currentStrPosition + $info["DataLength"] - 8;$i++){
				if($data[$i]== 5 || $data[$i] == 4){
					$info["Record"]["SRV"]["Target"] = $info["Record"]["SRV"]["Target"].".";
				}else{
					$info["Record"]["SRV"]["Target"] = $info["Record"]["SRV"]["Target"].chr($data[$i]);
				}
			}
			$currentStrPosition = $currentStrPosition - 6;
		}else if($info["Type"] == 1){
			$info["Record"]["A"]["IP"] = $data[$currentStrPosition].".".$data[$currentStrPosition + 1].".".$data[$currentStrPosition + 2].".".$data[$currentStrPosition + 3];
			$currentStrPosition = $currentStrPosition + $info["DataLength"];
		}else if($info["Type"] == 12){
			for($i = $currentStrPosition + 1;$i < $currentStrPosition + $info["DataLength"] - 1;$i++){
				if($data[$i]== 5 || $data[$i] == 4){
					$info["DomainName"] = $info["DomainName"].".";
				}else if($data[$i] > 31 && $data[$i] < 127){
					$info["DomainName"] = $info["DomainName"].chr($data[$i]);
				}
			}
		}else{
			$currentStrPosition = $currentStrPosition + $info["DataLength"];
		}
		
		$currentStrPosition = $currentStrPosition + $info["DataLength"];
		
		$info["NextFunctionReadFrom"] = $currentStrPosition;
		return $info;
	}
	
	public function parseQuestion($pos,$data){
		//$pos = real Position on packet
		//$currentStrPosition = relative position on packet, which calc start = 0
		
		//for calc the _tcp_google_cast.local
		$info = [];
		$currentStrPosition = $pos; 
		$flag = false;
		for($i = $currentStrPosition;$i <= sizeof($data);$i++){
			if($flag == false){
				if($data[$i] == 5){ // .
					if($data[$i + 1] == 108){ // l
						if($data[$i + 2] == 111){ // o
							if($data[$i + 3] == 99){ // c
								if($data[$i + 4] == 97){ // a
									if($data[$i + 5] == 108){ // l
										if($data[$i + 6] == 0){ // terminator
											$currentStrPosition = $i + 6;
											$flag = true;
										}
									}
								}
							}
						}
					}
				}
			}
		}
		for($i = $pos;$i < $currentStrPosition;$i++){
			if($data[$i]== 5 || $data[$i] == 4){
				$info["Name"] = $info["Name"].".";
			}else if($data[$i] > 31 && $data[$i] < 127){
				$info["Name"] = $info["Name"].chr($data[$i]);
			}
		}
		
		$currentStrPosition = $currentStrPosition + 1;
		$info["Type"] = hexdec(dechex($data[$currentStrPosition].$data[$currentStrPosition + 1]));
		
		$currentStrPosition = $currentStrPosition + 2;
		$info["Class"] = hexdec(dechex($data[$currentStrPosition].$data[$currentStrPosition + 1]));
		
		$currentStrPosition = $currentStrPosition + 2;
		$info["NextFunctionReadFrom"] = $currentStrPosition;
		return $info;
	}
	
	public function read(){
        $response = "";
        try {
            $response = socket_read($this->socket, 1024, PHP_BINARY_READ);
        } catch (Exception $e) {
        }

        if (strlen($response) < 1) {
            return "";
        }
		
        $bytes = [];
        for ($x = 0; $x < strlen($response); $x++) {
            array_push($bytes, ord(substr($response, $x, 1)));
        }

        return $bytes;
	}
	
	public function query($name){
		$b = json_decode('[16,152,0,0,0,1,0,0,0,0,0,0,11,95,103,111,111,103,108,101,99,97,115,116,4,95,116,99,112,5,108,111,99,97,108,0,0,12,0,1]');
		for ($x = 0; $x < sizeof($b); $x++) {
			$data .= chr($b[$x]);
		}
		socket_sendto($this->socket, $data, strlen($data), 0, '224.0.0.251', 5353);
	}
	
}
?>
