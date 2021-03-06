<?php

/**
 * Add & Manage CategoryEntry - assign entry to category
 *
 * @service categoryEntry
 */
class CategoryEntryService extends KalturaBaseService
{
	public function initService($serviceId, $serviceName, $actionName)
	{
		parent::initService($serviceId, $serviceName, $actionName);
		$this->applyPartnerFilterForClass('category');
		$this->applyPartnerFilterForClass('entry');	
	}
	
	/**
	 * Add new CategoryEntry
	 * 
	 * @action add
	 * @param KalturaCategoryEntry $categoryEntry
	 * @throws KalturaErrors::INVALID_ENTRY_ID
	 * @throws KalturaErrors::CATEGORY_NOT_FOUND
	 * @throws KalturaErrors::CANNOT_ASSIGN_ENTRY_TO_CATEGORY
	 * @throws KalturaErrors::CATEGORY_ENTRY_ALREADY_EXISTS
	 * @return KalturaCategoryEntry
	 */
	function addAction(KalturaCategoryEntry $categoryEntry)
	{
		$categoryEntry->validateForInsert();
		
		$entry = entryPeer::retrieveByPK($categoryEntry->entryId);
		if (!$entry)
			throw new KalturaAPIException(KalturaErrors::INVALID_ENTRY_ID, $categoryEntry->entryId);
			
		$category = categoryPeer::retrieveByPK($categoryEntry->categoryId);
		if (!$category)
			throw new KalturaAPIException(KalturaErrors::CATEGORY_NOT_FOUND, $categoryEntry->categoryId);
			
		$categoryEntries = categoryEntryPeer::retrieveActiveAndPendingByEntryId($categoryEntry->entryId);
		
		if (count($categoryEntries) >= entry::MAX_CATEGORIES_PER_ENTRY)
			throw new KalturaAPIException(KalturaErrors::MAX_CATEGORIES_FOR_ENTRY_REACHED, entry::MAX_CATEGORIES_PER_ENTRY);
			
		//validate user is entiteld to assign entry to this category 
		if (kEntitlementUtils::getEntitlementEnforcement() && $category->getContributionPolicy() != ContributionPolicyType::ALL)
		{
			$categoryKuser = categoryKuserPeer::retrieveByCategoryIdAndActiveKuserId($categoryEntry->categoryId, kCurrentContext::getCurrentKsKuserId());
			if(!$categoryKuser)
			{
				KalturaLog::err("User [" . kCurrentContext::getCurrentKsKuserId() . "] is not a member of the category [{$categoryEntry->categoryId}]");
				throw new KalturaAPIException(KalturaErrors::CANNOT_ASSIGN_ENTRY_TO_CATEGORY);
			}
			if($categoryKuser->getPermissionLevel() == CategoryKuserPermissionLevel::MEMBER)
			{
				KalturaLog::err("User [" . kCurrentContext::getCurrentKsKuserId() . "] permission level [" . $categoryKuser->getPermissionLevel() . "] on category [{$categoryEntry->categoryId}] is not member [" . CategoryKuserPermissionLevel::MEMBER . "]");
				throw new KalturaAPIException(KalturaErrors::CANNOT_ASSIGN_ENTRY_TO_CATEGORY);
			}
				
			if($categoryKuser->getPermissionLevel() != CategoryKuserPermissionLevel::MANAGER &&
				$entry->getKuserId() != kCurrentContext::getCurrentKsKuserId() && 
				$entry->getCreatorKuserId() != kCurrentContext::getCurrentKsKuserId())
				throw new KalturaAPIException(KalturaErrors::CANNOT_ASSIGN_ENTRY_TO_CATEGORY);				
		}
		
		$categoryEntryExists = categoryEntryPeer::retrieveByCategoryIdAndEntryId($categoryEntry->categoryId, $categoryEntry->entryId);
		if($categoryEntryExists && $categoryEntryExists->getStatus() == CategoryEntryStatus::ACTIVE)
			throw new KalturaAPIException(KalturaErrors::CATEGORY_ENTRY_ALREADY_EXISTS);
		
		if(!$categoryEntryExists)
		{
			$dbCategoryEntry = new categoryEntry();
		}
		else
		{
			$dbCategoryEntry = $categoryEntryExists;
		}
		
		$categoryEntry->toInsertableObject($dbCategoryEntry);
		
		$dbCategoryEntry->setStatus(CategoryEntryStatus::ACTIVE);
		
		if (kEntitlementUtils::getEntitlementEnforcement() && $category->getModeration())
		{
			$categoryKuser = categoryKuserPeer::retrieveByCategoryIdAndActiveKuserId($categoryEntry->categoryId, kCurrentContext::getCurrentKsKuserId());
			if(!$categoryKuser ||
				($categoryKuser->getPermissionLevel() != CategoryKuserPermissionLevel::MANAGER && 
				$categoryKuser->getPermissionLevel() != CategoryKuserPermissionLevel::MODERATOR))
				$dbCategoryEntry->setStatus(CategoryEntryStatus::PENDING);
		}
		
		$partnerId = kCurrentContext::$partner_id ? kCurrentContext::$partner_id : kCurrentContext::$ks_partner_id;
		$dbCategoryEntry->setPartnerId($partnerId);
		$dbCategoryEntry->save();
		
		//need to select the entry again - after update
		$entry = entryPeer::retrieveByPK($categoryEntry->entryId);		
		myNotificationMgr::createNotification(kNotificationJobData::NOTIFICATION_TYPE_ENTRY_UPDATE, $entry);
		
		$categoryEntry = new KalturaCategoryEntry();
		$categoryEntry->fromObject($dbCategoryEntry);

		return $categoryEntry;
	}
	
