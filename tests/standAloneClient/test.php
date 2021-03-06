<?php


/* --------  Validates arguments -------- */

$exampleMessage = "\tphp " . basename(__FILE__) . " input output\n";
$exampleMessage .= 	"For example\n";
$exampleMessage .= 	"\tphp " . basename(__FILE__) . " in.xml out.xml\n";

if($argc < 3)
{
	echo "Missing attributes, see usage:\n";
	echo $exampleMessage;
	exit;
}

$inFile = $argv[1];
$outFile = $argv[2];

if(!file_exists($inFile))
{
	echo "Input file is missing [$inFile]\n";
	exit;
}

//if(file_exists($outFile))
//{
//	echo "Output file already exists [$outFile]\n";
//	exit;
//}

/* --------  Parse input XML -------- */

$xmlFormat = '<xml>
	<request service="serviceName" action="actionName" plugin="pluginName">
		<itemName1 objectType="KalturaObjectClass1">
			<attr1>value1</attr1>
			<attr2>value2</attr2>
		</itemName1>
		<itemName2 objectType="KalturaObjectClass2">
			<attr1>value1</attr1>
			<attr2>value2</attr2>
		</itemName2>
	</request>
</xml>';

$inXml = new SimpleXMLElement(file_get_contents($inFile));
if($inXml->getName() != 'xml' || !isset($inXml->request))
{
	echo "Input file must match xml format [$xmlFormat]\n";
	exit;
}

function parseInputArray(SimpleXMLElement $items)
{
	$array = array();
	foreach($items as $item)
		$array[] = parseInputObject($item);
		
	return $array;
}

function parseInputObject(SimpleXMLElement $input)
{
	$type = 'string';
	if(isset($input['objectType']))
		$type = strval($input['objectType']);

	switch($type)
	{
		case 'string':
			return strval($input);
			
		case 'int':
			return intval(strval($input));
			
		case 'bool':
			return (bool)(strval($input));
			
		case 'array':
			return parseInputArray($input->item);
	}
	
	if(!class_exists($type))
	{
		echo "Type [$type] could not be found\n";
		exit;
	}
	
	$object = new $type();
	$properties = $input->children();
	foreach($properties as $property)
	{
		/* @var $property SimpleXMLElement */
		$propertyName = $property->getName();
		$object->$propertyName = parseInputObject($property);
	}
	return $object;
}


function generateSession($adminSecretForSigning, $userId, $type, $partnerId, $expiry, $privileges)
{
	$rand = rand(0, 32000);
	$expiry = time()+$expiry;
	$fields = array ( 
		$partnerId , 
		$partnerId , 
		$expiry , 
		$type, 
		$rand , 
		$userId , 
		$privileges 
	);
	$info = implode ( ";" , $fields );

	$signature = sha1($adminSecretForSigning . $info);	 
	$strToHash =  $signature . "|" . $info ;
	$encoded_str = base64_encode( $strToHash );

	return $encoded_str;
}

require_once 'lib/KalturaClient.php';

$config = new KalturaConfiguration();
if(isset($inXml->config))
{
	$configs = $inXml->config->children();
	foreach($configs as $configItem)
	{
		$configItemName = $configItem->getName();
		$config->$configItemName = strval($configItem);
	}
}

$client = new KalturaClient($config);
if(isset($inXml->session))
{
	$partnerId = intval($inXml->session->partnerId);
	$secret = strval($inXml->session->secret);
	$sessionType = intval($inXml->session->sessionType);
	$userId = isset($inXml->session->userId) ? strval($inXml->session->userId) : '';
	$expiry = isset($inXml->session->expiry) ? intval($inXml->session->expiry) : 86400;
	$privileges = isset($inXml->session->privileges) ? strval($inXml->session->privileges) : '';

	$ks = generateSession($secret, $userId, $sessionType, $partnerId, $expiry, $privileges);
	$client->setKs($ks);
}

$results = array();
foreach($inXml->request as $request)
{
	$arguments = array();
	$inputs = $request->children();
	foreach($inputs as $input)
		$arguments[$input->getName()] = parseInputObject($input);
				
	$serviceName = strval($request['service']);
	$actionName = strval($request['action']);
	$pluginName = ucfirst(strval($request['plugin']));
	
	if(isset($pluginName) && $pluginName != '') //get plugin service
	{
		$pluginClass = "Kaltura{$pluginName}ClientPlugin";
		require_once "lib/KalturaPlugins/$pluginClass.php";
		
		$plugin = call_user_func(array($pluginClass, 'get'), $client);
		$service = $plugin->$serviceName;
	}
	else //get core service
	{
		$services = get_object_vars($client);
		if(!isset($services[$serviceName]))
		{
			echo "Service [$serviceName] not found\n";
			exit;
		}
		
		$service = $services[$serviceName];
	}
	
	if(!method_exists($service, $actionName))
	{
		echo "Action [$actionName] not found on service [$serviceName]\n";
		exit;
	}
	
	$result = null;
	try 
	{
		echo "Executing action [$serviceName.$actionName]\n";
		$result = call_user_func_array(array($service, $actionName), $arguments);
	}
	catch(Exception $e)
	{
		echo "Executing failed [" . $e->getMessage() . "]\n";
		$result = $e;
	}
	$results[] = $result;
}




/* --------  Generate output XML -------- */

function appandObject(SimpleXMLElement $outXml, $object, $name)
{
	$objectXml = null;
	
	$type = gettype($object);
	if($type == 'array')
	{
		$objectXml = $outXml->addChild($name);
		$objectXml->addAttribute('objectType', 'array');
		foreach($object as $arrayItem)
			appandObject($objectXml, $arrayItem, 'item');
	}
	elseif($type == 'object')
	{
		$objectXml = $outXml->addChild($name);
		$objectXml->addAttribute('objectType', get_class($object));
		$attributes = get_object_vars($object);
		foreach($attributes as $attributeName => $attributes)
			appandObject($objectXml, $attributes, $attributeName);
	}
	else
	{
		$objectXml = $outXml->addChild($name, $object);
	}
}

$outXml = new SimpleXMLElement('<xml/>');
$outXmlResult = $outXml->addChild('output');

foreach($results as $result)
	appandObject($outXmlResult, $result, 'response');

$outXml->saveXML($outFile);