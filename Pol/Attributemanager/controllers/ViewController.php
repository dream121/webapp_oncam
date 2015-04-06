<?php

class Pol_Attributemanager_ViewController extends Mage_Core_Controller_Front_Action
{

	protected function _initAction($ids=null) {
		$this->loadLayout($ids);
		
		return $this;
	}
	
	public function indexAction()
	{
		
		$this->loadLayout(); 
		    
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'customer/form/myprofile.phtml')
			);
			
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);


			$this->getLayout()->getBlock('head')->setTitle($this->__('User Profile'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();
			
	}
	
	
}

?>