<?php
/**
 * @package api
 * @subpackage objects
 */
class KalturaStorageExportJobData extends KalturaStorageJobData
{
    
	/**
	 * @var bool
	 */   	
    public $force;
	
     /**
	 * @var KalturaAmazonS3StorageProfileFilesPermissionLevel
	 */   	
    public $filesPermissionInS3;   
    
	private static $map_between_objects = array
	(
	    "force",
		"filesPermissionInS3",
	);

	public function getMapBetweenObjects ( )
	{
		return array_merge ( parent::getMapBetweenObjects() , self::$map_between_objects );
	}

	
	public function toObject($dbData = null, $props_to_skip = array()) 
	{
		if(is_null($dbData))
			$dbData = new kStorageExportJobData();
			
		return parent::toObject($dbData);
	}
	
	/**
	 * @param string $subType
	 * @return int
	 */
	public function toSubType($subType)
	{
		// TODO - change to pluginable enum to support more file export protocols
		return $subType;
	}
	
	/**
	 * @param int $subType
	 * @return string
	 */
	public function fromSubType($subType)
	{
		// TODO - change to pluginable enum to support more file export protocols
		return $subType;
	}
}
