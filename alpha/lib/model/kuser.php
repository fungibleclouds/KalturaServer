<?php

require_once( 'myContentStorage.class.php');
require_once( 'dateUtils.class.php');
require_once( 'model/ktagword.class.php');
require_once ( "myStatisticsMgr.class.php");

/**
 * Subclass for representing a row from the 'kuser' table.
 *
 * 
 *
 * @package Core
 * @subpackage model
 */ 
class kuser extends Basekuser implements IIndexable
{
	public function __construct()
	{
		$this->roleIds = null;
		$this->roleIdsChanged = false;
	}
	
	const BULK_UPLOAD_ID = "bulk_upload_id";
	
	const ANONYMOUS_PUSER_ID = "KALANONYM";
	
	const MINIMUM_ID_TO_DISPLAY = 8999;
		
	const KUSER_KALTURA = 0;
	
	const KUSER_ID_THAT_DOES_NOT_EXIST = 0;
	
	// different sort orders for browsing kswhos
	const KUSER_SORT_MOST_VIEWED = 1;  
	const KUSER_SORT_MOST_RECENT = 2;  
	const KUSER_SORT_NAME = 3;
	const KUSER_SORT_AGE = 4;
	const KUSER_SORT_COUNTRY = 5;
	const KUSER_SORT_CITY = 6;
	const KUSER_SORT_GENDER = 7;
	const KUSER_SORT_MOST_FANS = 8;
	const KUSER_SORT_MOST_ENTRIES = 9;
	const KUSER_SORT_PRODUCED_KSHOWS = 10;
	
	const PUSER_ID_REGEXP = '/^[A-Za-z0-9,!#\$%&\'\*\+\?\^_`\{\|}~.@-]{1,320}$/';
	
	private $roughcut_count = -1;
	
	private static $indexFieldTypes = null;
	
	private static $indexFieldsMap = null;
	
	public static function getColumnNames()	{	return array ( 
		"screen_name" , "full_name" , "url_list" , "tags" , 
		"about_me" , "network_highschool" , "network_college" ,"network_other") ; 
	}
	public static function getSearchableColumnName () { return "search_text" ; }

	public function save(PropelPDO $con = null)
	{
		myPartnerUtils::setPartnerIdForObj( $this );
		
		mySearchUtils::setDisplayInSearch( $this );
		
		if (!$this->getIsAdmin()) {
			$this->setIsAdmin(false);
		}
					
		return parent::save( $con );	
	}
	
	public function preSave(PropelPDO $con = null)
	{
		// verify that all role ids set are valid
		if ($this->roleIdsChanged)
		{
			// add new roles
			$idsArray = explode(',',$this->roleIds);
			foreach ($idsArray as $id)
			{				
				if (!is_null($id) && $id != '')
				{
					// check if user role item exists
					$userRole = UserRolePeer::retrieveByPK($id);
					if (!$userRole || !in_array($userRole->getPartnerId(),array($this->getPartnerId(),PartnerPeer::GLOBAL_PARTNER) ) )
					{
						throw new kPermissionException("A user role with ID [$id] does not exist", kPermissionException::USER_ROLE_NOT_FOUND);
					}
				}
			}
			
			if ($this->getIsAccountOwner())
			{
				$adminRoleId = $this->getPartner()->getAdminSessionRoleId();
				if (!(in_array($adminRoleId, $idsArray)))
				{
				 	throw new kPermissionException('Account owner must be set with a partner administrator role', kPermissionException::ACCOUNT_OWNER_NEEDS_PARTNER_ADMIN_ROLE);	
				}
			}
		}
		return parent::preSave($con);
	}
	
	
	public function postSave(PropelPDO $con = null) 
	{
		if ($this->roleIdsChanged)
		{
			// delete old roles
			$c = new Criteria();
			$c->addAnd(KuserToUserRolePeer::KUSER_ID, $this->getId(), Criteria::EQUAL);
			KuserToUserRolePeer::doDelete($c);
			
			// add new roles
			$idsArray = explode(',',$this->roleIds);
			foreach ($idsArray as $id)
			{				
				if (!is_null($id) && $id != '')
				{
					$kuserToRole = new KuserToUserRole();
					$kuserToRole->setUserRoleId($id);
					$kuserToRole->setKuserId($this->getId());
					$kuserToRole->save();
				}
			}
		}
		
		$this->roleIdsChanged = false;
		
		//update all categoryKuser object with kuser
		
		//TODO - need to check if kuser needs to add job
		if ($this->getColumnsOldValue(kuserPeer::SCREEN_NAME) != $this->getScreenName() &&
			categoryKuserPeer::isCategroyKuserExistsForKuser($this->getId()))
		{
			$featureStatusToRemoveIndex = new kFeatureStatus();
			$featureStatusToRemoveIndex->setType(IndexObjectType::CATEGORY_USER);
			
			$featureStatusesToRemove = array();
			$featureStatusesToRemove[] = $featureStatusToRemoveIndex;
			
			$filter = new categoryKuserFilter();
			$filter->setUserIdEqual($this->getPuserId());
	
			kJobsManager::addIndexJob($this->getPartnerId(), IndexObjectType::CATEGORY_USER, $filter, true, $featureStatusesToRemove);
		}
			
		
		return parent::postSave();	
	}
	

