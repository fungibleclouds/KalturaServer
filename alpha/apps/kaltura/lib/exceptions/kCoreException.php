<?php
/**
 * @FIXME - refactor the current error codes to another exception class which will inherit from kCoreException
 * 
 * @package Core
 * @subpackage errors
 */
class kCoreException extends Exception
{
	/**
	 * Exception additional data
	 * @var string
	 */
	private $data;
	
	public function __construct($message, $code = null, $data = null)
	{
		KalturaLog::err("Code: [$code] Message: [$message]");
		$this->message = $message;
		$this->code = $code;
		$this->data = $data;
	}
	
	/**
	 * Exception additional data
	 * @return string
	 */
	public function getData()
	{
		return $this->data;
	}
	
	const INVALID_QUERY = "INVALID_QUERY";
	
	const INVALID_ENUM_FORMAT = "INVALID_ENUM_FORMAT";
	
	const ENUM_NOT_FOUND = "ENUM_NOT_FOUND";
	
	const DUPLICATE_CATEGORY = "DUPLICATE_CATEGORY";
	
	const PARENT_ID_IS_CHILD = "PARENT_ID_IS_CHILD";
	
	const MAX_NUMBER_OF_ACCESS_CONTROLS_REACHED = "MAX_NUMBER_OF_ACCESS_CONTROLS_REACHED";
	
	const MAX_CATEGORIES_PER_ENTRY = "MAX_CATEGORIES_PER_ENTRY";
	
	const INTERNAL_SERVER_ERROR = "INTERNAL_SERVER_ERROR";
	
	const OBJECT_TYPE_NOT_FOUND = "OBJECT_TYPE_NOT_FOUND";
	
	const OBJECT_API_TYPE_NOT_FOUND = "OBJECT_API_TYPE_NOT_FOUND";
	
	const SOURCE_FILE_NOT_FOUND = "SOURCE_FILE_NOT_FOUND";
	
	const FILE_NOT_FOUND = "FILE_NOT_FOUND";
	
	const ACCESS_CONTROL_CANNOT_DELETE_PARTNER_DEFAULT = "ACCESS_CONTROL_CANNOT_DELETE_PARTNER_DEFAULT";
	
	const ACCESS_CONTROL_CANNOT_DELETE_USED_PROFILE = "ACCESS_CONTROL_CANNOT_DELETE_USED_PROFILE";
	
	const INVALID_USER_ID = "INVALID_USER_ID";
	
	const INVALID_ENTRY_ID = "INVALID_ENTRY_ID";
	
	const SEARCH_TOO_GENERAL = "SEARCH_TOO_GENERAL";
	
	const ID_NOT_FOUND = 'ID_NOT_FOUND';
	
	const INVALID_KS = 'INVALID_KS';
	
	const TEMPLATE_PARTNER_COPY_LIMIT_EXCEEDED = 'TEMPLATE_PARTNER_COPY_LIMIT_EXCEEDED';
	
	const MISSING_MANDATORY_PARAMETERS = 'MISSING_MANDATORY_PARAMETERS';
	
	const PARTNER_BLOCKED = 'PARTNER_BLOCKED';
	
	const INVALID_XSLT = 'INVALID_XSLT';
}