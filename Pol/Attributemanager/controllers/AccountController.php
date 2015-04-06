<?php

require_once 'Mage/Customer/controllers/AccountController.php';

class Pol_Attributemanager_AccountController extends Mage_Customer_AccountController
{

    public function preDispatch()
    {
        parent::preDispatch();
       /*  if (!Mage::getSingleton('customer/session')->authenticate($this)) {
            $this->setFlag('', 'no-dispatch', true);
        } */
    }
	
	public function indexAction()
    {	
        $this->loadLayout();
		//echo $userAuthToken = Mage::getModel("core/session")->getEncryptedSessionId();
		Mage::getSingleton('customer/session')->setCurrentPage('');
        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('catalog/session');

        $this->getLayout()->getBlock('content')->append(
            $this->getLayout()->createBlock('social/account_dashboard')
        );
        $this->getLayout()->getBlock('head')->setTitle($this->__('My Account'));
        $this->renderLayout();
    }
	
   /**
     * Login post action
     */
    public function loginPostAction()
    {   
		$session = $this->_getSession();		
		
        	if($session->getLoginAttempt())
			$la = $session->getLoginAttempt();
		else
			$la = 0;
				
		$session->setLoginAttempt($la+1);
		
	    	if(isset($_POST["recaptcha_response_field"]) && $session->getLoginAttempt()>2){
			$privatekey = Mage::getStoreConfig("fontis_recaptcha/setup/private_key");
			
			$resp = Mage::helper("fontis_recaptcha")->recaptcha_check_answer(  $privatekey,$_SERVER["REMOTE_ADDR"],$_POST["recaptcha_challenge_field"],$_POST["recaptcha_response_field"]);
			if ($resp == true)
			{
				//
			}
			else
			{ // if recaptcha response is incorrect, reload the page

				Mage::getSingleton('core/session')->addError(Mage::helper('contacts')->__('Your reCAPTCHA entry is incorrect. Please try again.'));

				Mage::getSingleton('core/session')->setFormData($data);

				$this->_redirect('*/*/');
				return;
			}
		}
		
		if ($this->_getSession()->isLoggedIn()) {
            		$this->_redirect('*/*/');
            		return;
        	}
    
		$resource = Mage::getSingleton('core/resource');
		$write = $resource->getConnection('core_write');
		$userlogin_log = $resource->getTableName('userlogin_log');		
		
        if ($this->getRequest()->isPost()) {
            $login = $this->getRequest()->getPost('login');
            if (!empty($login['username']) && !empty($login['password'])) {

				if($this->getRequest()->getPost('remember_me')){ 
					Mage::getModel('core/cookie')->set('Oncamusername', $login['username']);
					Mage::getModel('core/cookie')->set('Oncampassword', $login['password']);
				}
				
                try {
                    $session->login($login['username'], $login['password']);
					if($session->getCustomer()->getIsActive() == 0){
						$this->_getSession()->logout()->setBeforeAuthUrl(Mage::getUrl());
						//$this->_redirect('social/account/inactive'); 
					}
                    if ($session->getCustomer()->getIsJustConfirmed()) {
			   $session->setLoginAttempt(0);
                        $this->_welcomeCustomer($session->getCustomer(), true);
                    }	
					if($this->getRequest()->getPost('remember_me')){ 
						//create cookies with user information, and salted password
						$user = $this->_getSession()->getCustomer()->getName();
						//At the moment Created At timestamp could be a good idea to salt the password
						$salt = $this->_getSession()->getCustomer()->getCreatedAtTimestamp();
						$pass = $this->_getSession()->getCustomer()->getPasswordHash();
						$safe_pass = sha1(md5($pass).md5($salt));
						//Set the cookie with prepared data
						setcookie('info',$safe_pass,time()+60*60*24*30,'/');
						setcookie('userEmail',$login['username'],time()+60*60*24*30,'/');
						setcookie('salt',$salt,time()+60*60*24*30,'/');
						setcookie('pass',$pass,time()+60*60*24*30,'/');
					}else{
						if (isset($_COOKIE['info']))
							setcookie('info','',time()-60*60*24*30,'/');
							setcookie('userEmail','',time()-60*60*24*30,'/');
					}	
			$write->query("insert into $userlogin_log (email, ip_addr, browser, status, created_time) values('".$login['username']."', '".$_SERVER['REMOTE_ADDR']."', '".$_SERVER['HTTP_USER_AGENT']."', 'success', now())");
                } catch (Mage_Core_Exception $e) {
                    switch ($e->getCode()) {
                        case Mage_Customer_Model_Customer::EXCEPTION_EMAIL_NOT_CONFIRMED:
                            $message = Mage::helper('customer')->__('This account is not confirmed. <a href="%s">Click here</a> to resend confirmation email.', Mage::helper('customer')->getEmailConfirmationUrl($login['username']));
                            break;
                        case Mage_Customer_Model_Customer::EXCEPTION_INVALID_EMAIL_OR_PASSWORD:
                            $message = $e->getMessage();
                            break;
                        default:
                            $message = $e->getMessage();
                    }
                    $session->addError($message);
                    $session->setUsername($login['username']);
					$write->query("insert into $userlogin_log (email, ip_addr, browser, status, created_time) values('".$login['username']."', '".$_SERVER['REMOTE_ADDR']."', '".$_SERVER['HTTP_USER_AGENT']."', 'fail', now())");
                } catch (Exception $e) {
					$write->query("insert into $userlogin_log (email, ip_addr, browser, status, created_time) values('".$login['username']."', '".$_SERVER['REMOTE_ADDR']."', '".$_SERVER['HTTP_USER_AGENT']."', 'fail', now())");
                }
				
            } else {
				$write->query("insert into $userlogin_log (email, ip_addr, browser, status, created_time) values('".$login['username']."', '".$_SERVER['REMOTE_ADDR']."', '".$_SERVER['HTTP_USER_AGENT']."', 'fail', now())");
                $session->addError($this->__('Login and password are required.'));
            }
        }
	
        $this->_loginPostRedirect($login['current_url']);
    	}
	public function logoutAction()
	{
		//Remove the cookie if someone clicked logout
		if (isset($_COOKIE['info']))
			setcookie('info','',time()-60*60*24*30,'/');

		//Do whatever original method does
		parent::logoutAction();
	}
	//edit core login redirect code
	protected function _loginPostRedirect($current_url)
    {
        $session = $this->_getSession();
		if($session->isLoggedIn()){
			$user_id = Mage::getSingleton( 'customer/session' )->getCustomerId();
			$customer = Mage::getModel('customer/customer')->load($user_id);
			$customer->setAccGenBy('oauth_oncam');
			$customer->save();
			$jabberAuth = Mage::getModel('profile/profile')->jabberAuth();
		}
        if ($session) {
            // Set default URL to redirect customer to
			//echo 'test'; exit;
            $session->setBeforeAuthUrl($current_url);
            // Redirect customer to the last page visited after logging in
            if ($session->isLoggedIn()) {
                if (!Mage::getStoreConfigFlag(
                    Mage_Customer_Helper_Data::XML_PATH_CUSTOMER_STARTUP_REDIRECT_TO_DASHBOARD
                )) { //echo "test"; exit;
                    $referer = $this->getRequest()->getParam(Mage_Customer_Helper_Data::REFERER_QUERY_PARAM_NAME);
                    if ($referer) {
                        // Rebuild referer URL to handle the case when SID was changed
                        $referer = Mage::getModel('core/url')
                            ->getRebuiltUrl(Mage::helper('core')->urlDecode($referer));
                        if ($this->_isUrlInternal($referer)) {
                            $session->setBeforeAuthUrl($referer);
                        }
                    }
                } else if ($session->getAfterAuthUrl()) {
                    $session->setBeforeAuthUrl($session->getAfterAuthUrl(true));
                }
            } else {
                $session->setBeforeAuthUrl(Mage::helper('customer')->getLoginUrl());
            }
        } else if ($session->getBeforeAuthUrl() == Mage::helper('customer')->getLogoutUrl()) {
            $session->setBeforeAuthUrl(Mage::helper('customer')->getDashboardUrl());
        } else {
            if (!$session->getAfterAuthUrl()) {
                $session->setAfterAuthUrl($session->getBeforeAuthUrl());
            }
            if ($session->isLoggedIn()) {
                $session->setBeforeAuthUrl($session->getAfterAuthUrl(true));
            }
        }
		if($session->isLoggedIn()){
			
			//$userAuthToken = ereg_replace('[^A-Za-z0-9.]', '-', date('m-d-y H:i:s'));
			$userAuthToken = Mage::getModel("core/session")->getEncryptedSessionId();//die;
			$userAuthToken = $this->encode($userAuthToken);
			$session->setUserAuthToken($userAuthToken);
			$customer = Mage::getModel('customer/customer')->load($session->getCustomerId());
			$customer->setUserAuthToken($userAuthToken)->save(); 
		}
        $this->_redirectUrl($session->getBeforeAuthUrl(true));
    }

