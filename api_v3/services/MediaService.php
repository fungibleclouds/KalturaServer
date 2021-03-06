<?php

/**
 * Media service lets you upload and manage media files (images / videos & audio)
 *
 * @service media
 * @package api
 * @subpackage services
 */
class MediaService extends KalturaEntryService
{
	protected function kalturaNetworkAllowed($actionName)
	{
		if ($actionName === 'get') {
			return true;
		}
		if ($actionName === 'convert') {
			return true;
		}
		if ($actionName === 'addFromEntry') {
			return true;
		}
		if ($actionName === 'addFromFlavorAsset') {
			return true;
		}
		if ($actionName === 'addContent') {
			return true;
		}
		if ($actionName === 'updateContent') {
			return true;
		}
	
		// admin and batch
		if ($actionName === 'list' && kCurrentContext::$master_partner_id < 0) {
			return true;
		}
		
		return parent::kalturaNetworkAllowed($actionName);
	}
	
	protected function partnerRequired($actionName)
	{
		if ($actionName === 'flag') {
			return false;
		}
		return parent::partnerRequired($actionName);
	}

    /**
     * Add entry
     *
     * @action add
     * @param KalturaMediaEntry $entry
     * @return KalturaMediaEntry
     */
    function addAction(KalturaMediaEntry $entry)
    {
    	if($entry->conversionQuality && !$entry->conversionProfileId)
    		$entry->conversionProfileId = $entry->conversionQuality;
    		
    	$dbEntry = parent::add($entry, $entry->conversionProfileId);
		$dbEntry->setStatus(entryStatus::NO_CONTENT);
		$dbEntry->save();
		
		$trackEntry = new TrackEntry();
		$trackEntry->setEntryId($dbEntry->getId());
		$trackEntry->setTrackEventTypeId(TrackEntry::TRACK_ENTRY_EVENT_TYPE_ADD_ENTRY);
		$trackEntry->setDescription(__METHOD__ . ":" . __LINE__ . "::ENTRY_MEDIA");
		TrackEntry::addTrackEntry($trackEntry);
		
    	myNotificationMgr::createNotification(kNotificationJobData::NOTIFICATION_TYPE_ENTRY_ADD, $dbEntry, $dbEntry->getPartnerId(), null, null, null, $dbEntry->getId());
	
		$entry->fromObject($dbEntry);
		return $entry;
    }

    /**
     * Add content to media entry which is not yet associated with content (therefore is in status NO_CONTENT).
     * If the requirement is to replace the entry's associated content, use action updateContent.
     *
     * @action addContent
     * @param string $entryId
     * @param KalturaResource $resource
     * @return KalturaMediaEntry
     * @throws KalturaErrors::ENTRY_ID_NOT_FOUND
     * @throws KalturaErrors::ENTRY_ALREADY_WITH_CONTENT
     * @validateUser entry entryId edit
     */
    function addContentAction($entryId, KalturaResource $resource = null)
    {
		$dbEntry = entryPeer::retrieveByPK($entryId);

		if (!$dbEntry || $dbEntry->getType() != KalturaEntryType::MEDIA_CLIP)
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);
		
		if ($dbEntry->getStatus() != entryStatus::NO_CONTENT)
			throw new KalturaAPIException(KalturaErrors::ENTRY_ALREADY_WITH_CONTENT);
			
		
		
    	$resource->validateEntry($dbEntry);
    	$kResource = $resource->toObject();
    	$this->attachResource($kResource, $dbEntry);
    	
    	$resource->entryHandled($dbEntry);
    	