	/**
	 * Delete CategoryEntry
	 * 
	 * @action delete
	 * @param string $entryId
	 * @param int $categoryId
	 * @throws KalturaErrors::INVALID_ENTRY_ID
	 * @throws KalturaErrors::CATEGORY_NOT_FOUND
	 * @throws KalturaErrors::CANNOT_REMOVE_ENTRY_FROM_CATEGORY
	 * @throws KalturaErrors::ENTRY_IS_NOT_ASSIGNED_TO_CATEGORY
	 * 
	 */
	function deleteAction($entryId, $categoryId)
	{
		$entry = entryPeer::retrieveByPK($entryId);

		if (!$entry)
		{
			if (kCurrentContext::$master_partner_id != Partner::BATCH_PARTNER_ID)
				throw new KalturaAPIException(KalturaErrors::INVALID_ENTRY_ID, $entryId);
		}
			
		$category = categoryPeer::retrieveByPK($categoryId);
		if (!$category && kCurrentContext::$master_partner_id != Partner::BATCH_PARTNER_ID)
			throw new KalturaAPIException(KalturaErrors::CATEGORY_NOT_FOUND, $categoryId);
		
		//validate user is entiteld to remove entry from category 
		if(kEntitlementUtils::getEntitlementEnforcement() && 
			$entry->getKuserId() != kCurrentContext::getCurrentKsKuserId() && 
			$entry->getCreatorKuserId() != kCurrentContext::getCurrentKsKuserId())
		{
			$categoryKuser = categoryKuserPeer::retrieveByCategoryIdAndActiveKuserId($categoryId, kCurrentContext::getCurrentKsKuserId());
			if(!$categoryKuser || $categoryKuser->getPermissionLevel() != CategoryKuserPermissionLevel::MANAGER)
				throw new KalturaAPIException(KalturaErrors::CANNOT_REMOVE_ENTRY_FROM_CATEGORY);		
		}
			
		$dbCategoryEntry = categoryEntryPeer::retrieveByCategoryIdAndEntryId($categoryId, $entryId);
		if(!$dbCategoryEntry)
			throw new KalturaAPIException(KalturaErrors::ENTRY_IS_NOT_ASSIGNED_TO_CATEGORY);
		
		$dbCategoryEntry->setStatus(CategoryEntryStatus::DELETED);
		$dbCategoryEntry->save();
		
		//need to select the entry again - after update
		$entry = entryPeer::retrieveByPK($entryId);		
		myNotificationMgr::createNotification(kNotificationJobData::NOTIFICATION_TYPE_ENTRY_UPDATE, $entry);
	}
	
