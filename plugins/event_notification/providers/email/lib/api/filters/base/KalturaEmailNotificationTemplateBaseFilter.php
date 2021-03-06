<?php
/**
 * @package plugins.emailNotification
 * @subpackage api.filters.base
 * @abstract
 */
abstract class KalturaEmailNotificationTemplateBaseFilter extends KalturaEventNotificationTemplateFilter
{
	static private $map_between_objects = array
	(
	);

	static private $order_by_map = array
	(
	);

	public function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), KalturaEmailNotificationTemplateBaseFilter::$map_between_objects);
	}

	public function getOrderByMap()
	{
		return array_merge(parent::getOrderByMap(), KalturaEmailNotificationTemplateBaseFilter::$order_by_map);
	}
}
