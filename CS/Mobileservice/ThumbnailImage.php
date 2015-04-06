<?php
//requires GD 2.0.1 or higher
//note about gif
class ThumbnailImage{
	public $image;
	//not applicable to gif or png
	private $quality = 100;
	private $mimetype;
	private $imageproperties;
	private $initialfilesize;
////////////////////////////////////////////////////////
//constructor
////////////////////////////////////////////////////////
	public function __construct($file, $thumbnailsize1 = 100, $thumbnailsize2 = 100, $keepProportion=false, $_quality=85){
		//check path
		is_file($file) or die ("File: $file doesn't exist.");
		$this->quality = $_quality;
		$this->initialfilesize = filesize($file);
		$this->imageproperties = getimagesize($file) or die ("Incorrect file type.");
		// new function image_type_to_mime_type
		$this->mimetype = image_type_to_mime_type($this->imageproperties[2]);
		//create image
		switch($this->imageproperties[2]){
			case IMAGETYPE_JPEG:
				$this->image = imagecreatefromjpeg($file);
				break;
			case IMAGETYPE_GIF:	
				$this->image = imagecreatefromgif($file);
				break;
			case IMAGETYPE_PNG:
				$this->image = imagecreatefrompng($file);
				break;
			default:
				die("Couldn't create image.");
		}
		
		$this->createThumb($thumbnailsize1, $thumbnailsize2, $keepProportion);
	}
////////////////////////////////////////////////////////
//destructor
////////////////////////////////////////////////////////
	public function __destruct(){
		if(isset($this->image)){
			imagedestroy($this->image);			
		}
	}
////////////////////////////////////////////////////////
//public methods
////////////////////////////////////////////////////////
	public function getImage(){
		header("Content-type: $this->mimetype");
		switch($this->imageproperties[2]){
			case IMAGETYPE_JPEG:
				imagejpeg($this->image,"",$this->quality);
				break;
			case IMAGETYPE_GIF:
				imagegif($this->image);
				break;
			case IMAGETYPE_PNG:
				imagepng($this->image);
				break;
			default:
				die("Couldn't create image.");
		}
	}
////////////////////////////////////////////////////////
	public function getMimeType(){
  
		return $this->mimetype;
	}
////////////////////////////////////////////////////////
	public function getBase64(){
		$randomName = 'tmpFile' . uniqid();
		switch($this->imageproperties[2]){
			case IMAGETYPE_JPEG:
				imagejpeg($this->image,$randomName,$this->quality);
				break;
			case IMAGETYPE_GIF:
				imagegif($this->image,$randomName);
				break;
			case IMAGETYPE_PNG:
				imagepng($this->image,$randomName);
				break;
			default:
				die("Couldn't get image base64.");
		}
		$b64 = base64_encode(file_get_contents($randomName, FILE_BINARY));
		unlink($randomName);
		return $b64;
	}
	public function saveTo($filePath, $quality=null){
		if ($quality==null) {
			$quality = $this->quality;
		}
		switch($this->imageproperties[2]){
			case IMAGETYPE_JPEG:
				imagejpeg($this->image,$filePath,$this->quality);
				break;
			case IMAGETYPE_GIF:
				imagegif($this->image,$filePath);
				break;
			case IMAGETYPE_PNG:
				imagepng($this->image,$filePath);
				break;
			default:
				die("Couldn't save image.");
		}
	}
////////////////////////////////////////////////////////
	public function getQuality(){
		$quality = null;
		if($this->imageproperties[2] == IMAGETYPE_JPEG){
			$quality = $this->quality;
		}
		return $quality;
	}
////////////////////////////////////////////////////////
	public function setQuality($quality){
		if($quality > 100 || $quality  <  1){
			$quality = 75;
    }
		if($this->imageproperties[2] == IMAGETYPE_JPEG){
			$this->quality = $quality;
		}
	}
////////////////////////////////////////////////////////
	public function getInitialFileSize(){	
		return $this->initialfilesize;
	}
////////////////////////////////////////////////////////
//private methods
////////////////////////////////////////////////////////
	private function createThumb($thumbnailsize1, $thumbnailsize2, $keepProportion){
		//echo $thumbnailsize1."///";
		//array elements
		$srcW = $this->imageproperties[0];
		$srcH = $this->imageproperties[1];
		//only adjust if larger than reduction size
		if($srcW >$thumbnailsize1 && $srcH > $thumbnailsize2){
			if ($keepProportion == true) {
				if ($thumbnailsize1 > $thumbnailsize2 ) {
					$maxSideSize = $thumbnailsize1;
					$reduction = $this->calculateReduction($maxSideSize);
					$desW = $thumbnailsize1;
					$desH = $srcH/$reduction;
				} else if ($thumbnailsize1 < $thumbnailsize2) {
					$maxSideSize = $thumbnailsize2;
					$reduction = $this->calculateReduction($maxSideSize);
					$desW = $srcW/$reduction;
					$desH = $thumbnailsize2;
				} else {
					$reduction = $this->calculateReduction($thumbnailsize1);
					$desW = $srcW/$reduction;
					$desH = $srcH/$reduction;
				}
				
				//get proportions
			} else {
				$desW = $thumbnailsize1;
				$desH = $thumbnailsize2;
			}
			$copy = imagecreatetruecolor($desW, $desH);
			imagecopyresampled($copy,$this->image,0,0,0,0,$desW, $desH, $srcW, $srcH) or die ("Image copy failed.");
			//destroy original
			imagedestroy($this->image);
			$this->image = $copy;			
		}
	}
////////////////////////////////////////////////////////
	private function calculateReduction($thumbnailsize){
		//adjust
		$srcW = $this->imageproperties[0];
		$srcH = $this->imageproperties[1];
  	if($srcW < $srcH){
  		$reduction = floor($srcH/$thumbnailsize);
  	}else{  			
  		$reduction = floor($srcW/$thumbnailsize);
  	}
		return $reduction;
	}
}//end class
////////////////////////////////////////////////////////
?>