	/* (non-PHPdoc)
	 * @see lib/model/om/Basekuser#postUpdate()
	 */
	public function postUpdate(PropelPDO $con = null)
	{
		if ($this->alreadyInSave)
			return parent::postUpdate($con);
		
		$objectUpdated = $this->isModified();
		$objectDeleted = false;
		if($this->isColumnModified(kuserPeer::STATUS) && $this->getStatus() == KuserStatus::DELETED) {
			$objectDeleted = true;
		}
			
		$oldLoginDataId = null;
		if ($this->isColumnModified(kuserPeer::LOGIN_DATA_ID)) {
			$oldLoginDataId = $this->oldColumnsValues[kuserPeer::LOGIN_DATA_ID];
		}
		
		if ($this->isColumnModified(kuserPeer::EMAIL) && $this->getIsAccountOwner() && !is_null($this->oldColumnsValues[kuserPeer::EMAIL])) {
			myPartnerUtils::emailChangedEmail($this->getPartnerId(), $this->oldColumnsValues[kuserPeer::EMAIL], $this->getEmail(), $this->getPartner()->getName() , PartnerPeer::KALTURAS_PARTNER_EMAIL_CHANGE );
		}

		if ($this->getIsAccountOwner() && ( $this->isColumnModified(kuserPeer::EMAIL) || $this->isColumnModified(kuserPeer::FIRST_NAME) || $this->isColumnModified(kuserPeer::LAST_NAME) ))
		{
			$partner = $this->getPartner();
			$partner->setAccountOwnerKuserId($this->getId(), false);
			$partner->save();
		}
				
		$ret = parent::postUpdate($con);
		
		if ($objectDeleted)
		{
			kEventsManager::raiseEvent(new kObjectDeletedEvent($this));
			// if user is deleted - check if shoult also delete login data
			UserLoginDataPeer::notifyOneLessUser($this->getLoginDataId());
		}

		if($objectUpdated)
		{
		    kEventsManager::raiseEvent(new kObjectUpdatedEvent($this));
		    if (!$objectDeleted && !is_null($oldLoginDataId) && is_null($this->getLoginDataId()))
		    {
			    // if login was disabled - check if should also delete login data
			    UserLoginDataPeer::notifyOneLessUser($oldLoginDataId);
		    }
		}
			
		return $ret;
	}
		
	public function setRoughcutCount ( $count )
	{
		$this->roughcut_count = $count ;
	}
	
	// TODO - move implementation to kuserPeer - i'm not doing so now because there are changes i don't want to commit 
	public function getRoughcutCount ()
	{
		if ( $this->roughcut_count == -1  )
		{
			$c = new Criteria();
			$c->add ( entryPeer::TYPE , entryType::MIX );
			$c->add ( entryPeer::KUSER_ID , $this->getId() );
			$this->roughcut_count = entryPeer::doCount( $c );
		}
		return $this->roughcut_count;
	}

	
	static public function getKuserById ( $id )
	{
		$c = new Criteria();
		$c->add(kuserPeer::ID, $id );
		return kuserPeer::doSelectOne($c);
	}

	// TODO - PERFORMANCE DB - move to use cache !!
	// will increment the views by 1
	public function incViews ( $should_save = true  )
	{
		myStatisticsMgr::incKuserViews( $this );
/*		
		$v = $this->getViews ( );
		if ( ! is_numeric( $v ) ) $v=0;
		$this->setViews( $v + 1 );
		
		if ( $should_save) $this->save();
*/
	}

	// TODO - PERFORMANCE DB - move to use cache !!
	// will update the number of fans of kuser according the fans table
	// this should not be called ! - it is handled via myStatisticsMgr
	private  function updateFans ( $should_save = true )
	{
		// select all the people who favor me (ignore privacy)
		$c = new Criteria();
		$c->add(favoritePeer::SUBJECT_ID, $this->getId());
		$c->add(favoritePeer::SUBJECT_TYPE, favorite::SUBJECT_TYPE_USER);
//		$c->add(favoritePeer::PRIVACY, $privacy);
		$c->setDistinct();		
		$fan_count = favoritePeer::doCount( $c );
		$this->setFans( $fan_count );
		
		if ( $should_save) $this->save();
		
		return $fan_count;
	}

/*	
	// TODO - PERFORMANCE DB - move to use cache !!
	// will update the number of entries of kuser 
	// should be called every time this kuser contributes
	public function incEntries ( $should_save = true )
	{
		$v = $this->getEntries();
		if ( ! is_numeric( $v ) ) $v=0;
		$this->setEntries( $v + 1 );
		
		if ( $should_save) $this->save();
	}

	// TODO - PERFORMANCE DB - move to use cache !!
	// will update the number of produced_kshows of kuser 
	// should be called every time this kuser publishes a kshow
	public function incProducedKshows ( $should_save = true )
	{
		$v = $this->getProducedKshows();
		if ( ! is_numeric( $v ) ) $v=0;
		$this->setProducedKshows( $v + 1 );
		
		if ( $should_save) $this->save();
	}

*/

	public function getBlockEmailStr()
	{
		return myBlockedEmailUtils::createBlockEmailStr ( $this->getEmail() );	
	}

	public function getBlockEmailUrl()
	{
		return myBlockedEmailUtils::createBlockEmailUrl ( $this->getEmail() );	
	}
		
	public function getPicturePath() 
	{ 
		$picfile = $this->getPicture();
		$picfile = substr( $picfile, strpos( $picfile, '^'));		
		return myContentStorage::getGeneralEntityPath("kuser/pic", $this->getId(), $this->getId(), $picfile);
	}

