<?php
class Php53ClientGenerator extends ClientGeneratorFromXml
{
	private $cacheEnums = array();
	private $cacheTypes = array();
	
	/**
	 * @var DOMDocument
	 */
	protected $_doc = null;
	
	function Php53ClientGenerator($xmlPath, $sourcePath = null)
	{
		if(!$sourcePath)
			$sourcePath = realpath("sources/php53");
			
		parent::ClientGeneratorFromXml($xmlPath, $sourcePath);
		$this->_doc = new KDOMDocument();
		$this->_doc->load($this->_xmlFile);
	}
	
	function getSingleLineCommentMarker()
	{
		return '//';
	}
	
	private function cacheEnum(DOMElement $enumNode)
	{
		$enumName = $enumNode->getAttribute('name');
		$enumCacheName = preg_replace('/^Kaltura(.+)$/', '$1', $enumName); 
		
		$classInfo = new PhpZend2ClientGeneratorClassInfo();
		$classInfo->setClassName($enumCacheName);
		if($enumNode->hasAttribute('plugin'))
		{
			$pluginName = ucfirst($enumNode->getAttribute('plugin'));
			$classInfo->setNamespace("Kaltura\\Client\\Plugin\\{$pluginName}\\Enum");
		}
		else
		{
			$classInfo->setNamespace("Kaltura\\Client\\Enum");
		}
		$this->cacheEnums[$enumName] = $classInfo;
	} 
	
	private function cacheType(DOMElement $classNode)
	{
		$className = $classNode->getAttribute('name');
		$classCacheName = preg_replace('/^Kaltura(.+)$/', '$1', $className); 
		
		$classInfo = new PhpZend2ClientGeneratorClassInfo();
		$classInfo->setClassName($classCacheName);
		if($classNode->hasAttribute('plugin'))
		{
			$pluginName = ucfirst($classNode->getAttribute('plugin'));
			$classInfo->setNamespace("Kaltura\\Client\\Plugin\\{$pluginName}\\Type");
		}
		else
		{
			$classInfo->setNamespace("Kaltura\\Client\\Type");	
		}
		$this->cacheTypes[$className] = $classInfo;
	} 
	
	function generate() 
	{
		parent::generate();
	
		$xpath = new DOMXPath($this->_doc);
		
		$enumNodes = $xpath->query("/xml/enums/enum");
		foreach($enumNodes as $enumNode)
			$this->cacheEnum($enumNode);
			
		$classNodes = $xpath->query("/xml/classes/class");
		foreach($classNodes as $classNode)
			$this->cacheType($classNode);
		
    	$this->startNewTextBlock();
		$this->appendLine('<?php');
		
		$this->appendLine('/**');
		$this->appendLine(' * @namespace');
		$this->appendLine(' */');
		$this->appendLine('namespace Kaltura\Client;');
		$this->appendLine();
		
		if($this->generateDocs)
		{
			$this->appendLine('/**');
			$this->appendLine(" * @package $this->package");
			$this->appendLine(" * @subpackage $this->subpackage");
			$this->appendLine(' */');
		}
			
		$this->appendLine('class TypeMap');
		$this->appendLine('{');
		
		$classNodes = $xpath->query("/xml/classes/class");
		$this->appendLine('	private static $map = array(');
		foreach($classNodes as $classNode)
		{
			$kalturaType = $classNode->getAttribute('name');
			$zendType = $this->getTypeClassInfo($kalturaType);
			$this->appendLine("		'$kalturaType' => '{$zendType->getFullyQualifiedNameNoPrefixSlash()}',");
		}
		$this->appendLine('	);');
		$this->appendLine('	');
		
		$this->appendLine('	public static function getZendType($kalturaType)');
		$this->appendLine('	{');
		$this->appendLine('		if(isset(self::$map[$kalturaType]))');
		$this->appendLine('			return self::$map[$kalturaType];');
		$this->appendLine('		return null;');
		$this->appendLine('	}');
		$this->appendLine('}');
		
    	$this->addFile($this->getMapPath(), $this->getTextBlock());
			
		// enumes
		$enumNodes = $xpath->query("/xml/enums/enum");
		foreach($enumNodes as $enumNode)
		{
    		$this->startNewTextBlock();
			$this->appendLine('<?php');
			$this->writeEnum($enumNode);
    		$this->addFile($this->getEnumPath($enumNode), $this->getTextBlock());
		}
    	
		// classes
		$classNodes = $xpath->query("/xml/classes/class");
		foreach($classNodes as $classNode)
		{
	    	$this->startNewTextBlock();
			$this->appendLine('<?php');
			$this->writeClass($classNode);
    		$this->addFile($this->getTypePath($classNode), $this->getTextBlock());
		}
		
		// services
		$serviceNodes = $xpath->query("/xml/services/service");
		foreach($serviceNodes as $serviceNode)
		{
	    	$this->startNewTextBlock();
			$this->appendLine('<?php');
		    $this->writeService($serviceNode);
    		$this->addFile($this->getServicePath($serviceNode), $this->getTextBlock());
		}
		
    	$this->startNewTextBlock();
		$this->appendLine('<?php');
	    $this->writeMainClient($serviceNodes);
    	$this->addFile($this->getMainPath(), $this->getTextBlock());
    	
    	
		// plugins
		$pluginNodes = $xpath->query("/xml/plugins/plugin");
		foreach($pluginNodes as $pluginNode)
		{
		    $this->writePlugin($pluginNode);
		}
	}
	