	public function passwordAction() {	
	
		$this->loadLayout(); 
		  
			Mage::getSingleton('customer/session')->setCurrentPage('password');
			
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'customer/form/password.phtml')
			);
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);

			$this->getLayout()->getBlock('head')->setTitle($this->__('Password'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();

	}
	
	public function profileAction() {
	$session = $this->_getSession();
	if($_SESSION['rosterImage']==1){
		Mage::getModel('profile/profile')->updateJabberRosterInfo($session->getCustomerId());
		unset($_SESSION['rosterImage']);
	}
		$this->loadLayout(); 
		     Mage::getSingleton('customer/session')->setCurrentPage('profile');
			 
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'customer/form/profile.phtml')
			);
			
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);

			$this->getLayout()->getBlock('head')->setTitle($this->__('Profile'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();

	}
	
	public function noticeAction() {	
	
		$this->loadLayout(); 
		
		    Mage::getSingleton('customer/session')->setCurrentPage('notice');
			
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'customer/form/notices.phtml')
			);
			
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);

			$this->getLayout()->getBlock('head')->setTitle($this->__('Notice'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();

	}
	
	public function creditAction() {	
	
		$this->loadLayout(); 
		     
			Mage::getSingleton('customer/session')->setCurrentPage('credit');
			
			 $block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'customer/form/credit.phtml')
			);
			/*$block = $this->getLayout()->createBlock(
			'Mod_Creditcard_Block_Creditcard',
			'customer_creditcard',
			array('template' => 'creditcard/creditcard.phtml')
			); */
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);

			$this->getLayout()->getBlock('head')->setTitle($this->__('Credit Card'));
			
			$creditcard =Mage::getModel('creditcard/creditcard')->getCreditcard();			
			Mage::register('creditcard', $creditcard);
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();
	}
	
	public function designAction() {	
	
		$this->loadLayout(); 
		     Mage::getSingleton('customer/session')->setCurrentPage('design');
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'customer/form/design.phtml')
			);
			
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);

			$this->getLayout()->getBlock('head')->setTitle($this->__('Design'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();
	}
	
	
	public function quickregisterAction() {	
	
		$this->loadLayout(); 
		     Mage::getSingleton('customer/session')->setCurrentPage('quickregister');
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'customer/form/quickregister.phtml')
			);
			
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);

			$this->getLayout()->getBlock('head')->setTitle($this->__('quickregister'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();
	}
	
	public function serviceAction() {	
	
		$this->loadLayout(); 
		     Mage::getSingleton('customer/session')->setCurrentPage('service');
			if($session = Mage::getSingleton('customer/session')->getId()==13875){
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'customer/form/service-test.phtml')
			);
			}
			else{
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'customer/form/service.phtml')
			);
			}
			
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);

			$this->getLayout()->getBlock('head')->setTitle($this->__('Service'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();
	}
	
	public function viewAction() {
	//echo "mohan";die;
		if ( trim($this->getRequest()->getParam('nickname')) == ''  ) {
			$this->_redirect('home');

		} else {


			//$nickname = $this->getRequest()->getParam('nickname');
			
			$this->loadLayout( array(
			                'default',
			                'attributemanager_account_view'
			            ));

			$this->getLayout()->getBlock('head')->setTitle($this->__('Profile'));
        	$this->renderLayout();
		}

	}
	
	public function deleteImageAction() {
	
		if ($this->getRequest()->getParam('image')) {
        	//$id = $this->getRequest()->getParam('id');
        	$code = $this->getRequest()->getParam('image');
			$customer = Mage::getModel('customer/customer')->load($this->_getSession()->getCustomerId());
			$customer->setData($code, '');
			try {
			
                $customer->save();

                $this->_getSession()->setCustomer($customer);

				
	            $this->_getSession()->addSuccess($this->__('Information was successfully saved'));
	            
                $this->_redirectReferer();
                return;
            } catch (Mage_Core_Exception $e) {
            	#->setCustomerFormData($this->getRequest()->getPost())
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
            	#->setCustomerFormData($this->getRequest()->getPost())
                $this->_getSession()->addException($e, $this->__('Can\'t save customer'));
            }
		}
		 $this->_redirectReferer();

	}
	
	
	public function myprofileAction() {	
	
		$this->loadLayout(); 
		     Mage::getSingleton('customer/session')->setCurrentPage('dashboard');
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'customer/form/myprofile.phtml')
			);
			
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);


			$this->getLayout()->getBlock('head')->setTitle($this->__('Profile'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();
	}
	
	public function dashboardAction() {	
	
		$this->loadLayout(); 
		     Mage::getSingleton('customer/session')->setCurrentPage('dashboard');
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'customer/form/dashboard.phtml')
			);
			
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);

			$this->getLayout()->getBlock('head')->setTitle($this->__('Dashboard'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();
	}
	
	
	public function deactivateAction() {	
	
		$this->loadLayout(); 
		  
		//	Mage::getSingleton('customer/session')->setCurrentPage('password');
			
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'customer/form/deactivate.phtml')
			);
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);

			$this->getLayout()->getBlock('head')->setTitle($this->__('Account Deactivate'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();

	}
	public function inactiveAction() {	
	
		$this->loadLayout(); 
		  
		//	Mage::getSingleton('customer/session')->setCurrentPage('password');
			
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'customer/form/inactive.phtml')
			);
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);

			$this->getLayout()->getBlock('head')->setTitle($this->__('Account Inactive'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();

	}
	
	public function setNewPasswordPostAction()
    {
        if ($this->getRequest()->isPost()) {
            /* @var $customer Mage_Customer_Model_Customer */
            $customer = $this->_getSession()->getCustomer();

            $errors = array();
           

                // If password change was requested then add it to common validation scheme
                if ($this->getRequest()->getParam('change_password')) {
                    
                    $newPass    = $this->getRequest()->getPost('password');
                    $confPass   = $this->getRequest()->getPost('confirmation');
                        if (strlen($newPass)) {
                            // Set entered password and its confirmation - they will be validated later to match each other and be of right length
                            $customer->setPassword($newPass);
                            $customer->setConfirmation($confPass);
                            $customer->setAccGenBy('chattrspace');
							$customer->sendPasswordReminderEmail();
                        } else {
                            $errors[] = $this->__('New password field cannot be empty.');
                        }
                    
                }

                // Validate account and compose list of errors if any
//                $customerErrors = $customer->validate();
                if (is_array($customerErrors)) {
                    $errors = array_merge($errors, $customerErrors);
                }
   
            if (!empty($errors)) {
                $this->_getSession()->setCustomerFormData($this->getRequest()->getPost());
                foreach ($errors as $message) {
                    $this->_getSession()->addError($message);
                }
                $this->_redirect('social/account/password');
                return $this;
            }

            try {
                $customer->setConfirmation(null);
                $customer->save();
                $this->_getSession()->setCustomer($customer)
                    ->addSuccess($this->__('The account information has been saved.'));

                $this->_redirect('social/account/password');
                return;
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->setCustomerFormData($this->getRequest()->getPost())
                    ->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->setCustomerFormData($this->getRequest()->getPost())
                    ->addException($e, $this->__('Cannot save the customer.'));
            }
        }

        $this->_redirect('social/account/password');
    }
	
	public function editPasswordPostAction()
    {
        if ($this->getRequest()->isPost()) {
            /* @var $customer Mage_Customer_Model_Customer */
            $customer = $this->_getSession()->getCustomer();

            $errors = array();
           

                // If password change was requested then add it to common validation scheme
                if ($this->getRequest()->getParam('change_password')) {
                    $currPass   = $this->getRequest()->getPost('current_password');
                    $newPass    = $this->getRequest()->getPost('password');
                    $confPass   = $this->getRequest()->getPost('confirmation');

                    $oldPass = $this->_getSession()->getCustomer()->getPasswordHash();
                    if (Mage::helper('core/string')->strpos($oldPass, ':')) {
                        list($_salt, $salt) = explode(':', $oldPass);
                    } else {
                        $salt = false;
                    }

                    if ($customer->hashPassword($currPass, $salt) == $oldPass) {
                        if (strlen($newPass)) {
                            // Set entered password and its confirmation - they will be validated later to match each other and be of right length
                            $customer->setPassword($newPass);
                            $customer->setConfirmation($confPass);
							$customer->sendPasswordReminderEmail();
                        } else {
                            $errors[] = $this->__('New password field cannot be empty.');
                        }
                    } else {
                        $errors[] = $this->__('Invalid current password');
                    }
                }

                // Validate account and compose list of errors if any
                //$customerErrors = $customer->validate();
                //if (is_array($customerErrors)) {
                //    $errors = array_merge($errors, $customerErrors);
                //}
   
            if (!empty($errors)) {
                $this->_getSession()->setCustomerFormData($this->getRequest()->getPost());
                foreach ($errors as $message) {
                    $this->_getSession()->addError($message);
                }
                $this->_redirect('social/account/password');
                return $this;
            }

            try {
                $customer->setConfirmation(null);
                $customer->save();
                $this->_getSession()->setCustomer($customer)
                    ->addSuccess($this->__('The account information has been saved.'));

                $this->_redirect('social/account/password');
                return;
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->setCustomerFormData($this->getRequest()->getPost())
                    ->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->setCustomerFormData($this->getRequest()->getPost())
                    ->addException($e, $this->__('Cannot save the customer.'));
            }
        }

        $this->_redirect('social/account/password');
    }
	
	public function editEmailPostAction()
    {
        $email = $this->getRequest()->getPost('email');
        if ($email) {
            if (!Zend_Validate::is($email, 'EmailAddress')) {
                //$this->_getSession()->setForgottenEmail($email);
                $this->_getSession()->addError($this->__('Invalid email address.'));
                $this->_redirectReferer();
                return;
            }
			$collection = Mage::getResourceModel('customer/customer_collection')
									->addAttributeToFilter('email', $email)
									->addAttributeToFilter('entity_id', array('nin' => $customer_id) );
			if ( $collection->count() > 0 ) {
				$this->_getSession()->addError($this->__('This user email already exists.'));
				$this->_redirectReferer();
                return;
			}
			else{
            $customer = Mage::getModel('customer/customer')
						->load($this->_getSession()->getCustomerId());
			//sendMail To old address			
			$oldEmail = $customer->getEmail();
			if($oldEmail!=$email)
				$this->sendMail($customer);	
          
                try {
                    $customer->setEmail($email);
					$customer->save();
                    $this->_getSession()->addSuccess($this->__('Information was successfully saved.'));
					$this->_redirectReferer();
                    //$this->getResponse()->setRedirect(Mage::getUrl('*/*'));
                    return;
                }
                catch (Exception $e){
                    $this->_getSession()->addError($e->getMessage());
                }
            }
        } else {
            //$this->_getSession()->addError($this->__('Please enter your email.'));
            $this->_redirectReferer();
            return;
        }

        $this->_redirectReferer();
    	}
	public function editPostAction()
    	{
		$pageRedirect = Mage::getSingleton('customer/session')->getCurrentPage();
        if ($this->getRequest()->isPost()) {
        	$customer = Mage::getModel('customer/customer')->load($this->_getSession()->getCustomerId());
            	$customer->setWebsiteId($this->_getSession()->getCustomer()->getWebsiteId())
                ->setUsername($this->_getSession()->getCustomer()->getUsername());

            $errors = array();
            $fields = $this->getRequest()->getParams();
			
			if(!isset($fields['privacy']) && isset($fields['hidden_privacy']))
				$fields['privacy']='';
			if(!isset($fields['notice']) && isset($fields['hidden_notice']))
				$fields['notice']='';
			if(!isset($fields['history']) && isset($fields['hidden_history']))
				$fields['history']='';
			
			if($fields['dob']=='dob'){
				$fields['dob']=$fields['m']."/".$fields['d']."/".$fields['y'];
			}
			//print_r($fields); exit;
            foreach ($fields as $code=>$value) {
            	if ( $code != 'form_key' ) {
                	if ( ($error = $this->validateProfileFields($code, $value, $customer->getId())) !== true ) {
						$errors[] = $error;
                	} else {
						if ( $code == 'username' && $value!='') {
						try{
							$oldusername = $customer->getUsername();
							//===================================================================
							$con=mysql_connect("chattr-jabbr.cwliz6chxmwt.us-east-1.rds.amazonaws.com","chattr_process2","h6fnf5.p4-");
							if(!$con)
							{
							  return 'Could not connect: ' . mysql_error();
							}
							$db_selected = mysql_select_db('chattr_process2', $con);
							if (!$db_selected)
							{
								return "Could not connect db : " . mysql_error();
							}
							
							$sql = "update rosterusers set username='".$value."' where username='".$oldusername."'";
							$aaa=mysql_query($sql,$con);
														
							$sql1 = "update rosterusers set jid='".$value."@chatweb.oncam.com' where jid='".$oldusername."@chatweb.oncam.com'";
							$bbb=mysql_query($sql1,$con);
							
							//===================================================================
							#remove the old urlrewrite
							$uldURLCollection = Mage::getModel('core/url_rewrite')->getResourceCollection();
							$uldURLCollection->getSelect()
								->where('id_path=?', 'csprofile/'.strtolower($customer->getUsername()));

							$uldURLCollection->setPageSize(1)->load();

							if ( $uldURLCollection->count() > 0 ) {
								$uldURLCollection->getFirstItem()->delete();
							}					
							
							#add url rewrite
			                		$modelURLRewrite = Mage::getModel('core/url_rewrite');
							
			                		$modelURLRewrite->setIdPath('csprofile/'.strtolower($value))
			                    		->setTargetPath('csprofile/index/view/username/'.$value)
			                    		->setOptions('')
			                    		->setDescription(null)
			                   		->setRequestPath($value);

			                		$modelURLRewrite->save();

						} catch (Exception $e) {
								//$this->_getSession()->addError('Invalid file type');
								$this->_getSession()->addError($this->__('Invalid data'));
								//$mesg='fail';
								//$_SESSION['new_event_mesg']='fail';
								$this->_redirectReferer();	
								return;						
						}

						}
						
						#save the new value
						if(is_array($value)){
							$value = implode(",", $value);
						}
						
							
						if($code=='web'){
							$trans = array("http://" => "", "https://" => "", "ftp://" => "");
							$value = strtr($value, $trans);							
						}
						
						if($code=='firstname'){
							if(Mage::getModel('profile/profile')->checkFirstLastName($value) == 0){
								$errors[] = "Bad words, Choose another firstname.";
							}
						}
						if($code=='lastname'){
							if(Mage::getModel('profile/profile')->checkFirstLastName($value) == 0){
								$errors[] = "Bad words, Choose another lastname.";
							}
						}
						$customer->setData($code, $value);
                	}
                }
            }
			//print_r($_FILES['profile_picture']);DIE;
            if ( isset($_FILES['profile_picture']) && $_FILES['profile_picture']['name'] != '' ) {
				$limit_size=26214400;//25MB
				$min_limit_size=10240;//25MB
				//$file_size=filesize(basename($_FILES['profile_picture']));
				if($_FILES['profile_picture']['size'] < $min_limit_size){
				//$_SESSION['new_products_errors'] = 'file must not be no larger than 25MB';
					$this->_getSession()->addError($this->__('Invalid file size, please upload a large file.'));
					$this->_redirect('social/account/'.$pageRedirect);	
					return;						
				
				}
							
				$filename = $_FILES['profile_picture']['name'];
            	$uid = $this->_getSession()->getCustomerId();
				if($filename!='') {
						try {	
														
							$mediaDir = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
							$img = ereg_replace('[^A-Za-z0-9.-]', '-', $filename);
							$img = $uid.$img;
							
							$path = Mage::getBaseDir('media') . DS .  'profile';
							if(!file_exists($path))     
							mkdir($path,0777);
							
							Mage::getModel('attributemanager/uploadimage')->move_upload($_FILES['profile_picture']['tmp_name'] , $path."/".$img);
							
							
						} catch (Exception $e) {
							//$_SESSION['new_products_errors'] = "Invalid file";
							$this->_getSession()->addError($e->getMessage());
							return $this->_redirectReferer();
						}
					
						//this way the name is saved in DB
						$customer->setData('profile_picture', $img);
					}
				
				//$this->_addImages($magentoProductModel, $data['name'], $data['designtype']);
				//$path = $uid."/".$img;
				
				//get resized image
				try{
				$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($img);	
				$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($img, 128, 80, "128x80");
				$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($img, 128, 128, "128x128");
				$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($img, 30, 30, "30x30");
				$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($img, 48, 48, "48x48");
				$_SESSION['rosterImage']=1;
				} catch (Exception $e) {
					$this->_getSession()->addError($this->__('Invalid File Type'));
					$this->_redirectReferer();	
					return;						
				}
            }
			
			//save bg image
			if ( isset($_FILES['bgimage']) && $_FILES['bgimage']['name'] != '' ) {
				$filename = $_FILES['bgimage']['name'];
							
					try {	
						/* Starting upload */	
						$uploader = new Varien_File_Uploader('bgimage');
						$uploader->checkMimeType(array('jpg','jpeg','gif','png'));
						// Any extention would work
						$uploader->setAllowedExtensions(array('jpg','jpeg','gif','png'));
						$uploader->setAllowRenameFiles(false);							
						
						$img = mt_rand().ereg_replace('[^A-Za-z0-9.]', '-', $filename);
						$filename = 'bgimage'.$img;
						$path = Mage::getBaseDir('media') . DS .  'chattrspace'. DS;							
						$uploader->setFilesDispersion(false);									
						// We set media as the upload dir
						//$path = Mage::getBaseDir('media') . DS .'customersproducts'. DS;
						$uploader->save($path,  $filename);
						} catch (Exception $e) {
						$this->_getSession()->addError($this->__("Image size should be less than or equal to 2 MB."));
						$this->_redirect('social/account/'.$pageRedirect);	
						return;						
					}
					list($width, $height, $type, $attr) = getimagesize($path.$filename);
						//echo $type; exit;
						if($type == 1 || $type == 2 || $type == 3){
							
						} else {
							$this->_getSession()->addError($this->__("Invalid image type, image should be jpg, png or gif."));
							$this->_redirect('social/account/'.$pageRedirect);
							return;
						}
						
						if($width < 400){
							$this->_getSession()->addError($this->__("Invalid image dimension, image width should be greater or equal to 400px."));
							$this->_redirect('social/account/'.$pageRedirect);		
							return;
						}
					//Save to S3	
					$imageResized=$path.$filename;	
					$bucketName = 'chattrspace';
					$objectname = 'user_bgimages/'.$filename;
					$filename1 = Mage::getModel('uploadjob/amazonS3')
						->putImage( $bucketName, $imageResized, $objectname, 'public');	
					//End Save to S3	
					
					$customer->setData('bgimage', $img);
            }
			// Save Away Banner Image
			if ( isset($_FILES['banner']) && isset($_POST['bannersavebtn']) && $_FILES['banner']['name'] != '' ) {
				$filename = $_FILES['banner']['name'];
								
					try {	
						/* Starting upload */	
						$uploader = new Varien_File_Uploader('banner');
						$uploader->checkMimeType(array('jpg','jpeg','gif','png'));
						// Any extention would work
						$uploader->setAllowedExtensions(array('jpg','jpeg','gif','png'));
						$uploader->setAllowRenameFiles(false);							
						
						$img = mt_rand().ereg_replace('[^A-Za-z0-9.]', '-', $filename);
						$filename = $this->_getSession()->getCustomerId().'-'.$img;
						$path = Mage::getBaseDir('media') . DS .  'banner'. DS;							
						$uploader->setFilesDispersion(false);									
						// We set media as the upload dir
						//$path = Mage::getBaseDir('media') . DS .'customersproducts'. DS;
						$uploader->save($path,  $filename);
						} catch (Exception $e) {
						$this->_getSession()->addError($this->__("Image size should be less than or equal to 2 MB and should be jpg, png and gif."));
						$this->_redirect('social/account/index');	
						return;						
					}
					list($width, $height, $type, $attr) = getimagesize($path.$filename);
						//echo $type; exit;
						if($type == 1 || $type == 2 || $type == 3){
							
						} else {
							$this->_getSession()->addError($this->__("Invalid image type, image should be jpg, png or gif."));
							$this->_redirect('social/account/index');
							return;
						}
						/*
						if($width < 400){
							$this->_getSession()->addError($this->__("Invalid image dimension, image width should be greater or equal to 400px."));
							$this->_redirect('social/account/index');		
							return;
						}*/
					//Save to S3	
					$imageResized=$path.$filename;	
					$bucketName = 'chattrspace';
					$objectname = 'adslots/'.$filename;
					$filename1 = Mage::getModel('uploadjob/amazonS3')
						->putImage( $bucketName, $imageResized, $objectname, 'public');	
					//End Save to S3	
					
					$bannerpath="http://chattrspace.s3.amazonaws.com/adslots/".$filename;
					$customer->setData('away_banner', $filename);
            }
			// End Away Banner Image
			// Clear Away Banner Image
			if ( isset($_POST['clearbtn'])) {
				$customer->setData('away_banner', '');
            }
			// End Clear Away Banner Image
			// Save Left Banner Image
			if ( isset($_FILES['leftbanner']) && isset($_POST['leftbannersavebtn']) && $_FILES['leftbanner']['name'] != '' ) {
				$filename = $_FILES['leftbanner']['name'];
				
					try {	
						/* Starting upload */	
						$uploader = new Varien_File_Uploader('leftbanner');
						$uploader->checkMimeType(array('jpg','jpeg','gif','png'));
						// Any extention would work
						$uploader->setAllowedExtensions(array('jpg','jpeg','gif','png'));
						$uploader->setAllowRenameFiles(false);							
						
						$img = mt_rand().ereg_replace('[^A-Za-z0-9.]', '-', $filename);
						$filename = $this->_getSession()->getCustomerId().'-L-'.$img;
						$path = Mage::getBaseDir('media') . DS .  'banner'. DS;							
						$uploader->setFilesDispersion(false);									
						// We set media as the upload dir
						//$path = Mage::getBaseDir('media') . DS .'customersproducts'. DS;
						$uploader->save($path,  $filename);
						} catch (Exception $e) {
						$this->_getSession()->addError($this->__("Image size should be less than or equal to 2 MB and should be jpg, png and gif."));
						$this->_redirect('social/account/index');	
						return;						
					}
					list($width, $height, $type, $attr) = getimagesize($path.$filename);
						//echo $type; exit;
						if($type == 1 || $type == 2 || $type == 3){
							
						} else {
							$this->_getSession()->addError($this->__("Invalid image type, image should be jpg, png or gif."));
							$this->_redirect('social/account/index');
							return;
						}
						
					//Save to S3	
					$imageResized=$path.$filename;	
					$bucketName = 'chattrspace';
					$objectname = 'adslots/'.$filename;
					$filename1 = Mage::getModel('uploadjob/amazonS3')
						->putImage( $bucketName, $imageResized, $objectname, 'public');	
					//End Save to S3	
					
					$bannerpath="http://chattrspace.s3.amazonaws.com/adslots/".$filename;
					$customer->setData('left_slot_banner', $filename);
            }
			// End Left Banner Image
			// Clear Left Banner Image
			if ( isset($_POST['leftclearbtn'])) {
				$customer->setData('left_slot_banner', '');
            }
			// End Clear Left Banner Image
			// Save Right Banner Image
			if ( isset($_FILES['rightbanner']) && isset($_POST['rightbannersavebtn']) && $_FILES['rightbanner']['name'] != '' ) {
				$filename = $_FILES['rightbanner']['name'];
				
					try {	
						/* Starting upload */	
						$uploader = new Varien_File_Uploader('rightbanner');
						$uploader->checkMimeType(array('jpg','jpeg','gif','png'));
						// Any extention would work
						$uploader->setAllowedExtensions(array('jpg','jpeg','gif','png'));
						$uploader->setAllowRenameFiles(false);							
						
						$img = mt_rand().ereg_replace('[^A-Za-z0-9.]', '-', $filename);
						$filename = $this->_getSession()->getCustomerId().'-R-'.$img;
						$path = Mage::getBaseDir('media') . DS .  'banner'. DS;							
						$uploader->setFilesDispersion(false);									
						// We set media as the upload dir
						//$path = Mage::getBaseDir('media') . DS .'customersproducts'. DS;
						$uploader->save($path,  $filename);
						} catch (Exception $e) {
						$this->_getSession()->addError($this->__("Image size should be less than or equal to 2 MB and should be jpg, png and gif."));
						$this->_redirect('social/account/index');	
						return;						
					}
					list($width, $height, $type, $attr) = getimagesize($path.$filename);
						//echo $type; exit;
						if($type == 1 || $type == 2 || $type == 3){
							
						} else {
							$this->_getSession()->addError($this->__("Invalid image type, image should be jpg, png or gif."));
							$this->_redirect('social/account/index');
							return;
						}
						
					//Save to S3	
					$imageResized=$path.$filename;	
					$bucketName = 'chattrspace';
					$objectname = 'adslots/'.$filename;
					$filename1 = Mage::getModel('uploadjob/amazonS3')
						->putImage( $bucketName, $imageResized, $objectname, 'public');	
					//End Save to S3	
					
					$bannerpath="http://chattrspace.s3.amazonaws.com/adslots/".$filename;
					$customer->setData('right_slot_banner', $filename);
            }
			// End Right Banner Image
			// Clear Right Banner Image
			if ( isset($_POST['rightclearbtn'])) {
				$customer->setData('right_slot_banner', '');
            }
			// End Clear Right Banner Image
			
            if ($this->_getSession()->getCustomerGroupId()) {
                $customer->setGroupId($this->_getSession()->getCustomerGroupId());
            }

            try {
				//if($fields['bgtile_1']==1)
					//$customer->setBgtile(1);
				//elseif($fields['bgtile_1']==0)
					//$customer->setBgtile(0);
				//else
					//$customer->setBgtile($customer->getBgtile());
						
				if (!empty($errors)) {
	                foreach ($errors as $message) {
	                    $this->_getSession()->addError($message);
	                }
	            } else {
					$customer->save();
					$this->_getSession()->setCustomer($customer);
	            	$this->_getSession()->addSuccess($this->__('Information was successfully saved'));
	            }

               // $this->_redirect('social/account/edit');
                //$this->_redirectReferer();
				if($pageRedirect == ""){
					$this->_redirect('social/account/index');
				} else {
					$this->_redirect('social/account/'.$pageRedirect);
				}
                //return;
            } catch (Mage_Core_Exception $e) {
            	#->setCustomerFormData($this->getRequest()->getPost())
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
            	#->setCustomerFormData($this->getRequest()->getPost())
                $this->_getSession()->addException($e, $this->__('Can\'t save customer'));
            }
        }
		if($pageRedirect == ""){
		$this->_redirect('social/account/index');
		} else {
			$this->_redirect('social/account/'.$pageRedirect);
		}
	}

	private function uploadPhoto($photo_name, $type='') {
		$max_size = 3670016; // the max. size for uploading
		$my_upload = Mage::getModel('attributemanager/uploadimage');
		$my_upload->upload_dir = Mage::getBaseDir().'/media/chattrspace/'; // "files" is the folder for the uploaded files
		$my_upload->extensions = array(".gif", ".jpg",".jpeg",".png"); // specify the allowed extensions here
		$my_upload->max_length_filename = 50; // change this value to fit your field length in your database (standard 100)
		$my_upload->rename_file = true;
		$my_upload->filename =	$photo_name;
		
		if($type=='bgimage'){
			$my_upload->upload_dir = Mage::getBaseDir().'/media/chattrspace/';
			$my_upload->the_temp_file = $_FILES['bgimage']['tmp_name'];
			$my_upload->the_file = $_FILES['bgimage']['name'];
			$my_upload->http_error = $_FILES['bgimage']['error'];
		}else{
			$my_upload->the_temp_file = $_FILES['profile_picture']['tmp_name'];
			$my_upload->the_file = $_FILES['profile_picture']['name'];
			$my_upload->http_error = $_FILES['profile_picture']['error'];
		}
		
		$my_upload->replace = true; #false; #(isset($_POST['replace'])) ? $_POST['replace'] : "n"; // because only a checked checkboxes is true
		$my_upload->do_filename_check = (isset($_POST['check'])) ? $_POST['check'] : "y"; // use this boolean to check for a valid filename
		$new_name = (isset($_POST['name'])) ? $_POST['name'] : "";

		if ($my_upload->upload($new_name)) { // new name is an additional filename information, use this to rename the uploaded file
			return true;
			#$full_path = $my_upload->upload_dir.$my_upload->file_copy;
			#$info = $my_upload->get_uploaded_file_info($full_path);
			// ... or do something like insert the filename to the database
		} else {
			return $my_upload->show_error_string();
			#$this->_getSession()->addError($my_upload->show_error_string());
			#Mage::getSingleton('core/session')->addError($my_upload->show_error_string());
			#return false;
		}



	}

	private function validateProfileFields($code, $value, $customer_id) {
		switch ($code) {
			case 'username':
				#check if this nikname is a-z09_
				if ( !preg_match('/^([a-zA-Z0-9_]+)$/', $value) ) {
				//if ( !preg_match("/^[\[\]=,\?&@~\{\}\+'\.*!™`A-Za-z0-9_-]+$/", $value) ) {
					return 'Your username should contain only letters, numbers and underscore';
					//return 'username should be without whitespace';
					
				}
				if(Mage::getModel('profile/profile')->checkReserveBadKey($value) == 0)
					return 'Choose another username';
				elseif(Mage::getModel('profile/profile')->checkReserveBadKey($value) == 1)
					return 'Username already taken';
				elseif (strlen($value) < 6 || strlen($value) > 15) {
					return 'Your username should be 6 to 15 characters long';
				} else {
					#check if another customer has it
					$collection = Mage::getResourceModel('customer/customer_collection')
									->addAttributeToFilter('username', $value)
									->addAttributeToFilter('entity_id', array('nin' => $customer_id) );
					if ( $collection->count() > 0 ) {
						return 'Your username should be unique';
					}
				}
			break;
			case 'email':
				#check if this nikname is a-z09_
				#if ( !preg_match('/^([a-Z0-9_]+)$/', $value) ) {
				if ( !preg_match("/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/", $value) ) {
					//return 'Your username should contain only letters, numbers and underscore';
					return 'Doesn\'t look like a valid email';
					
				} else {
					#check if another customer has it
					$collection = Mage::getResourceModel('customer/customer_collection')
									->addAttributeToFilter('email', $value)
									->addAttributeToFilter('entity_id', array('nin' => $customer_id) );
					if ( $collection->count() > 0 ) {
						return 'This user email already exists';
					}
				}
			break;
			case 'dob':
				if($this->getAge($value) < 13) {					
					return 'age must be greater than or equal to 13 years';					
				}
			break;
		}
		return true;
	}
	
	//events notification
	public function notificationAction() {
		$this->loadLayout();  
		$block = $this->getLayout()->createBlock(
			'Mod_Events_Block_Events',
			'live_events',
			array('template' => 'events/notification.phtml')
			);
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);
			$this->getLayout()->getBlock('head')->setTitle($this->__('Events Notification'));
			
		$this->_initLayoutMessages('customer/session');
		$this->_initLayoutMessages('core/session');
		$this->renderLayout();		
	}
	
	//people
	public function peopleAction() {
		$this->loadLayout();  
		$block = $this->getLayout()->createBlock(
			'Mod_Events_Block_Events',
			'live_events',
			array('template' => 'events/people.phtml')
			);
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);
			$this->getLayout()->getBlock('head')->setTitle($this->__('People'));
			
		$this->_initLayoutMessages('customer/session');
		$this->_initLayoutMessages('core/session');
		$this->renderLayout();		
	}

	//followed
	public function followedAction() {
		$this->loadLayout();  
		$block = $this->getLayout()->createBlock(
			'Mod_Events_Block_Events',
			'live_events',
			array('template' => 'people/followed.phtml')
			);
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);
			$this->getLayout()->getBlock('head')->setTitle($this->__('Followed'));
			
		$this->_initLayoutMessages('customer/session');
		$this->_initLayoutMessages('core/session');
		$this->renderLayout();		
	}
	
	//followed
	public function followingAction() {
		$this->loadLayout();  
		$block = $this->getLayout()->createBlock(
			'Mod_Events_Block_Events',
			'live_events',
			array('template' => 'people/following.phtml')
			);
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);
			$this->getLayout()->getBlock('head')->setTitle($this->__('Following'));
			
		$this->_initLayoutMessages('customer/session');
		$this->_initLayoutMessages('core/session');
		$this->renderLayout();		
	}

	//flash video action
	public function flashvideoAction() {	
	
		$this->loadLayout(); 
		  
		//	Mage::getSingleton('customer/session')->setCurrentPage('flashvideo');
			
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'customer/form/flashvideo.phtml')
			);
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);

			$this->getLayout()->getBlock('head')->setTitle($this->__('flashvideo'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();

	}
	
	 /**
     * Customer register form page
     */
    // public function createAction()
    // {
        // if ($this->_getSession()->isLoggedIn()) {
            // $this->_redirect('*/*');
            // return;
        // }
		// $i_code = $this->getRequest()->getParam('code');
		// if (empty($i_code)){
			 // Mage::getSingleton('core/session')->addError($this->__('Invitation code required.'));
			// $this->_redirectReferer();
			// return;
		// }else{
			// $resource = Mage::getSingleton('core/resource');
			// $read= $resource->getConnection('core_read');
			// $write = $resource->getConnection('core_write');
			// $invitation_code = $resource->getTableName('invitation_code');
			
			// $select = "select count(*) as cnt from $invitation_code WHERE code='".$i_code."' and status=1";
			// $rs = $read->fetchRow($select);	
			// if($rs['cnt'] < 1){
				// Mage::getSingleton('core/session')->addError($this->__('Invalid invitation code.'));
				// $this->_redirectReferer();
				// return;				
			// }
		// }
		////echo $this->getRequest()->getParam('code');die;
        // $this->loadLayout();
        // $this->_initLayoutMessages('customer/session');
        // $this->renderLayout();
    // }
	
	/**
     * Create customer account action
     */
    public function createPostAction()
    {	//print_r($this->getRequest()->getParams());die; 
		
		$fields = $this->getRequest()->getParams();
		if(isset($fields['user_name']))
			$user_name = $this->getRequest()->getParam('user_name');
		else
			$user_name = $this->getRequest()->getParam('username');
		
		if(isset($fields['email_addr']))
			$email_addr = $this->getRequest()->getParam('email_addr');
		else
			$email_addr = $this->getRequest()->getParam('email');
		/* Added at 20-11-2012 */
		$fname = $this->getRequest()->getParam('firstname');
		$lname = $this->getRequest()->getParam('lastname');
		/* End */
        $session = $this->_getSession();
        if ($session->isLoggedIn()) {
            $this->_redirect('*/*/');
            return;
        }
        $session->setEscapeMessages(true); // prevent XSS injection in user input
        if ($this->getRequest()->isPost()) {
            $errors = array();
			
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$write = $resource->getConnection('core_write');
				/*$invite_code = $this->getRequest()->getParam("invite_code");
		 if (!empty($invite_code) && preg_match('/^([a-zA-Z0-9]+)$/', $invite_code)) 
			{	
				$resource = Mage::getSingleton('core/resource');
				$read= $resource->getConnection('core_read');
				$write = $resource->getConnection('core_write');
				$invitation_code = $resource->getTableName('invitation_code');
				
				$select = "select $invitation_code.* from $invitation_code WHERE code='".$invite_code."' and status=1";
				$rs = $read->fetchRow($select);	
			//echo count($rs['id']);die;
				if(count($rs['id'])>0){
					$msg = true;
					//$write->query("update $invitation_code set status=0 WHERE code='".$invite_code."'");
				}
				else{
					 Mage::getSingleton('core/session')->addError($this->__('Invalid invitation code.'));
					$this->_redirect('social/account/login');
					return;
				}
			}
			else{
					 Mage::getSingleton('core/session')->addError($this->__('Invalid invitation code.'));
					$this->_redirect('social/account/login');
					return;
				} */
				
            if (!$customer = Mage::registry('current_customer')) {
                $customer = Mage::getModel('customer/customer')->setId(null);
            }

            /* @var $customerForm Mage_Customer_Model_Form */
            $customerForm = Mage::getModel('customer/form');
            $customerForm->setFormCode('customer_account_create')
                ->setEntity($customer);
//print_r($this->getRequest()->getParams());die;
            $customerData = $customerForm->extractData($this->getRequest());

            if ($this->getRequest()->getParam('is_subscribed', false)) {
                $customer->setIsSubscribed(1);
            }
			
			if($this->getRequest()->getParam('dob')=='-1' && $this->getRequest()->getParam('month')!=''){
				$fields['dob']=$this->getRequest()->getParam('month')."/".$this->getRequest()->getParam('day')."/".$this->getRequest()->getParam('year');
				/* if($this->getAge($fields['dob']) >= 13) {
					$customer->setDob($fields['dob']);
				}else{
					$session->addError('age must be greater than or equal to 13 years');
					$this->_redirectReferer();
					return;
				} */
			}
			//$this->getRequest()->getParam('dob')=$fields['dob'];
			//echo $fields['dob'];die;
            /**
             * Initialize customer group id
             */
            $customer->getGroupId();

            if ($this->getRequest()->getPost('create_address')) {
                /* @var $address Mage_Customer_Model_Address */
                $address = Mage::getModel('customer/address');
                /* @var $addressForm Mage_Customer_Model_Form */
                $addressForm = Mage::getModel('customer/form');
                $addressForm->setFormCode('customer_register_address')
                    ->setEntity($address);

                $addressData    = $addressForm->extractData($this->getRequest(), 'address', false);
                $addressErrors  = $addressForm->validateData($addressData);
                if ($addressErrors === true) {
                    $address->setId(null)
                        ->setIsDefaultBilling($this->getRequest()->getParam('default_billing', false))
                        ->setIsDefaultShipping($this->getRequest()->getParam('default_shipping', false));
                    $addressForm->compactData($addressData);
                    $customer->addAddress($address);

                    $addressErrors = $address->validate();
                    if (is_array($addressErrors)) {
                        $errors = array_merge($errors, $addressErrors);
                    }
                } else {
                    $errors = array_merge($errors, $addressErrors);
                }
            }
			
            try {
				$customerData['email'] = $email_addr;
                $customerErrors = $customerForm->validateData($customerData);
                if ($customerErrors !== true) {
                    $errors = array_merge($customerErrors, $errors);
                } else {
                    $customerForm->compactData($customerData);
                    $customer->setPassword($this->getRequest()->getPost('password'));
                    $customer->setConfirmation($this->getRequest()->getPost('confirmation'));
                    $customerErrors = $customer->validate();
                    if (is_array($customerErrors)) {
                        $errors = array_merge($customerErrors, $errors);
                    }
                }
				
				if($this->getRequest()->getParam('dob')=='-1' && $this->getRequest()->getParam('month')!='')
					{
						$fields['dob']=$this->getRequest()->getParam('month')."/".$this->getRequest()->getParam('day')."/".$this->getRequest()->getParam('year');
						if($this->getAge($fields['dob']) >= 13) {
							$customer->setDob($fields['dob']);
						}else{
							//$session->addError('age must be greater than or equal to 13 years');
							$errors = array_merge($errors, array('dob'=>'age must be greater than or equal to 13 years'));
							//$this->_redirectReferer();
							//return;
						}
				}
					
                $un = $user_name;
				if ($un) {
					/* if ( ($error = $this->validateProfileFields('username', $un, 0)) !== true ) {
						$errors[] = $error;
                	} */
					$collection = Mage::getResourceModel('customer/customer_collection')
									->addAttributeToFilter('username', $un);
									
					if (strlen($un) < 6 || strlen($un) > 15){
						$errors = array_merge($errors, array('username'=>'Your username should be 6 to 15 characters long'));
					}elseif ( !preg_match('/^([a-zA-Z0-9_]+)$/', $un) ) {
							$errors = array_merge($errors, array('username'=>'Your username should contain only letters, numbers and underscore'));
							//$session->addError('Your username should contain only letters, numbers, dot and underscore');
							//$this->_redirectReferer();
							//return;
					}
					elseif(Mage::getModel('profile/profile')->checkReserveBadKey($un) == 0){
						$errors = array_merge($errors, array('username'=>'Choose another username'));
					}
					elseif(Mage::getModel('profile/profile')->checkReserveBadKey($un) == 1){
						$errors = array_merge($errors, array('username'=>'Username already taken'));
					}
					elseif( $collection->count() > 0 ){
						$errors = array_merge($errors, array('username'=>'Your username should be unique'));
				}
				else{
					$customer->setUsername($user_name); 
				}
				}
				/* Added at 20-11-2012 */
				if(Mage::getModel('profile/profile')->checkFirstLastName($fname) == 0){
					$errors = array_merge($errors, array('username'=>'Bad words, Choose another firstname'));
				}
				if(Mage::getModel('profile/profile')->checkFirstLastName($lname) == 0){
					$errors = array_merge($errors, array('username'=>'Bad words, Choose another lastname'));
				}
				/* End */
				if ($email_addr) {
					if ( ($error = $this->validateProfileFields('email', $email_addr, 0)) !== true ) {
						$errors = array_merge($errors, $error);
					}					
				}
				
				$validationResult = count($errors) == 0;
				
                if (true === $validationResult) {
					//set default timezone
					$customer->setTimezone('America/Los_Angeles');
					
					$customer->save();
					/* $select = "select $invitation_code.* from $invitation_code WHERE code='".$invite_code."' and status=1";
					$rs = $read->fetchRow($select);	
					//echo count($rs['id']);die;
					if(count($rs['id'])>0 && $rs['created_on']!='0000-00-00 00:00:00'){
						//$write->query("update $invitation_code set status=0, WHERE code='".$invite_code."'");
					}
					else
						$write->query("update $invitation_code set created_on=now() WHERE code='".$invite_code."'");
					 *///create default widget entry
					$widget = $resource->getTableName('widget_info');
					//$widget_key = ereg_replace('[^A-Za-z0-9.]', '-', date('m-d-y H:i:s'));
					//$widget_key = $this->encode($widget_key);
					//$widget_key = $this->encode('winfo'.$customer->getId());
					$widget_key = $this->uniqueKey($customer->getId());
					$write->query("insert into $widget (username, is_default, widget_key, created_on, user_id, chat_input) values('".$customer->getUsername()."', 1, '".$widget_key."', now(), ".$customer->getId().", 1)");
					Mage::getModel('profile/profile')->updateJabberRosterInfo($customer->getId());
					//rewrite url
					if ( $user_name ) {
						$value = $user_name;
						if($value!='')	{
							#remove the old urlrewrite
							$uldURLCollection = Mage::getModel('core/url_rewrite')->getResourceCollection();
							$uldURLCollection->getSelect()
								->where('id_path=?', 'csprofile/'.strtolower($customer->getUsername()));

							$uldURLCollection->setPageSize(1)->load();

							if ( $uldURLCollection->count() > 0 ) {
								$uldURLCollection->getFirstItem()->delete();
							}					
							
							#add url rewrite
			                $modelURLRewrite = Mage::getModel('core/url_rewrite');
							
			                $modelURLRewrite->setIdPath('csprofile/'.strtolower($value))
			                    ->setTargetPath('csprofile/index/view/username/'.$value)
			                    ->setOptions('')
			                    ->setDescription(null)
			                    ->setRequestPath($value);

			                $modelURLRewrite->save();

						}
					}	//end rewrite url
                    if ($customer->isConfirmationRequired()) {
                        $customer->sendNewAccountEmail('confirmation', $session->getBeforeAuthUrl());
                        $session->addSuccess($this->__('Account confirmation is required. Please, check your email for the confirmation link. To resend the confirmation email please <a href="%s">click here</a>.', Mage::helper('customer')->getEmailConfirmationUrl($customer->getEmail())));
                        $this->_redirectSuccess(Mage::getUrl('*/*/index', array('_secure'=>true)));
                        return;
                    } else {
                        $session->setCustomerAsLoggedIn($customer);
						$jabberAuth = Mage::getModel('profile/profile')->jabberAuth();
                        $url = $this->_welcomeCustomer($customer);
                        $this->_redirectSuccess($url);
                        return;
                    }
                } else {
                    $session->setCustomerFormData($this->getRequest()->getPost());
                    if (is_array($errors)) {
                        foreach ($errors as $errorMessage) {
                            $session->addError($errorMessage);
                        }
                    } else {
                        $session->addError($this->__('Invalid customer data'));
                    }
                }
            } catch (Mage_Core_Exception $e) {
                $session->setCustomerFormData($this->getRequest()->getPost());
                if ($e->getCode() === Mage_Customer_Model_Customer::EXCEPTION_EMAIL_EXISTS) {
                    $url = Mage::getUrl('social/account/forgotpassword');
                    $message = $this->__('There is already an account with this email address. If you are sure that it is your email address, <a href="%s">click here</a> to get your password and access your account.', $url);
                    $session->setEscapeMessages(false);
                } else {
                    $message = $e->getMessage();
                }
                $session->addError($message);
            } catch (Exception $e) {
                $session->setCustomerFormData($this->getRequest()->getPost())
                    ->addException($e, $this->__($e->getMessage().'Cannot save the customer.'));
            }
        }

         //$this->_redirectError(Mage::getUrl('*/*/create', array('_secure' => true, 'code'=> $invite_code)));
         $this->_redirectError(Mage::getUrl('*/*/create', array('_secure' => true)));
    }
	
	public function getRandomString($len, $chars=null)
    {
        if (is_null($chars)) {
            $chars = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789";
        }
        mt_srand(10000000*(double)microtime());
        for ($i = 0, $str = '', $lc = strlen($chars)-1; $i < $len; $i++) {
            $str .= $chars[mt_rand(0, $lc)];
        }
        return $str;
    }
	//seave youtube info
/* 	public function saveYoutubeAction() {
	$ytid = $this->getRequest()->getParam('youtubename');
	$ytpw = $this->getRequest()->getParam('youtubepassword');
		if ($ytid) {
			if($fields['youtubename']!='' && $code=='youtubepassword' && $value!=''){
							$yt_auth = Mage::getModel('uploadjob/uploadjob')->clientLoginAuth($fields['youtubename'], $value);
							//print_r($yt_auth);
							if($yt_auth['username']){
								//echo $yt_auth['username'];
								$password = $this->encode($value);
								$value = $password;
							}else{
								$errors[] = "Invalid Youtube username or password.";								
								$customer->setData('youtubename', '');
								$customer->setData('youtubepassword', '');
							}
														
						}
			
			if($uname!=''){
				$customer->setData('username', $uname);
			}
			try {
			
                $customer->save();

                $this->_getSession()->setCustomer($customer);
				$this->_getSession()->setCustomerAsLoggedIn($customer);
				
	            $this->_getSession()->addSuccess($this->__('Information was successfully saved'));
	            
                //$this->_redirectReferer();
				$this->_redirect('social/account');
                return;
            } catch (Mage_Core_Exception $e) {
            	#->setCustomerFormData($this->getRequest()->getPost())
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
            	#->setCustomerFormData($this->getRequest()->getPost())
                $this->_getSession()->addException($e, $this->__('Can\'t save customer'));
            }
		}
		 $this->_getSession()->addSuccess($this->__('Information was successfully saved'));
		 //$this->_redirectReferer();
		 $this->_redirect('social/account');
		return;
	} */
	
	public function usernameAction() {
	//print_r($this->getRequest()->getParams());die;
		if ($cid = $this->getRequest()->getParam('cid')) {
			$uname = $this->getRequest()->getParam('username');
			$customer = Mage::getModel('customer/customer')->load($cid);
			
			if($uname!=''){
				$customer->setData('username', $uname);
			}
			try {
			
                $customer->save();

                $this->_getSession()->setCustomer($customer);
				$this->_getSession()->setCustomerAsLoggedIn($customer);
				
	            $this->_getSession()->addSuccess($this->__('Information was successfully saved'));
	            
                //$this->_redirectReferer();
				$this->_redirect('social/account');
                return;
            } catch (Mage_Core_Exception $e) {
            	#->setCustomerFormData($this->getRequest()->getPost())
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
            	#->setCustomerFormData($this->getRequest()->getPost())
                $this->_getSession()->addException($e, $this->__('Can\'t save customer'));
            }
		}
		 $this->_getSession()->addSuccess($this->__('Information was successfully saved'));
		 //$this->_redirectReferer();
		 $this->_redirect('social/account');
		return;
	}
	
	public function unlinkFacebookAction() {
		//$user_id = intval($user_id);
		if(Mage::getSingleton( 'customer/session' )->isLoggedIn()){
			
			//$username = $this->encode($username);
			$password = $this->encode($password);
			
			$user_id = Mage::getSingleton( 'customer/session' )->getCustomerId();
			$customer = Mage::getModel('customer/customer')->load($user_id);
			$customer->setFacebookUid('');
			$customer->setFacebookUsername('');
			$customer->setFacebookCode('');
			$customer->save();
			 $resource = Mage::getSingleton('core/resource');
			//$read= $resource->getConnection('core_read');
			$write = $resource->getConnection('core_write');
			$widget_fb_reg = $resource->getTableName('widget_fb_reg');
			$qstr = "delete from $widget_fb_reg where uid=$user_id";
			$write->query($qstr);
			$this->_getSession()->addSuccess($this->__('successfully unlink with facebook'));
				
		}
		else
			$this->_getSession()->addException($e, $this->__('Can\'t save customer'));

			$this->_redirect('social/account/service');
			return;		
	}
	
	public function unlinkTwitterAction() {
		//$user_id = intval($user_id);
		if(Mage::getSingleton( 'customer/session' )->isLoggedIn()){
			
			//$username = $this->encode($username);
			$password = $this->encode($password);
			
			$user_id = Mage::getSingleton( 'customer/session' )->getCustomerId();
			$customer = Mage::getModel('customer/customer')->load($user_id);
			$customer->setTwitterId('');
			$customer->setTwitterUsername('');
			$customer->setTwitterOauthSecret('');
			$customer->setTwitterOauthToken('');
			$customer->setTwitterAccessToken('');
			$customer->save();
			$this->_getSession()->addSuccess($this->__('successfully unlink with twitter'));
			
		}
		else
			$this->_getSession()->addException($e, $this->__('Can\'t save customer'));

			$this->_redirect('social/account/service');
			return;
	}
	
	public function unlinkYoutubeAction() {
		//$user_id = intval($user_id);
		if(Mage::getSingleton( 'customer/session' )->isLoggedIn()){
			
			//$username = $this->encode($username);
			//$password = $this->encode($password);
			
			$user_id = Mage::getSingleton( 'customer/session' )->getCustomerId();
			$customer = Mage::getModel('customer/customer')->load($user_id);
			$customer->setYoutubeToken('');
			$customer->setYoutubename('');
			//$customer->setYoutubepassword('');
			$customer->save();
			
			$resource = Mage::getSingleton('core/resource');
			$read = $resource->getConnection('core_read');
			$write = $resource->getConnection('core_write');
			$table = $resource->getTableName('youtube_access_token');
			$sqlSelect = "select * from $table where user_id=".$user_id;
			$result = $read->fetchAll($sqlSelect);
			if(count($result) > 0){
				$sqlDelete = "delete from $table where user_id=".$user_id;
				$write->query($sqlDelete);
			}
			$this->_getSession()->addSuccess($this->__('successfully unlink with youtube'));
		}
		else
			$this->_getSession()->addException($e, $this->__('Can\'t save customer'));

			$this->_redirect('social/account/service');
			return;
	}
	
	/* public function unlinkFacebookAction() {
	//print_r($this->getRequest()->getParams());die;
		if ($cid = $this->getRequest()->getParam('cid')) {
			$facebook_uid = $this->getRequest()->getParam('facebook_uid');
			$customer = Mage::getModel('customer/customer')->load($cid);
			
			if($facebook_uid!=''){
				$customer->setData('facebook_uid', $facebook_uid);
			}
			try {
			
                $customer->save();

                $this->_getSession()->setCustomer($customer);
				
	            $this->_getSession()->addSuccess($this->__('successfully unlink with facebook'));
	            
                //$this->_redirectReferer();
				$this->_redirect('social/account');
                return;
            } catch (Mage_Core_Exception $e) {
            	#->setCustomerFormData($this->getRequest()->getPost())
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
            	#->setCustomerFormData($this->getRequest()->getPost())
                $this->_getSession()->addException($e, $this->__('Can\'t save customer'));
            }
		}
		// $this->_getSession()->addSuccess($this->__('Information was successfully saved'));
		 //$this->_redirectReferer();
		 $this->_redirect('social/account/service');
		return;
	} */
	
	public function deactivateAccountAction() {
			try {
				$customer = Mage::getModel('customer/customer')->load($this->_getSession()->getCustomerId());
                
                 if ($this->_getSession()->isLoggedIn()) {
					//$this->_getSession()->logout()->setBeforeAuthUrl(Mage::getUrl());
					//$customer->confirmation = md5(uniqid(rand(), TRUE));
					$customer->setIsActive(0);
					$customer->save();
					Mage::getSingleton('core/session')->addSuccess($this->__('You successfully deactivated your\'s account.'));
					$this->_getSession()->logout();
					
				}
				              
            } catch (Mage_Core_Exception $e) {
            	#->setCustomerFormData($this->getRequest()->getPost())
                Mage::getSingleton('core/session')->addError($e->getMessage());
            } catch (Exception $e) {
            	#->setCustomerFormData($this->getRequest()->getPost())
                 Mage::getSingleton('core/session')->addException($e, $this->__('Can\'t save deactivate, please try again.'));
            }
		
		// $this->_getSession()->addSuccess($this->__('Information was successfully saved'));
		 //$this->_redirectReferer();
		  $this->_redirect('*/*/logoutSuccess');
		return;
	}
	
	public function sendMail($customer)
    {			
	    //$post = $this->getRequest()->getPost();		
		if ($customer->getEmail()){
				$to_email = $customer->getEmail();
				$to_name = 	$customer->getFirstname()." ".$customer->getLastname();
				$webUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
				$skinUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN)."frontend/default/chattrspace/images/";
				
				$subject = 'You recently changed your OnCam email.';
				
				$Body='<html>
<body style="background:#F6F6F6; font-family:"lucida grande",tahoma,verdana,arial,sans-serif;; font-size:12px; margin:0; padding:0;">
<div style="background:#F6F6F6; font-family:"lucida grande",tahoma,verdana,arial,sans-serif;; font-size:12px; margin:0; padding:0;">
<table cellspacing="0" cellpadding="0" border="0" height="100%" width="100%">
        
		<tr>
            <td align="center" valign="top" style="padding:20px 0 20px 0">
               
                <table bgcolor="FFFFFF" cellspacing="0" cellpadding="10" border="0" width="680" style="border:1px solid #E0E0E0;">
                
				<tr>
					<td valign="top">
						<a href="'.$webUrl.'"><img src="'.$skinUrl.'logo.gif" alt="OnCam"  border="0"/></a>
					</td>
				</tr>
				<tr>
                    <td valign="top">
					<div class="im">
                        <h1 style="font-size:22px;font-weight:normal;line-height:22px;margin:0 0 11px 0">Dear '.$to_name.',</h1>
                        <p style="font-size:12px;line-height:16px;margin:0 0 8px 0">You recently changed your OnCam email. As a security precaution, this notification has been sent to all email addresses associated with your account.</p>
                     </div>
					 <p style="font-size:12px;line-height:16px;margin:0">If you did not change your password, your account may have been the victim of a phishing scam. Please <a href="mailto:info@chattrspace.com">contact us</a>. to regain control over your account. </p>
                    </td>
                </tr>
				<tr>
					<td bgcolor="#EAEAEA" align="center" style="background:#EAEAEA; text-align:center;"><center><p style="font-size:12px; margin:0;">The <strong>OnCam</strong>Team</p></center></td>
				</tr>
					 </table>
            </td>
        </tr>
    </table>
	   
</body>
</html>
';
				
				$sender_email = "info@oncam.com";
				$sender_name = "oncam";
				 
				$mail = new Zend_Mail(); //class for mail
			    $mail->setBodyHtml($Body); //for sending message containing html code
				$mail->setFrom($sender_email, $sender_name);
				$mail->addTo($to_email, $to_name);
				//$mail->addCc($cc, $ccname);    //can set cc
				//$mail->addBCc($bcc, $bccname);    //can set bcc
				$mail->setSubject($subject);
				$msg  ='';
				try {
					  if($mail->send())
					  {
						 $msg = true;
					  }
					}
				catch(Exception $ex) {
						$msg = false;
						//die("Error sending mail to $to,$error_msg");
				}
				return;
				//$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($msg));
			}
	}
	
	public function getAge($Birthdate)
	{
			// Explode the date into meaningful variables
			/* list($BirthYear,$BirthMonth,$BirthDay) = explode("/", $Birthdate);
			// Find the differences
			echo $YearDiff = date("Y") - $BirthYear;
			$MonthDiff = date("m") - $BirthMonth;
			$DayDiff = date("d") - $BirthDay;
			// If the birthday has not occured this year
			if ($DayDiff < 0 || $MonthDiff < 0)
			  $YearDiff--; */
			$startdate =  date("Y-m-d G:i:s");
			$enddate = date("Y-m-d G:i:s", strtotime($Birthdate));
			
			$diff =  strtotime($startdate) - strtotime($enddate);
			$time = round(($diff/60/60/24/30/12),0);
			return $time;
	}
	
	public function encode($string, $key="chattrspace rocks") {
		$string = base64_encode(base64_encode($string));
		$key = sha1($key);
		$strLen = strlen($string);
		$keyLen = strlen($key);
		$j=0;$hash='';
		for ($i = 0; $i < $strLen; $i++) {
			$ordStr = ord(substr($string,$i,1));
			if ($j == $keyLen) { $j = 0; }
			$ordKey = ord(substr($key,$j,1));
			$j++;
			$hash .= strrev(base_convert(dechex($ordStr + $ordKey),16,36));
		}
		return $hash;
	}
	
	public function isCSKeyword($string) {
		$cs_keywords = array("register", "dashboard", "eventtitle", "EventsAttended", "EventsHosted", "Follower", "Followers", "Following", "Recording", "Recordings", "purchase", "people", "event", "events", "account", "password", "notice", "notices", "profile", "design", "service", "services", "deactivate", "search", "About", "Blog", "FAQ", "CommunityGuidelines", "Terms", "Privacy", "Business", "Businesses", "Publisher", "Publishers", "YouTuber", "YouTubers", "Developer", "Developers", "Widget", "QuickStart", "Help", "Mobile", "Status", "Job", "Jobs", "Career", "Careers", "Advertiser", "Advertisers", "Media", "Resource", "Resources", "SignUp", "Message", "Messages", "Safety", "Partner", "Partners", "Press", "Copyright", "Browse", "record", "trailer", "trailers", "movie", "movies", "music", "show", "shows", "live", "shortcut", "shortcuts", "promoted", "app", "apps", "application", "applications", "store", "deal", "deals", "ads", "notification", "notifications", "inbox", "contact", "contacts", "invite", "feedback", "policie", "policies", "shuffle", "list", "lists", "like", "likes", "abuse", "community", "cspservice", "csservice", "csprofile", "video", "videos", "user", "users", "space", "chattrspace", "chatr", "chattrstatus", "eworks", "eworksindia", "aimsvaristy", "ccBillTextFile", "csusers", "downloader", "errors", "ExcelToDb", "facebook-php-sdk", "flex", "flexwidget", "imagecache", "in", "includes", "js", "lib", "media", "pkginfo", "profile", "shell", "skin", "temp", "var", "video_v1", "videos", "youtube_upload", "yt_upload", "code", "local", "Aoe", "CS", "Magentix", "Magesocial", "Mod", "Pol", "Admintheme", "Creditcard", "Csservice", "Events", "People", "Profile", "Uploadjob", "Block", "controllers", "etc", "Helper", "Model", "sql");

		if(in_array($string, $cs_keywords)===true)
			return true;
		else
			return false;
	}
	
	//crop image
	public function cropAction(){
	    if ($data = $this->getRequest()->getPost()) {			
			//print_r($data);exit;
			$id = (string) $this->getRequest()->getParam('id');			
			if (empty($id)) {
				$this->_forward('*/*');
				return;
			}
		try
		{	
				$model= Mage::getModel('attributemanager/square');
				$img_name = $model->resizeThumbnailImage($data, $type='square');
				$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($img_name, 128, 80, "128x80", true);
				$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($img_name, 128, 128, "128x128", true);
				$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($img_name, 30, 30, "30x30", true);
				$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($img_name, 48, 48, "48x48", true);
				//die;
				//$model->generateReapeatImages($magentoProductModel, $squareImgUrl, 90);
				//$model->generateEngineeredImages($magentoProductModel, $portrateImgUrl, 90);
				
				 $this->_getSession()->addSuccess($this->__('Information was successfully saved'));
		} catch (Exception $e) {
           $this->_getSession()->addError($e->getMessage());
		}
		}
		$ref=$this->getRequest()->getParam('ref');
		if($ref){
			$this->_redirect('social/user/profiles');
		}else{
			$this->_redirect('social/account/profile');
		}
		return;
    }
	
	public function editLanguageAction()
    {
        if ($this->getRequest()->isPost()) {
        	$customer = Mage::getModel('customer/customer')->load($this->_getSession()->getCustomerId());
            $customer->setWebsiteId($this->_getSession()->getCustomer()->getWebsiteId())
                ->setUsername($this->_getSession()->getCustomer()->getUsername());
			$lang = $this->getRequest()->getParam('language');
			$customer->setLanguage($lang);
			try{
				//$customer->setStoreId(12);
				$customer->save();
				$this->_getSession()->addSuccess($this->__('Information was successfully saved'));
			$this->_redirect('social/account/?lang='.$lang."#");
			//$this->_redirect('*/*/', array('lang'=>$lang));
	            
            } catch (Exception $e) {
            	$this->_getSession()->addException($e, $this->__('Can\'t save Laguage'));
				$this->_redirectReferer();
            }
		}
		//$this->_redirectReferer();
        return;
	}
	
	/**
     * Confirm customer account by id and confirmation key
     */
    public function confirmAction()
    {
        if ($this->_getSession()->isLoggedIn()) {
            $this->_redirect('*/*/');
            return;
        }
        try {
            $id      = $this->getRequest()->getParam('id', false);
            $key     = $this->getRequest()->getParam('key', false);
            echo $backUrl = $this->getRequest()->getParam('back_url', false);
	    
	    //die;
            if (empty($id) || empty($key)) {
                throw new Exception($this->__('Bad request.'));
            }

            // load customer by id (try/catch in case if it throws exceptions)
            try {
                $customer = Mage::getModel('customer/customer')->load($id);
                if ((!$customer) || (!$customer->getId())) {
                    throw new Exception('Failed to load customer by id.');
                }
            }
            catch (Exception $e) {
                throw new Exception($this->__('Wrong customer account specified.'));
            }

            // check if it is inactive
            if ($customer->getConfirmation()) {
                if ($customer->getConfirmation() !== $key) {
                    throw new Exception($this->__('Wrong confirmation key.'));
                }

                // activate customer
                try {
                    $customer->setConfirmation(null);
                    $customer->save();
                }
                catch (Exception $e) {
                    throw new Exception($this->__('Failed to confirm customer account.'));
                }

                // log in and send greeting email, then die happy
                $this->_getSession()->setCustomerAsLoggedIn($customer);
                $successUrl = $this->_welcomeCustomer($customer, true);
                $this->_redirectSuccess('social/user/interesttab');
                //$this->_redirectSuccess($backUrl ? $backUrl : $successUrl);
		$this->_redirect('social/user/interesttab');
                return;
            }
		$this->_redirect('social/user/interesttab');
            // die happy
           // $this->_redirectSuccess(Mage::getUrl('*/*/index', array('_secure'=>true)));
            return;
        }
        catch (Exception $e) {
            // die unhappy
            $this->_getSession()->addError($e->getMessage());
            $this->_redirectError(Mage::getUrl('*/*/index', array('_secure'=>true)));
            return;
        }
    }

    /**
     * Send confirmation link to specified email
     */
    public function confirmationAction()
    {
        $customer = Mage::getModel('customer/customer');
        if ($this->_getSession()->isLoggedIn()) {
            $this->_redirect('*/*/'); 
            return;
        }

        // try to confirm by email
        $email = $this->getRequest()->getPost('email');
        if ($email) {
            try {
                $customer->setWebsiteId(Mage::app()->getStore()->getWebsiteId())->loadByEmail($email);
                if (!$customer->getId()) {
                    throw new Exception('');
                }
                if ($customer->getConfirmation()) {
                    $customer->sendNewAccountEmail('confirmation');
                    $this->_getSession()->addSuccess($this->__('Please, check your email for confirmation key.'));
                } else {
                    $this->_getSession()->addSuccess($this->__('This email does not require confirmation.'));
                }
                $this->_getSession()->setUsername($email);
                $this->_redirectSuccess(Mage::getUrl('*/*/index', array('_secure' => true)));
            } catch (Exception $e) {
                $this->_getSession()->addException($e, $this->__('Wrong email.'));
                $this->_redirectError(Mage::getUrl('*/*/*', array('email' => $email, '_secure' => true)));
            }
            return;
        }

        // output form
        $this->loadLayout();

        $this->getLayout()->getBlock('accountConfirmation')
            ->setEmail($this->getRequest()->getParam('email', $email));

        $this->_initLayoutMessages('customer/session');
        $this->renderLayout();
    }
	public function uniqueKey($id){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$widget_info = $resource->getTableName('widget_info');
		
		$key = rand().$id.rand();
		$unikey = $read->fetchRow("SELECT widget_key from $widget_info WHERE widget_key='".$key."'");
		if(!empty($unikey['widget_key'])){
			$key = $this->uniqueKey($id);
		}
		return $key;
	}
	public function eventAction() {	
	
		$this->loadLayout(); 
		     Mage::getSingleton('customer/session')->setCurrentPage('event');
			 
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'customer/form/event.phtml')
			);
			
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);

			$this->getLayout()->getBlock('head')->setTitle($this->__('Events'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();

	}
	public function productsAction() {	
	
		$this->loadLayout(); 
		     Mage::getSingleton('customer/session')->setCurrentPage('products');
			 
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'customer/form/products.phtml')
			);
			
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);

			$this->getLayout()->getBlock('head')->setTitle($this->__('Products'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();

	}
	public function listproductsAction() {	
	
		$this->loadLayout(); 
		     Mage::getSingleton('customer/session')->setCurrentPage('listproducts');
			 
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'customer/form/listproducts.phtml')
			);
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);

			$this->getLayout()->getBlock('head')->setTitle($this->__('Products'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();
	}
	public function ordersAction() {	
		$this->loadLayout(); 
		     Mage::getSingleton('customer/session')->setCurrentPage('orders');
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'customer/form/orders.phtml')
			);
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');			
			$this->getLayout()->getBlock('content')->append($block);
			$this->getLayout()->getBlock('head')->setTitle($this->__('Orders'));
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();
	}
	public function orderviewAction() {	
		$this->loadLayout(); 
		     Mage::getSingleton('customer/session')->setCurrentPage('orders');
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'customer/form/orderview.phtml')
			);
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');			
			$this->getLayout()->getBlock('content')->append($block);
			$this->getLayout()->getBlock('head')->setTitle($this->__('Orders'));
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();
	}
	public function pdfinvoicesAction(){
        $invoicesIds = $this->getRequest()->getPost('invoice_ids');
        if (!empty($invoicesIds)) {
            $invoices = Mage::getResourceModel('sales/order_invoice_collection')
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('entity_id', array('in' => $invoicesIds))
                ->load();
            if (!isset($pdf)){
                $pdf = Mage::getModel('sales/order_pdf_invoice')->getPdf($invoices);
            } else {
                $pages = Mage::getModel('sales/order_pdf_invoice')->getPdf($invoices);
                $pdf->pages = array_merge ($pdf->pages, $pages->pages);
            }

            return $this->_prepareDownloadResponse('invoice'.Mage::getSingleton('core/date')->date('Y-m-d_H-i-s').
                '.pdf', $pdf->render(), 'application/pdf');
        }
        $this->_redirect('*/*/');
    }
}
?>