	/**
	 * List all categoryEntry
	 * 
	 * @action list
	 * @param KalturaCategoryEntryFilter $filter
	 * @param KalturaFilterPager $pager
	 * @throws KalturaErrors::MUST_FILTER_ENTRY_ID_EQUAL
	 * @throws KalturaErrors::MUST_FILTER_ON_ENTRY_OR_CATEGORY
	 * @return KalturaCategoryEntryListResponse
	 */
	function listAction(KalturaCategoryEntryFilter $filter = null, KalturaFilterPager $pager = null)
	{
		if ($filter === null)
			$filter = new KalturaCategoryEntryFilter();
		
		if ($pager == null)
			$pager = new KalturaFilterPager();
			
		if ($filter->entryIdEqual == null &&
			$filter->categoryIdIn == null &&
			$filter->categoryIdEqual == null && 
			(kEntitlementUtils::getEntitlementEnforcement() || !kCurrentContext::$is_admin_session))
			throw new KalturaAPIException(KalturaErrors::MUST_FILTER_ON_ENTRY_OR_CATEGORY);		
			
		if(kEntitlementUtils::getEntitlementEnforcement())
		{
			//validate entitl for entry
			if($filter->entryIdEqual != null)
			{
				$entry = entryPeer::retrieveByPK($filter->entryIdEqual);
				if(!$entry)
					throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $filter->entryIdEqual);
			}
			
			//validate entitl for entryIn
			if($filter->entryIdIn != null)
			{
				$entry = entryPeer::retrieveByPKs($filter->entryIdIn);
				if(!$entry)
					throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $filter->entryIdIn);
			}
			
			//validate entitl categories
			if($filter->categoryIdIn != null)
			{
				$categoryIdInArr = explode(',', $filter->categoryIdIn);
				$categoryIdInArr = array_unique($categoryIdInArr);
				
				$entitledCategories = categoryPeer::retrieveByPKs($categoryIdInArr);
				
				if(!count($entitledCategories) || count($entitledCategories) != count($categoryIdInArr))
					throw new KalturaAPIException(KalturaErrors::CATEGORY_NOT_FOUND, $filter->categoryIdIn);
					
				$categoriesIdsUnlisted = array();
				foreach($entitledCategories as $category)
				{
					if($category->getDisplayInSearch() == DisplayInSearchType::CATEGORY_MEMBERS_ONLY)
						$categoriesIdsUnlisted[] = $category->getId();
				}

				if(count($categoriesIdsUnlisted))
				{
					$categoriesUnlistWithMembership = categoryKuserPeer::retrieveByCategoriesIdsAndActiveKuserId($categoriesIdsUnlisted, kCurrentContext::getCurrentKsKuserId());

					if(count($categoriesIdsUnlisted) != count($categoriesUnlistWithMembership))
						throw new KalturaAPIException(KalturaErrors::CATEGORY_NOT_FOUND, $filter->categoryIdIn);
				}
			}
			