	public function setPicture($filename )
	{
		if (kCurrentContext::isApiV3Context())
		{
			parent::setPicture($filename);
			return;
		}
		
		parent::setPicture( myContentStorage::generateRandomFileName( $filename, $this->getPicture() ) );
	}

	public function getPictureUrl() 
	{ 
		if ( parent::getPicture() == null ) return "";
		$path = $this->getPicturePath ( );
		$url = requestUtils::getHost() . $path ;
		return $url;
	}
	
	public function setTags($tags , $update_db = true )
	{
		if ($this->tags !== $tags) {
			$tags = ktagword::updateTags($this->tags, $tags , $update_db );
			parent::setTags($tags);
		}
	} 
	

	public function getCreatedAtAsInt ()
	{
		return $this->getCreatedAt( null );
	}

	public function getUpdateAtAsInt ()
	{
		return $this->getUpdatedAt( null );
	}
	
	public function getFormattedCreatedAt( $format = dateUtils::KALTURA_FORMAT )
	{
		return dateUtils::formatKalturaDate( $this , 'getCreatedAt' , $format );
	}

	public function getFormattedUpdatedAt( $format = dateUtils::KALTURA_FORMAT )
	{
		return dateUtils::formatKalturaDate( $this , 'getUpdatedAt' , $format );
	}
	
	public function getHomepageURL()
	{
		if ( $this->getURLlist() != null && $this->getURLlist() != "" )
		{
			$urls = explode( "|" , $this->getURLlist()  );
			if ( count ($urls) > 0 ) return $urls[0];
		}
		else return null;
	}

	public function getMyspaceURL()
	{
		if ( $this->getUrlList() != null && $this->getUrlList() != "" )
		{
			$urls = explode( "|" , $this->getURLlist()  );
			if ( count ($urls) > 1 ) return $urls[1];
		}
		else return null;
	}
	
	public function setHomepageMyspace( $homepage, $myspace )
	{
		$this->setUrlList( kString::add_http( $homepage ).'|'. kString::add_http( $myspace) );
	}
	
	public function getIMArray()
	{
		if ( $this->getImList() != null && $this->getImList() != "" )
		{
			$ims = explode( "|" , $this->getImList()  );
			if ( count( $ims ) == 6 )
			{	$ims_final = array( "AIM"=>$ims[0], "MSN"=>$ims[1], "ICQ"=>$ims[2], "Skype"=>$ims[3], "Yahoo"=>$ims[4], "Google"=>$ims[5]);
				return $ims_final; }
				else return null;
		}
		else return null;
	}
	
	public function setIMArray( $aim, $msn, $icq, $skype, $yahoo, $google)
	{
		$this->setImList( $aim.'|'.$msn.'|'.$icq.'|'.$skype.'|'.$yahoo.'|'.$google);
	}
	
	public function getMobileArray()
	{
		if ( $this->getMobileNum() != null && $this->getMobileNum() != "" )
		{
			$mobilearr = explode( "|" , $this->getMobileNum()  );
			if ( count( $mobilearr ) == 3 )
			{	$mobile_final = array( "countrycode"=>$mobilearr[0], "areacode"=>$mobilearr[1], "number"=>$mobilearr[2]);
				return $mobile_final; }
				else return null;
		}
		else return null;
	}
	
	public function setMobileArray( $country, $area, $number )
	{
		$this->setMobileNum( $country.'|'.$area.'|'.$number);
		
	}
	
	
	public function getTagsArray()
	{
		return ktagword::getTagsArray ( $this->getTags() );
	}
	
	public function getDateOfBirth($format = '%x')
	{
		$dateStr = parent::getDateOfBirth();
		if ($dateStr  === null)
			return null;
			
		return strtotime($dateStr); 
	}
	
	public function setDateOfBirth($v)
	{
		// keep only the date from the timestamp
		$year = (int)date("Y", $v);
		$day = (int)date("j", $v);
		$month = (int)date("n", $v);
		$dateOnly = mktime(0, 0, 0, $month, $day, $year);
		parent::setDateOfBirth(date("Y-m-d", $dateOnly));
	}
	
	/**
	 * caluculates the age of the kuser according to his date_of_birth
	 */
	public function getAge ()
	{
  		$now_year = date("Y");
		$dateFormat = new sfDateFormat();
		$dob = $this->getDateOfBirth();
		if ( $dob != null && $dob != "0000-00-00" )
		{
			$yob = $dateFormat->format( $dob , 'Y');
			return $now_year-$yob;
		} 
		return null;
	}

	public function setGenderByText ( $gender_text )
	{
		$gender_text = strtolower ( $gender_text );
		if ( $gender_text == "male" ) $this->setGender( 1 );
		elseif ( $gender_text == "female" ) $this->setGender( 2 );
		else $this->setGender( 0 );
	}
	
	public function getGenderText ()
	{
		$gender = $this->getGender();
		if ( $gender === 1 ) return "Male";
		if ( $gender === 2 ) return "Female";
		return "";
	}
	
	public function setCountryByLongIp ( $long_ip )
	{
		$ip = long2ip ( $this->getRegistrationIp() ) ;
		return $this->setCountyByIp ( $ip );
	}
	
