<?php
class kAkamaiProvisionJobData extends kProvisionJobData
{
	/**
	 * @var string
	 */
	private $wsdlUsername;
	
	/**
	 * @var string
	 */
	private $wsdlPassword;
	
	/**
	 * @var string
	 */
	private $cpcode;
 	
	/**
	 * @var string
	 */
	private $emailId;
 	
	/**
	 * @var string
	 */
	private $primaryContact;
 	
	/**
	 * @var string
	 */
	private $secondaryContact;
	
	/**
	 * @return the $wsdlUsername
	 */
	public function getWsdlUsername()
	{
		return $this->wsdlUsername;
	}
	
	/**
	 * @return the $wsdlPassword
	 */
	public function getWsdlPassword()
	{
		return $this->wsdlPassword;
	}
	
	/**
	 * @return the $cpcode
	 */
	public function getCpcode()
	{
		return $this->cpcode;
	}
	
	/**
	 * @return the $emailId
	 */
	public function getEmailId()
	{
		return $this->emailId;
	}
	
	/**
	 * @return the $primaryContact
	 */
	public function getPrimaryContact()
	{
		return $this->primaryContact;
	}
	
	/**
	 * @return the $secondaryContact
	 */
	public function getSecondaryContact()
	{
		return $this->secondaryContact;
	}
	
	/**
	 * @param $wsdlUsername the $wsdlUsername to set
	 */
	public function setWsdlUsername($wsdlUsername)
	{
		$this->wsdlUsername = $wsdlUsername;
	}

	/**
	 * @param $wsdlPassword the $wsdlPassword to set
	 */
	public function setWsdlPassword($wsdlPassword)
	{
		$this->wsdlPassword = $wsdlPassword;
	}
	
	/**
	 * @param $cpcode the $cpcode to set
	 */
	public function setCpcode($cpcode)
	{
		$this->cpcode = $cpcode;
	}
	
	/**
	 * @param $emailId the $emailId to set
	 */
	public function setEmailId($emailId)
	{
		$this->emailId = $emailId;
	}
	
	/**
	 * @param $primaryContact the $primaryContact to set
	 */
	public function setPrimaryContact($primaryContact)
	{
		$this->primaryContact = $primaryContact;
	}
	
	/**
	 * @param $secondaryContact the $secondaryContact to set
	 */
	public function setSecondaryContact($secondaryContact)
	{
		$this->secondaryContact = $secondaryContact;
	}
	
}