			//validate entitl category
			if($filter->categoryIdEqual != null)
			{
				$category = categoryPeer::retrieveByPK($filter->categoryIdEqual);
				if(!$category && kCurrentContext::$master_partner_id != Partner::BATCH_PARTNER_ID)
					throw new KalturaAPIException(KalturaErrors::CATEGORY_NOT_FOUND, $filter->categoryIdEqual);

				if(($category->getDisplayInSearch() == DisplayInSearchType::CATEGORY_MEMBERS_ONLY) && 
					!categoryKuserPeer::retrieveByCategoryIdAndActiveKuserId($category->getId(), kCurrentContext::getCurrentKsKuserId()))
				{
					throw new KalturaAPIException(KalturaErrors::CATEGORY_NOT_FOUND, $filter->categoryIdEqual);
				}
			}
		}
			
		$categoryEntryFilter = new categoryEntryFilter();
		$filter->toObject($categoryEntryFilter);
		 
		$c = KalturaCriteria::create(categoryEntryPeer::OM_CLASS);
		$categoryEntryFilter->attachToCriteria($c);
		$totalCount = categoryEntryPeer::doCount($c);
		
		if(!kEntitlementUtils::getEntitlementEnforcement() || ($filter->entryIdEqual == null && $filter->entryIdEqual == null))
			$pager->attachToCriteria($c);
			
		$dbCategoriesEntry = categoryEntryPeer::doSelect($c);
		
		if(kEntitlementUtils::getEntitlementEnforcement() && count($dbCategoriesEntry) && $filter->entryIdEqual != null)
		{
			//remove unlisted categories: display in search is set to members only
			$categoriesIds = array();
			foreach ($dbCategoriesEntry as $dbCategoryEntry)
				$categoriesIds[] = $dbCategoryEntry->getCategoryId();
				
			$c = KalturaCriteria::create(categoryPeer::OM_CLASS);
			$c->add(categoryPeer::ID, $categoriesIds, Criteria::IN);
			$pager->attachToCriteria($c);
			$c->applyFilters();
			
			$categoryIds = $c->getFetchedIds();
			
			foreach ($dbCategoriesEntry as $key => $dbCategoryEntry)
			{
				if(!in_array($dbCategoryEntry->getCategoryId(), $categoryIds))
				{
					KalturaLog::debug('Category [' . print_r($dbCategoryEntry->getCategoryId(),true) . '] is not listed to user');
					unset($dbCategoriesEntry[$key]);
				}
			}
			
			$totalCount = $c->getRecordsCount();
		}
		
		$categoryEntrylist = KalturaCategoryEntryArray::fromCategoryEntryArray($dbCategoriesEntry);
		$response = new KalturaCategoryEntryListResponse();
		$response->objects = $categoryEntrylist;
		$response->totalCount = $totalCount; // no pager since category entry is limited to ENTRY::MAX_CATEGORIES_PER_ENTRY
		return $response;
	}
	
	/**
	 * Index CategoryEntry by Id
	 * 
	 * @action index
	 * @param string $entryId
	 * @param int $categoryId
	 * @param bool $shouldUpdate
	 * @throws KalturaErrors::ENTRY_IS_NOT_ASSIGNED_TO_CATEGORY
	 * @return int
	 */
	function indexAction($entryId, $categoryId, $shouldUpdate = true)
	{
		if(kEntitlementUtils::getEntitlementEnforcement())
			throw new KalturaAPIException(KalturaErrors::CANNOT_INDEX_OBJECT_WHEN_ENTITLEMENT_IS_ENABLE);
		
		$dbCategoryEntry = categoryEntryPeer::retrieveByCategoryIdAndEntryId($categoryId, $entryId);
		if(!$dbCategoryEntry)
			throw new KalturaAPIException(KalturaErrors::ENTRY_IS_NOT_ASSIGNED_TO_CATEGORY);
			
		if (!$shouldUpdate)
		{
			$dbCategoryEntry->setUpdatedAt(time());
			$dbCategoryEntry->save();
			
			return $dbCategoryEntry->getIntId();
		}
		
		$dbCategoryEntry->reSetCategoryFullIds();
		$dbCategoryEntry->save();
		
		
		$entry = entryPeer::retrieveByPK($dbCategoryEntry->getEntryId());	
		if($entry)
		{
			$categoryEntries = categoryEntryPeer::retrieveActiveByEntryId($entryId);
			
			$categoriesIds = array();
			foreach($categoryEntries as $categoryEntry)
			{
				$categoriesIds[] = $categoryEntry->getCategoryId();
			}
			
			$categories = categoryPeer::retrieveByPKs($categoriesIds);
			
			$isCategoriesModified = false;
			$categoriesFullName = array();
			foreach($categories as $category)
			{
				if($category->getPrivacyContexts() == null)
				{
					$categoriesFullName[] = $category->getFullName();
					$isCategoriesModified = true;
				}
			} 
				
			$entry->setCategories(implode(',', $categoriesFullName));
			categoryEntryPeer::syncEntriesCategories($entry, $isCategoriesModified);
			$entry->save();
		}
		
		return $dbCategoryEntry->getId();
				
	}
	
	/**
	 * activate CategoryEntry when it is pending moderation
	 * 
	 * @action activate
	 * @param string $entryId
	 * @param int $categoryId
	 * @throws KalturaErrors::INVALID_ENTRY_ID
	 * @throws KalturaErrors::CATEGORY_NOT_FOUND
	 * @throws KalturaErrors::ENTRY_IS_NOT_ASSIGNED_TO_CATEGORY
	 * @throws KalturaErrors::CANNOT_ACTIVATE_CATEGORY_ENTRY
	 */
	function activateAction($entryId, $categoryId)
	{
		$entry = entryPeer::retrieveByPK($entryId);
		if (!$entry)
			throw new KalturaAPIException(KalturaErrors::INVALID_ENTRY_ID, $entryId);
			
		$category = categoryPeer::retrieveByPK($categoryId);
		if (!$category)
			throw new KalturaAPIException(KalturaErrors::CATEGORY_NOT_FOUND, $categoryId);
		
		$dbCategoryEntry = categoryEntryPeer::retrieveByCategoryIdAndEntryIdNotRejected($categoryId, $entryId);
		if(!$dbCategoryEntry)
			throw new KalturaAPIException(KalturaErrors::ENTRY_IS_NOT_ASSIGNED_TO_CATEGORY);
			
		//validate user is entiteld to activate entry from category 
		if(kEntitlementUtils::getEntitlementEnforcement())
		{
			$categoryKuser = categoryKuserPeer::retrieveByCategoryIdAndActiveKuserId($categoryId, kCurrentContext::getCurrentKsKuserId());
			if(!$categoryKuser || 
				($categoryKuser->getPermissionLevel() != CategoryKuserPermissionLevel::MANAGER && 
				 $categoryKuser->getPermissionLevel() != CategoryKuserPermissionLevel::MODERATOR))
					throw new KalturaAPIException(KalturaErrors::CANNOT_ACTIVATE_CATEGORY_ENTRY);
		}
			
		if($dbCategoryEntry->getStatus() != CategoryEntryStatus::PENDING)
			throw new KalturaAPIException(KalturaErrors::CANNOT_ACTIVATE_CATEGORY_ENTRY_SINCE_IT_IS_NOT_PENDING);
			
		$dbCategoryEntry->setStatus(CategoryEntryStatus::ACTIVE);
		$dbCategoryEntry->save();
	}
	
	/**
	 * activate CategoryEntry when it is pending moderation
	 * 
	 * @action reject
	 * @param string $entryId
	 * @param int $categoryId
	 * @throws KalturaErrors::INVALID_ENTRY_ID
	 * @throws KalturaErrors::CATEGORY_NOT_FOUND
	 * @throws KalturaErrors::ENTRY_IS_NOT_ASSIGNED_TO_CATEGORY
	 * @throws KalturaErrors::CANNOT_ACTIVATE_CATEGORY_ENTRY
	 */
	function rejectAction($entryId, $categoryId)
	{
		$entry = entryPeer::retrieveByPK($entryId);
		if (!$entry)
			throw new KalturaAPIException(KalturaErrors::INVALID_ENTRY_ID, $entryId);
			
		$category = categoryPeer::retrieveByPK($categoryId);
		if (!$category)
			throw new KalturaAPIException(KalturaErrors::CATEGORY_NOT_FOUND, $categoryId);
		
		$dbCategoryEntry = categoryEntryPeer::retrieveByCategoryIdAndEntryId($categoryId, $entryId);
		if(!$dbCategoryEntry)
			throw new KalturaAPIException(KalturaErrors::ENTRY_IS_NOT_ASSIGNED_TO_CATEGORY);
			
		//validate user is entiteld to reject entry from category 
		if(kEntitlementUtils::getEntitlementEnforcement())
		{
			$categoryKuser = categoryKuserPeer::retrieveByCategoryIdAndActiveKuserId($categoryId, kCurrentContext::getCurrentKsKuserId());
			if(!$categoryKuser || 
				($categoryKuser->getPermissionLevel() != CategoryKuserPermissionLevel::MANAGER && 
				 $categoryKuser->getPermissionLevel() != CategoryKuserPermissionLevel::MODERATOR))
					throw new KalturaAPIException(KalturaErrors::CANNOT_REJECT_CATEGORY_ENTRY);
		}
			
		if($dbCategoryEntry->getStatus() != CategoryEntryStatus::PENDING)
			throw new KalturaAPIException(KalturaErrors::CANNOT_REJECT_CATEGORY_ENTRY_SINCE_IT_IS_NOT_PENDING);
			
		$dbCategoryEntry->setStatus(CategoryEntryStatus::REJECTED);
		$dbCategoryEntry->save();
	}
}