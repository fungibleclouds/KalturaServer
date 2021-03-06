<?php
/**
 * @package plugins.youtubeApiDistribution
 * @subpackage api.filters.base
 * @abstract
 */
abstract class KalturaYoutubeApiDistributionProfileBaseFilter extends KalturaConfigurableDistributionProfileFilter
{
	static private $map_between_objects = array
	(
	);

	static private $order_by_map = array
	(
	);

	public function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), KalturaYoutubeApiDistributionProfileBaseFilter::$map_between_objects);
	}

	public function getOrderByMap()
	{
		return array_merge(parent::getOrderByMap(), KalturaYoutubeApiDistributionProfileBaseFilter::$order_by_map);
	}
}
