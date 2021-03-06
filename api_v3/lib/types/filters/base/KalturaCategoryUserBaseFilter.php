<?php
/**
 * @package api
 * @subpackage filters.base
 * @abstract
 */
abstract class KalturaCategoryUserBaseFilter extends KalturaFilter
{
	static private $map_between_objects = array
	(
		"categoryIdEqual" => "_eq_category_id",
		"categoryIdIn" => "_in_category_id",
		"userIdEqual" => "_eq_user_id",
		"userIdIn" => "_in_user_id",
		"permissionLevelEqual" => "_eq_permission_level",
		"permissionLevelIn" => "_in_permission_level",
		"statusEqual" => "_eq_status",
		"statusIn" => "_in_status",
		"createdAtGreaterThanOrEqual" => "_gte_created_at",
		"createdAtLessThanOrEqual" => "_lte_created_at",
		"updatedAtGreaterThanOrEqual" => "_gte_updated_at",
		"updatedAtLessThanOrEqual" => "_lte_updated_at",
		"updateMethodEqual" => "_eq_update_method",
		"updateMethodIn" => "_in_update_method",
		"categoryFullIdsStartsWith" => "_likex_category_full_ids",
		"categoryFullIdsEqual" => "_eq_category_full_ids",
	);

	static private $order_by_map = array
	(
		"+createdAt" => "+created_at",
		"-createdAt" => "-created_at",
		"+updatedAt" => "+updated_at",
		"-updatedAt" => "-updated_at",
	);

	public function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), KalturaCategoryUserBaseFilter::$map_between_objects);
	}

	public function getOrderByMap()
	{
		return array_merge(parent::getOrderByMap(), KalturaCategoryUserBaseFilter::$order_by_map);
	}

	/**
	 * @var int
	 */
	public $categoryIdEqual;

	/**
	 * @var string
	 */
	public $categoryIdIn;

	/**
	 * @var string
	 */
	public $userIdEqual;

	/**
	 * @var string
	 */
	public $userIdIn;

	/**
	 * @var KalturaCategoryUserPermissionLevel
	 */
	public $permissionLevelEqual;

	/**
	 * @var string
	 */
	public $permissionLevelIn;

	/**
	 * @var KalturaCategoryUserStatus
	 */
	public $statusEqual;

	/**
	 * @var string
	 */
	public $statusIn;

	/**
	 * @var int
	 */
	public $createdAtGreaterThanOrEqual;

	/**
	 * @var int
	 */
	public $createdAtLessThanOrEqual;

	/**
	 * @var int
	 */
	public $updatedAtGreaterThanOrEqual;

	/**
	 * @var int
	 */
	public $updatedAtLessThanOrEqual;

	/**
	 * @var KalturaUpdateMethodType
	 */
	public $updateMethodEqual;

	/**
	 * @var string
	 */
	public $updateMethodIn;

	/**
	 * @var string
	 */
	public $categoryFullIdsStartsWith;

	/**
	 * @var string
	 */
	public $categoryFullIdsEqual;
}