	protected function getEnumPath(DOMElement $enumNode)
	{
		$enumName = $enumNode->getAttribute('name');
		$enumName = preg_replace('/^Kaltura(.+)$/', '$1', $enumName); 
			
		if(!$enumNode->hasAttribute('plugin'))
			return "library/Kaltura/Client/Enum/{$enumName}.php";

		$pluginName = ucfirst($enumNode->getAttribute('plugin'));
		return "library/Kaltura/Client/Plugin/{$pluginName}/Enum/{$enumName}.php";
	}
	
	protected function getTypePath(DOMElement $classNode)
	{
		$className = $classNode->getAttribute('name');
		$className = preg_replace('/^Kaltura(.+)$/', '$1', $className); 
			
		if(!$classNode->hasAttribute('plugin'))
			return "library/Kaltura/Client/Type/{$className}.php";

		$pluginName = ucfirst($classNode->getAttribute('plugin'));
		return "library/Kaltura/Client/Plugin/{$pluginName}/Type/{$className}.php";
	}
	
	protected function getServicePath($serviceNode)
	{
		$serviceName = ucfirst($serviceNode->getAttribute('name'));
			
		if(!$serviceNode->hasAttribute('plugin'))
			return "library/Kaltura/Client/Service/{$serviceName}Service.php";

		$pluginName = ucfirst($serviceNode->getAttribute('plugin'));
		return "library/Kaltura/Client/Plugin/{$pluginName}/Service/{$serviceName}Service.php";
	}
	
	protected function getPluginPath($pluginName)
	{
		$pluginName = ucfirst($pluginName);
		return "library/Kaltura/Client/Plugin/{$pluginName}/".$this->getPluginClass($pluginName).".php";
	}
	
	protected function getMainPath()
	{
		return 'library/Kaltura/Client/Client.php';
	}
	
	protected function getMapPath()
	{
		return 'library/Kaltura/Client/TypeMap.php';
	}
	
	/**
	 * @return PhpZend2ClientGeneratorClassInfo
	 */
	protected function getEnumClassInfo($enumName)
	{
		if(!isset($this->cacheEnums[$enumName]))
			throw new Exception("Enum info for {$enumName} not found"); 
		
		return $this->cacheEnums[$enumName];
	}
	
