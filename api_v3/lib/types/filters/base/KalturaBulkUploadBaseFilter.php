<?php
/**
 * @package api
 * @subpackage filters.base
 * @abstract
 */
abstract class KalturaBulkUploadBaseFilter extends KalturaFilter
{
	static private $map_between_objects = array
	(
		"uploadedOnGreaterThanOrEqual" => "_gte_uploaded_on",
		"uploadedOnLessThanOrEqual" => "_lte_uploaded_on",
		"uploadedOnEqual" => "_eq_uploaded_on",
		"statusIn" => "_in_status",
		"statusEqual" => "_eq_status",
		"bulkUploadObjectTypeEqual" => "_eq_bulk_upload_object_type",
		"bulkUploadObjectTypeIn" => "_in_bulk_upload_object_type",
	);

	static private $order_by_map = array
	(
	);

	public function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), KalturaBulkUploadBaseFilter::$map_between_objects);
	}

	public function getOrderByMap()
	{
		return array_merge(parent::getOrderByMap(), KalturaBulkUploadBaseFilter::$order_by_map);
	}

	/**
	 * @var int
	 */
	public $uploadedOnGreaterThanOrEqual;

	/**
	 * @var int
	 */
	public $uploadedOnLessThanOrEqual;

	/**
	 * @var int
	 */
	public $uploadedOnEqual;

	/**
	 * @var string
	 */
	public $statusIn;

	/**
	 * @var KalturaBatchJobStatus
	 */
	public $statusEqual;

	/**
	 * @var KalturaBulkUploadObjectType
	 */
	public $bulkUploadObjectTypeEqual;

	/**
	 * @dynamicType KalturaBulkUploadObjectType
	 * @var string
	 */
	public $bulkUploadObjectTypeIn;
}
