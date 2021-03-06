<?php
/**
 * @package plugins.emailNotification
 * @subpackage model.data
 */
class kEmailNotificationDispatchJobData extends kEventNotificationDispatchJobData
{
	/**
	 * Define the email sender email
	 * 
	 * @var string
	 */
	private $fromEmail;
	
	/**
	 * Define the email sender name
	 * 
	 * @var string
	 */
	private $fromName;
	
	/**
	 * Email recipient emails and names, key is mail address and value is the name
	 * 
	 * @var array<email,name>
	 */
	private $to;
	
	/**
	 * Email cc emails and names, key is mail address and value is the name
	 * 
	 * @var array<email,name>
	 */
	private $cc;
	
	/**
	 * Email bcc emails and names, key is mail address and value is the name
	 * 
	 * @var array<email,name>
	 */
	private $bcc;
	
	/**
	 * Email addresses that a reading confirmation will be sent to
	 * 
	 * @var array<email,name>
	 */
	private $replyTo;
	
	/**
	 * Define the email priority of enum EmailNotificationTemplatePriority
	 * 
	 * @var int
	 */
	private $priority;
	
	/**
	 * Email address that a reading confirmation will be sent
	 * 
	 * @var string
	 */
	private $confirmReadingTo;
	
	/**
	 * Hostname to use in Message-Id and Received headers and as default HELO string. 
	 * If empty, the value returned by SERVER_NAME is used or 'localhost.localdomain'.
	 * 
	 * @var string
	 */
	private $hostname;
	
	/**
	 * Sets the message ID to be used in the Message-Id header.
	 * If empty, a unique id will be generated.
	 * 
	 * @var string
	 */
	private $messageID;
	
	/**
	 * Adds a e-mail custom header
	 * 
	 * @var array<key,value>
	 */
	private $customHeaders;
	
	/**
	 * Define the content dynamic parameters
	 * 
	 * @var array<key,value>
	 */
	private $contentParameters;
	
	/**
	 * @return the $fromEmail
	 */
	public function getFromEmail() 
	{
		return $this->fromEmail;
	}

	/**
	 * @return the $fromName
	 */
	public function getFromName()  
	{
		return $this->fromName;
	}

	/**
	 * @param string $fromEmail
	 */
	public function setFromEmail($fromEmail)  
	{
		$this->fromEmail = $fromEmail;
	}

	/**
	 * @param string $fromName
	 */
	public function setFromName($fromName)  
	{
		$this->fromName = $fromName;
	}

	/**
	 * @return int $priority of enum EmailNotificationTemplatePriority
	 */
	public function getPriority()
	{
		return $this->priority;
	}

	/**
	 * @return array<key,value> $contentParameters
	 */
	public function getContentParameters()
	{
		return $this->contentParameters;
	}

	/**
	 * @param int $priority of enum EmailNotificationTemplatePriority
	 */
	public function setPriority($priority)
	{
		$this->priority = $priority;
	}

	/**
	 * @param array<key,value> $contentParameters
	 */
	public function setContentParameters(array $contentParameters)
	{
		$this->contentParameters = $contentParameters;
	}
	
	/**
	 * @return array $to
	 */
	public function getTo()
	{
		return $this->to;
	}

	/**
	 * @return array $cc
	 */
	public function getCc()
	{
		return $this->cc;
	}

	/**
	 * @return array $bcc
	 */
	public function getBcc()
	{
		return $this->bcc;
	}

	/**
	 * @param array<email,name> $to
	 */
	public function setTo(array $to)
	{
		$this->to = $to;
	}

	/**
	 * @param array<email,name> $cc
	 */
	public function setCc(array $cc)
	{
		$this->cc = $cc;
	}

	/**
	 * @param array<email,name> $bcc
	 */
	public function setBcc(array $bcc)
	{
		$this->bcc = $bcc;
	}
	
	/**
	 * @return string $confirmReadingTo
	 */
	public function getConfirmReadingTo()
	{
		return $this->confirmReadingTo;
	}

	/**
	 * @return array<key,value> $replyTo
	 */
	public function getReplyTo()
	{
		return $this->replyTo;
	}

	/**
	 * @return string $hostname
	 */
	public function getHostname()
	{
		return $this->hostname;
	}

	/**
	 * @return string $messageID
	 */
	public function getMessageID()
	{
		return $this->messageID;
	}

	/**
	 * @return array<key,value> $customHeaders
	 */
	public function getCustomHeaders()
	{
		return $this->customHeaders;
	}

	/**
	 * @param string $confirmReadingTo
	 */
	public function setConfirmReadingTo($confirmReadingTo)
	{
		$this->confirmReadingTo = $confirmReadingTo;
	}

	/**
	 * @param array<key,value> $replyTo
	 */
	public function setReplyTo(array $replyTo)
	{
		$this->replyTo = $replyTo;
	}

	/**
	 * @param string $hostname
	 */
	public function setHostname($hostname)
	{
		$this->hostname = $hostname;
	}

	/**
	 * @param string $messageID
	 */
	public function setMessageID($messageID)
	{
		$this->messageID = $messageID;
	}

	/**
	 * @param array<key,value> $customHeaders
	 */
	public function setCustomHeaders(array $customHeaders)
	{
		$this->customHeaders = $customHeaders;
	}
}