	public function setCountyByIp ( $ip )
	{
		//long2ip ( $user->getRegistrationIp() ) ;
		// for first time use, geocode registration IP address
		$myGeoCoder = new myIPGeocoder();
		$country_code = $myGeoCoder->iptocountry( $ip );
		if ( $country_code == null || $country_code == "" || $country_code == "ZZ" ) $country_code  = "US"; //if we can't identify, assume the US
		if ( $this->getCountry() == "" ) $this->setCountry( $country_code );
		
		return $this->getCountry();
	}
	
	public function getLastKshowId( )
	{
		// return the last kshow_id created by this kuser
		$c = new Criteria();
		$c->add ( kshowPeer::PRODUCER_ID , $this->getId() );
		$c->addDescendingOrderByColumn( kshowPeer::ID );
		$kshow = kshowPeer::doSelectOne();

		if ( $kshow )
		{
			return $kshow->getId();
		}
		else
		{
			return 0;
		}
	}

	public function getLastKshowUrl( )
	{
		// return the last kshow_id created by this kuser
		$c = new Criteria();
		$c->add ( kshowPeer::PRODUCER_ID , $this->getId() );
		$c->addDescendingOrderByColumn( kshowPeer::ID );
		$kshow = kshowPeer::doSelectOne( $c );
				
		$host = requestUtils::getHost() . "/";
		
		if ( $kshow )
		{
			return "<a href='" . $host . "/id/" . $kshow->getId() . "'>" . $kshow->getName() . "</a>";
		}
		else
		{
			// This should never happen
			return "<a href='" . $host . "'>Kaltura</a>";
		}
	}
	
	
	public function disable ( $disable_all_content = false )
	{
		$this->setStatus ( KuserStatus::BLOCKED );
	}

	public function moderate ($new_moderation_status) 
	{
		$error_msg = "Moderation status [$new_moderation_status] not supported by user object	";
		switch($new_moderation_status) 
		{
			case moderation::MODERATION_STATUS_APPROVED:
				throw new Exception($error_msg);
				break;			
			case moderation::MODERATION_STATUS_BLOCK:
				myNotificationMgr::createNotification(kNotificationJobData::NOTIFICATION_TYPE_USER_BANNED , $this );		
				break;
			case moderation::MODERATION_STATUS_DELETE:
				throw new Exception($error_msg);
				break;
			case moderation::MODERATION_STATUS_PENDING:
				throw new Exception($error_msg);
				break;
			case moderation::MODERATION_STATUS_REVIEW:
				myNotificationMgr::createNotification(kNotificationJobData::NOTIFICATION_TYPE_USER_BANNED , $this );
//				throw new Exception($error_msg);
				break;
			default:
				throw new Exception($error_msg);
				break;
		}
		
		$this->save();
	}
	
	// TODO - fix when we enable the blocking of users
	public function getModerationStatus()
	{
//		if ( $this->getStatus() == KuserStatus::BLOCKED )
//			return moderation::MODERATION_STATUS_BLOCK;
		return moderation::MODERATION_STATUS_APPROVED;
	}

	
	public function getPuserId()
	{
		$puserId = parent::getPuserId();
		if (is_null($puserId) && !kCurrentContext::isApiV3Context())
			$puserId = PuserKuserPeer::getPuserIdFromKuserId ( $this->getPartnerId(), $this->getId() );
		
		return $puserId;
	}
	
	// this will make sure that the extra data set in the search_text won't leak out 
	public function getSearchText()	{	return '';	}
	
	public function getKuserId()
	{
		return $this->getId();
	}
	
	/**
	 * Set last_login_time parameter to $time (in custom_data)
	 * @param int $time timestamp
	 */
	public function setLastLoginTime($time)
	{
		$this->putInCustomData('last_login_time', $time);
	}
	
	/**
	 * @return last_login_time parameter from custom_data
	 */
	public function getLastLoginTime()
	{
		return $this->getFromCustomData('last_login_time');
	}
	
	/**
	 * Set allowed_partner_ids parameter to $allowedPartnerIds (in custom_data)
	 * @param string $allowed_partner_ids
	 */
	public function setAllowedPartners($allowedPartnerIds)
	{
		$this->putInCustomData('allowed_partner_ids', $allowedPartnerIds);
	}
	
	/**
	 * @return allowed_partner_ids parameter from custom_data
	 */
	public function getAllowedPartners()
	{
		return $this->getFromCustomData('allowed_partner_ids');
	}
	
	/**
	 * Set allowed_partner_packages parameter to $allowedPartnerPackages (in custom_data)
	 * @param string $allowed_partner_packages
	 */
	public function setAllowedPartnerPackages($allowedPartnerPackags)
	{
		$this->putInCustomData('allowed_partner_packages', $allowedPartnerPackags);
	}
	
	/**
	 * @return allowed_partner_packages parameter from custom_data
	 */
	public function getAllowedPartnerPackages()
	{
		return $this->getFromCustomData('allowed_partner_packages');
	}
	
