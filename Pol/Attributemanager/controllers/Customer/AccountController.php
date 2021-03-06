<?php

require_once 'Mage/Customer/controllers/AccountController.php';

class Pol_Attributemanager_Customer_AccountController extends Mage_Customer_AccountController
{

    public function preDispatch()
    {
        // a brute-force protection here would be nice

	if ( !strstr($this->getRequest()->getRequestUri(), 'attributemanager/account/view/username') ) {
			parent::preDispatch();
		}

    }

	public function viewAction() {

		if ( trim($this->getRequest()->getParam('nickname')) == ''  ) {
			$this->_redirect('home');

		} else {

			$nickname = $this->getRequest()->getParam('nickname');

			$this->loadLayout( array(
			                'default',
			                'customerprofile_account_view'
			            ));

			$this->getLayout()->getBlock('head')->setTitle($this->__('Customer Profile'));
        	$this->renderLayout();
		}

	}

	public function editProfileAction()
    {

        if (!$this->_validateFormKey()) {
            return $this->_redirect('customer/*/edit');
        }

        if ($this->getRequest()->isPost()) {
        	$customer = Mage::getModel('customer/customer')->load($this->_getSession()->getCustomerId());
            $customer->setWebsiteId($this->_getSession()->getCustomer()->getWebsiteId())
                ->setNickname($this->_getSession()->getCustomer()->getNickname());

            $errors = array();
            $fields = $this->getRequest()->getParams();
            foreach ($fields as $code=>$value) {
            	if ( $code != 'form_key' ) {
                	if ( ($error = $this->validateProfileFields($code, $value, $customer->getId())) !== true ) {
						$errors[] = $error;
                	} else {
						if ( $code == 'nickname' ) {

							#remove the old urlrewrite
							$uldURLCollection = Mage::getModel('core/url_rewrite')->getResourceCollection();
							$uldURLCollection->getSelect()
								->where('id_path=?', 'customerprofile/'.strtolower($customer->getNickname()));

							$uldURLCollection->setPageSize(1)->load();

							if ( $uldURLCollection->count() > 0 ) {
								$uldURLCollection->getFirstItem()->delete();
							}

							#add url rewrite
			                $modelURLRewrite = Mage::getModel('core/url_rewrite');

			                $modelURLRewrite->setIdPath('customerprofile/'.strtolower($value))
			                    ->setTargetPath('customerprofile/account/view/nickname/'.$value)
			                    ->setOptions('')
			                    ->setDescription(null)
			                    ->setRequestPath($value);

			                $modelURLRewrite->save();

						}
						#save the new value
						$customer->setData($code, $value);
                	}
                }
            }
		
            if ( isset($_FILES['photo']) && $_FILES['photo']['name'] != '' ) {
            	if ( ( $error = $this->uploadPhoto($customer->getId()) ) !== true ) {
            		$errors[] = $error;
            	}
            }

            if ($this->_getSession()->getCustomerGroupId()) {
                $customer->setGroupId($this->_getSession()->getCustomerGroupId());
            }

            try {

                $customer->save();

                $this->_getSession()->setCustomer($customer);

				if (!empty($errors)) {
	                foreach ($errors as $message) {
	                    $this->_getSession()->addError($message);
	                }
	            } else {
	            	$this->_getSession()->addSuccess($this->__('Profile information was successfully saved'));
	            }

                $this->_redirect('customer/account/edit');
                return;
            } catch (Mage_Core_Exception $e) {
            	#->setCustomerFormData($this->getRequest()->getPost())
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
            	#->setCustomerFormData($this->getRequest()->getPost())
                $this->_getSession()->addException($e, $this->__('Can\'t save customer'));
            }
        }

        $this->_redirect('customer/*/*');
    }

	private function uploadPhoto($photo_name) {
		$max_size = 3670016; // the max. size for uploading
		$my_upload = Mage::getModel('customerprofile/uploadimage');
		$my_upload->upload_dir = Mage::getBaseDir().'/media/photo/'; // "files" is the folder for the uploaded files
		$my_upload->extensions = array(".gif", ".jpg",".jpeg",".png"); // specify the allowed extensions here
		$my_upload->max_length_filename = 50; // change this value to fit your field length in your database (standard 100)
		$my_upload->rename_file = true;
		$my_upload->filename =	$photo_name;

		$my_upload->the_temp_file = $_FILES['photo']['tmp_name'];
		$my_upload->the_file = $_FILES['photo']['name'];
		$my_upload->http_error = $_FILES['photo']['error'];
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
			$this->_getSession()->addError($my_upload->show_error_string());
			Mage::getSingleton('core/session')->addError($my_upload->show_error_string());
			#return false;
		}



	}

	private function validateProfileFields($code, $value, $customer_id) {
		switch ($code) {
			case 'nickname':
				#check if this nikname is a-z09_
				#if ( !preg_match('/^([a-Z0-9_]+)$/', $value) ) {
				if ( !preg_match('/^[a-z0-9]+$/i', $value) ) {
					return 'Your nickname should contain only letters, numbers and underscore';
				} elseif ( strlen($value) < 4 ) {
					return 'Your nickname should be at least 4 characters long';
				} else {
					#check if another customer has it
					$collection = Mage::getResourceModel('customer/customer_collection')
									->addAttributeToFilter('nickname', $value)
									->addAttributeToFilter('entity_id', array('nin' => $customer_id) );
					if ( $collection->count() > 0 ) {
						return 'Your nickname should be unique';
					}
				}
			break;
		}
		return true;
	}

}
?>