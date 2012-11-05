<?php
/**
 * @package api
 * @subpackage objects
 */
class KalturaAmazonS3StorageProfile extends KalturaStorageProfile
{
	/**
	 * @var KalturaAmazonS3StorageProfileFilesPermissionLevel
	 */
	public $filesPermissionPublicInS3;
	
	private static $map_between_objects = array
	(
		"filesPermissionPublicInS3",
	);
	
	public function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), self::$map_between_objects);
	}

	public function toObject ($object_to_fill = null , $props_to_skip = array() )
	{
		if(is_null($object_to_fill))
			$object_to_fill = new AmazonS3StorageProfile();
				
		$object_to_fill =  parent::toObject($object_to_fill, $props_to_skip);
				
		return $object_to_fill;
	}
		
}