	//TODO: check if needed
	public function getIsAdmin()
	{
		return parent::getisAdmin() == true;
	}
	
	
	/**
	 * @return Kuser's full name = first_name + last_name
	 */
	public function getFullName()
	{
		if (!$this->getFirstName() && parent::getFullName())
		{
			// full_name is deprecated - this is for backward compatibiliy and for migration
			KalturaLog::ALERT('Field [full_name] on object [kuser] is deprecated but still being read');
			return parent::getFullName();
		}
		return trim($this->getFirstName().' '.$this->getLastName());
	}
	
	
	private function setStatusUpdatedAt($v)
	{		
		// we treat '' as NULL for temporal objects because DateTime('') == DateTime('now')
		// -- which is unexpected, to say the least.
		if ($v === null || $v === '') {
			$dt = null;
		} elseif ($v instanceof DateTime) {
			$dt = $v;
		} else {
			// some string/numeric value passed; we normalize that so that we can
			// validate it.
			try {
				if (is_numeric($v)) { // if it's a unix timestamp
					$dt = new DateTime('@'.$v, new DateTimeZone('UTC'));
					// We have to explicitly specify and then change the time zone because of a
					// DateTime bug: http://bugs.php.net/bug.php?id=43003
					$dt->setTimeZone(new DateTimeZone(date_default_timezone_get()));
				} else {
					$dt = new DateTime($v);
				}
			} catch (Exception $x) {
				throw new PropelException('Error parsing date/time value: ' . var_export($v, true), $x);
			}
		}

		$curValue = $this->getStatusUpdatedAt();
		if ( $curValue !== null || $dt !== null ) {
			// (nested ifs are a little easier to read in this case)

			$currNorm = ($curValue !== null && $tmpDt = new DateTime($curValue)) ? $tmpDt->format('Y-m-d H:i:s') : null;
			$newNorm = ($dt !== null) ? $dt->format('Y-m-d H:i:s') : null;

			if ( ($currNorm !== $newNorm) // normalized values don't match 
					)
			{
				$newValue = ($dt ? $dt->format('Y-m-d H:i:s') : null);
				$this->putInCustomData('status_updated_at', $newValue, null);
			}
		} // if either are not null

		return $this;
	}
	
	public function getStatusUpdatedAt($format = 'Y-m-d H:i:s')
	{
		$value = $this->getFromCustomData('status_updated_at', null, null);
		
		if ($value === null) {
			return null;
		}

		if ($value === '0000-00-00 00:00:00') {
			// while technically this is not a default value of NULL,
			// this seems to be closest in meaning.
			return null;
		} else {
			try {
				$dt = new DateTime($value);
			} catch (Exception $x) {
				throw new PropelException("Internally stored date/time/timestamp value could not be converted to DateTime: " . var_export($value, true), $x);
			}
		}

		if ($format === null) {
			// We cast here to maintain BC in API; obviously we will lose data if we're dealing with pre-/post-epoch dates.
			return (int) $dt->format('U');
		} elseif (strpos($format, '%') !== false) {
			return strftime($format, $dt->format('U'));
		} else {
			return $dt->format($format);
		}
	}
	 
	
	private function setDeletedAt($v)
	{		
		// we treat '' as NULL for temporal objects because DateTime('') == DateTime('now')
		// -- which is unexpected, to say the least.
		if ($v === null || $v === '') {
			$dt = null;
		} elseif ($v instanceof DateTime) {
			$dt = $v;
		} else {
			// some string/numeric value passed; we normalize that so that we can
			// validate it.
			try {
				if (is_numeric($v)) { // if it's a unix timestamp
					$dt = new DateTime('@'.$v, new DateTimeZone('UTC'));
					// We have to explicitly specify and then change the time zone because of a
					// DateTime bug: http://bugs.php.net/bug.php?id=43003
					$dt->setTimeZone(new DateTimeZone(date_default_timezone_get()));
				} else {
					$dt = new DateTime($v);
				}
			} catch (Exception $x) {
				throw new PropelException('Error parsing date/time value: ' . var_export($v, true), $x);
			}
		}

		$curValue = $this->getDeletedAt();
		if ( $curValue !== null || $dt !== null ) {
			// (nested ifs are a little easier to read in this case)

			$currNorm = ($curValue !== null && $tmpDt = new DateTime($curValue)) ? $tmpDt->format('Y-m-d H:i:s') : null;
			$newNorm = ($dt !== null) ? $dt->format('Y-m-d H:i:s') : null;

			if ( ($currNorm !== $newNorm) // normalized values don't match 
					)
			{
				$newValue = ($dt ? $dt->format('Y-m-d H:i:s') : null);
				$this->putInCustomData('deleted_at', $newValue, null);
			}
		} // if either are not null

		return $this;
	}
	
	public function getDeletedAt($format = 'Y-m-d H:i:s')
	{
		$value = $this->getFromCustomData('deleted_at', null, null);
		
		if ($value === null) {
			return null;
		}

		if ($value === '0000-00-00 00:00:00') {
			// while technically this is not a default value of NULL,
			// this seems to be closest in meaning.
			return null;
		} else {
			try {
				$dt = new DateTime($value);
			} catch (Exception $x) {
				throw new PropelException("Internally stored date/time/timestamp value could not be converted to DateTime: " . var_export($value, true), $x);
			}
		}

		if ($format === null) {
			// We cast here to maintain BC in API; obviously we will lose data if we're dealing with pre-/post-epoch dates.
			return (int) $dt->format('U');
		} elseif (strpos($format, '%') !== false) {
			return strftime($format, $dt->format('U'));
		} else {
			return $dt->format($format);
		}
	}
	
	/**
	 * Set status and statusUpdatedAt fields
	 * @see Basekuser::setStatus()
	 * @throws kUserException::CANNOT_DELETE_OR_BLOCK_ROOT_ADMIN_USER
	 */
	public function setStatus($status)
	{
		if (($status == KuserStatus::DELETED || $status == KuserStatus::BLOCKED) && $this->getIsAccountOwner()) {
			throw new kUserException('', kUserException::CANNOT_DELETE_OR_BLOCK_ROOT_ADMIN_USER);
		}
				
		parent::setStatus($status);
		$this->setStatusUpdatedAt(time());
		if ($status == KuserStatus::DELETED) {
			$this->setDeletedAt(time());
		}
	}
	
