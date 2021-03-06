<?php

class Pol_Attributemanager_Model_Square extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
       // $this->_init('uploadartwork/square');
    }

	public function getImageType($imagesource){
		$image = "";
		$filetype = substr($imagesource,strlen($imagesource)-4,4); 
		$filetype = strtolower($filetype); 
		if($filetype == ".gif")  $image = @imagecreatefromgif($imagesource);  
		if($filetype == ".jpg" || $filetype == ".jpeg")  $image = @imagecreatefromjpeg($imagesource); 
		if($filetype == ".png")  $image = @imagecreatefrompng($imagesource); 
		return  $image;
	}
	
	public function getWidth($imagesource){
		$dirImg = Mage::getBaseDir().str_replace("/",DS,strstr($imagesource,'/media'));
		if (file_exists($dirImg)) {
			$imageObj = new Varien_Image($dirImg);
			return $width = $imageObj->getOriginalWidth();
		}
		else {
			return 1;
		} 
	}
	
	public function getHeight($imagesource){
		$dirImg = Mage::getBaseDir().str_replace("/",DS,strstr($imagesource,'/media'));
		if (file_exists($dirImg)) {
			$imageObj = new Varien_Image($dirImg);			
			return $height = $imageObj->getOriginalHeight();
		}
		else {
			return 1;
		} 
	}
	
	public function resizeOriginalImage($dirname, $image, $w=400, $h=400){
		$mediDir = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
		$imagesdir = $mediDir .'profile'. DS . $dirname . DS . $image;
		$imageUrl = $imagesdir;
		// create folder
		 if(!file_exists("".$mediDir."profile/resized/". $dirname))     
		 mkdir("".$mediDir."profile/". $dirname,0777);

		 // get image name
		 //$imageName = basename($imageUrl);
		 //echo $imageName = substr(strrchr($imageUrl,"/"),1);

		 // resized image path (media/catalog/category/resized/IMAGE_NAME)
		 $imageResized = Mage::getBaseDir('media').DS."profile".DS."resized".DS. $dirname .DS.$image;

		 // changing image url into direct path
		 $dirImg = Mage::getBaseDir().str_replace("/",DS,strstr($imageUrl,'/media'));

		 // if resized image doesn't exist, save the resized image to the resized directory
		 if (!file_exists($imageResized) && file_exists($dirImg)) :
		 $imageObj = new Varien_Image($dirImg);
		 $imageObj->constrainOnly(TRUE);
		 $imageObj->keepAspectRatio(TRUE);
		 $imageObj->keepFrame(FALSE);
		 $imageObj->resize($w, $h);
		 $imageObj->save($imageResized);
		 endif;

		 //return $newImageUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)."customersproducts/resized/customersproducts/".	$dirname . '/' . $image;
	}
	
	public function resizeOriginalImageNew($image, $w=400, $h=400, $type="resized",$croped=false,$user_id=0){
		$mediDir = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
		
		if($croped)
			$imagesdir = $mediDir .'profile'. DS . 'croped'. DS .$image;
		else
			$imagesdir = $mediDir .'profile'. DS . $image;
			
		$imageUrl = $imagesdir;
		// create folder
		/*  if(!file_exists("".$mediDir."profile/".$type))     
		 mkdir("".$mediDir."profile/". $type,0777); */
		$path = Mage::getBaseDir('media') . DS .  'profile'. DS . $type;
		if(!file_exists($path))     
			mkdir($path,0777);
			
		 // get image name
		 //$imageName = basename($imageUrl);
		 //echo $imageName = substr(strrchr($imageUrl,"/"),1);

		 // resized image path (media/catalog/category/resized/IMAGE_NAME)
		 $imageResized = Mage::getBaseDir('media').DS."profile".DS.$type.DS.$image;

		 // changing image url into direct path
		 $dirImg = Mage::getBaseDir().str_replace("/",DS,strstr($imageUrl,'/media'));

		 // if resized image doesn't exist, save the resized image to the resized directory
		 if (file_exists($dirImg)) :
		 $imageObj = new Varien_Image($dirImg);
		 $imageObj->constrainOnly(TRUE);
		 $imageObj->keepAspectRatio(TRUE);
		 $imageObj->keepFrame(FALSE);
		 $imageObj->resize($w, $h);
		 $imageObj->save($imageResized);
		 //save in s3
		$bucketName = 'chattrspace';
		$objectname = 'profiles/'.$type."/".$image;
		$filename = Mage::getModel('uploadjob/amazonS3')
					->putImage( $bucketName, $imageResized, $objectname, 'public');
		if($type == "128x128"){
			if($user_id == 0)
				$user_id = Mage::getSingleton('customer/session')->getId();
			$bucketName = 'chattrspace';
			$objectname = "profileimgbyid/30x30/".$user_id.".png";
			$filename = Mage::getModel('uploadjob/amazonS3')
						->putImage( $bucketName, $imageResized, $objectname, 'public');
		}		
		//end s3
		 endif;

		 //return $newImageUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)."customersproducts/resized/customersproducts/".	$dirname . '/' . $image;
	}
	
	public function resizeThumbnailImage($data, $type){
		$start_width = $data['x1'];
		$start_height = $data['y1'];
		$width = $data['width']; 
		$height = $data['height']; 
		$image = $data['image'];
		//print_r($data);
		$pid = $data['id'];
		$w = $this->getHeight($image);
		
		$scale = 1024/$w; //echo $scale; exit;
		//die;
		$mediaDir = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
		//$imagesdir = $mediaDir .DS . $image;
		$imageUrl = $image;
		// create folder

		 /* if(!file_exists("".$mediaDir."profile/resized/croped"))     
		 mkdir("".$mediaDir."profile/resized/croped",0777); */
		 
		 $path = Mage::getBaseDir('media') . DS .  'profile'. DS . 'croped';
		if(!file_exists($path))     
			mkdir($path,0777);

		 // get image name
		 $imageName = substr(strrchr($imageUrl,"/"),1);

		 // resized image path (media/catalog/category/resized/IMAGE_NAME)
		 $thumb_image_name = Mage::getBaseDir('media'). DS . "profile". DS . "croped" . DS . $imageName;

		 // changing image url into direct path			
			$newImageWidth = ceil($width * $scale);
			$newImageHeight = ceil($height * $scale);
			$newImage = imagecreatetruecolor($newImageWidth,$newImageHeight);
			//new
			 $dirImg = Mage::getBaseDir().str_replace("/",DS,strstr($imageUrl,'/media'));
			$source = $this->getImage($dirImg);
			
			//$source = imagecreatefromjpeg($imageUrl);
			imagecopyresampled($newImage,$source,0,0,$start_width,$start_height,$newImageWidth,$newImageHeight,$width,$height);
			
			//new
			$filetype = $this->getFileType($dirImg);
			if($filetype == ".gif") imagegif($newImage,$thumb_image_name,100);
			if($filetype == ".jpg" || $filetype == "jpeg") imagejpeg($newImage,$thumb_image_name,90); 
			if($filetype == ".png") imagepng($newImage,$thumb_image_name);	
			
			chmod($thumb_image_name, 0777);
			return $imageName;
			//return $newImageUrl = $path. DS . $pid . $imageName;
			//return $newImageUrl = "profile/resized/croped/". $pid . $imageName;	
	}
	
	public function getImage($imagesource){
		$image = "";
		$filetype = substr($imagesource,strlen($imagesource)-4,4); 
		$filetype = strtolower($filetype); 
		if($filetype == ".gif")  $image = @imagecreatefromgif($imagesource);  
		if($filetype == ".jpg" || $filetype == "jpeg")  $image = @imagecreatefromjpeg($imagesource); 
		if($filetype == ".png")  $image = @imagecreatefrompng($imagesource); 
		return  $image;
	}

	public function getFileType($imagesource){
		$image = "";
		$filetype = substr($imagesource,strlen($imagesource)-4,4); 
		$filetype = strtolower($filetype); 	
		return  $filetype;
	}
}	