	/**
	 * @return PhpZend2ClientGeneratorClassInfo
	 */
	protected function getTypeClassInfo($className)
	{
		if($className == 'KalturaObjectBase')
		{
			$classInfo = new PhpZend2ClientGeneratorClassInfo();
			$classInfo->setClassName($className);
			$classInfo->setNamespace("Kaltura\\Client\\Type");
			return $classInfo;
		}
		
		if(!isset($this->cacheTypes[$className]))
			throw new Exception("Class info for {$className} not found");
		
		return $this->cacheTypes[$className];
	}
	
	protected function getServiceClass(DOMElement $serviceNode)
	{
		$serviceName = ucfirst($serviceNode->getAttribute('name'));
		
		return "{$serviceName}Service";
	}
	
	protected function getPluginClass($pluginName)
	{
		$pluginName = ucfirst($pluginName);
		return "{$pluginName}Plugin";
	}
	
	protected function formatMultiLineComment($description, $ident = 1)
	{
		$tabs = "";
		for($i = 0; $i < $ident; $i++)
			$tabs .= "\t";
		return str_replace("\n", "\n$tabs * ", $description); // to format multiline descriptions
	}
	
	function writePlugin(DOMElement $pluginNode)
	{
		$xpath = new DOMXPath($this->_doc);
		
		$pluginName = $pluginNode->getAttribute("name");
		$pluginClassName = $this->getPluginClass($pluginName);
		
    	$this->startNewTextBlock();
		$this->appendLine('<?php');
		
		$this->appendLine('/**');
		$this->appendLine(' * @namespace');
		$this->appendLine(' */');
		$this->appendLine("namespace Kaltura\\Client\\Plugin\\".ucfirst($pluginName).";");
		$this->appendLine();
		
		if($this->generateDocs)
		{
			$this->appendLine('/**');
			$this->appendLine(" * @package $this->package");
			$this->appendLine(" * @subpackage $this->subpackage");
			$this->appendLine(' */');
		}
		
		$this->appendLine("class $pluginClassName extends \Kaltura\Client\Plugin");
		$this->appendLine('{');
	
		$serviceNodes = $xpath->query("/xml/services/service[@plugin = '$pluginName']");
		foreach($serviceNodes as $serviceNode)
		{
			$serviceName = $serviceNode->getAttribute("name");
			$serviceClass = $this->getServiceClass($serviceNode);
			$serviceRelativeClassName = "Service\\{$serviceClass}";
			$this->appendLine('	/**');
			$this->appendLine("	 * @var $serviceRelativeClassName");
			$this->appendLine('	 */');
			$this->appendLine("	protected \${$serviceName} = null;");
			$this->appendLine('');
		}
		
		$this->appendLine('	protected function __construct(\Kaltura\Client\Client $client)');
		$this->appendLine('	{');
		$this->appendLine('		parent::__construct($client);');
		$this->appendLine('	}');
		$this->appendLine('');
		$this->appendLine('	/**');
		$this->appendLine("	 * @return $pluginClassName");
		$this->appendLine('	 */');
		$this->appendLine('	public static function get(\Kaltura\Client\Client $client)');
		$this->appendLine('	{');
		$this->appendLine("		return new $pluginClassName(\$client);");
		$this->appendLine('	}');
		$this->appendLine('');
		$this->appendLine('	/**');
		$this->appendLine('	 * @return array<\Kaltura\Client\ServiceBase>');
		$this->appendLine('	 */');
		$this->appendLine('	public function getServices()');
		$this->appendLine('	{');
		$this->appendLine('		$services = array(');
		foreach($serviceNodes as $serviceNode)
		{
			$serviceName = $serviceNode->getAttribute("name");
			$serviceClass = $this->getServiceClass($serviceNode);
			$this->appendLine("			'$serviceName' => \$this->get{$serviceClass}(),");
		}
		$this->appendLine('		);');
		$this->appendLine('		return $services;');
		$this->appendLine('	}');
		$this->appendLine('');
		$this->appendLine('	/**');
		$this->appendLine('	 * @return string');
		$this->appendLine('	 */');
		$this->appendLine('	public function getName()');
		$this->appendLine('	{');
		$this->appendLine("		return '$pluginName';");
		$this->appendLine('	}');
		
		foreach($serviceNodes as $serviceNode)
		{
			$serviceName = $serviceNode->getAttribute("name");
			$description = $serviceNode->getAttribute("description");
			$serviceClass = $this->getServiceClass($serviceNode);
			$serviceRelativeClassName = "Service\\{$serviceClass}";
			
			$this->appendLine("	/**");
			$this->appendLine("	 * @return \\Kaltura\\Client\\Plugin\\".ucfirst($pluginName)."\\$serviceRelativeClassName");
			$this->appendLine("	 */");
			$this->appendLine("	public function get{$serviceClass}()");
			$this->appendLine("	{");
			$this->appendLine("		if (is_null(\$this->$serviceName))");
			$this->appendLine("			\$this->$serviceName = new $serviceRelativeClassName(\$this->_client);");
			$this->appendLine("		return \$this->$serviceName;");
			$this->appendLine("	}");
			
			
		}
		$this->appendLine('}');
		$this->appendLine('');
		
    	$this->addFile($this->getPluginPath($pluginName), $this->getTextBlock());
	}

	
	function writeEnum(DOMElement $enumNode)
	{
		$enumClassInfo = $this->getEnumClassInfo($enumNode->getAttribute('name'));
		
		$this->appendLine('/**');
		$this->appendLine(' * @namespace');
		$this->appendLine(' */');
		$this->appendLine("namespace {$enumClassInfo->getNamespace()};");
		$this->appendLine();
		
		if($this->generateDocs)
		{
			$this->appendLine('/**');
			$this->appendLine(" * @package $this->package");
			$this->appendLine(" * @subpackage $this->subpackage");
			$this->appendLine(' */');
		}
		
	 	$this->appendLine("class {$enumClassInfo->getClassName()}");		
		$this->appendLine("{");
		foreach($enumNode->childNodes as $constNode)
		{
			if ($constNode->nodeType != XML_ELEMENT_NODE)
				continue;
				
			$propertyName = $constNode->getAttribute("name");
			$propertyValue = $constNode->getAttribute("value");
			if ($enumNode->getAttribute("enumType") == "string")
				$this->appendLine("	const $propertyName = \"$propertyValue\";");
			else
				$this->appendLine("	const $propertyName = $propertyValue;");
		}
		$this->appendLine("}");
		$this->appendLine();
	}
	