		return $this->getEntry($entryId);
    }
    
    /**
     * @param KalturaResource $resource
     * @param entry $dbEntry
     * @param int $conversionProfileId
     */
    protected function replaceResource(KalturaResource $resource, entry $dbEntry, $conversionProfileId = null)
    {
		if($dbEntry->getStatus() == KalturaEntryStatus::NO_CONTENT || $dbEntry->getMediaType() == KalturaMediaType::IMAGE)
		{
			$resource->validateEntry($dbEntry);
			
			if($conversionProfileId)
			{
				$dbEntry->setConversionQuality($conversionProfileId);
				$dbEntry->save();
			}
			
			$kResource = $resource->toObject();
			$this->attachResource($kResource, $dbEntry);
		}
		else 
		{
			$partner = $this->getPartner();
			if(!$partner->getEnabledService(PermissionName::FEATURE_ENTRY_REPLACEMENT))
			{
				KalturaLog::notice("Replacement is not allowed to the partner permission [FEATURE_ENTRY_REPLACEMENT] is needed");
				throw new KalturaAPIException(KalturaErrors::FEATURE_FORBIDDEN, PermissionName::FEATURE_ENTRY_REPLACEMENT);
			}
			
			if($dbEntry->getReplacingEntryId())
				throw new KalturaAPIException(KalturaErrors::ENTRY_REPLACEMENT_ALREADY_EXISTS);
				
			$resource->validateEntry($dbEntry);
			
			$tempMediaEntry = new KalturaMediaEntry();
		 	$tempMediaEntry->type = $dbEntry->getType();
			$tempMediaEntry->mediaType = $dbEntry->getMediaType();
			$tempMediaEntry->conversionProfileId = $dbEntry->getConversionQuality();
			
			if($conversionProfileId)
				$tempMediaEntry->conversionProfileId = $conversionProfileId;
			
			$tempDbEntry = $this->prepareEntryForInsert($tempMediaEntry);
			$tempDbEntry->setDisplayInSearch(mySearchUtils::DISPLAY_IN_SEARCH_SYSTEM);
			$tempDbEntry->setPartnerId($dbEntry->getPartnerId());
			$tempDbEntry->setReplacedEntryId($dbEntry->getId());
			$tempDbEntry->save();
			
			$dbEntry->setReplacingEntryId($tempDbEntry->getId());
			$dbEntry->setReplacementStatus(entryReplacementStatus::NOT_READY_AND_NOT_APPROVED);
			if(!$partner->getEnabledService(PermissionName::FEATURE_ENTRY_REPLACEMENT_APPROVAL))
				$dbEntry->setReplacementStatus(entryReplacementStatus::APPROVED_BUT_NOT_READY);
			$dbEntry->save();
				
			$kResource = $resource->toObject();
			$this->attachResource($kResource, $tempDbEntry);
		}
    	$resource->entryHandled($dbEntry);
    }
    
    /**
     * @param kResource $resource
     * @param entry $dbEntry
     * @param asset $dbAsset
     * @return asset
     */
    protected function attachResource(kResource $resource, entry $dbEntry, asset $dbAsset = null)
    {
    	switch($resource->getType())
    	{
			case 'kAssetsParamsResourceContainers':
				// image entry doesn't support asset params
				if($dbEntry->getMediaType() == KalturaMediaType::IMAGE)
					throw new KalturaAPIException(KalturaErrors::RESOURCE_TYPE_NOT_SUPPORTED, get_class($resource));
					
				return $this->attachAssetsParamsResourceContainers($resource, $dbEntry);
				
			case 'kAssetParamsResourceContainer':
				// image entry doesn't support asset params
				if($dbEntry->getMediaType() == KalturaMediaType::IMAGE)
					throw new KalturaAPIException(KalturaErrors::RESOURCE_TYPE_NOT_SUPPORTED, get_class($resource));
					
				return $this->attachAssetParamsResourceContainer($resource, $dbEntry, $dbAsset);
				
			case 'kUrlResource':
				return $this->attachUrlResource($resource, $dbEntry, $dbAsset);
				
			case 'kLocalFileResource':
				return $this->attachLocalFileResource($resource, $dbEntry, $dbAsset);
				
			case 'kFileSyncResource':
				return $this->attachFileSyncResource($resource, $dbEntry, $dbAsset);
				
			case 'kRemoteStorageResource':
			case 'kRemoteStorageResources':
				return $this->attachRemoteStorageResource($resource, $dbEntry, $dbAsset);
				
			case 'kOperationResource':
				if($dbEntry->getMediaType() == KalturaMediaType::IMAGE)
					throw new KalturaAPIException(KalturaErrors::RESOURCE_TYPE_NOT_SUPPORTED, get_class($resource));
					
				return $this->attachOperationResource($resource, $dbEntry, $dbAsset);
				
			default:
				KalturaLog::err("Resource of type [" . get_class($resource) . "] is not supported");
				$dbEntry->setStatus(entryStatus::ERROR_IMPORTING);
				$dbEntry->save();
				
				throw new KalturaAPIException(KalturaErrors::RESOURCE_TYPE_NOT_SUPPORTED, get_class($resource));
    	}
    }
    
	/**
	 * Adds new media entry by importing an HTTP or FTP URL.
	 * The entry will be queued for import and then for conversion.
	 * This action should be exposed only to the batches
	 * 
	 * @action addFromBulk
	 * @param KalturaMediaEntry $mediaEntry Media entry metadata  
	 * @param string $url An HTTP or FTP URL
	 * @param int $bulkUploadId The id of the bulk upload job
	 * @return KalturaMediaEntry The new media entry
	 * 
	 * @throws KalturaErrors::PROPERTY_VALIDATION_MIN_LENGTH
	 * @throws KalturaErrors::PROPERTY_VALIDATION_CANNOT_BE_NULL
	 * 
	 * @deprecated use media.add instead
	 */
	function addFromBulkAction(KalturaMediaEntry $mediaEntry, $url, $bulkUploadId)
	{
		return $this->addDbFromUrl($mediaEntry, $url, $bulkUploadId);
	}
	
	/**
	 * Adds new media entry by importing an HTTP or FTP URL.
	 * The entry will be queued for import and then for conversion.
	 * 
	 * @action addFromUrl
	 * @param KalturaMediaEntry $mediaEntry Media entry metadata  
	 * @param string $url An HTTP or FTP URL
	 * @return KalturaMediaEntry The new media entry
	 * 
	 * @throws KalturaErrors::PROPERTY_VALIDATION_MIN_LENGTH
	 * @throws KalturaErrors::PROPERTY_VALIDATION_CANNOT_BE_NULL
	 * 
	 * @deprecated use media.add instead
	 */
	function addFromUrlAction(KalturaMediaEntry $mediaEntry, $url)
	{
		return $this->addDbFromUrl($mediaEntry, $url);
	}
	
	private function addDbFromUrl(KalturaMediaEntry $mediaEntry, $url, $bulkUploadId = null)
	{
    	if($mediaEntry->conversionQuality && !$mediaEntry->conversionProfileId)
    		$mediaEntry->conversionProfileId = $mediaEntry->conversionQuality;
    		
		$dbEntry = $this->prepareEntryForInsert($mediaEntry);
		if($bulkUploadId)
			$dbEntry->setBulkUploadId($bulkUploadId);
		
        $kshowId = $dbEntry->getKshowId();
		
		// setup the needed params for my insert entry helper
		$paramsArray = array (
			"entry_media_source" => KalturaSourceType::URL,
            "entry_media_type" => $dbEntry->getMediaType(),
			"entry_url" => $url,
			"entry_license" => $dbEntry->getLicenseType(),
			"entry_credit" => $dbEntry->getCredit(),
			"entry_source_link" => $dbEntry->getSourceLink(),
			"entry_tags" => $dbEntry->getTags(),
		);
		
		$token = $this->getKsUniqueString();
		$insert_entry_helper = new myInsertEntryHelper(null , $dbEntry->getKuserId(), $kshowId, $paramsArray);
		$insert_entry_helper->setPartnerId($this->getPartnerId(), $this->getPartnerId() * 100);
		$insert_entry_helper->insertEntry($token, $dbEntry->getType(), $dbEntry->getId(), $dbEntry->getName(), $dbEntry->getTags(), $dbEntry);
		$dbEntry = $insert_entry_helper->getEntry();

		myNotificationMgr::createNotification( kNotificationJobData::NOTIFICATION_TYPE_ENTRY_ADD, $dbEntry, $this->getPartnerId(), null, null, null, $dbEntry->getId());

		// FIXME: need to remove something from cache? in the old code the kshow was removed
		$mediaEntry->fromObject($dbEntry);
		return $mediaEntry;
	}

	/**
	 * Adds new media entry by importing the media file from a search provider. 
	 * This action should be used with the search service result.
	 *
	 * @action addFromSearchResult
	 * @param KalturaMediaEntry $mediaEntry Media entry metadata
	 * @param KalturaSearchResult $searchResult Result object from search service
	 * @return KalturaMediaEntry The new media entry
	 * 
	 * @throws KalturaErrors::PROPERTY_VALIDATION_MIN_LENGTH
	 * @throws KalturaErrors::PROPERTY_VALIDATION_CANNOT_BE_NULL
	 * 
	 * @deprecated use media.add instead
	 */
	function addFromSearchResultAction(KalturaMediaEntry $mediaEntry = null, KalturaSearchResult $searchResult = null)
	{
    	if($mediaEntry->conversionQuality && !$mediaEntry->conversionProfileId)
    		$mediaEntry->conversionProfileId = $mediaEntry->conversionQuality;
    		
		if ($mediaEntry === null)
			$mediaEntry = new KalturaMediaEntry();
			
		if ($searchResult === null)
			$searchResult = new KalturaSearchResult();
			
		// copy the fields from search result if they are missing in media entry 
		// this should be checked before prepareEntry method call
		if ($mediaEntry->name === null)
			$mediaEntry->name = $searchResult->title;
			
		if ($mediaEntry->mediaType === null)
			$mediaEntry->mediaType = $searchResult->mediaType;

        if ($mediaEntry->description === null)
        	$mediaEntry->description = $searchResult->description;
        
        if ($mediaEntry->creditUrl === null)
        	$mediaEntry->creditUrl = $searchResult->sourceLink;
        	
       	if ($mediaEntry->creditUserName === null)
       		$mediaEntry->creditUserName = $searchResult->credit;
       		
     	if ($mediaEntry->tags === null)
      		$mediaEntry->tags = $searchResult->tags;

     	$searchResult->validatePropertyNotNull("searchSource");
     	
    	$mediaEntry->sourceType = KalturaSourceType::SEARCH_PROVIDER;
     	$mediaEntry->searchProviderType = $searchResult->searchSource;
     	$mediaEntry->searchProviderId = $searchResult->id;
     	
		$dbEntry = $this->prepareEntryForInsert($mediaEntry);
      	$dbEntry->setSourceId( $searchResult->id );
      	
        $kshowId = $dbEntry->getKshowId();
        	
       	// $searchResult->licenseType; // FIXME, No support for licenseType
        // FIXME - no need to clone entry if $dbEntry->getSource() == entry::ENTRY_MEDIA_SOURCE_KALTURA_USER_CLIPS
		if ($dbEntry->getSource() == entry::ENTRY_MEDIA_SOURCE_KALTURA ||
			$dbEntry->getSource() == entry::ENTRY_MEDIA_SOURCE_KALTURA_PARTNER ||
			$dbEntry->getSource() == entry::ENTRY_MEDIA_SOURCE_KALTURA_PARTNER_KSHOW ||
			$dbEntry->getSource() == entry::ENTRY_MEDIA_SOURCE_KALTURA_KSHOW ||
			$dbEntry->getSource() == entry::ENTRY_MEDIA_SOURCE_KALTURA_USER_CLIPS)
		{
			$sourceEntryId = $searchResult->id;
			$copyDataResult = myEntryUtils::copyData($sourceEntryId, $dbEntry);
			
			if (!$copyDataResult) // will be false when the entry id was not found
				throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $sourceEntryId);
				
			$dbEntry->setStatusReady();
			$dbEntry->save();
		}
		else
		{
			// setup the needed params for my insert entry helper
			$paramsArray = array (
				"entry_media_source" => $dbEntry->getSource(),
	            "entry_media_type" => $dbEntry->getMediaType(),
				"entry_url" => $searchResult->url,
				"entry_license" => $dbEntry->getLicenseType(),
				"entry_credit" => $dbEntry->getCredit(),
				"entry_source_link" => $dbEntry->getSourceLink(),
				"entry_tags" => $dbEntry->getTags(),
			);
			
			$token = $this->getKsUniqueString();
			$insert_entry_helper = new myInsertEntryHelper(null , $dbEntry->getKuserId(), $kshowId, $paramsArray);
			$insert_entry_helper->setPartnerId($this->getPartnerId(), $this->getPartnerId() * 100);
			$insert_entry_helper->insertEntry($token, $dbEntry->getType(), $dbEntry->getId(), $dbEntry->getName(), $dbEntry->getTags(), $dbEntry);
			$dbEntry = $insert_entry_helper->getEntry();
		}

		myNotificationMgr::createNotification( kNotificationJobData::NOTIFICATION_TYPE_ENTRY_ADD, $dbEntry);

		$mediaEntry->fromObject($dbEntry);
		return $mediaEntry;
	}
	
	/**
	 * Add new entry after the specific media file was uploaded and the upload token id exists
	 *
	 * @action addFromUploadedFile
	 * @param KalturaMediaEntry $mediaEntry Media entry metadata
	 * @param string $uploadTokenId Upload token id
	 * @return KalturaMediaEntry The new media entry
	 * 
	 * @throws KalturaErrors::PROPERTY_VALIDATION_MIN_LENGTH
	 * @throws KalturaErrors::PROPERTY_VALIDATION_CANNOT_BE_NULL
	 * @throws KalturaErrors::UPLOADED_FILE_NOT_FOUND_BY_TOKEN
	 * 
	 * @deprecated use media.add instead
	 */
	function addFromUploadedFileAction(KalturaMediaEntry $mediaEntry, $uploadTokenId)
	{
    	if($mediaEntry->conversionQuality && !$mediaEntry->conversionProfileId)
    		$mediaEntry->conversionProfileId = $mediaEntry->conversionQuality;
    		
		try
		{
		    // check that the uploaded file exists
			    $entryFullPath = kUploadTokenMgr::getFullPathByUploadTokenId($uploadTokenId);
		}
		catch(kCoreException $ex)
		{
		    if ($ex->getCode() == kUploadTokenException::UPLOAD_TOKEN_INVALID_STATUS);
			    throw new KalturaAPIException(KalturaErrors::UPLOAD_TOKEN_INVALID_STATUS_FOR_ADD_ENTRY);
			    
		    throw $ex;
		}
		
		if (!file_exists($entryFullPath))
		{
			$remoteDCHost = kUploadTokenMgr::getRemoteHostForUploadToken($uploadTokenId, kDataCenterMgr::getCurrentDcId());
			if($remoteDCHost)
			{
				kFileUtils::dumpApiRequest($remoteDCHost);
			}
			else
			{
				throw new KalturaAPIException(KalturaErrors::UPLOADED_FILE_NOT_FOUND_BY_TOKEN);
			}
		}
			
		$dbEntry = $this->prepareEntryForInsert($mediaEntry);
		
        $kshowId = $dbEntry->getKshowId();
			
		// setup the needed params for my insert entry helper
		$paramsArray = array (
			"entry_media_source" => KalturaSourceType::FILE,
			"entry_media_type" => $dbEntry->getMediaType(),
			"entry_full_path" => $entryFullPath,
			"entry_license" => $dbEntry->getLicenseType(),
			"entry_credit" => $dbEntry->getCredit(),
			"entry_source_link" => $dbEntry->getSourceLink(),
			"entry_tags" => $dbEntry->getTags(),
		);
		
		$token = $this->getKsUniqueString();
		$insert_entry_helper = new myInsertEntryHelper(null , $dbEntry->getKuserId(), $kshowId, $paramsArray);
		$insert_entry_helper->setPartnerId($this->getPartnerId(), $this->getPartnerId() * 100);
		$insert_entry_helper->insertEntry($token, $dbEntry->getType(), $dbEntry->getId(), $dbEntry->getName(), $dbEntry->getTags(), $dbEntry);
		$dbEntry = $insert_entry_helper->getEntry();

		kUploadTokenMgr::closeUploadTokenById($uploadTokenId);
		
		$ret = new KalturaMediaEntry();
		if($dbEntry)
		{
			myNotificationMgr::createNotification( kNotificationJobData::NOTIFICATION_TYPE_ENTRY_ADD, $dbEntry, $dbEntry->getPartnerId(), null, null, null, $dbEntry->getId());
			$ret->fromObject($dbEntry);
		}
		
		return $ret;
	}
	
	/**
	 * Add new entry after the file was recored on the server and the token id exists
	 *
	 * @action addFromRecordedWebcam
	 * @param KalturaMediaEntry $mediaEntry Media entry metadata
	 * @param string $webcamTokenId Token id for the recored webcam file 
	 * @return KalturaMediaEntry The new media entry
	 * 
	 * @throws KalturaErrors::PROPERTY_VALIDATION_MIN_LENGTH
	 * @throws KalturaErrors::PROPERTY_VALIDATION_CANNOT_BE_NULL
	 * @throws KalturaErrors::RECORDED_WEBCAM_FILE_NOT_FOUND
	 * 
	 * @deprecated use media.add instead
	 */
	function addFromRecordedWebcamAction(KalturaMediaEntry $mediaEntry, $webcamTokenId)
	{
    	if($mediaEntry->conversionQuality && !$mediaEntry->conversionProfileId)
    		$mediaEntry->conversionProfileId = $mediaEntry->conversionQuality;
    		
	    // check that the webcam file exists
	    $content = myContentStorage::getFSContentRootPath();
	    $webcamBasePath = $content."/content/webcam/".$webcamTokenId; // filesync ok
		$entryFullPath = $webcamBasePath.'.flv';
		if (! file_exists ( $entryFullPath )) {
			kFileUtils::dumpApiRequest ( kDataCenterMgr::getRemoteDcExternalUrlByDcId ( 1 - kDataCenterMgr::getCurrentDcId () ) );
			throw new KalturaAPIException ( KalturaErrors::RECORDED_WEBCAM_FILE_NOT_FOUND );
		}
			
		$dbEntry = $this->prepareEntryForInsert($mediaEntry);
		
        $kshowId = $dbEntry->getKshowId();
			
		// setup the needed params for my insert entry helper
		$paramsArray = array (
			"entry_media_source" => KalturaSourceType::WEBCAM,
            "entry_media_type" => $dbEntry->getMediaType(),
			"webcam_suffix" => $webcamTokenId,
			"entry_license" => $dbEntry->getLicenseType(),
			"entry_credit" => $dbEntry->getCredit(),
			"entry_source_link" => $dbEntry->getSourceLink(),
			"entry_tags" => $dbEntry->getTags(),
		);
		
		$token = $this->getKsUniqueString();
		$insert_entry_helper = new myInsertEntryHelper(null , $dbEntry->getKuserId(), $kshowId, $paramsArray);
		$insert_entry_helper->setPartnerId($this->getPartnerId(), $this->getPartnerId() * 100);
		$insert_entry_helper->insertEntry($token, $dbEntry->getType(), $dbEntry->getId(), $dbEntry->getName(), $dbEntry->getTags(), $dbEntry);
		$dbEntry = $insert_entry_helper->getEntry();

		myNotificationMgr::createNotification( kNotificationJobData::NOTIFICATION_TYPE_ENTRY_ADD, $dbEntry);

		$mediaEntry->fromObject($dbEntry);
		return $mediaEntry;
	}
	
	/**
	 * Copy entry into new entry
	 * 
	 * @action addFromEntry
	 * @param string $sourceEntryId Media entry id to copy from
	 * @param KalturaMediaEntry $mediaEntry Media entry metadata
	 * @param int $sourceFlavorParamsId The flavor to be used as the new entry source, source flavor will be used if not specified
	 * @return KalturaMediaEntry The new media entry
	 * @throws KalturaErrors::ENTRY_ID_NOT_FOUND
	 * @throws KalturaErrors::ORIGINAL_FLAVOR_ASSET_IS_MISSING
	 * @throws KalturaErrors::FLAVOR_PARAMS_NOT_FOUND
	 * @throws KalturaErrors::ORIGINAL_FLAVOR_ASSET_NOT_CREATED
	 * 
	 * @deprecated use media.add instead
	 */
	function addFromEntryAction($sourceEntryId, KalturaMediaEntry $mediaEntry = null, $sourceFlavorParamsId = null)
	{
    	if($mediaEntry->conversionQuality && !$mediaEntry->conversionProfileId)
    		$mediaEntry->conversionProfileId = $mediaEntry->conversionQuality;
    		
		$srcEntry = entryPeer::retrieveByPK($sourceEntryId);

		if (!$srcEntry || $srcEntry->getType() != entryType::MEDIA_CLIP)
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $sourceEntryId);
		
		$srcFlavorAsset = null;
		if(is_null($sourceFlavorParamsId))
		{
			$srcFlavorAsset = assetPeer::retrieveOriginalByEntryId($sourceEntryId);
			if(!$srcFlavorAsset)
				throw new KalturaAPIException(KalturaErrors::ORIGINAL_FLAVOR_ASSET_IS_MISSING);
		}
		else
		{
			$srcFlavorAssets = assetPeer::retrieveReadyByEntryIdAndFlavorParams($sourceEntryId, array($sourceFlavorParamsId));
			if(count($srcFlavorAssets))
			{
				$srcFlavorAsset = reset($srcFlavorAssets);
			}
			else
			{
				throw new KalturaAPIException(KalturaErrors::FLAVOR_PARAMS_NOT_FOUND);
			}
		}
		
		if ($mediaEntry === null)
			$mediaEntry = new KalturaMediaEntry();
			
		$mediaEntry->mediaType = $srcEntry->getMediaType();
			
		return $this->addEntryFromFlavorAsset($mediaEntry, $srcEntry, $srcFlavorAsset);
	}
	
	/**
	 * Copy flavor asset into new entry
	 * 
	 * @action addFromFlavorAsset
	 * @param string $sourceFlavorAssetId Flavor asset id to be used as the new entry source
	 * @param KalturaMediaEntry $mediaEntry Media entry metadata
	 * @return KalturaMediaEntry The new media entry
	 * @throws KalturaErrors::FLAVOR_ASSET_ID_NOT_FOUND
	 * @throws KalturaErrors::ENTRY_ID_NOT_FOUND
	 * @throws KalturaErrors::ORIGINAL_FLAVOR_ASSET_NOT_CREATED
	 * 
	 * @deprecated use media.add instead
	 */
	function addFromFlavorAssetAction($sourceFlavorAssetId, KalturaMediaEntry $mediaEntry = null)
	{
    	if($mediaEntry->conversionQuality && !$mediaEntry->conversionProfileId)
    		$mediaEntry->conversionProfileId = $mediaEntry->conversionQuality;
    		
		$srcFlavorAsset = assetPeer::retrieveById($sourceFlavorAssetId);

		if (!$srcFlavorAsset)
			throw new KalturaAPIException(KalturaErrors::FLAVOR_ASSET_ID_NOT_FOUND, $sourceFlavorAssetId);
		
		$sourceEntryId = $srcFlavorAsset->getEntryId();
		$srcEntry = entryPeer::retrieveByPK($sourceEntryId);

		if (!$srcEntry || $srcEntry->getType() != entryType::MEDIA_CLIP)
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $sourceEntryId);
		
		if ($mediaEntry === null)
			$mediaEntry = new KalturaMediaEntry();
			
		$mediaEntry->mediaType = $srcEntry->getMediaType();
			
		return $this->addEntryFromFlavorAsset($mediaEntry, $srcEntry, $srcFlavorAsset);
	}
	
	/**
	 * Convert entry
	 * 
	 * @action convert
	 * @param string $entryId Media entry id
	 * @param int $conversionProfileId
	 * @param KalturaConversionAttributeArray $dynamicConversionAttributes
	 * @return int job id
	 * @throws KalturaErrors::ENTRY_ID_NOT_FOUND
	 * @throws KalturaErrors::CONVERSION_PROFILE_ID_NOT_FOUND
	 * @throws KalturaErrors::FLAVOR_PARAMS_NOT_FOUND
	 */
	function convertAction($entryId, $conversionProfileId = null, KalturaConversionAttributeArray $dynamicConversionAttributes = null)
	{
		return $this->convert($entryId, $conversionProfileId, $dynamicConversionAttributes);
	}
	
	/**
	 * Get media entry by ID.
	 * 
	 * @action get
	 * @param string $entryId Media entry id
	 * @param int $version Desired version of the data
	 * @return KalturaMediaEntry The requested media entry
	 * 
	 * @throws KalturaErrors::ENTRY_ID_NOT_FOUND
	 */
	function getAction($entryId, $version = -1)
	{
		return $this->getEntry($entryId, $version, KalturaEntryType::MEDIA_CLIP);
	}

    /**
     * Get MRSS by entry id
     * XML will return as an escaped string
     *
     * @action getMrss
     * @param string $entryId Entry id
     * @return string
     */
    function getMrssAction($entryId)
    {
        $dbEntry = entryPeer::retrieveByPKNoFilter($entryId);
		if (!$dbEntry || $dbEntry->getType() != KalturaEntryType::MEDIA_CLIP)
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);

        /* @var $mrss SimpleXMLElement */
        $mrss = kMrssManager::getEntryMrssXml($dbEntry);
        return $mrss->asXML();
    }

	/**
	 * Update media entry. Only the properties that were set will be updated.
	 * 
	 * @action update
	 * @param string $entryId Media entry id to update
	 * @param KalturaMediaEntry $mediaEntry Media entry metadata to update
	 * @return KalturaMediaEntry The updated media entry
	 * @throws KalturaErrors::ENTRY_ID_NOT_FOUND
	 * @validateUser entry entryId edit
	 */
	function updateAction($entryId, KalturaMediaEntry $mediaEntry)
	{
		$dbEntry = entryPeer::retrieveByPK($entryId);
		if (!$dbEntry)
		{ 
			$dcIndex = kDataCenterMgr::getDCByObjectId($entryId, true);
			if ($dcIndex != kDataCenterMgr::getCurrentDcId())
			{
				kalturaLog::debug("EntryID [$entryId] wasn't found on current DC. dumping the request to DC id [$dcIndex]");
				kFileUtils::dumpApiRequest ( kDataCenterMgr::getRemoteDcExternalUrlByDcId ($dcIndex ) );
			}
		}
		if (!$dbEntry || $dbEntry->getType() != KalturaEntryType::MEDIA_CLIP)
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);
		
		$mediaEntry = $this->updateEntry($entryId, $mediaEntry, KalturaEntryType::MEDIA_CLIP);
		
		return $mediaEntry;
	}

	/**
	 * Replace content associated with the media entry.
	 * 
	 * @action updateContent
	 * @param string $entryId Media entry id to update
	 * @param KalturaResource $resource Resource to be used to replace entry media content
	 * @param int $conversionProfileId The conversion profile id to be used on the entry
	 * @return KalturaMediaEntry The updated media entry
	 * @throws KalturaErrors::ENTRY_ID_NOT_FOUND
	 * @throws KalturaErrors::ENTRY_REPLACEMENT_ALREADY_EXISTS
     * @throws KalturaErrors::INVALID_OBJECT_ID
     * @validateUser entry entryId edit
	 */
	function updateContentAction($entryId, KalturaResource $resource, $conversionProfileId = null)
	{
		$dbEntry = entryPeer::retrieveByPK($entryId);

		if (!$dbEntry || $dbEntry->getType() != KalturaEntryType::MEDIA_CLIP)
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);

		
		
		$this->replaceResource($resource, $dbEntry, $conversionProfileId);
				
		return $this->getEntry($entryId);
	}

	/**
	 * Delete a media entry.
	 *
	 * @action delete
	 * @param string $entryId Media entry id to delete
	 * 
 	 * @throws KalturaErrors::ENTRY_ID_NOT_FOUND
 	 * @validateUser entry entryId edit
	 */
	function deleteAction($entryId)
	{
		$this->deleteEntry($entryId, KalturaEntryType::MEDIA_CLIP);
	}

	/**
	 * Approves media replacement
	 *
	 * @action approveReplace
	 * @param string $entryId Media entry id to replace
	 * @return KalturaMediaEntry The replaced media entry
	 * 
 	 * @throws KalturaErrors::ENTRY_ID_NOT_FOUND
	 */
	function approveReplaceAction($entryId)
	{
		$dbEntry = entryPeer::retrieveByPK($entryId);

		if (!$dbEntry || $dbEntry->getType() != KalturaEntryType::MEDIA_CLIP)
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);
			
		switch($dbEntry->getReplacementStatus())
		{
			case entryReplacementStatus::APPROVED_BUT_NOT_READY:
				break;
				
			case entryReplacementStatus::READY_BUT_NOT_APPROVED:
				kBusinessConvertDL::replaceEntry($dbEntry);
				break;
				
			case entryReplacementStatus::NOT_READY_AND_NOT_APPROVED:
				$dbEntry->setReplacementStatus(entryReplacementStatus::APPROVED_BUT_NOT_READY);
				$dbEntry->save();
				
				//preventing race conditions of temp entry being ready just as you approve the replacement
				$dbReplacingEntry = entryPeer::retrieveByPK($dbEntry->getReplacingEntryId());
				if ($dbReplacingEntry && $dbReplacingEntry->getStatus() == entryStatus::READY)
					kBusinessConvertDL::replaceEntry($dbEntry);
				break;
			
			case entryReplacementStatus::NONE:
			default:
				throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_REPLACED, $entryId);
				break;
		}
		
		return $this->getEntry($entryId, -1, KalturaEntryType::MEDIA_CLIP);
	}

	/**
	 * Cancels media replacement
	 *
	 * @action cancelReplace
	 * @param string $entryId Media entry id to cancel
	 * @return KalturaMediaEntry The canceled media entry
	 * 
 	 * @throws KalturaErrors::ENTRY_ID_NOT_FOUND
	 */
	function cancelReplaceAction($entryId)
	{
		$dbEntry = entryPeer::retrieveByPK($entryId);

		if (!$dbEntry || $dbEntry->getType() != KalturaEntryType::MEDIA_CLIP)
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);
			
		if($dbEntry->getReplacingEntryId())
		{
			$dbTempEntry = entryPeer::retrieveByPK($dbEntry->getReplacingEntryId());
			if($dbTempEntry)
				myEntryUtils::deleteEntry($dbTempEntry);
		}
		
		$dbEntry->setReplacingEntryId(null);
		$dbEntry->setReplacementStatus(entryReplacementStatus::NONE);
		$dbEntry->save();
		
		return $this->getEntry($entryId, -1, KalturaEntryType::MEDIA_CLIP);
	}
	
	/**
	 * List media entries by filter with paging support.
	 * 
	 * @action list
     * @param KalturaMediaEntryFilter $filter Media entry filter
	 * @param KalturaFilterPager $pager Pager
	 * @return KalturaMediaListResponse Wrapper for array of media entries and total count
	 */
	function listAction(KalturaMediaEntryFilter $filter = null, KalturaFilterPager $pager = null)
	{
	    myDbHelper::$use_alternative_con = myDbHelper::DB_HELPER_CONN_PROPEL3;
		

	    if (!$filter)
			$filter = new KalturaMediaEntryFilter();
			
	    $filter->typeEqual = KalturaEntryType::MEDIA_CLIP;
	    list($list, $totalCount) = parent::listEntriesByFilter($filter, $pager);
	    
	    $newList = KalturaMediaEntryArray::fromEntryArray($list);
		$response = new KalturaMediaListResponse();
		$response->objects = $newList;
		$response->totalCount = $totalCount;
		return $response;
	}
	
	/**
	 * Count media entries by filter.
	 * 
	 * @action count
     * @param KalturaMediaEntryFilter $filter Media entry filter
	 * @return int
	 */
	function countAction(KalturaMediaEntryFilter $filter = null)
	{
	    if (!$filter)
			$filter = new KalturaMediaEntryFilter();
			
		$filter->typeEqual = KalturaEntryType::MEDIA_CLIP;
		
		return parent::countEntriesByFilter($filter);
	}

	/**
	 * Upload a media file to Kaltura, then the file can be used to create a media entry. 
	 * 
	 * @action upload
	 * @param file $fileData The file data
	 * @return string Upload token id
	 * 
	 * @deprecated use upload.upload or uploadToken.add instead
	 */
	function uploadAction($fileData)
	{
		$ksUnique = $this->getKsUniqueString();
		
		$uniqueId = substr(base_convert(md5(uniqid(rand(), true)), 16, 36), 1, 20);
		
		$ext = pathinfo($fileData["name"], PATHINFO_EXTENSION);
		$token = $ksUnique."_".$uniqueId.".".$ext;
		
		$res = myUploadUtils::uploadFileByToken($fileData, $token, "", null, true);
	
		return $res["token"];
	}

	/**
	 * Update media entry thumbnail by a specified time offset (In seconds)
	 * If flavor params id not specified, source flavor will be used by default
	 * 
	 * @action updateThumbnail
	 * @param string $entryId Media entry id
	 * @param int $timeOffset Time offset (in seconds)
	 * @param int $flavorParamsId The flavor params id to be used
	 * @return KalturaMediaEntry The media entry
	 * 
	 * @throws KalturaErrors::ENTRY_ID_NOT_FOUND
	 * @throws KalturaErrors::PERMISSION_DENIED_TO_UPDATE_ENTRY
	 * 
	 * @deprecated
	 */
	function updateThumbnailAction($entryId, $timeOffset, $flavorParamsId = null)
	{
		return parent::updateThumbnailForEntryFromSourceEntry($entryId, $entryId, $timeOffset, KalturaEntryType::MEDIA_CLIP, $flavorParamsId);
	}
	
	/**
	 * Update media entry thumbnail from a different entry by a specified time offset (In seconds)
	 * If flavor params id not specified, source flavor will be used by default
	 * 
	 * @action updateThumbnailFromSourceEntry
	 * @param string $entryId Media entry id
	 * @param string $sourceEntryId Media entry id
	 * @param int $timeOffset Time offset (in seconds)
	 * @param int $flavorParamsId The flavor params id to be used
	 * @return KalturaMediaEntry The media entry
	 * 
	 * @throws KalturaErrors::ENTRY_ID_NOT_FOUND
	 * @throws KalturaErrors::PERMISSION_DENIED_TO_UPDATE_ENTRY
	 * 
	 * @deprecated
	 */
	function updateThumbnailFromSourceEntryAction($entryId, $sourceEntryId, $timeOffset, $flavorParamsId = null)
	{
		return parent::updateThumbnailForEntryFromSourceEntry($entryId, $sourceEntryId, $timeOffset, KalturaEntryType::MEDIA_CLIP, $flavorParamsId);
	}	

	/**
	 * Update media entry thumbnail using a raw jpeg file
	 * 
	 * @action updateThumbnailJpeg
	 * @param string $entryId Media entry id
	 * @param file $fileData Jpeg file data
	 * @return KalturaMediaEntry The media entry
	 * 
	 * @throws KalturaErrors::ENTRY_ID_NOT_FOUND
	 * @throws KalturaErrors::PERMISSION_DENIED_TO_UPDATE_ENTRY
	 * 
	 * @deprecated
	 */
	function updateThumbnailJpegAction($entryId, $fileData)
	{
		return parent::updateThumbnailJpegForEntry($entryId, $fileData, KalturaEntryType::MEDIA_CLIP);
	}
	
	/**
	 * Update entry thumbnail using url
	 * 
	 * @action updateThumbnailFromUrl
	 * @param string $entryId Media entry id
	 * @param string $url file url
	 * @return KalturaBaseEntry The media entry
	 * 
	 * @throws KalturaErrors::ENTRY_ID_NOT_FOUND
	 * @throws KalturaErrors::PERMISSION_DENIED_TO_UPDATE_ENTRY
	 * 
	 * @deprecated
	 */
	function updateThumbnailFromUrlAction($entryId, $url)
	{
		return parent::updateThumbnailForEntryFromUrl($entryId, $url, KalturaEntryType::MEDIA_CLIP);
	}
	
	/**
	 * Request a new conversion job, this can be used to convert the media entry to a different format
	 * 
	 * @action requestConversion
	 * @param string $entryId Media entry id
	 * @param string $fileFormat Format to convert
	 * @return int The queued job id
	 * 
	 * @throws KalturaErrors::ENTRY_ID_NOT_FOUND
	 */
	public function requestConversionAction($entryId, $fileFormat)
	{
		$dbEntry = entryPeer::retrieveByPK($entryId);

		if (!$dbEntry || $dbEntry->getType() != KalturaEntryType::MEDIA_CLIP)
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);
		
		if ($dbEntry->getMediaType() == KalturaMediaType::AUDIO)
		{
			// for audio - force format flv regardless what the user really asked for
			$fileFormat = "flv";
		}