	/**
	 * Return user's login data object if valid
	 */
	public function getLoginData()
	{
		$loginDataId = $this->getLoginDataId();
		if (!$loginDataId) {
			return null;
		}
		return UserLoginDataPeer::retrieveByPK($loginDataId);
	}
	
	public function getAllowedPartnerIds(partnerFilter $partnerFilter = null)
	{
		$currentLoginDataId = $this->getLoginDataId();
		if (!$currentLoginDataId) {
			return array($this->getPartnerId());
		}
		$c = new Criteria();
		$c->addSelectColumn(kuserPeer::PARTNER_ID);
		$c->addAnd(kuserPeer::LOGIN_DATA_ID, $currentLoginDataId, Criteria::EQUAL);
		$c->addAnd(kuserPeer::STATUS, KuserStatus::ACTIVE, Criteria::EQUAL);
		kuserPeer::setUseCriteriaFilter(false);
		$stmt = kuserPeer::doSelectStmt($c);
		$ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
		kuserPeer::setUseCriteriaFilter(true);
		
		// apply filter on partner ids
		if ($partnerFilter)
		{
    		$c = new Criteria();
    		$c->addSelectColumn(PartnerPeer::ID);
    		$partnerFilter->setIdIn($ids);
    		$partnerFilter->attachToCriteria($c);
    		$stmt = PartnerPeer::doSelectStmt($c);
    		$ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
		}
		
		return $ids;
	}
	

	
	// -- start of deprecated functions
	
	public function setSalt($v)
	{
		// salt column is deprecated
		KalturaLog::ALERT('Field [salt] on object [kuser] is deprecated');
		throw new Exception('Field [salt] on object [kuser] is deprecated');
	}
	
	public function setSha1Password($v)
	{
		// sha1_password column is deprecated
		KalturaLog::ALERT('Field [sha1_password] on object [kuser] is deprecated - trace: ');
		throw new Exception('Field [sha1_password] on object [kuser] is deprecated');
	}
	
	public function getSalt()
	{
		// salt column is deprecated
		KalturaLog::ALERT('Field [salt] on object [kuser] is deprecated - getSalt should be removed from schema after migration');
		return parent::getSalt();
	}
	
	public function getSha1Password()
	{
		// sha1_password column is deprecated
		KalturaLog::ALERT('Field [sha1_password] on object [kuser] is deprecated - getSha1Password should be removed from schema after migration');
		return parent::getSha1Password();
	}
	
	public function setFullName($v)
	{
		// full_name column is deprecated
		KalturaLog::ALERT('Field [full_name] on object [kuser] is deprecated');
		list($firstName, $lastName) = kString::nameSplit($v);
		$this->setFirstName($firstName);
		$this->setLastName($lastName);
	}
	
	// -- end of deprecated functions
	
	
	/**
	 * Disable user login
	 * @throws kUserException::USER_LOGIN_ALREADY_DISABLED
	 * @throws kUserException::CANNOT_DISABLE_LOGIN_FOR_ADMIN_USER
	 */
	public function disableLogin()
	{
		if ($this->getIsAdmin())
		{
			throw new kUserException('', kUserException::CANNOT_DISABLE_LOGIN_FOR_ADMIN_USER);
		}
		
		if (!$this->getLoginDataId())
		{
			throw new kUserException('', kUserException::USER_LOGIN_ALREADY_DISABLED);
		}
		
		$loginDataId = $this->getLoginDataId();
		$this->setLoginDataId(null);
		$this->save();
		
		return true;	
	}
	
	/**
	 * Enable user login 
	 * @param string $loginId
	 * @param string $password
	 * @param bool $checkPasswordStructure
	 * @throws kUserException::USER_LOGIN_ALREADY_ENABLED
	 * @throws kUserException::INVALID_EMAIL
	 * @throws kUserException::INVALID_PARTNER
	 * @throws kUserException::ADMIN_LOGIN_USERS_QUOTA_EXCEEDED
	 * @throws kUserException::PASSWORD_STRUCTURE_INVALID
	 * @throws kUserException::LOGIN_ID_ALREADY_USED
	 */
	public function enableLogin($loginId, $password = null, $checkPasswordStructure = true, $sendEmail = null)
	{
		if (!$password)
		{
			$password = UserLoginDataPeer::generateNewPassword();
			if (is_null($sendEmail)) {
				$sendEmail = true;
			}
		}
				
		if ($this->getLoginDataId())
		{
			throw new kUserException('', kUserException::USER_LOGIN_ALREADY_ENABLED);
		}
		
		$loginDataExisted = null;
		$loginData = UserLoginDataPeer::addLoginData($loginId, $password, $this->getPartnerId(), $this->getFirstName(), $this->getLastName(), $this->getIsAdmin(), $checkPasswordStructure, $loginDataExisted);	
		if (!$loginData)
		{
			throw new kUserException('', kUserException::LOGIN_DATA_NOT_FOUND);
		}
		
		$this->setLoginDataId($loginData->getId());
				
		if ($sendEmail)
		{
			if ($loginDataExisted) {
				kuserPeer::sendNewUserMail($this, true);
			}
			else {
				kuserPeer::sendNewUserMail($this, false);
			}
			kuserPeer::sendNewUserMailToAdmins($this);			
		}	
		return $this;
	}
	
	
	public function getIsAccountOwner()
	{
		if ($this->isNew()) {
			return false;
		}
		try {
			$partner = $this->getPartner();
		}
		catch (Exception $e) {
			return false;
		}
		if (!$partner) {
			return false;
		}
		else {
			return $this->getId() == $partner->getAccountOwnerKuserId();
		}
	}
	