	function writeClass(DOMElement $classNode)
	{
		$kalturaType = $classNode->getAttribute('name');
		$description = $classNode->getAttribute("description");
		$type = $this->getTypeClassInfo($kalturaType);
		
		$abstract = '';
		if ($classNode->hasAttribute("abstract"))
			$abstract = 'abstract ';
			
		$this->appendLine('/**');
		$this->appendLine(' * @namespace');
		$this->appendLine(' */');
		$this->appendLine("namespace {$type->getNamespace()};");
		$this->appendLine();
		
		if($this->generateDocs)
		{
			$this->appendLine('/**');
			if ($description)
				$this->appendLine(" * " . $this->formatMultiLineComment($description, 0));
			$this->appendLine(" * @package $this->package");
			$this->appendLine(" * @subpackage $this->subpackage");
			$this->appendLine(' */');
		}
		
		// class definition
		$baseClass = '\Kaltura\Client\ObjectBase';
		if ($classNode->hasAttribute('base'))
		{
			$baseClassInfo = $this->getTypeClassInfo($classNode->getAttribute('base'));
			$baseClass = $baseClassInfo->getFullyQualifiedName();
		}
			
		$this->appendLine($abstract . "class {$type->getClassName()} extends $baseClass");
		$this->appendLine("{");
		$this->appendLine("	public function getKalturaObjectType()");
		$this->appendLine("	{");
		$this->appendLine("		return '$kalturaType';");
		$this->appendLine("	}");
		$this->appendLine("	");
	
		$this->appendLine('	public function __construct(\SimpleXMLElement $xml = null)');
		$this->appendLine('	{');
		$this->appendLine('		parent::__construct($xml);');
		$this->appendLine('		');
		$this->appendLine('		if(is_null($xml))');
		$this->appendLine('			return;');
		$this->appendLine('		');
		
		foreach($classNode->childNodes as $propertyNode)
		{
			if ($propertyNode->nodeType != XML_ELEMENT_NODE)
				continue;
			
			$propName = $propertyNode->getAttribute("name");
			$isEnum = $propertyNode->hasAttribute("enumType");
			$propType = $propertyNode->getAttribute("type");
		
			switch ($propType) 
			{
				case "int" :
				case "float" :
					$this->appendLine("		if(count(\$xml->{$propName}))");
					$this->appendLine("			\$this->$propName = ($propType)\$xml->$propName;");
					break;
					
				case "bool" :
					$this->appendLine("		if(!empty(\$xml->{$propName}))");
					$this->appendLine("			\$this->$propName = true;");
					break;
					
				case "string" :
					$this->appendLine("		\$this->$propName = ($propType)\$xml->$propName;");
					break;
					
				case "array" :
					$this->appendLine("		if(empty(\$xml->{$propName}))");
					$this->appendLine("			\$this->$propName = array();");
					$this->appendLine("		else");
					$this->appendLine("			\$this->$propName = \Kaltura\Client\Client::unmarshalItem(\$xml->$propName);");
					break;
					
				default : // sub object
					$this->appendLine("		if(!empty(\$xml->{$propName}))");
					$this->appendLine("			\$this->$propName = \Kaltura\Client\Client::unmarshalItem(\$xml->$propName);");
					break;
			}
		}
		
		$this->appendLine('	}');
		
		// class properties
		foreach($classNode->childNodes as $propertyNode)
		{
			if ($propertyNode->nodeType != XML_ELEMENT_NODE)
				continue;
			
			$propName = $propertyNode->getAttribute("name");
			$isReadyOnly = $propertyNode->getAttribute("readOnly") == 1;
			$isInsertOnly = $propertyNode->getAttribute("insertOnly") == 1;
			$isEnum = $propertyNode->hasAttribute("enumType");
			$propType = null;
			if ($isEnum)
				$propType = $propertyNode->getAttribute("enumType");
			else
				$propType = $propertyNode->getAttribute("type");
			$description = $propertyNode->getAttribute("description");
			
			$this->appendLine("	/**");
			$this->appendLine("	 * " . $this->formatMultiLineComment($description));
			if ($propType == "array")
				$this->appendLine("	 * @var $propType of {$propertyNode->getAttribute("arrayType")}");
			elseif ($this->isSimpleType($propType))
				$this->appendLine("	 * @var $propType");
			elseif ($isEnum) 
			{
				$propClassInfo = $this->getEnumClassInfo($propType);
				$this->appendLine("	 * @var {$propClassInfo->getFullyQualifiedName()}");
			} 
			else
			{
				$propClassInfo = $this->getTypeClassInfo($propType);
				$this->appendLine("	 * @var {$propClassInfo->getFullyQualifiedName()}");
			}
			if ($isReadyOnly )
				$this->appendLine("	 * @readonly");
			if ($isInsertOnly)
				$this->appendLine("	 * @insertonly");
			$this->appendLine("	 */");
			
			$propertyLine =	"public $$propName";
			
			if ($this->isSimpleType($propType) || $isEnum)
			{
				$propertyLine .= " = null";
			}
			
			$this->appendLine("	$propertyLine;");
			$this->appendLine("");
		}

		// close class
		$this->appendLine("}");
	}
	
