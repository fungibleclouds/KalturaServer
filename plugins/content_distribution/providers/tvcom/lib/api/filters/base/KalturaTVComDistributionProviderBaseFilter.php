<?php
/**
 * @package plugins.tvComDistribution
 * @subpackage api.filters.base
 * @abstract
 */
abstract class KalturaTVComDistributionProviderBaseFilter extends KalturaDistributionProviderFilter
{
	static private $map_between_objects = array
	(
	);

	static private $order_by_map = array
	(
	);

	public function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), KalturaTVComDistributionProviderBaseFilter::$map_between_objects);
	}

	public function getOrderByMap()
	{
		return array_merge(parent::getOrderByMap(), KalturaTVComDistributionProviderBaseFilter::$order_by_map);
	}
}
