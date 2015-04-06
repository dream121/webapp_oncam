<?php

class Pol_Attributemanager_UserController extends Mage_Core_Controller_Front_Action
{
	static $corpImgAttribute = "";
	public function preDispatch()
    {
        parent::preDispatch();
      if (!Mage::getSingleton('customer/session')->authenticate($this)) {
            $this->setFlag('', 'no-dispatch', true);
        } 
    }
	
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
			array('template' => 'social/userinfo.phtml')
			);
			
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);
			$this->getLayout()->getBlock('head')->setTitle($this->__('User Information'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();
			
	}
	
	public function _getSession()
    {
        return Mage::getSingleton('customer/session');
    }
	
	public function profileAction()
	{
		
		$this->loadLayout(); 
		    Mage::getSingleton('customer/session')->setUploadImage();
			Mage::getSingleton('customer/session')->setImgUploaded();
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'social/profile.phtml')
			);
			
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);
			$this->getLayout()->getBlock('head')->setTitle($this->__('User Information'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();
			
	}
	
	public function spaceAction()
	{
		
		$this->loadLayout(); 
		    
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'social/space.phtml')
			);
			
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);
			$this->getLayout()->getBlock('head')->setTitle($this->__('User Information'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();
			
	}
	
	public function friendsAction()
	{
		
		$this->loadLayout(); 
		    
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'social/friends.phtml')
			);
			
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);
			$this->getLayout()->getBlock('head')->setTitle($this->__('User Information'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();
			
	}
	
	public function profilePicAction()
	{
	
		$filename = strip_tags($_REQUEST['filename']);
		$maxSize = strip_tags($_REQUEST['maxSize']);
		$maxW = strip_tags($_REQUEST['maxW']);
		$fullPath = strip_tags($_REQUEST['fullPath']);
		$relPath = strip_tags($_REQUEST['relPath']);
		$colorR = strip_tags($_REQUEST['colorR']);
		$colorG = strip_tags($_REQUEST['colorG']);
		$colorB = strip_tags($_REQUEST['colorB']);
		$maxH = strip_tags($_REQUEST['maxH']);
		$filesize_image = $_FILES[$filename]['size'];
		if($filesize_image > 0){
			try{
			$upload_image = $this->uploadImage($filename, $maxSize, $maxW, $fullPath, $relPath, $colorR, $colorG, $colorB, $maxH);
			}catch(Exception $e){
				echo $e->getMessage();
			}
			//print_r($upload_image);
			if(is_array($upload_image)){
				foreach($upload_image as $key => $value) {
					if($value == "-ERROR-") {
						unset($upload_image[$key]);
					}
				}
				$document = array_values($upload_image);
				for ($x=0; $x<sizeof($document); $x++){
					$errorList[] = $document[$x];
				}
				$imgUploaded = false;
				Mage::getSingleton('customer/session')->setUploadImage();
			}else{
				$imgUploaded = true;
				Mage::getSingleton('customer/session')->setUploadImage($upload_image);
			}
			
		}else{
			$imgUploaded = false;
			$errorList[] = "File Size Empty";
		}
			//return $imgUploaded ;
			Mage::getSingleton('customer/session')->setImgUploaded($imgUploaded);
			Mage::getSingleton('core/session')->addError($errorList);
			
	}
	
	public function uploadImage($fileName, $maxSize, $maxW, $fullPath, $relPath, $colorR, $colorG, $colorB, $maxH = null){
		$folder = $relPath;
		$maxlimit = $maxSize;$save='';
		$allowed_ext = "jpg,jpeg,gif,png,bmp";
		$match = "";
		$filesize = $_FILES[$fileName]['size'];
		if($filesize > 0){	
			$filename = strtolower($_FILES[$fileName]['name']);
			$filename = preg_replace('/\s/', '_', $filename);
		   	if($filesize < 1){ 
				$errorList[] = "File size is empty.";
			}
			if($filesize > $maxlimit){ 
				$errorList[] = "File size is too big.";
			}
			if(count($errorList)<1){
				$file_ext = preg_split("/\./",$filename);
				$allowed_ext = preg_split("/\,/",$allowed_ext);
				foreach($allowed_ext as $ext){
					if($ext==end($file_ext)){
						$match = "1"; // File is allowed
						$NUM = time();
						$front_name = substr($file_ext[0], 0, 15);
						$newfilename = $front_name."_".$NUM.".".end($file_ext);
						$filetype = end($file_ext);
						$folder = Mage::getBaseDir('media') . DS .  'profile'.DS;
						$save = $folder.$newfilename;
						
						if(!file_exists($save)){
							list($width_orig, $height_orig) = getimagesize($_FILES[$fileName]['tmp_name']);
							if($maxH == null){
								if($width_orig < $maxW){
									$fwidth = $width_orig;
								}else{
									$fwidth = $maxW;
								}
								$ratio_orig = $width_orig/$height_orig;
								$fheight = $fwidth/$ratio_orig;
								
								$blank_height = $fheight;
								$top_offset = 0;
									
							}else{
								if($width_orig <= $maxW && $height_orig <= $maxH){
									$fheight = $height_orig;
									$fwidth = $width_orig;
								}else{
									if($width_orig > $maxW){
										$ratio = ($width_orig / $maxW);
										$fwidth = $maxW;
										$fheight = ($height_orig / $ratio);
										if($fheight > $maxH){
											$ratio = ($fheight / $maxH);
											$fheight = $maxH;
											$fwidth = ($fwidth / $ratio);
										}
									}
									if($height_orig > $maxH){
										$ratio = ($height_orig / $maxH);
										$fheight = $maxH;
										$fwidth = ($width_orig / $ratio);
										if($fwidth > $maxW){
											$ratio = ($fwidth / $maxW);
											$fwidth = $maxW;
											$fheight = ($fheight / $ratio);
										}
									}
								}
								if($fheight == 0 || $fwidth == 0 || $height_orig == 0 || $width_orig == 0){
									die("FATAL ERROR REPORT ERROR CODE [add-pic-line-67-orig] to <a href='/404'>AT WEB RESULTS</a>");
								}
								if($fheight < 45){
									$blank_height = 45;
									$top_offset = round(($blank_height - $fheight)/2);
								}else{
									$blank_height = $fheight;
								}
							}
							$image_p = imagecreatetruecolor($fwidth, $blank_height);
							$white = imagecolorallocate($image_p, $colorR, $colorG, $colorB);
							imagefill($image_p, 0, 0, $white);
							switch($filetype){
								case "gif":
									$image = @imagecreatefromgif($_FILES[$fileName]['tmp_name']);
								break;
								case "jpg":
									$image = @imagecreatefromjpeg($_FILES[$fileName]['tmp_name']);
								break;
								case "jpeg":
									$image = @imagecreatefromjpeg($_FILES[$fileName]['tmp_name']);
								break;
								case "png":
									$image = @imagecreatefrompng($_FILES[$fileName]['tmp_name']);
								break;
							}
							@imagecopyresampled($image_p, $image, 0, $top_offset, 0, 0, $fwidth, $fheight, $width_orig, $height_orig);
							switch($filetype){
								case "gif":
									if(!@imagegif($image_p, $save)){
										$errorList[]= "PERMISSION DENIED [GIF]";
									}
								break;
								case "jpg":
									if(!@imagejpeg($image_p, $save, 100)){
										$errorList[]= "PERMISSION DENIED [JPG]";
									}
								break;
								case "jpeg":
									if(!@imagejpeg($image_p, $save, 100)){
										$errorList[]= "PERMISSION DENIED [JPEG]";
									}
								break;
								case "png":
									if(!@imagepng($image_p, $save, 0)){
										$errorList[]= "PERMISSION DENIED [PNG]";
									}
								break;
							}
							@imagedestroy($filename);
						}else{
							$errorList[]= "CANNOT MAKE IMAGE IT ALREADY EXISTS";
						}	
					}
				}		
			}
		}else{
			$errorList[]= "NO FILE SELECTED";
		}
		if(!$match){
		   	$errorList[]= "File type isn't allowed: $filename";
		}
		$uid = Mage::getSingleton('customer/session')->getCustomerId();
		$customer = Mage::getModel('customer/customer')->load($uid);
		if(sizeof($errorList) == 0){
			//return $fullPath.$newfilename;
			$customer->setData('profile_picture', $newfilename);
			$customer->save();
			try{
				$testImage = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($newfilename);	
				$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($newfilename, 128, 80, "128x80");
				$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($newfilename, 128, 128, "128x128");
				$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($newfilename, 30, 30, "30x30");
				$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($newfilename, 48, 48, "48x48");
				if($save!=''){
					$img = $fullPath.$newfilename;
					$testImage = $fullPath.'resized/'.$newfilename;
					
					$s_m = Mage::getModel('attributemanager/square');
					$x2 =  $s_m->getWidth($img);

					$y2 = $s_m->getHeight($img);

					$x1 =  $s_m->getWidth($testImage);
					
					$y1 =  $s_m->getHeight($testImage);
					
				//self::$corpImgAttribute = '<input  type="hidden" name="ratiow_1" id="ratiow_1" value="'. $x2/$x1.'"/><input  type="hidden" name="ratioh_1" id="ratioh_1" value="'.$y2/$y1.'"/><input  type="hidden" name="image_1" id="image_1" value="'. $img.' "/><input  type="hidden" name="testImage_1" id="testImage_1" value="'. $testImage.' "/>';
				}
				} catch (Exception $e) {
					Mage::getSingleton('customer/session')->addError($this->__('Invalid File Type'));
					//$this->_redirectReferer();	
					//return;						
				}
				return $fullPath.'128x128/'.$newfilename;
		}else{
			$eMessage = array();
			for ($x=0; $x<sizeof($errorList); $x++){
				$eMessage[] = $errorList[$x];
			}
		   	return $eMessage;
		}
	}
	
	public function getprofileImgAction() {	
		$filename = strip_tags($_REQUEST['filename']);
		$maxSize = strip_tags($_REQUEST['maxSize']);
		$maxW = strip_tags($_REQUEST['maxW']);
		$fullPath = strip_tags($_REQUEST['fullPath']);
		$relPath = strip_tags($_REQUEST['relPath']);
		$colorR = strip_tags($_REQUEST['colorR']);
		$colorG = strip_tags($_REQUEST['colorG']);
		$colorB = strip_tags($_REQUEST['colorB']);
		$maxH = strip_tags($_REQUEST['maxH']);
		$filesize_image = $_FILES[$filename]['size'];
		if($filesize_image > 0){
			try{
			$upload_image = $this->uploadImage($filename, $maxSize, $maxW, $fullPath, $relPath, $colorR, $colorG, $colorB, $maxH);
			}catch(Exception $e){
				echo $e->getMessage();
			}
			//print_r($upload_image);
			if(is_array($upload_image)){
				foreach($upload_image as $key => $value) {
					if($value == "-ERROR-") {
						unset($upload_image[$key]);
					}
				}
				$document = array_values($upload_image);
				for ($x=0; $x<sizeof($document); $x++){
					$errorList[] = $document[$x];
				}
				$imgUploaded = false;
				Mage::getSingleton('customer/session')->setUploadImage();
			}else{
				$imgUploaded = true;
				Mage::getSingleton('customer/session')->setUploadImage($upload_image);
			}
			
		}else{
			$imgUploaded = false;
			$errorList[] = "File Size Empty";
		}
		
		if($imgUploaded){
			echo '<img src="/images/success.gif" width="16" height="16" border="0" style="marin-bottom: -4px;" /> <img src="'.$upload_image.'" border="0" />';
			echo self::$corpImgAttribute;

		}else{
			echo '<img src="/images/error.gif" width="16" height="16px" border="0" style="marin-bottom: -3px;" /> Error(s) Found: ';
			foreach($errorList as $value){
					echo $value.', ';
			}
		}
	}
	
	public function profiletabAction()
	{		
			$this->loadLayout(); 
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'social/profiletab.phtml')
			);
			
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);
			$this->getLayout()->getBlock('head')->setTitle($this->__('User Profile'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();
			
	}
	
	public function designtabAction()
	{		
			$this->loadLayout(); 
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'social/designtab.phtml')
			);
			
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);
			$this->getLayout()->getBlock('head')->setTitle($this->__('Design Your Page'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();
			
	}
	
	public function interesttabAction()
	{		
			$this->loadLayout(); 
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'social/intresttab.phtml')
			);
			
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);
			$this->getLayout()->getBlock('head')->setTitle($this->__('Design Your Page'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();
			
	}
	
	public function designAction()
	{		
			$this->loadLayout(); 
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'social/design.phtml')
			);
			
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);
			$this->getLayout()->getBlock('head')->setTitle($this->__('Design Your Page'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();
			
	}
	
	public function interestAction()
	{		
			$this->loadLayout(); 
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'social/intrest.phtml')
			);
			
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);
			$this->getLayout()->getBlock('head')->setTitle($this->__('Suggested Users'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();
			
	}
	
	public function friendstabAction()
	{		
			$this->loadLayout(); 
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'social/friendstab.phtml')
			);
			
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);
			$this->getLayout()->getBlock('head')->setTitle($this->__('Design Your Page'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();
			
	}
	
	public function profilesAction()
	{		
			$this->loadLayout(); 
			$block = $this->getLayout()->createBlock(
			'Mage_Customer_Block_Form_Login',
			'customer_form_login',
			array('template' => 'social/profiles.phtml')
			);
			
			$this->getLayout()->getBlock('root')
            ->setTemplate('page/1column.phtml');
			
			$this->getLayout()->getBlock('content')->append($block);
			$this->getLayout()->getBlock('head')->setTitle($this->__('User Profile'));
			
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('core/session');
        	$this->renderLayout();
			
	}
	
	public function postInterestAction()
    {
       $this->_redirect('social/user/friends');
    }
	
	public function postFriendsAction()
    {
       $this->_redirect('social/user/profile');
    }
	
	public function postProfileAction()
    {
       if ($this->getRequest()->isPost()) {
        	$customer = Mage::getModel('customer/customer')->load($this->_getSession()->getCustomerId());
            $customer->setWebsiteId($this->_getSession()->getCustomer()->getWebsiteId())
                ->setUsername($this->_getSession()->getCustomer()->getUsername());

            $errors = array();
            $fields = $this->getRequest()->getParams();
			
			if($fields['dob']=='dob'){
				$fields['dob']=$fields['m']."/".$fields['d']."/".substr($fields['y'],2,2);
			}
			//echo $fields['dob'];die;
            foreach ($fields as $code=>$value) {
            	if ( $code != 'form_key' ) {
                	if ( ($error = $this->validateProfileFields($code, $value, $customer->getId())) !== true ) {
						$errors[] = $error;
                	} else {
						#save the new value
						if(is_array($value)){
							$value = implode(",", $value);
						}
						
							
						if($code=='web'){
							$trans = array("http://" => "", "https://" => "", "ftp://" => "");
							$value = strtr($value, $trans);							
						}
						
						$customer->setData($code, $value);
						
						
                	}
                }
            }
			

            try {
				$customer->save();

                $this->_getSession()->setCustomer($customer);

				if (!empty($errors)) {
	                foreach ($errors as $message) {
	                    $this->_getSession()->addError($message);
	                }
	            } else {
	            	$this->_getSession()->addSuccess($this->__('Information was successfully saved'));
	            }

               $this->_redirect('social/user/designtab');
                //$this->_redirectReferer();
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
        //$this->_redirect('customer/*/*');
    }
	
	public function postDesignAction()
    {
       if ($this->getRequest()->isPost()) {
        	$customer = Mage::getModel('customer/customer')->load($this->_getSession()->getCustomerId());
            $customer->setWebsiteId($this->_getSession()->getCustomer()->getWebsiteId())
                ->setUsername($this->_getSession()->getCustomer()->getUsername());

            $errors = array();
            $fields = $this->getRequest()->getParams();
			
			//echo $fields['dob'];die;
            foreach ($fields as $code=>$value) {
            	if ( $code != 'form_key' ) {
                	if ( ($error = $this->validateProfileFields($code, $value, $customer->getId())) !== true ) {
						$errors[] = $error;
                	} else {
						#save the new value
						if(is_array($value)){
							$value = implode(",", $value);
						}
						
						$customer->setData($code, $value);
						
                	}
                }
            }
			
			//save bg image
			if ( isset($_FILES['bgimage']) && $_FILES['bgimage']['name'] != '' ) {
				$filename = $_FILES['bgimage']['name'];
				
					try {	
						/* Starting upload */	
						$uploader = new Varien_File_Uploader('bgimage');						
						// Any extention would work
						$uploader->setAllowedExtensions(array('jpg','jpeg','gif','png'));
						$uploader->setAllowRenameFiles(false);							
						
						$img = mt_rand().ereg_replace('[^A-Za-z0-9.]', '-', $filename);
						$filename = 'bgimage'.$img;
						$path = Mage::getBaseDir('media') . DS .  'chattrspace'. DS;							
						$uploader->setFilesDispersion(false);						
						$uploader->save($path,  $filename);
						
					} catch (Exception $e) {
						//$this->_getSession()->addError('Invalid file type');
						$this->_getSession()->addError($this->__('Invalid File Type'));
						//$mesg='fail';
						//$_SESSION['new_event_mesg']='fail';
						$this->_redirectReferer();	
						return;						
					}
					$customer->setData('bgimage', $img);
            }
			
            try {
				$customer->save();

                $this->_getSession()->setCustomer($customer);

				if (!empty($errors)) {
	                foreach ($errors as $message) {
	                    $this->_getSession()->addError($message);
	                }
	            } else {
	            	$this->_getSession()->addSuccess($this->__('Information was successfully saved'));
	            }

               $this->_redirect('social/account/');
                //$this->_redirectReferer();
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
        //$this->_redirect('customer/*/*');
    }
	
	private function validateProfileFields($code, $value, $customer_id) {
		switch ($code) {
			case 'username':
				#check if this nikname is a-z09_
				if ( !preg_match('/^([a-zA-Z0-9_\.]+)$/', $value) ) {
				//if ( !preg_match("/^[\[\]=,\?&@~\{\}\+'\.*!™`A-Za-z0-9_-]+$/", $value) ) {
					return 'Your username should contain only letters, numbers, dot and underscore';
					//return 'username should be without whitespace';
					
				}
				if($this->isCSKeyword($value))
					return 'Reserved key, try diffrent username';
				elseif (strlen($value) < 4 || strlen($value) > 15) {
					return 'Your username should be 4 to 15 characters long';
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
	
	public function getAge($Birthdate)
	{
		$startdate =  date("Y-m-d G:i:s");
		$enddate = date("Y-m-d G:i:s", strtotime($Birthdate));
		
		$diff =  strtotime($startdate) - strtotime($enddate);
		$time = round(($diff/60/60/24/30/12),0);
		return $time;
	}
}

?>