	function writeService(DOMElement $serviceNode)
	{
		$plugin = null;
		if($serviceNode->hasAttribute('plugin'))
			$plugin = $serviceNode->getAttribute('plugin');
			
		$serviceName = $serviceNode->getAttribute("name");
		$serviceId = $serviceNode->getAttribute("id");
		$description = $serviceNode->getAttribute("description");
					
		$serviceClassName = $this->getServiceClass($serviceNode, $plugin);
		$this->appendLine();
		
		$this->appendLine('/**');
		$this->appendLine(' * @namespace');
		$this->appendLine(' */');
		if ($plugin)
			$this->appendLine("namespace Kaltura\\Client\\Plugin\\".ucfirst($plugin)."\\Service;");
		else
			$this->appendLine('namespace Kaltura\Client\Service;');
		$this->appendLine();

		if($this->generateDocs)
		{
			$this->appendLine('/**');
			if ($description)
				$this->appendLine(" * " . $this->formatMultiLineComment($description, 0));
			$this->appendLine(" * @package $this->package");
			$this->appendLine(" * @subpackage $this->subpackage");
			$this->appendLine(' */');
		}
		
		$this->appendLine("class $serviceClassName extends \Kaltura\Client\ServiceBase");
		$this->appendLine("{");
		$this->appendLine("	function __construct(\\Kaltura\\Client\\Client \$client = null)");
		$this->appendLine("	{");
		$this->appendLine("		parent::__construct(\$client);");
		$this->appendLine("	}");
		
		$actionNodes = $serviceNode->childNodes;
		foreach($actionNodes as $actionNode)
		{
		    if ($actionNode->nodeType != XML_ELEMENT_NODE)
				continue;
				
		    $this->writeAction($serviceId, $serviceName, $actionNode);
		}
		$this->appendLine("}");
	}
	