	/**
	 * @return Partner
	 */
	public function getPartner()
	{
		$partner = PartnerPeer::retrieveByPK($this->getPartnerId());
		return $partner;
	}
	
	
	// ----------------------------------------
	// -- start of user role handling functions
	// ----------------------------------------
		
	private $roleIds = null;
	private $roleIdsChanged = false;
				
	/**
	 * @return string Comma seperated string of role names associated to the current user
	 */
	public function getUserRoleNames()
	{		
		$c = new Criteria();
		$c->addSelectColumn(UserRolePeer::NAME);
		$c->add(UserRolePeer::ID, explode(',',$this->getRoleIds()), Criteria::IN);
		$stmt = UserRolePeer::doSelectStmt($c);
		$names = $stmt->fetchAll(PDO::FETCH_COLUMN);
		$names = implode(',', $names);
		return $names;
	}
	
	/**
	 * @return string Comma seperated string of role ids associated to the current user
	 */
	public function getRoleIds()
	{
		if (is_null($this->roleIds))
		{
			$this->roleIds = '';
			$c = new Criteria();
			$c->addAnd(KuserToUserRolePeer::KUSER_ID, $this->getId(), Criteria::EQUAL);
			$selectResults = KuserToUserRolePeer::doSelect($c);
			foreach ($selectResults as $selectResult)
			{
				if ($this->roleIds != '')
				{
					$this->roleIds .= ',';
				}
				$this->roleIds .= $selectResult->getUserRoleId();
			}  
		}
		return $this->roleIds;
	}

	/**
	 * Set the roles of the current kuser
	 * @param string $idsString A comma seperated string of user role IDs
	 */
	public function setRoleIds($idsString)
	{		
		$this->roleIds = $idsString;
		$this->roleIdsChanged = true;
	}

		
	/**
	 * Checks if the current user has one of the permissions with the given names
	 * @param array $permissionNamesArray Permission names
	 * @return true or false
	 */
	public function hasPermissionOr(array $permissionNamesArray)
	{
		$roleIds = explode(',', $this->getRoleIds());
		foreach ($roleIds as $roleId)
		{
			$userRole = UserRolePeer::retrieveByPK($roleId);
			if ($userRole) {
				$permissions = explode(',', $userRole->getPermissionNames());
				foreach ($permissionNamesArray as $permissionName) {
					if (in_array($permissionName, $permissions)) {
						return true;
					}
				}
			}
		}
		return false;
	}
	

	public function getCacheInvalidationKeys()
	{
		return array("kuser:id=".$this->getId(), "kuser:partnerId=".$this->getPartnerId().",puserid=".$this->getPuserId());
	}
	
	/* (non-PHPdoc)
     * @see IIndexable::getIntId()
     */
    public function getIntId ()
    {
        return $this->id;
        
    }

	/* (non-PHPdoc)
     * @see IIndexable::getEntryId()
     */
    public function getEntryId ()
    {
        // TODO Auto-generated method stub
        
    }

	/* (non-PHPdoc)
     * @see IIndexable::getObjectIndexName()
     */
    public function getObjectIndexName ()
    {
       return kuserPeer::getOMClass(false);
    }

	/* (non-PHPdoc)
     * @see IIndexable::getIndexFieldsMap()
     */
    public function getIndexFieldsMap ()
    {
    	if (!self::$indexFieldsMap)
    	{
    		self::$indexFieldsMap = array ( 
        		"login_data_id" => "loginDataId",
                "is_admin" => "isAdmin",
                "screen_name" => "screenName",
                "full_name" => "fullName",
                "first_name" => "firstName",
                "last_name" => "lastName",
                "email" => "email",
                "sha1_password" => "sha1Password",
                "salt" => "salt",
                "date_of_birth" => "dateOfBirth",
                "country" => "country",
                "state" => "state",
                "city" => "city",
                "zip" => "zip",
                "url_list" => "urlList",
                "picture" => "picture",
                "icon" => "icon",
                "about_me" => "aboutMe",
                "tags" => "tags",
                "tagline" => "tagline",
                "network_highschool" => "networkHighschool",
                "network_college" => "networkCollege",
                "network_other" => "networkOther",
                "mobile_num" => "mobileNum",
                "mature_content" => "matureContent",
                "gender" => "gender",
                "registration_ip" => "registrationIp",
                "registration_cookie" => "registrationCookie",
                "im_list" => "imList",
                "views" => "views",
                "fans" => "fans",
                "entries" => "entries",
                "storage_size" => "storageSize",
                "produced_kshows" => "producedKshows",
                "kuser_status" => "status",
                "created_at" => "createdAt",
                "updated_at" => "updatedAt",
                "partner_id" => "partnerId",
                "display_in_search" => "displayInSearch",
                "partner_data" => "partnerData",
                "puser_id" => "puserId",
                "admin_tags" => "adminTags",
                "indexed_partner_data_int" => "indexedPartnerDataInt",
                "indexed_partner_data_string" => "indexedPartnerDataString",
                "custom_data" => "customData",
       		 );
    	}
        
    	return self::$indexFieldsMap; 
    }

