<?php 
/**
 * @package Admin
 * @subpackage Users
 */
class Form_ChangeUserRole extends Zend_Form
{
	public function init()
	{
		// Set the method for the display form to POST
		$this->setMethod('post');
		$this->setAttrib('class', 'form');
		
		$this->setDescription('user change role');
		$this->loadDefaultDecorators();
		$this->addDecorator('Description', array('placement' => 'prepend'));
		
		
		// Add a name element
		$this->addElement('text', 'name', array(
			'label'			=> 'User Name:',
			'filters'		=> array('StringTrim'),
			'readonly'		=> true,
			'ignore' 		=> true,
		));
		
		// Add an email address element
		$this->addElement('text', 'email', array(
			'label'			=> 'Email address:',
			'filters'		=> array('StringTrim'),
			'readonly'		=> true,
			'ignore' 		=> true,
		));
		
		// Add a new role element
		$this->addElement('select', 'role', array(
			'label'			=> 'Role:',
			'filters'		=> array('StringTrim'),
			'required'		=> true,
		));
		
		$element = $this->getElement('role');
		
		$client = Infra_ClientHelper::getClient();
		$filter = new Kaltura_Client_Type_UserRoleFilter();
		$filter->tagsMultiLikeAnd = 'admin_console';
		$userRoles = $client->userRole->listAction($filter);
		if ($userRoles && isset($userRoles->objects)) {
			$userRoles = $userRoles->objects;
			foreach($userRoles as $role) {
				$element->addMultiOption($role->id, $role->name);
			}
		}
	
	}
}