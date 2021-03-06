<?php
/**
 * @package api
 * @subpackage filters.base
 * @abstract
 */
abstract class KalturaMediaInfoBaseFilter extends KalturaFilter
{
	static private $map_between_objects = array
	(
		"flavorAssetIdEqual" => "_eq_flavor_asset_id",
	);

	static private $order_by_map = array
	(
	);

	public function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), KalturaMediaInfoBaseFilter::$map_between_objects);
	}

	public function getOrderByMap()
	{
		return array_merge(parent::getOrderByMap(), KalturaMediaInfoBaseFilter::$order_by_map);
	}

	/**
	 * @var string
	 */
	public $flavorAssetIdEqual;
}
