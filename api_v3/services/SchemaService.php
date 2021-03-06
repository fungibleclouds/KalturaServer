<?php
/**
 * Expose the schema definitions for syndication MRSS, bulk upload XML and other schema types. 
 * 
 * @service schema
 * @package api
 * @subpackage services
 */
class SchemaService extends KalturaBaseService 
{
	const CORE_SCHEMA_NAME = 'core';
	const ENUM_SCHEMA_NAME = 'enum';
	
	/* (non-PHPdoc)
	 * @see KalturaBaseService::partnerRequired()
	 */
	protected function partnerRequired($actionName)
	{
		return false;
	}
	
	/* (non-PHPdoc)
	 * @see KalturaBaseService::isPermitted()
	 */
	protected function isPermitted(&$allowPrivatePartnerData)
	{
		return true;
	}
	
	/**
	 * Serves the requested XSD according to the type and name. 
	 * 
	 * @action serve
	 * @param KalturaSchemaType $type  
	 * @return file 
	 */
	function serveAction($type)
	{
		header("Content-Type: text/plain; charset=UTF-8");
		
		$cacheXsdFile = self::getSchemaPath($type);
		kFileUtils::dumpFile($cacheXsdFile);
	}
	
	/**
	 * @param KalturaSchemaType $type  
	 * @return string filePath 
	 */
	public static function getSchemaPath($type)
	{
		$cacheXsdFile = kConf::get("cache_root_path") . "/$type.xsd";
		if(file_exists($cacheXsdFile))
			return realpath($cacheXsdFile);
		
		$xsdFile = fopen($cacheXsdFile, 'w');
		$elementsXSD = '';
		
		$baseXsdElement = new SimpleXMLElement('<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema"/>');
		if($type == SchemaType::SYNDICATION)
		{
			$baseXsdElement = new SimpleXMLElement(file_get_contents(kConf::get("syndication_core_xsd_path")));
		}
		else
		{
			$plugin = kPluginableEnumsManager::getPlugin($type);
			if($plugin instanceof IKalturaSchemaDefiner)
			{
				KalturaLog::debug("Found plugin [" . get_class($plugin) . "]");
				$baseXsdElement = $plugin->getPluginSchema($type);
			}
		}
		
		if(!($baseXsdElement instanceof SimpleXMLElement))
			$baseXsdElement = new SimpleXMLElement('<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema"/>');
	
		$version = '1.0';
		if($baseXsdElement['version'])
			$version = $baseXsdElement['version'];
		
		fwrite($xsdFile, "<xs:schema xmlns:xs=\"http://www.w3.org/2001/XMLSchema\" version=\"$version\">");
			
		foreach($baseXsdElement->children('http://www.w3.org/2001/XMLSchema') as $element)
		{
			/* @var $element SimpleXMLElement */
			$xsd = $element->asXML();
			$elementsXSD .= $xsd;
			
			fwrite($xsdFile, '
	' . $xsd);
		}
		
		$schemaContributors = KalturaPluginManager::getPluginInstances('IKalturaSchemaContributor');
		foreach($schemaContributors as $key => $schemaContributor)
		{
			/* @var $schemaContributor IKalturaSchemaContributor */
			$elements = $schemaContributor->contributeToSchema($type);
			if($elements)
			{
				$elementsXSD .= $elements;
				fwrite($xsdFile, $elements);
			}
		}
		
		fwrite($xsdFile, '
	<!-- Kaltura enum types -->
	');
		
		$enumClasses = array();
		$matches = null;
		if(preg_match_all('/type="(Kaltura[^"]+)"/', $elementsXSD, $matches))
			$enumClasses = $matches[1];
		
		$enumTypes = array();
		foreach($enumClasses as $class)
		{
			$classTypeReflector = KalturaTypeReflectorCacher::get($class);
			if($classTypeReflector)
				self::loadClassRecursively($classTypeReflector, $enumTypes);
		}
		
		foreach($enumTypes as $class => $classTypeReflector)
		{
			if(!is_subclass_of($class, 'KalturaEnum') && !is_subclass_of($class, 'KalturaStringEnum')) // class must be enum
				continue;
				
			$xsdType = 'int';
			if($classTypeReflector->isStringEnum())
				$xsdType = 'string';
			
			$xsd = '
	<xs:simpleType name="' . $class . '">
		<xs:annotation><xs:documentation>http://' . kConf::get('www_host') . '/api_v3/testmeDoc/index.php?object=' . $class . '</xs:documentation></xs:annotation>
		<xs:restriction base="xs:' . $xsdType . '">';
		
			$contants = $classTypeReflector->getConstants();
			foreach($contants as $contant)
			{
				$xsd .= '
			<xs:enumeration value="' . $contant->getDefaultValue() . '"><xs:annotation><xs:documentation>' . $contant->getName() . '</xs:documentation></xs:annotation></xs:enumeration>';
			}
			
						
			$xsd .= '
		</xs:restriction>
	</xs:simpleType>
			';
			
			fwrite($xsdFile, $xsd);
		}
		
		fwrite($xsdFile, '
</xs:schema>');
		
		fclose($xsdFile);
		
		return realpath($cacheXsdFile);
	}
	
	private static function loadClassRecursively(KalturaTypeReflector $classTypeReflector, &$enumClasses)
	{
		$class = $classTypeReflector->getType();
		if(
			$class == 'KalturaEnum'
			||
			$class == 'KalturaStringEnum'
			||
			$class == 'KalturaObject'
		)
			return;
			
		$enumClasses[$class] = $classTypeReflector;
		$parentClassTypeReflector = $classTypeReflector->getParentTypeReflector();
		if($parentClassTypeReflector)
			self::loadClassRecursively($parentClassTypeReflector, $enumClasses);
	}
}