	function writeAction($serviceId, $serviceName, DOMElement $actionNode, $plugin = null)
	{
		$action = $actionNode->getAttribute("name");
	    $resultNode = $actionNode->getElementsByTagName("result")->item(0);
	    $resultType = $resultNode->getAttribute("type");
		$description = $actionNode->getAttribute("description");
		
		// method signature
		$signature = "";
		if (in_array($action, array("list", "clone", "goto"))) // because list & clone are preserved in PHP
			$signature .= "function ".$action."Action(";
		else
			$signature .= "function ".$action."(";
			
		$paramNodes = $actionNode->getElementsByTagName("param");
		$signature .= $this->getSignature($paramNodes);
		
		$this->appendLine();
		$this->appendLine("	/**");
		if ($description)
			$this->appendLine("	 * " . $this->formatMultiLineComment($description));
		$this->appendLine("	 * ");			
		if (!$resultType)
		{
			$this->appendLine("	 * @return");
		}
		elseif ($this->isSimpleType($resultType) || $resultType == 'array' || $resultType == 'file')
		{
			$this->appendLine("	 * @return $resultType");
		}
		else
		{
			$resultTypeClassInfo = $this->getTypeClassInfo($resultType);
			$this->appendLine("	 * @return {$resultTypeClassInfo->getFullyQualifiedName()}");
		}
		$this->appendLine("	 */");
		$this->appendLine("	$signature");
		$this->appendLine("	{");
		
		$this->appendLine("		\$kparams = array();");
		$haveFiles = false;
		foreach($paramNodes as $paramNode)
		{
			$paramType = $paramNode->getAttribute("type");
		    $paramName = $paramNode->getAttribute("name");
		    $isEnum = $paramNode->hasAttribute("enumType");
		    $isOptional = $paramNode->getAttribute("optional");
			
		    if ($haveFiles === false && $paramType == "file")
	    	{
		        $haveFiles = true;
	        	$this->appendLine("		\$kfiles = array();");
	    	}
	    
			if (!$this->isSimpleType($paramType))
			{
				if ($isEnum)
				{
					$this->appendLine("		\$this->client->addParam(\$kparams, \"$paramName\", \$$paramName);");
				}
				else if ($paramType == "file")
				{
					$this->appendLine("		\$this->client->addParam(\$kfiles, \"$paramName\", \$$paramName);");
				}
				else if ($paramType == "array")
				{
					$extraTab = "";
					if ($isOptional)
					{
						$this->appendLine("		if (\$$paramName !== null)");
						$extraTab = "	";
					}
					$this->appendLine("$extraTab		foreach(\$$paramName as \$index => \$obj)");
					$this->appendLine("$extraTab		{");
					$this->appendLine("$extraTab			\$this->client->addParam(\$kparams, \"$paramName:\$index\", \$obj->toParams());");
					$this->appendLine("$extraTab		}");
				}
				else
				{
					$extraTab = "";
					if ($isOptional)
					{
						$this->appendLine("		if (\$$paramName !== null)");
						$extraTab = "	";
					}
					$this->appendLine("$extraTab		\$this->client->addParam(\$kparams, \"$paramName\", \$$paramName"."->toParams());");
				}
			}
			else
			{
				$this->appendLine("		\$this->client->addParam(\$kparams, \"$paramName\", \$$paramName);");
			}
		}
		
	    if($resultType == 'file')
	    {
			$this->appendLine("		\$this->client->queueServiceActionCall('" . strtolower($serviceId) . "', '$action', \$kparams);");
			$this->appendLine('		$resultObject = $this->client->getServeUrl();');
	    }
	    else
	    {
			if ($haveFiles)
				$this->appendLine("		\$this->client->queueServiceActionCall(\"".strtolower($serviceId)."\", \"$action\", \$kparams, \$kfiles);");
			else
				$this->appendLine("		\$this->client->queueServiceActionCall(\"".strtolower($serviceId)."\", \"$action\", \$kparams);");
			$this->appendLine("		if (\$this->client->isMultiRequest())");
			$this->appendLine("			return \$this->client->getMultiRequestResult();;");
			$this->appendLine("		\$resultObject = \$this->client->doQueue();");
			$this->appendLine("		\$this->client->throwExceptionIfError(\$resultObject);");
			
			switch($resultType)
			{
				case 'int':
					$this->appendLine("		\$resultObject = (int)\$resultObject;");
					break;
				case 'bool':
					$this->appendLine("		\$resultObject = (bool)\$resultObject;");
					break;
				case 'string':
					$this->appendLine("		\$resultObject = (string)\$resultObject;");
					break;
				case 'array':
					$this->appendLine("		if(!\$resultObject)");
					$this->appendLine("			\$resultObject = array();");
					$this->appendLine("		\$this->client->validateObjectType(\$resultObject, \"$resultType\");");
					break;
				default:
					if ($resultType)
					{
						$resultTypeClassInfo = $this->getTypeClassInfo($resultType);
						$resultObjectTypeEscaped = str_replace("\\", "\\\\", $resultTypeClassInfo->getFullyQualifiedName());
						$this->appendLine("		\$this->client->validateObjectType(\$resultObject, \"{$resultObjectTypeEscaped}\");");
					}
			}
	    }
			
		$this->appendLine("		return \$resultObject;");
		$this->appendLine("	}");
	}
	