//		$job = myBatchDownloadVideoServer::addJob($this->getKuser()->getPuserId(), $dbEntry, null, $fileFormat);
		$flavorParams = myConversionProfileUtils::getFlavorParamsFromFileFormat ( $this->getPartnerId() , $fileFormat );
		
		$err = null;
		$job = kBusinessPreConvertDL::decideAddEntryFlavor(null, $dbEntry->getId(), $flavorParams->getId(), $err);
		
		if ( $job )	
			return $job->getId();
		else
			return null;
	}
	
	/**
	 * Flag inappropriate media entry for moderation
	 *
	 * @action flag
	 * @param string $entryId
	 * @param KalturaModerationFlag $moderationFlag
	 * 
 	 * @throws KalturaErrors::ENTRY_ID_NOT_FOUND
	 */
	public function flagAction(KalturaModerationFlag $moderationFlag)
	{
		KalturaResponseCacher::disableCache();		
		return parent::flagEntry($moderationFlag, KalturaEntryType::MEDIA_CLIP);
	}
	
	/**
	 * Reject the media entry and mark the pending flags (if any) as moderated (this will make the entry non playable)
	 *
	 * @action reject
	 * @param string $entryId
	 * 
	 * @throws KalturaErrors::ENTRY_ID_NOT_FOUND
	 */
	public function rejectAction($entryId)
	{
		parent::rejectEntry($entryId, KalturaEntryType::MEDIA_CLIP);
	}
	
	/**
	 * Approve the media entry and mark the pending flags (if any) as moderated (this will make the entry playable) 
	 *
	 * @action approve
	 * @param string $entryId
	 * 
	 * @throws KalturaErrors::ENTRY_ID_NOT_FOUND
	 */
	public function approveAction($entryId)
	{
		parent::approveEntry($entryId, KalturaEntryType::MEDIA_CLIP);
	}
	
	/**
	 * List all pending flags for the media entry
	 *
	 * @action listFlags
	 * @param string $entryId
	 * @param KalturaFilterPager $pager
	 * @return KalturaModerationFlagListResponse
	 */
	public function listFlags($entryId, KalturaFilterPager $pager = null)
	{
		return parent::listFlagsForEntry($entryId, $pager);
	}
	
	/**
	 * Anonymously rank a media entry, no validation is done on duplicate rankings
	 *  
	 * @action anonymousRank
	 * @param string $entryId
	 * @param int $rank
	 */
	public function anonymousRankAction($entryId, $rank)
	{
		return parent::anonymousRankEntry($entryId, KalturaEntryType::MEDIA_CLIP, $rank);
	}
	
	/* (non-PHPdoc)
	 * @see KalturaEntryService::prepareEntryForInsert()
	 */
	protected function prepareEntryForInsert(KalturaBaseEntry $entry, entry $dbEntry = null)
	{
		if(!($entry instanceof KalturaMediaEntry))
			throw new KalturaAPIException(KalturaErrors::INVALID_ENTRY_TYPE,$entry->id, $entry->getType(), entryType::MEDIA_CLIP);
		$entry->validatePropertyNotNull("mediaType");
		
		$conversionQuality = $this->getConversionQuality($entry);
		if (!is_null($conversionQuality))
			$entry->conversionQuality = $conversionQuality;
		
		$dbEntry = parent::prepareEntryForInsert($entry, $dbEntry);
		$kshow = $this->createDummyKShow();
        $kshowId = $kshow->getId();
        $dbEntry->setKshowId($kshowId);
		$dbEntry->save();
		return $dbEntry;
	}
	
	private function getConversionQuality($entry) 
	{
		$conversionQuality = $entry->conversionQuality;
		if (parent::getConversionQualityFromRequest())
			$conversionQuality = parent::getConversionQualityFromRequest();	
		if(is_null($conversionQuality))
			return null;
		$conversionProfile2 = conversionProfile2Peer::retrieveByPK($conversionQuality);
		if (!$conversionProfile2) {
			KalturaLog::debug("Maybe old conversion profile");
			$conversionProfile = ConversionProfilePeer::retrieveByPK($conversionQuality);
			if ($conversionProfile)
				$conversionQuality = $conversionProfile->getConversionProfile2Id();
		}
		return $conversionQuality;
	}
	
}