	/* (non-PHPdoc)
     * @see IIndexable::getIndexFieldType()
     */
    public function getIndexFieldType ($field)
    {
    	if (!self::$indexFieldTypes)
    	{
    		self::$indexFieldTypes = array (
        		"login_data_id" => IIndexable::FIELD_TYPE_INTEGER,
                "is_admin" => IIndexable::FIELD_TYPE_INTEGER,
                "screen_name" => IIndexable::FIELD_TYPE_STRING,
                "full_name" => IIndexable::FIELD_TYPE_STRING,
                "first_name" => IIndexable::FIELD_TYPE_STRING,
                "last_name" => IIndexable::FIELD_TYPE_STRING,
                "email" => IIndexable::FIELD_TYPE_STRING,
                "sha1_password" => IIndexable::FIELD_TYPE_STRING,
                "salt" => IIndexable::FIELD_TYPE_STRING,
                "date_of_birth" => IIndexable::FIELD_TYPE_DATETIME,
                "country" => IIndexable::FIELD_TYPE_STRING,
                "state" => IIndexable::FIELD_TYPE_STRING,
                "city" => IIndexable::FIELD_TYPE_STRING,
                "zip" => IIndexable::FIELD_TYPE_STRING,
                "url_list" => IIndexable::FIELD_TYPE_STRING,
                "picture" => IIndexable::FIELD_TYPE_STRING,
                "icon" => IIndexable::FIELD_TYPE_STRING,
                "about_me" => IIndexable::FIELD_TYPE_STRING,
                "tags" => IIndexable::FIELD_TYPE_STRING,
                "tagline" => IIndexable::FIELD_TYPE_STRING,
                "network_highschool" => IIndexable::FIELD_TYPE_STRING,
                "network_college" => IIndexable::FIELD_TYPE_STRING,
                "network_other" => IIndexable::FIELD_TYPE_STRING,
                "mobile_num" => IIndexable::FIELD_TYPE_STRING,
                "mature_content" => IIndexable::FIELD_TYPE_INTEGER,
                "gender" => IIndexable::FIELD_TYPE_INTEGER,
                "registration_ip" => IIndexable::FIELD_TYPE_STRING,
                "registration_cookie" => IIndexable::FIELD_TYPE_STRING,
                "im_list" => IIndexable::FIELD_TYPE_STRING,
                "views" => IIndexable::FIELD_TYPE_INTEGER,
                "fans" => IIndexable::FIELD_TYPE_INTEGER,
                "entries" => IIndexable::FIELD_TYPE_INTEGER,
                "storage_size" => IIndexable::FIELD_TYPE_INTEGER,
                "produced_kshows" => IIndexable::FIELD_TYPE_INTEGER,
                "kuser_status" => IIndexable::FIELD_TYPE_INTEGER,
                "created_at" => IIndexable::FIELD_TYPE_DATETIME,
                "updated_at" => IIndexable::FIELD_TYPE_DATETIME,
                "partner_id" => IIndexable::FIELD_TYPE_INTEGER,
                "display_in_search" => IIndexable::FIELD_TYPE_INTEGER,
                "partner_data" => IIndexable::FIELD_TYPE_STRING,
                "puser_id" => IIndexable::FIELD_TYPE_STRING,
                "admin_tags" => IIndexable::FIELD_TYPE_STRING,
                "indexed_partner_data_int" => IIndexable::FIELD_TYPE_INTEGER,
                "indexed_partner_data_string" => IIndexable::FIELD_TYPE_STRING,
       		 );
    	}
        if(isset(self::$indexFieldTypes[$field]))
			return self::$indexFieldTypes[$field];
			
		return null;
    }
	
	/* (non-PHPdoc)
	 * @see IIndexable::indexToSearchIndex()
	 */
	public function indexToSearchIndex()
	{
		kEventsManager::raiseEventDeferred(new kObjectReadyForIndexEvent($this));
	}
    
	/* (non-PHPdoc)
	 * @see lib/model/om/Baseentry#postInsert()
	 */
	public function postInsert(PropelPDO $con = null)
	{
		parent::postInsert($con);
	
		if (!$this->alreadyInSave)
			kEventsManager::raiseEvent(new kObjectAddedEvent($this));
	}
	
	// --------------------------------------
	// -- end of user role handling functions
	// --------------------------------------
	
	//Custom data functions
	
    public function setBulkUploadId ($bulkUploadId){$this->putInCustomData (self::BULK_UPLOAD_ID, $bulkUploadId);}
	public function getBulkUploadId (){return $this->getFromCustomData(self::BULK_UPLOAD_ID);}

	/**
	 * Force modifiedColumns to be affected even if the value not changed
	 * 
	 * @see Basekuser::setUpdatedAt()
	 */
	public function setUpdatedAt($v)
	{
		parent::setUpdatedAt($v);
		if(!in_array(kuserPeer::UPDATED_AT, $this->modifiedColumns, false))
			$this->modifiedColumns[] = kuserPeer::UPDATED_AT;
			
		return $this;
	}
	
	public function getSearchIndexFieldsEscapeType($fieldName)
	{
		return SearchIndexFieldEscapeType::DEFAULT_ESCAPE;
	}
}