	function getSignature($paramNodes, $plugin = null)
	{
		$signature = "";
		foreach($paramNodes as $paramNode)
		{
			$paramName = $paramNode->getAttribute("name");
			$paramType = $paramNode->getAttribute("type");
			$defaultValue = $paramNode->getAttribute("default");
						
			if ($this->isSimpleType($paramType) || $paramType == "file")
			{
				$signature .= "$".$paramName;
			}
			else if ($paramType == "array")
			{
				$signature .= "array $".$paramName;
			}
			else
			{
				$typeClass = $this->getTypeClassInfo($paramType);
				$signature .= $typeClass->getFullyQualifiedName()." $".$paramName;
			}
			
			
			if ($paramNode->getAttribute("optional"))
			{
				if ($this->isSimpleType($paramType))
				{
					if ($defaultValue === "false")
						$signature .= " = false";
					else if ($defaultValue === "true")
						$signature .= " = true";
					else if ($defaultValue === "null")
						$signature .= " = null";
					else if ($paramType == "string")
						$signature .= " = \"$defaultValue\"";
					else if ($paramType == "int" || $paramType == "float")
					{
						if ($defaultValue == "")
							$signature .= " = \"\""; // hack for partner.getUsage
						else
							$signature .= " = $defaultValue";
					} 
				}
				else
					$signature .= " = null";
			}
				
			$signature .= ", ";
		}
		if ($this->endsWith($signature, ", "))
			$signature = substr($signature, 0, strlen($signature) - 2);
		$signature .= ")";
		
		return $signature;
	}
	
