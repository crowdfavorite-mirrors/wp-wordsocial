<?php
class WSO_SURL
{
	// yourls
	const WSO_API_URL  = 'http://wso.li/yourls-api.php';
	const WSO_API_SIGN = '1abb246e99';
	// bitly
	const BITLY_LOGIN = "akisbis";
	const BITLY_SHORTEN_URL = "http://api.bitly.com/v3/shorten";
	const BITLY_EXPAND_URL = "http://api.bitly.com/v3/expand";
	const BITLY_API_KEY = "R_ec0fccf9e8d056cd8e3a70b2fc733c64";
	
	static public function Shorten($url, $apiUrl = WSO_SURL::WSO_API_URL, $sign = WSO_SURL::WSO_API_SIGN)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $apiUrl);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array(
			'signature' => $sign,
			'url'      => $url,
			'action'   => 'shorturl',
			'format'   => 'simple'
		));
		
		$data = curl_exec($ch);
		curl_close($ch);
		
		if($data === false) return WSO_SURL::Shorten($url);
		
		return $data;
	}
	
	function ShortenBitLy($url, $login = WSO_SURL::BITLY_LOGIN, $apikey = WSO_SURL::BITLY_API_KEY)
	{
		$bitly = WSO_SURL::BITLY_SHORTEN_URL . '?longUrl='.urlencode($url).'&login='.$login.'&apiKey='.$apikey.'&format=json';
		
		$response = file_get_contents($bitly);
		
		$json = json_decode($response, true);
		
		if($json['status_txt'] != "OK") return WSO_SURL::Shorten($url);
		
		return $json['data']['url'];
	}
}

?>