	function writeMainClient(DOMNodeList $serviceNodes)
	{
		$apiVersion = $this->_doc->documentElement->getAttribute('apiVersion');
		
		$this->appendLine('/**');
		$this->appendLine(' * @namespace');
		$this->appendLine(' */');
		$this->appendLine('namespace Kaltura\Client;');
		$this->appendLine();
		
		if($this->generateDocs)
		{
			$this->appendLine('/**');
			$this->appendLine(" * @package $this->package");
			$this->appendLine(" * @subpackage $this->subpackage");
			$this->appendLine(' */');
		}
		
		$this->appendLine("class Client extends Base");
		$this->appendLine("{");
		$this->appendLine("	/**");
		$this->appendLine("	 * @var string");
		$this->appendLine("	 */");
		$this->appendLine("	protected \$apiVersion = '$apiVersion';");
		$this->appendLine("");
	
		foreach($serviceNodes as $serviceNode)
		{
			if($serviceNode->hasAttribute("plugin"))
				continue;
				
			$serviceName = $serviceNode->getAttribute("name");
			$serviceClassName = "\\Kaltura\\Client\\Service\\".ucfirst($serviceName)."Service";
			$this->appendLine("	/**");
			$this->appendLine("	 * @var $serviceClassName");
			$this->appendLine("	 */");
			$this->appendLine("	protected \$$serviceName = null;");
			$this->appendLine("");
		}
		
		$this->appendLine("	/**");
		$this->appendLine("	 * Kaltura client constructor");
		$this->appendLine("	 *");
		$this->appendLine("	 * @param \\Kaltura\\Client\\Configuration \$config");
		$this->appendLine("	 */");
		$this->appendLine("	public function __construct(Configuration \$config)");
		$this->appendLine("	{");
		$this->appendLine("		parent::__construct(\$config);");
		$this->appendLine("	}");
		$this->appendLine("	");
	
		foreach($serviceNodes as $serviceNode)
		{
			if($serviceNode->hasAttribute("plugin"))
				continue;
				
			$serviceName = $serviceNode->getAttribute("name");
			$description = $serviceNode->getAttribute("description");
			$serviceClassName = "\\Kaltura\\Client\\Service\\".ucfirst($serviceName)."Service";
			
			$this->appendLine("	/**");
			$this->appendLine("	 * @return $serviceClassName");
			$this->appendLine("	 */");
			$this->appendLine("	public function get".ucfirst($serviceName)."Service()");
			$this->appendLine("	{");
			$this->appendLine("		if (is_null(\$this->$serviceName))");
			$this->appendLine("			\$this->$serviceName = new $serviceClassName(\$this);");
			$this->appendLine("		return \$this->$serviceName;");
			$this->appendLine("	}");
		}
		
		$this->appendLine("}");
	}
	
	protected function addFile($fileName, $fileContents, $addLicense = true)
	{
		$patterns = array(
			'/@package\s+.+/',
			'/@subpackage\s+.+/',
		);
		$replacements = array(
			'@package ' . $this->package,
			'@subpackage ' . $this->subpackage,
		);
		$fileContents = preg_replace($patterns, $replacements, $fileContents);
		parent::addFile($fileName, $fileContents, $addLicense);
	}
}

class PhpZend2ClientGeneratorClassInfo
{
	private $className;
	
	private $namespace;
	
	public function getClassName()
	{
		return $this->className;
	}

	public function getNamespace()
	{
		return $this->namespace;
	}

	public function getFullyQualifiedName()
	{
		return '\\'.$this->getNamespace().'\\'.$this->getClassName();
	}

	public function getFullyQualifiedNameNoPrefixSlash()
	{
		return $this->getNamespace().'\\'.$this->getClassName();
	}

	public function setClassName($className)
	{
		$this->className = $className;
	}

	public function setNamespace($namespace)
	{
		$this->namespace = $namespace;
	}

}