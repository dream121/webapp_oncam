<?php
include_once("ThumbnailImage.php");
require_once 'Zend/Cache.php';
require_once 'Zend/Cache/Backend/ExtendedInterface.php';
require_once 'Zend/Cache/Backend.php';
require_once 'Zend/Cache/Backend/ZendPlatform.php';
class RBY_Service_Main
{

	static $defaultProductImage = "/media/customizer/no-design.png";
	static $prefixForLocalTable = "rby";
	static $prefixForMagTable = "rbym";
	static $designerImageFolder = "designer";
	static $productImageUrl = "";
	static $defaultDesignerImage = "/upload/blank.jpg";
	static $usersRoomImageFolder = "/media/rooms_images/";
	static $invalidCategoryId = "Invalid Category ID";
	static $invalidCollectionId = "Invalid Collection ID";
	static $invalidDesignerId = "Invalid Designer ID";
	static $invalidWishlistCode = "Invalid Wishlist or Wishlist Not Shared";
	static $notYourRoom = "Invalid Room or Room Owner";
	static $notYourProduct = "Invalid Product or Product Owner";
	static $loginError = "Invalid Login or Session Timed Out";
	static $invalidLogin = "Invalid username or password";
	static $invalidShareWithList = "Invalid share with list";
	static $maxRecordCount = 20;
    static $caching = true;
    static $automatic_serialization = true;
    static $cacheDirectory = './var/rbyservice/';
    static $cacheLifetime = 7200; // 2 hours (in seconds) - this is default
    static $cacheShortTimeSpan = 1200; // 15 minutes (in seconds)
    static $cacheMediumTimeSpan = 2400; //45 minutes (in seconds)
    static $cacheOneDaySpan = 86400; //1 day (in seconds)
    static $cacheFiveDaysSpan = 432000; //5 days (in seconds)
    static $cacheLongTimeSpan = 28800; // 8 hours (in seconds)

	private $isMailTransportActive = false;
	private $transport = "";
    public function Main()
    {
		
    }
    public function __hello($str = "Hello!") {
        return $str;
    }
    public function hello($str = "Hello!") {
        return $this->loadCache()->__hello($str);
    }

	public function getDesigners() {
        //return $this->loadCache()->__getDesigners();
		return $this->__getDesigners();
    }
	public function __getDesigners() {
		$catId = $this->getCategoryIdByName("Designers");
		$designerCat = $this->getCategoryById($catId);
		$returnVal = array('designers' => array());
		for ($i=0; $i<count($designerCat['children']); $i++) {
			$returnVal['designers'][] = $this->getDesignerInfo($designerCat['children'][$i]['entity_id'], true);
			$returnVal['designers'][$i]['url_path'] = $designerCat['children'][$i]['url_path'];
		}
		return $returnVal;
	}
	public function getRandomProducts($count = 0) {
		$count = intval($count);
		if ($count == 0) {
			$count = self::$maxRecordCount;
		}
		$count = min(self::$maxRecordCount, $count);
		$categoryId = $this->getCategoryIdByName("Products");
		$category = new Mage_Catalog_Model_Category();
        $category->load($categoryId);
		$visibility = array(
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
                  );
		$storeId = Mage::app()->getStore()->getId();

		$coll = $category->getProductCollection()
            ->addAttributeToSelect('entity_id')
            ->addAttributeToSelect('category_id')
			->addAttributeToFilter('visibility', $visibility);

		$returnVal = array('products' => array());

		$shuffled_array = array();
		foreach ($coll as $key => $value) {
			$shuffled_array[$key] = $value;
		}
		shuffle($shuffled_array);

		//
		$i = 0;
		foreach ($shuffled_array as $product) {
			if ($i >= $count) {
				break;
			}
			$returnVal['products']['' . $product->getId()] = $this->getProduct($product->getId(), true, false, true);
			$i++;
		}
		return $returnVal;
    }
    public function getRandomDesigns($count = 0) {
		$count = intval($count);
		if ($count == 0) {
			$count = self::$maxRecordCount;
		}
		$count = min(self::$maxRecordCount, $count);
		
		$categoryId = $this->getCategoryIdByName("Designers");
		
		$category = new Mage_Catalog_Model_Category();
        $category->load($categoryId);
		
		$visibility = array(
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
                  );

		$storeId = Mage::app()->getStore()->getId();

		
		$cats = $category->getAllChildren();
		if ($cats != "") {
			$cats = "0," . $cats;
		} else {
			$cats = "0";
		}
		
		$coll = Mage::getResourceModel('reports/product_collection')
				->addAttributeToSelect('entity_id')
                ->addAttributeToSelect('category_id')
				->addAttributeToFilter('visibility', $visibility);
		
		$coll->getSelect()->where('category_ids IN (' . $cats . ')');
		
		$returnVal = array('designs' => array());

		$shuffled_array = array();
		foreach ($coll as $key => $value) {
			$shuffled_array[$key] = $value;
		}
		shuffle($shuffled_array);
		
		//
		$i = 0;
		$prod_ids = array();
		foreach ($shuffled_array as $product) {
			if ($i >= $count) {
				break;
			}
			$returnVal['designs']['' . $product->getId()] = $this->getProduct($product->getId(), true, false, false);
			$prod_ids[] = $product->getId();
			$i++;
		}
		$designUses = $this->getDesignUses($prod_ids);
		foreach ($designUses as $key => $value) {
			$returnVal['designs'][$key]['designUses'] = $value;
		}
		return $returnVal;
    }
	public function getTopSellingProducts() {
		$categoryId = $this->getCategoryIdByName("Products");
		$category = new Mage_Catalog_Model_Category();
        $category->load($categoryId);
		$visibility = array(
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
                  );

		$storeId = Mage::app()->getStore()->getId();
		$cats = $category->getAllChildren();
		if ($cats != "") {
			$cats = "0," . $cats;
		} else {
			$cats = "0";
		}
		$coll = Mage::getResourceModel('reports/product_collection')
				->addAttributeToSelect('entity_id')
                ->addAttributeToSelect('category_id')
				->addAttributeToFilter('visibility', $visibility);
		$coll->getSelect()->where('category_ids IN (' . $cats . ')');

		$coll->setOrder('ordered_qty', 'desc');
		//
		$returnVal = array('products' => array());
		foreach ($coll as $product) {
			$returnVal['products']['' . $product->getId()] = $this->getProduct($product->getId(), true, false, false);
		}
		return $returnVal;
	}
	public function getTopSellingDesigns() {
		$categoryId = $this->getCategoryIdByName("Designers");
		$category = new Mage_Catalog_Model_Category();
        $category->load($categoryId);
		$visibility = array(
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
                  );

		$storeId = Mage::app()->getStore()->getId();
		$cats = $category->getAllChildren();
		if ($cats != "") {
			$cats = "0," . $cats;
		} else {
			$cats = "0";
		}
		$coll = Mage::getResourceModel('reports/product_collection')
				->addAttributeToSelect('entity_id')
                ->addAttributeToSelect('category_id')
				->addAttributeToFilter('visibility', $visibility)
				->addOrderedQty();
		$coll->getSelect()->where('category_ids IN (' . $cats . ')');

		$coll->setOrder('ordered_qty', 'desc');
		//
		$prod_ids = array();
		$returnVal = array('designs' => array());
		foreach ($coll as $product) {
			$returnVal['designs']['' . $product->getId()] = $this->getProduct($product->getId(), true, false, false);
			$prod_ids[] = $product->getId();
		}
		$designUses = $this->getDesignUses($prod_ids);
		foreach ($designUses as $key => $value) {
			$returnVal['designs'][$key]['designUses'] = $value;
		}
		return $returnVal;
	}
	
	public function getLatestDesigns($count = 0) {
        return $this->loadCache()->__getLatestDesigns($count);
    }

	public function __getLatestDesigns($count = 0) {
		$count = intval($count);
		if ($count == 0) {
			$count = self::$maxRecordCount;
		}
		$count = min(self::$maxRecordCount, $count);
		$categoryId = $this->getCategoryIdByName("Designers");
		$category = new Mage_Catalog_Model_Category();
        $category->load($categoryId);
		
		$visibility = array(
			Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
			Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
		);

		$storeId = Mage::app()->getStore()->getId();

		$cats = $category->getAllChildren();
		if ($cats != "") {
			$cats = "0," . $cats;
		} else {
			$cats = "0";
		}
		$coll = Mage::getResourceModel('reports/product_collection')
				->addAttributeToSelect('entity_id')
                ->addAttributeToSelect('category_id')
				->addAttributeToFilter('visibility', $visibility)
                ->addAttributeToSort('repeats_design', 'desc');
		$coll->getSelect()->where('category_ids IN (' . $cats . ')');

		$coll->setOrder('created_at', 'desc');
		//
		$prod_ids = array();
		$returnVal = array('designs' => array());
		$i = 0;
		foreach ($coll as $product) {
			if ($i >= $count) {
				break;
			}
			$returnVal['designs']['' . $product->getId()] = $this->getProduct($product->getId(), true, false, false);
			$prod_ids[] = $product->getId();
			$i++;
		}
		$designUses = $this->getDesignUses($prod_ids);
		foreach ($designUses as $key => $value) {
			$returnVal['designs'][$key]['designUses'] = $value;
		}
		return $returnVal;
	}
	//
	public function getFeaturedDesigns($count = 0) {
        return $this->loadCache(self::$cacheLongTimeSpan)->__getFeaturedDesigns($count);
    }
	public function __getFeaturedDesigns($count = 0) {
		$count = intval($count);
		if ($count == 0) {
			$count = self::$maxRecordCount;
		}
		$count = min(self::$maxRecordCount, $count);
		$categoryId = $this->getCategoryIdByName("Designers");
		$category = new Mage_Catalog_Model_Category();
        $category->load($categoryId);

		$visibility = array(
			Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
			Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
		);

		$storeId = Mage::app()->getStore()->getId();

		$cats = $category->getAllChildren();
		if ($cats != "") {
			$cats = "0," . $cats;
		} else {
			$cats = "0";
		}
		$coll = Mage::getResourceModel('reports/product_collection')
				->addAttributeToSelect('entity_id')
                ->addAttributeToSelect('category_id')
				->addAttributeToFilter('visibility', $visibility)
				->addAttributeToFilter('featured_design', 1);
		$coll->getSelect()->where('category_ids IN (' . $cats . ')');
		$coll->setOrder('created_at', 'desc');
		//
		$prod_ids = array();
		$returnVal = array('designs' => array());
		$i = 0;
		foreach ($coll as $product) {
			if ($i >= $count) {
				break;
			}
			$returnVal['designs']['' . $product->getId()] = $this->getProduct($product->getId(), true, false, false);
			$prod_ids[] = $product->getId();
			$i++;
		}
		$designUses = $this->getDesignUses($prod_ids);
		foreach ($designUses as $key => $value) {
			$returnVal['designs'][$key]['designUses'] = $value;
		}
		return $returnVal;
	}
    //
    public function getFeaturedCollections() {
        return $this->loadCache(self::$cacheLongTimeSpan)->__getFeaturedCollections();
    }
    public function __getFeaturedCollections($count = 0) {

		$categoryId = $this->getCategoryIdByName("Designers");
		$category = new Mage_Catalog_Model_Category();
        $category->load($categoryId);

		$visibility = array(
			Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
			Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
		);

		$storeId = Mage::app()->getStore()->getId();

		$cats = $category->getAllChildren();
		if ($cats != "") {
			$cats = "0," . $cats;
		} else {
			$cats = "0";
		}

        $coll = Mage::getResourceModel('reports/product_collection')
				->addAttributeToFilter('visibility', $visibility)
				->addAttributeToFilter('featured_design', 1);
        $coll->getSelect()->where('category_ids IN (' . $cats . ')');
		$coll->setOrder('created_at', 'desc');

        $prod_ids = array();
		$returnVal = array('collections' => array());
		$i = 0;
        $designerInfos = array();

		foreach ($coll as $prod) {
            $theProd = $this->getProduct($prod->getId(), true, false, false);
            $catid = $theProd['categories'][0]['entity_id'];
			$_category = Mage::getModel('catalog/category')->load($catid);
            $child = $_category->toArray();
            //

			$child['designer'] = $this->getDesignerInfo($catid, true);

			$subs = $_category->getProductCollection()->addAttributeToFilter('visibility', $visibility)->addAttributeToSelect('name');

			if (count($subs) == 0) {
				continue;
			}

			$child['products'] = array();

			foreach ($subs as $product) {
				$imageInfo = $this->getProductImageInfo($product->getId());
				if (array_key_exists('file', $imageInfo) == true) {
					$imgUrl = $imageInfo['file'];
				} else {
					$imgUrl = '';
				}
				if ($imgUrl != '') {
					$child['products'][] = array('entity_id' => $product->getId(),
								'name' => $product->getName(),
								'image' => $imgUrl);
				}
			}


			$subs->addAttributeToFilter('category_image', 1);

			foreach ($subs as $product) {
				$imageInfo = $this->getProductImageInfo($product->getId());
				if (array_key_exists('file', $imageInfo) == true) {
					$imgUrl = $imageInfo['file'];
				} else {
					$imgUrl = '';
				}
				if ($imgUrl != '') {
					$child['category_image_product'] = array('entity_id' => $product->getId(),
								'name' => $product->getName(),
								'image' => $imgUrl);
					break;
				}
			}

			if (array_key_exists('category_image_product',$child) == false) {
				$child['category_image_product'] = array('entity_id' => $child['products'][0]['entity_id'],
								'name' => $child['products'][0]['name'],
								'image' => $child['products'][0]['image']);
			}

			$returnVal['collections'][] = $child;
        }
        
		return $returnVal;
    }
	
	//get Category image
	public function getCategoryImage($cid){
		$designerObj = new Designer_Profile_Block_View();
		$rsCatImage = $designerObj->getCategoryImage($cid);
		if(isset($rsCatImage["small_image"]) && $rsCatImage["small_image"]!=''){
			return $rsCatImage["small_image"];
		}else{
			$imgUrl = '';
			$rsCatFirstPID = $designerObj->getFirstDesignOfCollection($cid);
			if (count($rsCatFirstPID ) > 0) {
				$rsCatFirstPID = $rsCatFirstPID[0];
				$rsCatData = $designerObj->getDesignInfo($rsCatFirstPID['product_id']);
				if(isset($rsCatData["small_image"]))
					$imgUrl =  $rsCatData["small_image"];
				else
					$imgUrl = "/default/small_image/small_image.jpg";				
			}
			else {
				$imgUrl = "/default/small_image/small_image.jpg";
			}
			return $imgUrl;
		}
	}
	//
	public function getCollectionsByDesigner($designerId, $includeImagesForSlideshow=false) {
        return $this->loadCache()->__getCollectionsByDesigner($designerId, $includeImagesForSlideshow);
    }
	public function __getCollectionsByDesigner($designerId, $includeImagesForSlideshow=false) {
		$designerId = intval($designerId);
		if ($designerId == 0) {
			throw new Exception(self::$invalidDesignerId);
		}
		$prefixForLocalTable = self::$prefixForLocalTable;
		$sqlSelect = " Select CID from " . $prefixForLocalTable . "_designer_category where " .
					" DID = " . $designerId;
		$rs = mysql_query($sqlSelect);
		$numResults = mysql_num_rows($rs);
		if ($numResults == 0) {
			throw new Exception(self::$invalidDesignerId);
		}
		$rowTag = mysql_fetch_row($rs);
		$categoryId = $rowTag[0];
		$tree = $this->getCategoryById($categoryId);

		$designerInfo = $this->getDesignerInfo($categoryId, true);

		$returnVal = array('collections' => array());
		foreach ($tree['children'] as $child) {
			$catid = $child['entity_id'];
			$_category = Mage::getModel('catalog/category')->load($catid);



			$visibility = array(
						  Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
						  Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
					  );
			$child['designer'] = $designerInfo;
			$subs = $_category->getProductCollection()->addAttributeToFilter('visibility', $visibility)->addAttributeToSelect('name');
			
			if (count($subs) == 0) {
				continue;
			}

			$child['products'] = array();
			
			foreach ($subs as $product) {
				$imageInfo = $this->getProductImageInfo($product->getId());
				if (array_key_exists('file', $imageInfo) == true) {
					$imgUrl = $imageInfo['file'];
				} else {
					$imgUrl = '';
				}
				if ($imgUrl != '') {
					$child['products'][] = array('entity_id' => $product->getId(),
								'name' => $product->getName(),
								'image' => $imgUrl);
				}
			}
			

			$subs->addAttributeToFilter('category_image', 1);

			foreach ($subs as $product) {
				$imageInfo = $this->getProductImageInfo($product->getId());
				if (array_key_exists('file', $imageInfo) == true) {
					$imgUrl = $imageInfo['file'];
				} else {
					$imgUrl = '';
				}
				if ($imgUrl != '') {
					$child['category_image_product'] = array('entity_id' => $product->getId(),
								'name' => $product->getName(),
								'image' => $imgUrl);
					break;
				}
			}

			if (array_key_exists('category_image_product',$child) == false) {
				//print_r($child);
				/* $child['category_image_product'] = array('entity_id' => $child['products'][0]['entity_id'],
								'name' => $child['products'][0]['name'],
								'image' => $child['products'][0]['image']); */								
			
			}
			//get category image
			$child['categoryimage']= $this->getCategoryImage($catid);
			
			$returnVal['collections'][] = $child;
		}
		
		return $returnVal;
	}
	//
	public function getDesignsByCollection($collectionId,$drep=1,$dpat=1,$dport=0,$ua=0, $sku='') {
		$collectionId = intval($collectionId);
		if ($collectionId == 0) {
			throw new Exception(self::$invalidCollectionId);
		}
		$tree = $this->getProducts($collectionId,$drep,$dpat,$dport,$ua,$sku);
		$returnVal = array();
		$returnVal['designs'] = $tree['products'];
		return $returnVal;
	}
    public function getCollectionId($productId) {

    }
	//
	public function getUserWishlists() {
		if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		}
		$UID = $this->getCustomerId();
		$prefixForMagTable = self::$prefixForMagTable;
		$sqlSelect = "SELECT `event_id`, `customer_id`, `address_id`, `type_id`, `status`, " .
			" `sharing_code`, `date`, `search_allowed`, `pass`, `title`, `fname`, `lname`, `fname2`, " .
			" `lname2`, `emails` FROM `".$prefixForMagTable."_adjgiftreg_event` " .
			" WHERE customer_id = " . $UID . " LIMIT 0, 1000" ;
		mysql_query($sqlSelect);
	}
	public function getWishlistTypes() {
		$sqlSelect = "SELECT `type_id`, `pos`, `hide`, `code` " .
			 " FROM `".$prefixForMagTable."rbym_adjgiftreg_type` LIMIT 0, 1000 ;";
		mysql_query($sqlSelect);
		
	}
	//
	public function getRoomImageInfo($roomId) {
        return $this->loadCache(self::$cacheShortTimeSpan)->getRoomById($roomId);
    }
	
	 //decode encrypted roomid to actual roomid
	public function decode($string,$key) {    
		$key = sha1($key);
		$strLen = strlen($string);
		$keyLen = strlen($key);
		$j=0;$hash='';
		for ($i = 0; $i < $strLen; $i+=2) {
			$ordStr = hexdec(base_convert(strrev(substr($string,$i,2)),36,16));
			if ($j == $keyLen) { $j = 0; }
			$ordKey = ord(substr($key,$j,1));
			$j++;
			$hash .= chr($ordStr - $ordKey);
		}
		return base64_decode(base64_decode(base64_decode(base64_decode($hash))));
	}
	
	public function getPrecustomizedRoomByEncryptedId($roomId) {
        //call decode() for actual roomid
		$returnVal = $this->getPrecustomizedRoomById($this->decode($roomId,"Rooms By You, Inc. Customizer"));
		return $returnVal;
    }
	
	public function getPrecustomizedRoomById($roomId = 0) {
        $rooms = $this->getPrecustomizedRooms($roomId);
		$returnVal = array();
		if (count($rooms['rooms']) > 0) {
			$returnVal['room'] = $rooms['rooms'][0];
		} else {
			$returnVal['room'] = array();
		}
		return $returnVal;
    }
	
    public function getPrecustomizedRooms($roomId = 0, $contentType='', $roomType='', $roomStyle='') {
		$roomId = intval($roomId);
		$prefixForLocalTable = self::$prefixForLocalTable;
		$prefixForMagTable = self::$prefixForMagTable;
		
		$sqlSelect = " Select room_id, user_id, sharing_code, image_path, " .
					" thumbnail_path, created_at, updated_at, is_shared, data, room_title, room_comment, " .
                    " is_precustomized, roomtype, roomstyle, roomview, content_type, design_id, product_id from " .
					$prefixForMagTable ."_customizer_data where " .
					$prefixForMagTable ."_customizer_data.is_archive = 0 and " .
					$prefixForMagTable ."_customizer_data.is_precustomized > 0";
		if ($roomId!=0) {
			$sqlSelect .= " and " . $prefixForMagTable ."_customizer_data.room_id = " . $roomId;
		}
		if ($contentType!='') {
			$sqlSelect .= " and " . $prefixForMagTable ."_customizer_data.content_type = '" . $contentType . "'";
		}
		if ($roomType!='') {
			$sqlSelect .= " and " . $prefixForMagTable ."_customizer_data.roomtype = '" . $roomType . "'";
		}
		if ($roomStyle!='') {
			$sqlSelect .= " and " . $prefixForMagTable ."_customizer_data.roomstyle = '" . $roomStyle . "'";
		}

		$rs = mysql_query($sqlSelect);
		$numResults = mysql_num_rows($rs);
		$returnVal = array();
		$returnVal['rooms'] = array();
		for($k=0; $k < $numResults; $k++)
		{
			$rowTag = mysql_fetch_row($rs);
			$item = array();
			$item['room_id'] = $rowTag[0];
			$item['user_id'] = $rowTag[1];
			$item['image_path'] = $rowTag[3];
			$item['thumbnail_path'] = $rowTag[4];
			$item['created_at'] = $rowTag[5];
			$item['updated_at'] = $rowTag[6];
			$item['is_shared'] = $rowTag[7];
            if ($roomId!=0) {
                $item['data'] = $rowTag[8];
            }
			$item['room_title'] = $rowTag[9];
			$item['room_comment'] = $rowTag[10];
            $item['collectionId'] = $rowTag[11];
            $item['collectionName'] = $this->getCategoryName($rowTag[11]);
            $item['roomtype'] = $rowTag[12];
            $item['roomstyle'] = $rowTag[13];
            $item['roomview'] = $rowTag[14];
			$item['contenttype'] = $rowTag[15];			
			$item['designid'] = $rowTag[16];
			$item['productid'] = $rowTag[17];
			$item['designerName'] = $this->getDesignerNameByCollectionId($rowTag[11]);
			$returnVal['rooms'][] = $item;
		}

		return $returnVal;
	}
	
	public function getDesignerNameByCollectionId($collectionId) {
		$category = Mage::getModel("catalog/category")->load($collectionId);						
		$designer = Mage::getModel('catalog/category')->load($category->getParentId());
		return $designer->getName();
	}
	
	public function getSharedRoomById($roomId) {
		$roomId = intval($roomId);
        if ($roomId == 0) {
            throw new Exception(self::$notYourRoom);
        }
		$rooms = $this->getSharedRooms($roomId);
		$returnVal = array();
		if (count($rooms['rooms']) > 0) {
			$returnVal['room'] = $rooms['rooms'][0];
		} else {
			throw new Exception(self::$notYourRoom);
		}
		return $returnVal;
	}
	public function getSharedRooms($roomId=0) {
		$roomId = intval($roomId);
		$prefixForLocalTable = self::$prefixForLocalTable;
		$prefixForMagTable = self::$prefixForMagTable;
		//getAttributes
		if (Mage::getSingleton( 'customer/session' )->isLoggedIn() == false) {
			return 0;
		}
		$sqlSelect = "Select " . $prefixForMagTable . "_customizer_data_shared.id, " .
					$prefixForMagTable . "_customizer_data_shared.room_id, " .
					$prefixForMagTable . "_customizer_data_shared.shared_on, " .
					$prefixForMagTable . "_customizer_data_shared.friends_email, " .
					$prefixForMagTable . "_customizer_data_shared.personal_message, " .
					$prefixForMagTable ."_customizer_data.user_id, " .
					$prefixForMagTable ."_customizer_data.sharing_code, " .
					$prefixForMagTable ."_customizer_data.image_path, " .
					$prefixForMagTable ."_customizer_data.thumbnail_path, " .
					$prefixForMagTable ."_customizer_data.created_at, " .
					$prefixForMagTable ."_customizer_data.updated_at, " .
					$prefixForMagTable ."_customizer_data.is_shared, " .
					$prefixForMagTable ."_customizer_data.data, " .
					$prefixForMagTable ."_customizer_data.room_title, " .
					$prefixForMagTable ."_customizer_data.room_comment, " .
					$prefixForMagTable ."_customizer_data.roomtype, " .
					$prefixForMagTable ."_customizer_data.roomstyle, " .
					$prefixForMagTable ."_customizer_data.roomview, " .
					$prefixForMagTable ."_customizer_data.content_type, " .
					$prefixForMagTable ."_customizer_data.design_id, " .
					$prefixForMagTable ."_customizer_data.product_id " .
					" from " . $prefixForMagTable . "_customizer_data_shared, " . $prefixForMagTable ."_customizer_data" .
					" where " . $prefixForMagTable ."_customizer_data.room_id=" . $prefixForMagTable . "_customizer_data_shared.room_id " .
					" and " . $prefixForMagTable . "_customizer_data_shared.friends_email ='".$this->getCustomerEmail()."'".
					" and " . $prefixForMagTable . "_customizer_data_shared.is_active=1".
					" and " . $prefixForMagTable . "_customizer_data_shared.is_hidden=0";
		if ($roomId!=0) {
			$sqlSelect .= " and " . $prefixForMagTable ."_customizer_data.room_id=".$roomId;
		}
		$rs = mysql_query($sqlSelect);
		//error_log("RBYService getSharedRooms: ".var_export($sqlSelect),0);
		$numResults = mysql_num_rows($rs);
		$returnVal = array();
		$returnVal['rooms'] = array();
		//$homeUrl = Mage::helper( 'core/url' )->getHomeUrl();
		for ($i=0; $i<$numResults; $i++) {
			$rowTag = mysql_fetch_row($rs);
			/*if ($row[1] != "") {
				$designerImg = $homeUrl . self::$designerImageFolder . $row[1];
			} else {
				$designerImg = $homeUrl . self::$designerImageFolder . self::$defaultDesignerImage;
			}
			$returnVal['' . $row[0]] = $designerImg;*/

			$item = array();
			$item['sharing_id'] = $rowTag[0];
			$item['room_id'] = $rowTag[1];
			$item['shared_on'] = $rowTag[2];
			$item['friends_email'] = $rowTag[3];
			$item['personal_message'] = htmlspecialchars($rowTag[4]);
			$item['user_id'] = $rowTag[5];
			$getName = $this->getCustomerNameById($rowTag[5],Mage::getSingleton( 'customer/session' )->getCustomerId());
			$item['user_fullname'] = htmlspecialchars($getName['CustomerName']);
			$item['sharing_code'] = ""; // $rowTag[6]; Prevent this info to go to end user
			$item['image_path'] = $rowTag[7];
			$item['thumbnail_path'] = $rowTag[8];
			$item['created_at'] = $rowTag[9];
			$item['created_at'] = $rowTag[10];
			$item['is_shared'] = $rowTag[11];
			$item['data'] = $rowTag[12];
			$item['room_title'] = htmlspecialchars($rowTag[13]);
			$item['room_comment'] = htmlspecialchars($rowTag[14]);
			$item['roomtype'] = $rowTag[15];
			$item['roomstyle'] = $rowTag[16];
			$item['roomview'] = $rowTag[17];
			$item['contenttype'] = $rowTag[18];			
			$item['designid'] = $rowTag[19];
			$item['productid'] = $rowTag[20];
			$returnVal['rooms'][] = $item;
		}
        return $returnVal;
	}
	public function getSharedWithEmailsByRoomId($roomId) {
		if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		}
		$roomId = intval($roomId);
		if ($roomId == 0) {
			throw new Exception(self::$notYourRoom);
		}
		$prefixForLocalTable = self::$prefixForLocalTable;
		$prefixForMagTable = self::$prefixForMagTable;
		$sqlSelect = "Select " . $prefixForMagTable . "_customizer_data_shared.id, " .
					$prefixForMagTable . "_customizer_data_shared.room_id, " .
					$prefixForMagTable . "_customizer_data_shared.shared_on, " .
					$prefixForMagTable . "_customizer_data_shared.friends_email, " .
					$prefixForMagTable . "_customizer_data_shared.personal_message, " .
					$prefixForMagTable ."_customizer_data.user_id, " .
					$prefixForMagTable ."_customizer_data.sharing_code, " .
					$prefixForMagTable ."_customizer_data.image_path, " .
					$prefixForMagTable ."_customizer_data.thumbnail_path, " .
					$prefixForMagTable ."_customizer_data.created_at, " .
					$prefixForMagTable ."_customizer_data.updated_at, " .
					$prefixForMagTable ."_customizer_data.is_shared, " .
					$prefixForMagTable ."_customizer_data.data, " .
					$prefixForMagTable ."_customizer_data.room_title, " .
					$prefixForMagTable ."_customizer_data.room_comment " .
					" from " . $prefixForMagTable . "_customizer_data_shared, " . $prefixForMagTable ."_customizer_data" .
					" where " . $prefixForMagTable ."_customizer_data.room_id=" . $prefixForMagTable . "_customizer_data_shared.room_id ".
					" and " . $prefixForMagTable ."_customizer_data.room_id=".$roomId.
					" and " . $prefixForMagTable . "_customizer_data_shared.is_active=1";
		$rs = mysql_query($sqlSelect);
		$numResults = mysql_num_rows($rs);
		$returnVal = array();
		$returnVal['sharedwith'] = array();
		for ($i=0; $i<$numResults; $i++) {
			$rowTag = mysql_fetch_row($rs);
			$item = array();
			$item['sharing_id'] = $rowTag[0];
			$item['room_id'] = $rowTag[1];
			$item['shared_on'] = $rowTag[2];
			$item['friends_email'] = $rowTag[3];
			$returnVal['sharedwith'][] = $item;
		}
        return $returnVal;
	}
	public function isRoomSharedWithEmailId($roomId,$emailId) {
		if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		}
		$roomId = intval($roomId);
		if ($roomId == 0) {
			throw new Exception(self::$notYourRoom);
		}
		$prefixForLocalTable = self::$prefixForLocalTable;
		$prefixForMagTable = self::$prefixForMagTable;
		$sqlSelect = "Select " . $prefixForMagTable . "_customizer_data_shared.id, " .
					$prefixForMagTable . "_customizer_data_shared.room_id, " .
					$prefixForMagTable . "_customizer_data_shared.shared_on, " .
					$prefixForMagTable . "_customizer_data_shared.friends_email, " .
					$prefixForMagTable . "_customizer_data_shared.personal_message, " .
					$prefixForMagTable ."_customizer_data.user_id, " .
					$prefixForMagTable ."_customizer_data.sharing_code, " .
					$prefixForMagTable ."_customizer_data.image_path, " .
					$prefixForMagTable ."_customizer_data.thumbnail_path, " .
					$prefixForMagTable ."_customizer_data.created_at, " .
					$prefixForMagTable ."_customizer_data.updated_at, " .
					$prefixForMagTable ."_customizer_data.is_shared, " .
					$prefixForMagTable ."_customizer_data.data, " .
					$prefixForMagTable ."_customizer_data.room_title, " .
					$prefixForMagTable ."_customizer_data.room_comment " .
					" from " . $prefixForMagTable . "_customizer_data_shared, " . $prefixForMagTable ."_customizer_data" .
					" where " . $prefixForMagTable ."_customizer_data.room_id=" . $prefixForMagTable . "_customizer_data_shared.room_id ".
					" and " . $prefixForMagTable ."_customizer_data.room_id=".$roomId.
					" and " . $prefixForMagTable . "_customizer_data_shared.friends_email=\"".$emailId."\"".
					" and " . $prefixForMagTable . "_customizer_data_shared.is_active=1";
		$rs = mysql_query($sqlSelect);
		$numResults = mysql_num_rows($rs);
		/* $returnVal = array();
		$returnVal['sharedwith'] = array();
		for ($i=0; $i<$numResults; $i++) {
			$rowTag = mysql_fetch_row($rs);
			$item = array();
			$item['sharing_id'] = $rowTag[0];
			$item['room_id'] = $rowTag[1];
			$item['shared_on'] = $rowTag[2];
			$item['friends_email'] = $rowTag[3];
			$returnVal['sharedwith'][] = $item;
		}
        return $returnVal; */
		return $numResults;
	}
	public function unshareRoomWithEmailId($roomId, $email, $scode) {
		if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		}
		$roomId = intval($roomId);
		if ($roomId == 0 || $email == "") {
			throw new Exception(self::$notYourRoom);
		}
		$prefixForLocalTable = self::$prefixForLocalTable;
		$prefixForMagTable = self::$prefixForMagTable;
		$sqlSelect = "Select " . $prefixForMagTable . "_customizer_data_shared.id, " .
					$prefixForMagTable . "_customizer_data_shared.room_id, " .
					$prefixForMagTable . "_customizer_data_shared.shared_on, " .
					$prefixForMagTable . "_customizer_data_shared.friends_email, " .
					$prefixForMagTable . "_customizer_data_shared.personal_message, " .
					$prefixForMagTable ."_customizer_data.user_id, " .
					$prefixForMagTable ."_customizer_data.sharing_code, " .
					$prefixForMagTable ."_customizer_data.image_path, " .
					$prefixForMagTable ."_customizer_data.thumbnail_path, " .
					$prefixForMagTable ."_customizer_data.created_at, " .
					$prefixForMagTable ."_customizer_data.updated_at, " .
					$prefixForMagTable ."_customizer_data.is_shared, " .
					$prefixForMagTable ."_customizer_data.data, " .
					$prefixForMagTable ."_customizer_data.room_title, " .
					$prefixForMagTable ."_customizer_data.room_comment " .
					" from " . $prefixForMagTable . "_customizer_data_shared, " . $prefixForMagTable ."_customizer_data" .
					" where " . $prefixForMagTable ."_customizer_data.room_id=" . $prefixForMagTable . "_customizer_data_shared.room_id " .
					" and " . $prefixForMagTable . "_customizer_data_shared.friends_email ='".$email."'".
					" and " . $prefixForMagTable ."_customizer_data.room_id=".$roomId.
					" and " . $prefixForMagTable . "_customizer_data_shared.is_active=1".
					" and " . $prefixForMagTable . "_customizer_data_shared.id=".$scode;
		$rs = mysql_query($sqlSelect);
		$numResults = mysql_num_rows($rs);
		$returnVal = array();
		if ($numResults>0) {
			$sqlUpdate = "Update " . $prefixForMagTable . "_customizer_data_shared " .
					" SET " . $prefixForMagTable . "_customizer_data_shared.is_active=0".
					" Where " . $prefixForMagTable . "_customizer_data_shared.friends_email ='".$email."'".
					" and " . $prefixForMagTable . "_customizer_data_shared.room_id=".$roomId.
					" and " . $prefixForMagTable . "_customizer_data_shared.is_active=1".
					" and " . $prefixForMagTable . "_customizer_data_shared.id=".$scode;
			mysql_query($sqlUpdate);
            if (mysql_errno() != 0) {
                throw new Exception(mysql_error());
            }
			$item = array();
			$item['unshare_comment'] = "success";
			$item['room_id'] = $roomId;
			$returnVal['rooms'][] = $item;
		} else {
			throw new Exception(self::$notYourRoom);
		}
	}
	public function getRoomById($roomId) {
		$roomId = intval($roomId);
        if ($roomId == 0) {
            throw new Exception(self::$notYourRoom);
        }
		$rooms = $this->getRooms($roomId);
		$returnVal = array();
		if (count($rooms['rooms']) > 0) {
			$returnVal['room'] = $rooms['rooms'][0];
		} else {
			throw new Exception(self::$notYourRoom);
		}
		return $returnVal;
	}
	public function getRooms($roomId = 0) {
		$roomId = intval($roomId);
		if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		}
		$UID = $this->getCustomerId();
		$prefixForMagTable = self::$prefixForMagTable;
		$sqlSelect = " Select room_id, user_id, sharing_code, image_path, " .
					" thumbnail_path, created_at, updated_at, is_shared, data, room_title, room_comment, roomtype, roomstyle, roomview , content_type, design_id, product_id from " .
					$prefixForMagTable ."_customizer_data where " .
					$prefixForMagTable ."_customizer_data.user_id =". $UID ."";
		//$sqlSelect .= " and " . $prefixForMagTable ."_customizer_data.content_type = '" . $content_type ."'";
		
		if ($roomId!=0) {
			$sqlSelect .= " and " . $prefixForMagTable ."_customizer_data.room_id = " . $roomId;
		}
		
		$rs = mysql_query($sqlSelect);
		$numResults = mysql_num_rows($rs);
		$returnVal = array();
		$returnVal['rooms'] = array();
		for($k=0; $k < $numResults; $k++)
		{
			$rowTag = mysql_fetch_row($rs);
			$item = array();
			$item['room_id'] = $rowTag[0];
			$item['user_id'] = $rowTag[1];
			$item['sharing_code'] = $rowTag[2];
			$item['image_path'] = $rowTag[3];
			$item['thumbnail_path'] = $rowTag[4];
			$item['created_at'] = $rowTag[5];
			$item['updated_at'] = $rowTag[6];
			$item['is_shared'] = $rowTag[7];
			$item['data'] = $rowTag[8];
			$item['room_title'] = $rowTag[9];
			$item['room_comment'] = $rowTag[10];
			$item['roomtype'] = $rowTag[11];
			$item['roomstyle'] = $rowTag[12];
			$item['roomview'] = $rowTag[13];
			$item['contenttype'] = $rowTag[14];			
			$item['designid'] = $rowTag[15];
			$item['productid'] = $rowTag[16];
			$returnVal['rooms'][] = $item;
		}
		
		return $returnVal;
	}
	//
	public function getRoomImage($roomId, $label="large", $noredirect = true) {
		$roomId = intval($roomId);
        if ($roomId == 0) {
            throw new Exception(self::$notYourRoom);
        }
		$roomInfo = $this->getRoomImageInfo($roomId);
        $room = $roomInfo['room'];
		if ($label != "thumbnail") {
			$key = "image_path";
		} else {
			$key = "thumbnail_path";
		}
		if (array_key_exists($key, $room) == false) {
			header("HTTP/1.0 404 Not Found");
			header("Content-Type: image/jpeg");
			die();
		}
		if ($room[$key] == "") {
			header("HTTP/1.0 404 Not Found");
			header("Content-Type: image/jpeg");
			die();
		}
		if ($noredirect == false) {
			header("Cache-Control: max-age=3600"); // HTTP/1.1
			header("HTTP/1.1 301 Moved Permanently"); // moved forever
			header("Location:" . $room[$key]);
			die();
		}

		header("Content-Type: image/jpeg");
		header("Cache-Control: max-age=3600, private"); // HTTP/1.1
		$pinfo = realpath('.');
		$file = $pinfo . $room[$key];
		if (file_exists($file) == false) {
			header("HTTP/1.0 404 Not Found");
			die();
		}
		readfile($file);
		die();
		return $room;
	}
	//
    public function deleteRoom($roomId=0) {
        $roomId = intval($roomId);
        if ($roomId == 0) {
            throw new Exception(self::$notYourRoom);
        }
		$roomInfo = $this->getRoomImageInfo($roomId);
        $room = $roomInfo['room'];
		if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		}
		$UID = $this->getCustomerId();
        $room_id = $room['room_id'];
        $sqlDelete = 'DELETE FROM '. self::$prefixForMagTable .'_customizer_data WHERE room_id = ' . $room_id . ' and user_id = ' . $UID;
        mysql_query($sqlDelete);
        if (mysql_errno() != 0) {
            throw new Exception(mysql_error());
        }
		return array('room_id' => $roomId);
    }
	
	public function saveRoom($roomData="", $roomId=0, $roomImg=null, $room_title="", $room_comment="", $is_shared=0, $roomType="", $roomStyle="", $roomView=0, $contentType="Room", $designId=0, $productId=0, $price=0, $color=null) {
		
		//return $contentType." --- ".$roomId;
		$room_id = intval($roomId);
		$is_shared = intval($is_shared);
		$productId = intval($productId);
		$designId = intval($designId);
		$price = intval($price);
		$custId = 0;
		
		$custId = Mage::getSingleton( 'customer/session' )->getId();
		if($contentType=='3dproducts' && $room_id != 0 && $custId == 5){
			$productId = $this->getProductIdByRoomId($room_id);
		}
		
		if ($contentType=="1dproduct") {
			$UID = 0;
			//return  $roomData."-contentType-".$contentType."-room_title-".$room_title."-roomImg-".$roomImg."-designId-".$designId."-productId-".$productId;
		}
		else{
			if ($this->isLoggedIn() == false) {
				throw new Exception(self::$loginError);
			}
			$UID = $this->getCustomerId();
		}
		$prefixForLocalTable = self::$prefixForLocalTable;
		$prefixForMagTable = self::$prefixForMagTable;
		//
		if ($room_id == 0) {
			   $sqlInsert = " insert into " . $prefixForMagTable .
				"_customizer_data(user_id, sharing_code, data, created_at, is_shared, room_title, room_comment,roomtype,roomstyle,roomview, design_id, content_type, product_id, colors) values(" .
				$UID . ", '" . $UID . "-" . $this->getRandomString(8) . "', '" .
				mysql_real_escape_string($roomData) . "', '" .
				date("Y-m-d m:s") ."', ". mysql_real_escape_string($is_shared).", '".mysql_real_escape_string($room_title). "', ".
				"'".mysql_real_escape_string($room_comment)."', ".
				"'".$roomType."', '".$roomStyle."', ".$roomView.", ".$designId.", '".$contentType."', ".$productId.", '".mysql_real_escape_string($color). "');";
			try {
				mysql_query($sqlInsert);
			} catch (Exception $e) {
				throw new Exception("Error while saving room: ".$e->getMessage());
			}
			$theRoomId = mysql_insert_id();
		} else {
			 $sqlUpdate = " update " . $prefixForMagTable ."_customizer_data " .
				" SET data = '" . mysql_real_escape_string($roomData) .
				"', is_shared = '" . mysql_real_escape_string($is_shared) .
				"', room_title = '" . mysql_real_escape_string($room_title) .
				"', room_comment = '" . mysql_real_escape_string($room_comment) .
				"', updated_at='". date("Y-m-d m:s") .
				"', roomtype='". $roomType .
				"', roomstyle='". $roomStyle .
				"', roomview=". $roomView .
				", content_type='". $contentType .
				"', design_id=". $designId .
				", product_id=". $productId .
				", colors='". $color .
				"' WHERE room_id = " . $room_id . ";";
            mysql_query($sqlUpdate);
            if (mysql_errno() != 0) {
                throw new Exception(mysql_error());
            }
			$theRoomId = $room_id;
		}
		//
		
		if ($room_id == 0) {
			// set roomid in the xml itself
			// and set the sharing_code for this room
			$roomData = str_replace('<roomid>0</roomid>', '<roomid>'.$theRoomId.'</roomid><userid>'.$UID.'</userid>', $roomData);
			$theSharingCode = strtoupper( $UID . $theRoomId . "-" . $this->getRandomString(8) );
			$sqlUpdate = " UPDATE " . $prefixForMagTable ."_customizer_data "
				." SET data = '" . mysql_real_escape_string($roomData) . "',".
				" sharing_code = '" . $theSharingCode . "' " .
				" WHERE room_id = " . $theRoomId . ";";
			mysql_query($sqlUpdate);
		}
		
		// add 2dproducts code
		/* if ($productId > 0) {
			// set values when saving 2D product
			$sqlUpdate = " UPDATE " . $prefixForMagTable ."_customizer_data "
				." SET content_type = '" . $contentType . "',".
				" product_id = " . $productId . "," .
				" WHERE room_id = " . $theRoomId . ";";
			mysql_query($sqlUpdate);
		} */
		//end add 2dproducts code
		
		if ( isset ( $_FILES['image'] ))
		{
            
			if ($room_id == 0) {
				$fileName = strtoupper( $UID . $theRoomId . "-" . $this->getRandomString(8) );
				$fullFilePath = self::$usersRoomImageFolder . $fileName;
				$image_path = $fullFilePath . '_img.jpg';
				$thumbnail_path = $fullFilePath . '_thm.jpg';
			} else {
				$sqlGet = " SELECT image_path, thumbnail_path FROM " . $prefixForMagTable ."_customizer_data " .
				" WHERE room_id = " . $theRoomId . ";";
				$rs = mysql_query($sqlGet);
				$row = mysql_fetch_row($rs);

				$image_path = $row[0];
				$thumbnail_path = $row[1];
			}
			//$im = $roomImg;
			//$data = $GLOBALS["HTTP_RAW_POST_DATA"];
			//$im = $data;
			//$filename=$theRoomId . '-' . $UID;
			$path = realpath('.');
			$fullFilePath = $path . $image_path;
//			$handle=fopen($fullFilePath,"w");
//			fwrite($handle,$im);
//			fclose($handle);
            // create thumbnail:
            $thumbnail = new ThumbnailImage($_FILES['image']['tmp_name'], 260, 260, true, 75);
            // move orignal uploaded file:
            move_uploaded_file($_FILES['image']['tmp_name'] , $fullFilePath);
			// save thumbnail
			$fullFilePath = $path . $thumbnail_path;
			$thumbnail->saveTo($fullFilePath, 60);
		}
		
		$sqlUpdate = " UPDATE " . $prefixForMagTable ."_customizer_data "
			." SET image_path = '".mysql_real_escape_string($image_path)."', " .
			"thumbnail_path = '".mysql_real_escape_string($thumbnail_path)."' " .
			" WHERE room_id = " . $theRoomId . ";";
		
		mysql_query($sqlUpdate);
		
		//when pre-customized product then add a new product
		if($contentType=='3dproducts'):
			if ($this->isLoggedIn() != false) {			
				$customerId = Mage::getSingleton( 'customer/session' )->getId();
				if ($customerId == 5) {			
					//add new 3d product
					if ($room_id == 0){
						$fullFilePath = realpath('.') . $image_path;
						$thumbnailPath = realpath('.') . $thumbnail_path;
						$returnPID = $this->addPreCustomizedProduct($theRoomId, $room_title, $room_comment, $fullFilePath,$thumbnailPath, $price, $roomType, $roomStyle, $roomData, $color);
						$sqlUpdate = " UPDATE " . $prefixForMagTable ."_customizer_data "
						." SET product_id = " . $returnPID . " WHERE room_id = " . $theRoomId . ";";
						mysql_query($sqlUpdate);
					}//update new 3d product
					else{
						if($productId > 0)
							$this->update3dProduct($productId, $color, $room_title, $room_comment);
					}
				}
			}
		endif;
		//end when pre-customized product then add a new product
		
		$sqlSelect = " Select room_id, user_id, sharing_code, image_path, " .
					" thumbnail_path, created_at, updated_at, is_shared, room_title, room_comment, roomtype, roomstyle, roomview, content_type, design_id, product_id, colors from " .
				$prefixForMagTable ."_customizer_data where room_id = " . $theRoomId . " LIMIT 1;";
		$rs = mysql_query($sqlSelect);

		$numResults = mysql_num_rows($rs);
		$returnVal = array();
		$rowTag = mysql_fetch_row($rs);
		
		$item = array();
		$item['room_id'] = $rowTag[0];
		$item['user_id'] = $rowTag[1];
		$item['sharing_code'] = $rowTag[2];
		$item['image_path'] = $rowTag[3];
		$item['thumbnail_path'] = $rowTag[4];
		$item['created_at'] = $rowTag[5];
		$item['updated_at'] = $rowTag[6];
		$item['is_shared'] = $rowTag[7];
		$item['room_title'] = $rowTag[8];
		$item['room_comment'] = $rowTag[9];
		$item['roomtype'] = $rowTag[10];
		$item['roomstyle'] = $rowTag[11];
		$item['roomview'] = $rowTag[12];
		$item['contenttype'] = $rowTag[13];
		$item['designid'] = $rowTag[14];
		$item['productid'] = $rowTag[15];
		$item['color'] = $rowTag[16];
		$returnVal['room'] = $item;		
		
		return $returnVal;
	}
	//
	public function shareRoomWithEmailIds($roomId, $friendsEmail1="",$friendsEmail2="", $friendsEmail3="", $friendsEmail4="", $friendsEmail5="", $personalMessage="") {
		$room_id = intval($roomId);
		if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		}
		$UID = $this->getCustomerId();
		$prefixForLocalTable = self::$prefixForLocalTable;
		$prefixForMagTable = self::$prefixForMagTable;
		//
		$roomDetails = $this->getRooms($room_id);
		//$returnVal['rooms']
		if (!$roomDetails) {
			throw new Exception(self::$notYourRoom);
		}
		if ($friendsEmail1=="" && $friendsEmail2=="" && $friendsEmail3=="" &&
			$friendsEmail4=="" && $friendsEmail5=="" ) {
			throw new Exception(self::$invalidShareWithList);
		}
		//$UID = $this->getCustomerId();
		// Create sendEmail variables
		//$toEmail = $friendsEmail1;
		//$toEmail, $fromEmail, $subject, $body, $fromName, $toName

		$fromEmail = "no-reply@roomsbyyou.com";
		$subject = $this->getCustomerName()." has shared a room with you.";
		$body = $personalMessage."<br/><br/>Cheers";
		$fromName = $this->getCustomerName();
		$templateCode ="shareroomwithfriend";
		$rd = $roomDetails['rooms'][0];
		$item = array();
		$item['customerName'] = $fromName;
		$item['roomPath'] = "http://www.roomsbyyou.com/customizer";
		$item['thumbSource'] = $rd['image_path'];
		$item['roomTitle'] = $rd['room_title'];
		$item['contenttype'] = $rd['contenttype'];
		$item['personalMessage'] = $personalMessage;
		
		$mailBody='';
		if($item['contenttype']=='Room'){			
			$mailBody = "<center>".
					"<p>Hi,</p>".
					"<p>A friend has shared a room with you on RoomsByYou.com. You are invited to view " .
					$fromName."'s room, modify their room if you choose, save it and send it back to them with your changes. ".
					"You can also create your own customized room and share it with friends. ".
					"Click on the room shot below to view your friend's room. <br/><br/>". 
					"You have to login using this email id (your email address) and password to view rooms shared by your friend. ".
					"If you don't have an account at RoomsByYou, you'll need to create one using this email id and the password of your choice. ".
					"All rooms shared by your friends will appear under Shared Rooms side bar after you log in.<br/><br/>".
					"<a href='http://www.roomsbyyou.com/customizer/'><img src='http://www.roomsbyyou.com".$rd['image_path']."' border=0 /></a>".
					"<br/>".
					"<a href='http://www.roomsbyyou.com/customizer/'>".$rd['room_title']."</a><br />".
					$personalMessage."<br/>".
					"</p> ".
					"<p>Cheers,<br/>".
					"Rooms By You<br/>".
					"<a href='http://www.roomsbyyou.com/'>www.roomsbyyou.com</a></p>".
					"</center>";
		}
		if($item['contenttype']=='2dproduct'){
			$mailBody = "<center>".
					"<p>Hi,</p>".
					"<p>A friend has shared a 2dProduct with you on RoomsByYou.com. You are invited to view " .
					$fromName."'s 2dProduct, modify their 2dProduct if you choose, save it and send it back to them with your changes. ".
					"You can also create your own customized 2dProduct and share it with friends. ".
					"Click on the 2dProduct shot below to view your friend's 2dProduct. <br/><br/>". 
					"You have to login using this email id (your email address) and password to view 2dProducts shared by your friend. ".
					"If you don't have an account at RoomsByYou, you'll need to create one using this email id and the password of your choice. ".
					"All 2dProducts shared by your friends will appear under Shared 2dProducts side bar after you log in.<br/><br/>".
					"<a href='http://www.roomsbyyou.com/customizer/'><img src='http://www.roomsbyyou.com".$rd['image_path']."' border=0 /></a>".
					"<br/>".
					"<a href='http://www.roomsbyyou.com/customizer/'>".$rd['room_title']."</a><br />".
					$personalMessage."<br/>".
					"</p> ".
					"<p>Cheers,<br/>".
					"Rooms By You<br/>".
					"<a href='http://www.roomsbyyou.com/'>www.roomsbyyou.com</a></p>".
					"</center>";
		}
		if($item['contenttype']=='3dproducts'){
			$mailBody = "<center>".
					"<p>Hi,</p>".
					"<p>A friend has shared a 3dproducts with you on RoomsByYou.com. You are invited to view " .
					$fromName."'s 3dproducts, modify their 3dproducts if you choose, save it and send it back to them with your changes. ".
					"You can also create your own customized 3dproducts and share it with friends. ".
					"Click on the 3dproducts shot below to view your friend's 3dproducts. <br/><br/>". 
					"You have to login using this email id (your email address) and password to view 3dproducts shared by your friend. ".
					"If you don't have an account at RoomsByYou, you'll need to create one using this email id and the password of your choice. ".
					"All 3dproducts shared by your friends will appear under Shared 3dproducts side bar after you log in.<br/><br/>".
					"<a href='http://www.roomsbyyou.com/customizer/'><img src='http://www.roomsbyyou.com".$rd['image_path']."' border=0 /></a>".
					"<br/>".
					"<a href='http://www.roomsbyyou.com/customizer/'>".$rd['room_title']."</a><br />".
					$personalMessage."<br/>".
					"</p> ".
					"<p>Cheers,<br/>".
					"Rooms By You<br/>".
					"<a href='http://www.roomsbyyou.com/'>www.roomsbyyou.com</a></p>".
					"</center>";
		}
		$success1 = true;
		$success2 = true;
		$success3 = true;
		$success4 = true;
		$success5 = true;
		if ($friendsEmail1!="") {
			$checkShare = $this->isRoomSharedWithEmailId($room_id, $friendsEmail1);
			if ( !$checkShare )
			$success1 = $this->shareRoomInsert($room_id, $friendsEmail1, $personalMessage);
			if ( $success1 )
			$this->sendEmail($friendsEmail1, $fromEmail, $templateCode, $item, $subject, $mailBody, $fromName);
		}
		if ($friendsEmail2!="" && $success1 && $success2 && $success3 && $success4 && $success5) {
			$checkShare = $this->isRoomSharedWithEmailId($room_id, $friendsEmail2);
			if ( !$checkShare )
			$success2 = $this->shareRoomInsert($room_id, $friendsEmail2, $personalMessage);
			if ( $success2 )
			$this->sendEmail($friendsEmail2, $fromEmail, $templateCode, $item, $subject, $mailBody, $fromName);
		}
		if ($friendsEmail3!="" && $success1 && $success2 && $success3 && $success4 && $success5) {
			$checkShare = $this->isRoomSharedWithEmailId($room_id, $friendsEmail3);
			if ( !$checkShare )
			$success3 = $this->shareRoomInsert($room_id, $friendsEmail3, $personalMessage);
			if ( $success3 )
			$this->sendEmail($friendsEmail3, $fromEmail, $templateCode, $item, $subject, $mailBody, $fromName);
		}
		if ($friendsEmail4!="" && $success1 && $success2 && $success3 && $success4 && $success5) {
			$checkShare = $this->isRoomSharedWithEmailId($room_id, $friendsEmail4);
			if ( !$checkShare )
			$success4 = $this->shareRoomInsert($room_id, $friendsEmail4, $personalMessage);
			if ( $success4 )
			$this->sendEmail($friendsEmail4, $fromEmail, $templateCode, $item, $subject, $mailBody, $fromName);
		}
		if ($friendsEmail5!="" && $success1 && $success2 && $success3 && $success4 && $success5) {
			$checkShare = $this->isRoomSharedWithEmailId($room_id, $friendsEmail5);
			if ( !$checkShare )
			$success5 = $this->shareRoomInsert($room_id, $friendsEmail5, $personalMessage);
			if ( $success5 )
			$this->sendEmail($friendsEmail5, $fromEmail, $templateCode, $item, $subject, $mailBody, $fromName);
		}

	
		if($success1 && $success2 && $success3 && $success4 && $success5) {
			return "Room successfully shared with your friends";
		} else {
			throw new Exception("Unable to share room with one or more friends.");
		}
	}

	public function shareRoomInsert($roomId,$frnd_email,$personal_message) {
		if ($this->isRoomSharedWithEmailId($roomId, $frnd_email))
			throw new Exception("Room already being shared with ".$frnd_email);
		try {
			$sqlInsert = "Insert into rbym_customizer_data_shared(room_id, shared_on, friends_email, personal_message)".
						"values(" . $roomId . ", '" . date('Y-m-d h:m:s') . "', '" .
						$frnd_email . "', '". mysql_escape_string($personal_message) ."')";
			mysql_query($sqlInsert);
		}
		catch (Exception $e) {
			throw new Exception("Error while allowing share: ".$e->getMessage());
			return false;
		}
		return true;
	}
	
	//Share 2D Product
	public function shareProductWithEmailIds($productId, $designSku, $friendsEmail1="",$friendsEmail2="", $friendsEmail3="", $friendsEmail4="", $friendsEmail5="", $personalMessage="") {
		$product_id = intval($productId);
		
		if ($this->isSavedProduct($productId, $designSku) < 1)
			throw new Exception("You must saved product in order to share!!!");
			
		if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		}
		$UID = $this->getCustomerId();
		$prefixForLocalTable = self::$prefixForLocalTable;
		$prefixForMagTable = self::$prefixForMagTable;
		//
		$productDetails = $this->getProduct($product_id);
		//$returnVal['rooms']
		if (!$productDetails) {
			throw new Exception(self::$notYourProduct);
		}
		if ($friendsEmail1=="" && $friendsEmail2=="" && $friendsEmail3=="" &&
			$friendsEmail4=="" && $friendsEmail5=="" ) {
			throw new Exception(self::$invalidShareWithList);
		}

		$fromEmail = "no-reply@roomsbyyou.com";
		$subject = $this->getCustomerName()." has shared a product with you.";
		$body = $personalMessage."<br/><br/>Cheers";
		$fromName = $this->getCustomerName();
		$templateCode ="shareproductwithfriend";
		//$rd = $productDetails['rooms'][0];
		$item = array();
		/* $item['customerName'] = $fromName;
		$item['roomPath'] = "http://www.roomsbyyou.com/customizer";
		$item['thumbSource'] = $rd['image_path'];
		$item['roomTitle'] = $rd['room_title']; */
		$item['personalMessage'] = $personalMessage;
		$mailBody = "<center>".
					"<p>Hi,</p>".
					"<p>A friend has shared a product with you on RoomsByYou.com. You are invited to view " .
					$fromName."'s product, modify their product if you choose, save it and send it back to them with your changes. ".
					"You can also create your own customized product and share it with friends. ".
					"Click on the product shot below to view your friend's product. <br/><br/>". 
					"You have to login using this email id (your email address) and password to view product shared by your friend. ".
					"If you don�t have an account at RoomsByYou, you�ll need to create one using this email id and the password of your choice. ".
					"All product shared by your friends will appear under Shared Products side bar after you log in.<br/><br/>".
					"<a href='http://www.roomsbyyou.com/'><img src='http://www.roomsbyyou.com' border=0 /></a>".
					"<br/>".$personalMessage."<br/>".
					"</p> ".
					"<p>Cheers,<br/>".
					"Rooms By You<br/>".
					"<a href='http://www.roomsbyyou.com/'>www.roomsbyyou.com</a></p>".
					"</center>";
		$success1 = true;
		$success2 = true;
		$success3 = true;
		$success4 = true;
		$success5 = true;
		if ($friendsEmail1!="") {
			$checkShare = $this->isProductSharedWithEmailId($product_id, $designSku, $friendsEmail1);
			if ( !$checkShare )
			$success1 = $this->shareProductInsert($product_id, $designSku, $friendsEmail1, $personalMessage);
			if ( $success1 )
			$this->sendEmail($friendsEmail1, $fromEmail, $templateCode, $item, $subject, $mailBody, $fromName);
		}
		if ($friendsEmail2!="" && $success1 && $success2 && $success3 && $success4 && $success5) {
			$checkShare = $this->isProductSharedWithEmailId($product_id, $designSku, $friendsEmail2);
			if ( !$checkShare )
			$success2 = $this->shareProductInsert($product_id, $designSku, $friendsEmail2, $personalMessage);
			if ( $success2 )
			$this->sendEmail($friendsEmail2, $fromEmail, $templateCode, $item, $subject, $mailBody, $fromName);
		}
		if ($friendsEmail3!="" && $success1 && $success2 && $success3 && $success4 && $success5) {
			$checkShare = $this->isProductSharedWithEmailId($product_id, $designSku, $friendsEmail3);
			if ( !$checkShare )
			$success3 = $this->shareProductInsert($product_id, $designSku, $friendsEmail3, $personalMessage);
			if ( $success3 )
			$this->sendEmail($friendsEmail3, $fromEmail, $templateCode, $item, $subject, $mailBody, $fromName);
		}
		if ($friendsEmail4!="" && $success1 && $success2 && $success3 && $success4 && $success5) {
			$checkShare = $this->isProductSharedWithEmailId($product_id, $designSku, $friendsEmail4);
			if ( !$checkShare )
			$success4 = $this->shareProductInsert($product_id, $designSku, $friendsEmail4, $personalMessage);
			if ( $success4 )
			$this->sendEmail($friendsEmail4, $fromEmail, $templateCode, $item, $subject, $mailBody, $fromName);
		}
		if ($friendsEmail5!="" && $success1 && $success2 && $success3 && $success4 && $success5) {
			$checkShare = $this->isProductSharedWithEmailId($product_id, $designSku, $friendsEmail5);
			if ( !$checkShare )
			$success5 = $this->shareProductInsert($product_id, $designSku, $friendsEmail5, $personalMessage);
			if ( $success5 )
			$this->sendEmail($friendsEmail5, $fromEmail, $templateCode, $item, $subject, $mailBody, $fromName);
		}

	
		if($success1 && $success2 && $success3 && $success4 && $success5) {
			return "Product successfully shared with your friends";
		} else {
			throw new Exception("Unable to share product with one or more friends.");
		}
	}	
	
	public function shareProductInsert($productId=0,$designSku='',$frnd_email='',$personal_message='') {
		if ($this->isProductSharedWithEmailId($productId, $designSku, $frnd_email))
			throw new Exception("Product already being shared with ".$frnd_email);
		
		$prefixForLocalTable = self::$prefixForLocalTable;
		
		$savedPID = $this->isSavedProduct($productId, $designSku);
		try {
			$sqlInsert = "Insert into ".$prefixForLocalTable."_shared_products".
						 "(saved_product_id, shared_on, friends_email, personal_message)". 
						 "values(" . $savedPID . ", " . date('Y-m-d h:m:s') . "', '" .
						$frnd_email . "', '". mysql_escape_string($personal_message) ."')";
			mysql_query($sqlInsert);
		}
		catch (Exception $e) {
			throw new Exception("Error while allowing share: ".$e->getMessage());
			return false;
		}
		return true;
	}
	
	public function isProductSharedWithEmailId($productId, $designSku='', $frnd_email='') {
		if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		}
		$productId = intval($productId);
		$savedPID = $this->isSavedProduct($productId, $designSku);
		if ($savedPID < 1) {
			throw new Exception(self::$notYourProduct);
		}
		$prefixForLocalTable = self::$prefixForLocalTable;
		
			$sqlSelect = "select " . $prefixForLocalTable . "_shared_products.* from 
					 " . $prefixForLocalTable . "_shared_products 
					 where saved_product_id=".$savedPID."
					 and friends_email=\"".$frnd_email."\"";
			mysql_query($sqlSelect);
			$rs = mysql_query($sqlSelect);
			$numResults = mysql_num_rows($rs);	
			
		return $numResults;
	}
	
	//save 2d product
	public function saveProductInsert($productId=0,$designSku='') {
		if ($this->isSavedProduct($productId, $designSku) > 0)
			throw new Exception("Product already saved ");
			
		if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		}
		$UID = $this->getCustomerId();
		
		$prefixForLocalTable = self::$prefixForLocalTable;
		
		try {
			$sqlInsert = "Insert into ".$prefixForLocalTable."_saved_products".
						 "(user_id, product_id, design_sku, created_on)". 
						 "values(" . $UID . "," . $productId . ", '". $designSku ."' ,'" . date('Y-m-d h:m:s') . "')";
			mysql_query($sqlInsert);
		}
		catch (Exception $e) {
			throw new Exception("Error while Saving: ".$e->getMessage());
			return false;
		}
		return true;
	}
	
	public function isSavedProduct($productId=0,$designSku='') {
		if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		}
		$UID = $this->getCustomerId();
		
		$prefixForLocalTable = self::$prefixForLocalTable;
		
		$sqlSelect = $sqlSelect = "select " . $prefixForLocalTable . "_saved_products.* from 
					 " . $prefixForLocalTable . "_saved_products 
					 where product_id=".$productId."
					 and design_sku = '".$designSku."',
					 and user_id=\"".$UID."\"";;
		mysql_query($sqlSelect);
		$rs = mysql_query($sqlSelect);
		//return $numResults = mysql_num_rows($rs);	
		if(mysql_num_rows($rs) > 0){
			$row = mysql_fetch_row($rs);
			return $row[0];		
		}
		else 
			return 0;
	}	
	//end shared 2d product
	//
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
	//
	public function getDesignerInfo($categoryOrDesignerId, $useCategoryId = false) {
        return $this->loadCache()->__getDesignerInfo($categoryOrDesignerId, $useCategoryId);
    }
	public function __getDesignerInfo($categoryOrDesignerId, $useCategoryId = false) {
		$designerId = intval($categoryOrDesignerId);
		//
		$prefixForLocalTable = self::$prefixForLocalTable;
		$prefixForMagTable = self::$prefixForMagTable;
		//
		$homeUrl = Mage::helper( 'core/url' )->getHomeUrl();
		//
		$returnVal = array();
		if ($useCategoryId == false) {
			$returnVal['designerId'] = $designerId;
			$returnVal['categoryId'] = "";
		} else {
			$returnVal['designerId'] = "";
			$returnVal['categoryId'] = $designerId;
		}
		$returnVal['designer_profile_picture'] = $homeUrl . self::$designerImageFolder . self::$defaultDesignerImage;
		$returnVal['designer_profile_picture_path'] = "";
		$returnVal['designer_profile_thumbnail'] = $homeUrl . self::$designerImageFolder . self::$defaultDesignerImage;
		$returnVal['designer_profile_thumbnail_path'] = "";
		$returnVal['firstname'] = "";
		$returnVal['lastname'] = "";
		//
		$sqlSelect = " Select DID, CID from " . $prefixForLocalTable . "_designer_category where ";
		if ($useCategoryId) {
			$sqlSelect .= " CID = " . $designerId;
		} else {
			$sqlSelect .= " DID = " . $designerId;
		}
		$mainDesignerCategory = mysql_query($sqlSelect);
		$mainCatCount = mysql_num_rows($mainDesignerCategory);
		//
		if ($mainCatCount == 0 && $useCategoryId == true) {
			return $returnVal;
		}
		if ($mainCatCount > 0) {
			$mainRow = mysql_fetch_row($mainDesignerCategory);
			if ($useCategoryId == true) {
				$designerId = $mainRow[0];
			}
			$categoryId = $mainRow[1];
		}
		//
		$sqlSelect = " Select user_id, " . $prefixForLocalTable . "_designer_profile.image, " .
				$prefixForLocalTable . "_designer_profile.thumbnail_image from " .
				$prefixForMagTable . "_admin_role, " . $prefixForLocalTable . "_designer_profile where " .
				$prefixForMagTable . "_admin_role.parent_id = (Select role_id from " . $prefixForMagTable .
				"_admin_role where " . $prefixForMagTable . "_admin_role.role_name = 'Designer') and " .
				$prefixForLocalTable . "_designer_profile.DID = " . $prefixForMagTable . "_admin_role.user_id " .
				" and user_id = " . $designerId;
		//
		$rs = mysql_query($sqlSelect);
		//
		$numResults = mysql_num_rows($rs);
		//
		
		//
		if ($numResults == 0) {
			return $returnVal;
		}
		//
		$row = mysql_fetch_row($rs);
		$imageUrl = "";
		$thumbImageUrl = "";
		if ($row[1] == "") {
			$imageUrl = $homeUrl . self::$designerImageFolder . self::$defaultDesignerImage;
			$thumbImageUrl = $homeUrl . self::$designerImageFolder . self::$defaultDesignerImage;
		} else {
			$imageUrl = $homeUrl . self::$designerImageFolder . $row[1];
			$thumbImageUrl = $homeUrl . self::$designerImageFolder . $row[2];
		}
		//
		//
		$sqlSelectName = "Select user_id, firstname, lastname, username, " .
					$prefixForLocalTable . "_designer_profile.image, " .
					$prefixForLocalTable . "_designer_profile.thumbnail_image from " .
					$prefixForMagTable . "_admin_user, " . $prefixForLocalTable .
					"_designer_profile where " . $prefixForMagTable . "_admin_user.user_id = " .
					$designerId." and " . $prefixForLocalTable . "_designer_profile.DID = ".$designerId."";
		$rs1 = mysql_query($sqlSelectName);
		$desname = mysql_fetch_row($rs1);
		//
		$returnVal['designerId'] = $designerId;
		$returnVal['designer_profile_picture'] = $imageUrl;
		$returnVal['designer_profile_picture_path'] = $row[1];
		//
		$returnVal['designer_profile_thumbnail'] = $thumbImageUrl;
		$returnVal['designer_profile_thumbnail_path'] = $row[2];
		if ($mainCatCount > 0) {
			$returnVal['categoryId'] = $categoryId;
		} else {
			$returnVal['categoryId'] = "";
		}
		$returnVal['firstname'] = $desname[1];
		$returnVal['lastname'] = $desname[2];
		return $returnVal;
	}
	//
	public function getDesignerImageUrls($generateKeysOfCategoryId = false) {
        return $this->loadCache(self::$cacheLongTimeSpan)->__getDesignerImageUrls($generateKeysOfCategoryId);
    }
	public function __getDesignerImageUrls($generateKeysOfCategoryId = false) {
		$prefixForLocalTable = self::$prefixForLocalTable;
		$prefixForMagTable = self::$prefixForMagTable;

		$sqlSelect = " Select user_id, " . $prefixForLocalTable . "_designer_profile.thumbnail_image from " .
					$prefixForMagTable . "_admin_role, " . $prefixForLocalTable . "_designer_profile where " .
					$prefixForMagTable . "_admin_role.parent_id = (Select role_id from " .
					$prefixForMagTable . "_admin_role where " .
					$prefixForMagTable . "_admin_role.role_name = 'Designer') and " .
					$prefixForLocalTable . "_designer_profile.DID = " .
					$prefixForMagTable . "_admin_role.user_id";

		$rs = mysql_query($sqlSelect);
		$numResults = mysql_num_rows($rs);
		$returnVal = array();
		$homeUrl = Mage::helper( 'core/url' )->getHomeUrl();
		for ($i=0; $i<$numResults; $i++) {
			$row = mysql_fetch_row($rs);
			if ($row[1] != "") {
				$designerImg = $homeUrl . self::$designerImageFolder . $row[1];
			} else {
				$designerImg = $homeUrl . self::$designerImageFolder . self::$defaultDesignerImage;
			}
			$returnVal['' . $row[0]] = $designerImg;
		}
		return $returnVal;
	}
	public function getDesignerImage($designerId) {
		$designerId = intval($designerId);
		$image = $this->getDesignerInfo($designerId);
		if ($image['designer_profile_picture_path'] == "") {
			header("Content-Type: image/jpeg");
			header("HTTP/1.0 404 Not Found");
			exit();
		}
		
		$pinfo = realpath('.');
		$file = $pinfo . '/' . self::$designerImageFolder . $image['designer_profile_picture_path'];
		
		if (file_exists($file) == false) {
			header("HTTP/1.0 404 Not Found");
			exit();
		}
		$size = getimagesize($file);
		header("Content-Type: {$size['mime']}");
		readfile($file);
		exit();
	}
	public function getProductImage($productId, $label="small_image", $noredirect = true) {
		$image = $this->getProductImageInfo($productId, $label);
		if (array_key_exists('file', $image) == false) {
			$image['file'] = self::$defaultProductImage;
		}
		if ($image['file'] == "" || $image['file']==self::$defaultProductImage) {
			header("HTTP/1.0 404 Not Found");
			header("Content-Type: image/jpeg");
			if ($noredirect == false) {
                header("Location:".self::$defaultProductImage);
            }
		}
		if ($noredirect == false) {
			header("Cache-Control: max-age=3600"); // HTTP/1.1
			header("HTTP/1.1 301 Moved Permanently"); // moved forever
			header("Location:/media/catalog/product" . $image['file']);
			exit();
		}
        //
		header("Cache-Control: max-age=3600, private"); // HTTP/1.1
        //
		$pinfo = realpath('.');
		$file = $pinfo . '/media/catalog/product' . $image['file'];
        //
		if (file_exists($file) == false) {
			$file = $pinfo . self::$defaultProductImage;
		}
		$size = getimagesize($file);
		header("Content-Type: {$size['mime']}");
		readfile($file);
		exit();
	}
			
	public function getProductImageInfo($productId, $label="small_image") {
		if (is_numeric($productId) == false) { // assuming SKU instead of Product ID
			$productId = $this->getProductIdBySku($productId);
		}
		$productId = intval($productId);
		
		$product = $this->getProduct($productId, false, false, false);
		$images = $product['media_gallery']['images'];
		if (array_key_exists('small_image_label', $product) == false) {
			$image_label = $label;
		} else {
			//$image_label = $product['small_image_label'];
			$image_label = 'small_image';
		}
		if ($label != "small_image") {
			$image_label = $label;
		}
		$image = array();
		for ($i=0; $i < count($images); $i++) {
			if ($images[$i]['label'] == $image_label) {
				$image = $images[$i];
				break;
			}
		}
		return $image;
	}
	public function getBundledMessage() {
		$returnVal = array();
		$bundle = array();
		$bundle['getCustomerInfo'] = array( 'getCustomerInfo' => $this->getCustomerInfo() );
		$bundle['getProducts'] = array( 'getProducts' =>$this->getProducts() );
		$bundle['getDesigners'] = array( 'getDesigners' =>$this->getDesigners() );
		//$bundle['getLatestDesigns'] = array( 'getLatestDesigns' =>$this->getLatestDesigns() );
		//$bundle['get3DProducts'] = array( 'get3DProducts' =>$this->get3DProducts() ); 
		//$bundle['getFeaturedCollections'] = array( 'getFeaturedCollections' =>$this->getFeaturedCollections() );

		if (Mage::getSingleton( 'customer/session' )->isLoggedIn() == true) {
			// $bundle['getRooms'] = array( 'getCustomerInfo' =>$this->getRooms() ); // get rooms in bundled message increases file size enormously for Flash client to handle and therefore getRooms is removed
		}
		$returnVal['messages'] = $bundle;
		return $returnVal;
	}
	public function getCustomerInfo() {
		$returnVal = array();
		$returnVal['isLoggedIn'] = $this->isLoggedIn();
		$returnVal['getCustomerId'] = $this->isLoggedIn();
		$returnVal['getCustomerGroupId'] = $this->getCustomerGroupId();
		$returnVal['getCustomerName'] = $this->getCustomerName();
		return $returnVal;
	}
	public function isLoggedIn() {
        $returnVal = Mage::getSingleton( 'customer/session' )->isLoggedIn();
        return $returnVal;
    }

    public function getCustomerId(){
		if (Mage::getSingleton( 'customer/session' )->isLoggedIn() == false) {
			return -1;
		}
        $returnVal = Mage::getSingleton( 'customer/session' )->getCustomerId();
        return $returnVal;
    }
    public function getCustomerGroupId(){
        $returnVal = Mage::getSingleton( 'customer/session' )->getCustomerGroupId();

        return $returnVal;
    }
	public function getCustomerEmail() {
		//getAttributes
		if (Mage::getSingleton( 'customer/session' )->isLoggedIn() == false) {
			return 0;
		}
		//$returnVal = array();
        //$returnVal['getCustomerEmail'] = htmlspecialchars(Mage::getSingleton( 'customer/session' )->getCustomer()->getEmail());
        //return $returnVal;
		return htmlspecialchars(Mage::getSingleton( 'customer/session' )->getCustomer()->getEmail());
	}
    public function getCustomerName(){
        if (Mage::getSingleton( 'customer/session' )->isLoggedIn()) {
            $returnVal = Mage::helper( 'customer/data' )->getCustomerName();
        } else {
            $returnVal = "Guest";
        }
        return $returnVal;
    }
    public function getCustomerNameById($customerId=0,$token=""){
		$prefixForLocalTable = self::$prefixForLocalTable;
		$prefixForMagTable = self::$prefixForMagTable;
		$customerId=intval($customerId);
        if (Mage::getSingleton( 'customer/session' )->isLoggedIn()) {
            if ($customerId > 0 && $token==Mage::getSingleton( 'customer/session' )->getCustomerId()) {
				$sqlSelect = "SELECT (SELECT value FROM ".$prefixForMagTable."_customer_entity_varchar ".
					"WHERE attribute_id=5 and entity_id=".$customerId.") as FirstName, ".
					"(SELECT value FROM ".$prefixForMagTable."_customer_entity_varchar ".
					"WHERE attribute_id=7 and entity_id=".$customerId.") as LastName ".
					"FROM ". $prefixForMagTable ."_customer_entity_varchar LIMIT 1";
				$rs = mysql_query($sqlSelect);
				$numResults = mysql_num_rows($rs);
				if ($numResults>0) {
					$returnVal = array();
					$returnVal['CustomerID'] = $customerId;
					$rowTag = mysql_fetch_row($rs);
					$returnVal['CustomerName'] = $rowTag[0]. " " .$rowTag[1];
					return $returnVal;
				} else {
					return "";
				}
			} else return "";
        } else {
            return "";
        }

        return $returnVal;
    }
    public function login($email, $password){
        $returnValLogin = Mage::getSingleton( 'customer/session' )->login($email, $password);
		$returnVal = array();
		$returnVal['result'] = $returnValLogin;
		if ($returnValLogin == true) {
			$returnVal['getCustomerInfo'] = $this->getCustomerInfo();
		} else {
			throw new Exception(self::$invalidLogin);
		}
        return $returnVal;
    }
    public function logout(){
		$returnValLogin = Mage::getSingleton( 'customer/session' )->logout();
		$returnVal['result'] = true;
		$returnVal['getCustomerInfo'] = $this->getCustomerInfo();
        return $returnVal;
	}
    //
    // Sample:
    // /rbyservice?method=addProductToCart&product=1&qty=1&options[0]=NewDesCode&options[1]=1&optioncodes[0]=2&optioncodes[1]=1
    public function addProductToCartUrl($product, $qty=1, $options=array()) {
		$product = intval($product);
		$qty = intval($qty);
        // Secret Sauce - Initializes the Session for the FRONTEND
        // Magento uses different sessions for 'frontend' and 'adminhtml'
        Mage::getSingleton('core/session', array('name'=>'frontend'));
        $product = Mage::getModel('catalog/product')
        ->setStoreId(Mage::app()->getStore()->getId())
        ->load($product);
        $info = array();
        $info['uenc'] = Mage::helper('checkout/cart')->getAddUrl($product);
        $info['product'] = $product->getId();
        $info['qty'] = $qty;
        $info['options'] = $options;
        $info['related_product'] = "";
        $returnVal = Mage::helper('checkout/cart')->getAddUrl($product, $info);
        return $returnVal;
    }

    public function addProductToCartUenc($product, $qty=1, $options=array()) {
		$product = intval($product);
		$qty = intval($qty);
        $product = Mage::getModel('catalog/product')
        ->setStoreId(Mage::app()->getStore()->getId())
        ->load($product);
        $url = Mage::helper('checkout/cart')->getAddUrl($product);
        $parts = explode("/", $url);
        $uenc = "";
        for ($i=0; $i<count($parts); $i++) {
            if ($parts[$i] == 'uenc') {
                $uenc = $parts[$i + 1];
                break;
            }
        }
        return $uenc;
    }
    public function filterUencFromUrl($url = "") {
        $parts = explode("/", $url);
        $uenc = "";
        for ($i=0; $i<count($parts); $i++) {
            if ($parts[$i] == 'uenc') {
                $uenc = $parts[$i + 1];
                break;
            }
        }
        return $uenc;
    }
    //
    public function addMultiProductsToCart($productIds=array(), $qty=array()) {
        $returnVal = array();
        $returnVal['lastAddedProducts'] = array();
        $request = $_REQUEST;
        for($i=0; $i < count($productIds); $i++) {
            $quantity = isset($qty[$i])?$qty[$i]:1;
            $values = array();
            if (isset($request['optionValues_' . $i])) {
                $values = $request['optionValues_' . $i];
            }
            $codes = array();
            if (isset($request['optionCodes_' . $i])) {
                $codes = $request['optionCodes_' . $i];
            }
            $lastAddedItem = $this->addProductToCart($productIds[$i], $quantity, $values, $codes);
            $returnVal['lastAddedProducts'][] = $lastAddedItem['lastAddedProduct'];
        }
		//error_log("RBYService addProductToCart: ".var_export($productIds),0);
        return $returnVal;
    }
    public function addProductToCart($product, $qty=1, $options=array(), $optioncodes=array()) {
		$product = intval($product);
		$qty = intval($qty);
        $quoteId = Mage::getSingleton('checkout/session')->getQuoteId();
        $cart = Mage::helper('checkout/cart')->getCart();
        $product = Mage::getModel('catalog/product')
        ->setStoreId(Mage::app()->getStore()->getId())
        ->load($product);
		
        $numargs = func_num_args();

        $info = array();
        $info['uenc'] = $this->addProductToCartUenc($product->getId(), $qty);
        $info['product'] = $product->getId();
        $info['qty'] = $qty;
        //$info['options'] = array_merge($info['options'], explode(",", $options));

        $info['related_product'] = "";

        //$maxCode = max($optioncodes);
        $info['options'] = array();
        //$info['options'] = array_pad($info['options'], $maxCode, "");
        $productOptions = $product->getProductOptionsCollection();

        for($i=0; $i<count($optioncodes); $i++) {
            //$info['options'][$optioncodes[$i]] = $options[$i];
            $optionValue = $options[$i];
            $optioncode = $optioncodes[$i];
            //
            $prodOptionTitle = $this->getProductOptionTitle($productOptions, $optioncode);
            
            if ($prodOptionTitle == "") {
                // this is not a valid custom option for this product,
                continue; // continue to next option
            }
            if ($prodOptionTitle == "Design Code") {
                // this is design code,
                // if an integer is submitted,
                // we will find the SKU using that integer
                if ($optionValue != "n/a" && $optionValue != "0" && $optionValue != "") {
                    if (is_int(intval($optionValue)) == true) {
                        $designItem = Mage::getModel('catalog/product')
                            ->setStoreId(Mage::app()->getStore()->getId())
                            ->load($optionValue);
                        $optionValue = $designItem->getSku();
                    } else {
                        $optionValue = "n/a";
                    }
                } else {
                    $optionValue = "n/a";
                }
            }
            $info['options'][''.$optioncode] = $optionValue;
        }
        
        $cart = Mage::getSingleton('checkout/cart')->addProduct($product, $info);
        $cart->save();

        $returnVal = array();
        $returnVal['lastAddedProduct'] = array();
        $returnVal['lastAddedProduct']['productid'] = $cart->getCheckoutSession()->getLastAddedProductId();
        $returnVal['lastAddedProduct']['options'] = $info['options'];
        return  $returnVal;
        
        
    }
    public function replaceProductFromCart() {

    }
	public function clearAllCartItems()
    {
        $session = Mage::getSingleton('checkout/session');

		foreach($session->getQuote()->getAllItems() as $_item):
		 $id = intval($_item->getId());
		 //error_log("RBYService: clearAllCartItems: $id: ".var_export($id),0);
			if ($id) {
				try {
					//$rVal = removeProductFromCart($id);
					//Mage::getSingleton('checkout/cart')->_getCart()->removeItem($id)
					  //->save();
					
					$item = intval($id);
					$quoteId = Mage::getSingleton('checkout/session')->getQuoteId();
					$helper = Mage::helper('checkout/cart');
					//$cart = Mage::helper('checkout/cart')->getCart();
					$cart = Mage::getSingleton('checkout/cart');
					$quote = $cart->getQuote();


					$data = array();
					$theItem = $quote->getItemById($item);

					//if (!$theItem) return false;

					$cart->removeItem($theItem->getId());

					$cart->save();
					
				} catch (Exception $e) {
					//$this->_getSession()->addError($this->__('Cannot remove item'));
					return ("Error while clearing cart items: ".$e->getMessage());
				}
			}
		endforeach;
		return true;
    }
    public function updateCartItemQuantity($item, $qty=1) {
		$item = intval($item);
		$qty = intval($qty);
        $quoteId = Mage::getSingleton('checkout/session')->getQuoteId();
        $helper = Mage::helper('checkout/cart');
        $cart = Mage::helper('checkout/cart')->getCart();
        $quote = $cart->getQuote();
        $theItem = $quote->getItemById($item);
        if ($theItem == null) {
            return array('error' => array('message' => 'No such item id in your cart'));
        }
        $theItem->setQty($qty);
        $theItem->save();
        $cart->save();
        return true;
    }
    public function updateProductInCart($item, $product, $qty=1, $options=array(), $optioncodes=array()) {
		$item = intval($item);
		$product = intval($product);
		$qty = intval($qty);

        $quoteId = Mage::getSingleton('checkout/session')->getQuoteId();
        $helper = Mage::helper('checkout/cart');
        $cart = Mage::helper('checkout/cart')->getCart();
        $quote = $cart->getQuote();



        $theItem = $quote->getItemById($item);

        if ($theItem == null) {
            return array('error' => array('message' => 'No such item id in your cart'));
        }

        $productId = $theItem->getProductId();

        

        $numberOfItemsInCart = $cart->getItemsCount();
        // add new item

        $this->addProductToCart($product, $qty, $options, $optioncodes);

        $cart = Mage::helper('checkout/cart')->getCart();
        $allItems = $cart->getQuote()->getAllItems();



        if (count($allItems) == $numberOfItemsInCart) {
            return true;
        }

        $this->removeProductFromCart($item);


        return true;

        // following is attempt to preserve itemid and delete new itemid


        $cart = Mage::helper('checkout/cart')->getCart();
        $allItems = $cart->getQuote()->getAllItems();


        $newItem = $allItems[$numberOfItemsInCart];


        // $theItem->setData($newItem->getData());

        $theItem->setQty($newItem->getQty());

        $theItem->setProduct($newItem->getProduct());

        $custom_options = $theItem->getProduct()->getCustomOptions();


        $newOptions = $newItem->getOptions();
        foreach($newOptions as $newOption) {
            $itemOption = $theItem->getOptionByCode($newOption->getCode());
            if ($itemOption==null) {
                $theItem->addOption($newOption);
            } else {
                $itemOption->setValue($newOption->getValue());
            }
        }

        $itemOptions = $theItem->getOptions();
        foreach($itemOptions as $itemOption) {
            $newOption = $newItem->getOptionByCode($itemOption->getCode());
            if ($newOption == null) {
                return true;
                $theItem->removeOption($itemOption->getCode());
            }
        }

        $theItem->save();

        $cart->removeItem($newItem->getId());

        $cart->save();

        return true;

    }
	public function removeMultiProductsFromCart($itemsArray=array()) {
		//for($i=0; $i <= count($itemsArray); $i++) {
			//error_log("RBYService: removeMultiProductsFromCart: $itemsArray[$i]: ".var_export($itemsArray[$i]),0);
            //if ($itemsArray[$i] != null) {
				//$retVal = $this->removeProductFromCart($itemsArray[$i]);
            //}
			//$id = intval($itemsArray[$i]);
			//error_log("RBYService: clearAllCartItems: $id: ".var_export($id),0);
			$cart = Mage::getSingleton('checkout/cart');
			$quote = $cart->getQuote();
			if (true) {
				try {
					//$rVal = removeProductFromCart($id);
					//Mage::getSingleton('checkout/cart')->_getCart()->removeItem($id)
					  //->save();



					foreach($itemsArray as $item): 
						$item = intval($item);
						$cart->removeItem($item);
						$cart->save();
					endforeach; 

				} catch (Exception $e) {
					//$this->_getSession()->addError($this->__('Cannot remove item'));
					return ("Error while selected items from cart: ".$e->getMessage());
				}
			}
        //}
		//throw new Exception("removeMultiProductsFromCart: ". $itemsArray[0]);
		return true;
	}
    public function removeProductFromCart($item=0) {
		$item = intval($item);
        $quoteId = Mage::getSingleton('checkout/session')->getQuoteId();
        $helper = Mage::helper('checkout/cart');
        $cart = Mage::helper('checkout/cart')->getCart();
        $quote = $cart->getQuote();


        $data = array();

		//error_log("RBYService: removeProductFromCart: $item: ".var_export($item),0);
        $theItem = $quote->getItemById($item);

        if (!$theItem) return false;

        $cart->removeItem($theItem->getId());

        $cart->save();

        return true;
    }
    public function getCartItemsIds() {
        $returnVal = Mage::helper('checkout/cart')->getCart()->getProductIds();
        return $returnVal;
    }
    public function getProductsInCart() {
        $quoteId = Mage::getSingleton('checkout/session')->getQuoteId();
        $helper = Mage::helper('checkout/cart');
        $cart = Mage::helper('checkout/cart')->getCart();
        $data = Mage::helper('checkout/data');
        $productIds = array();
        $totalPrice = 0;
        $totalPriceInclTax = 0;
        $returnVal = array();
        $returnVal['products'] = array();
        $returnVal['info'] = array();
        $returnVal['info']['carturl'] = $helper->getCartUrl();
        $count = 0;
        if ($cart->getSummaryQty()>0) {
            foreach ($cart->getQuote()->getAllItems() as $item) {
                $count++;

                $i = array();
                $i["id"] = $item->getId();
                $i["sku"] = $item->getSku();
                $i["productid"] = $item->getProductId();
                $i["itemid"] = $item->getId();
                $i["name"] = $item->getName();
                $i["description"] = $item->getDescription();
                $i["quantity"] = $item->getQty();
                $i["producturl"] = $item->getProduct()->getProductUrl();
                $i["baseprice"] = $item->getBaseCalculationPrice();
                $totalPrice += $i["baseprice"]*$item->getQty();
                $i["basepriceincltax"] = $data->getBasePriceInclTax($item);
                $totalPriceInclTax += $i["basepriceincltax"]*$item->getQty();
                $options = $item->getOptions();
                $coll = $item->getProduct()->getProductOptionsCollection();
                $i['options'] = array();
                $option_titles = array();
                $currentOption = 0;
                foreach ($options as $option) {
                    if ($option->getCode() != 'info_buyRequest') {
                        $i['options'][$option->getCode()] = $option->toArray();
                        if ($option->getCode() == 'option_ids') {
                            $option_titles = explode(",",$option->getValue());
                            $i['options'][$option->getCode()]['option_title'] = "Option IDs";
                        } else {
                            $productOptionType = $this->getProductOptionType($coll, $option_titles[min($currentOption,count($option_titles)-1)]);
                            $i['options'][$option->getCode()]['option_title'] = $this->getProductOptionTitle($coll, $option_titles[min($currentOption,count($option_titles)-1)]);
                            if ($productOptionType == 'drop_down') {
                                $value_title = $this->getProductOptionValue($coll, $option_titles[min($currentOption,count($option_titles)-1)], $option->getValue());
                            } else {
                                $value_title = $option->getValue();
                            }
                            if ($i['options'][$option->getCode()]['option_title'] == 'Design Code') {
                                $prod_design_id = Mage::getModel('catalog/product')->getIdBySku($value_title);
                                if ($prod_design_id!=null) {
                                    $prod_design = Mage::getModel('catalog/product')->load($prod_design_id);
                                    if ($prod_design!=null) {
                                        $i['options'][$option->getCode()]['small_image'] = $prod_design->getData('small_image');
                                        $i['options'][$option->getCode()]['image'] = $prod_design->getData('image');
                                    }
                                }
                            }
                            $i['options'][$option->getCode()]['value_title'] = $value_title;
                            $i['options'][$option->getCode()]['product_option_id'] = $option_titles[min($currentOption,count($option_titles)-1)];
                            $i['options'][$option->getCode()]['product_option_type'] = $productOptionType;
                        }

                        //$i[$option->getCode()]['title'] = $item->getProduct()->getCustomOption($option->getCode())->value;
                        if ($option->getCode() != 'option_ids') {
                            $currentOption++;
                        }
                    }
                }

                ;
                $productIds[] = $i;
            }


            foreach ($productIds as $key => $row) {
                $prods_id[$key]  = $row['id'];
                $prods_name[$key] = $row['name'];
            }
            array_multisort($prods_name, SORT_ASC, $prods_id, SORT_ASC, $productIds);

            $returnVal['products'] = $productIds;

        }

        $returnVal['info']['totalitems'] = $count;
        $returnVal['info']['price'] = $totalPrice;
        $returnVal['info']['priceincltax'] = $totalPriceInclTax;
        $returnVal['info']['formattedprice'] = $data->formatPrice($totalPrice);
        $returnVal['info']['formattedpriceincltax'] = $data->formatPrice($totalPriceInclTax);


        //$productIds = array_unique($productIds);
        return $returnVal;
    }
	public function addToWishlist($productId) {
		$productId = intval($productId);
		if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		}
		$customerId = Mage::getSingleton( 'customer/session' )->getId();
		$wishlist = Mage::getModel('wishlist/wishlist')->loadByCustomer($customerId);
		if ($wishlist->getId() == "") {
			$wishlist = new Mage_Wishlist_Model_Wishlist();
			$wishlist->setCustomerId($customerId);
			$wishlist->setStore(Mage::app()->getStore());
			$wishlist->save();
		}
		$wishlist->addNewItem($productId);
		return true;
	}
	public function getWishlist() {
		if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		}
		$customerId = Mage::getSingleton( 'customer/session' )->getId();
		//$customer = Mage::getModel('customer/customer')->load($customerId);

		$wishlist = Mage::getModel('wishlist/wishlist')->loadByCustomer($customerId);
		
		$returnVal = $wishlist->toArray();
		$returnVal['items'] = array();
		$itemCollection = $wishlist->getItemCollection();
		foreach($itemCollection as $item) {
			$returnVal['items']['' . $item->getId()] = array();
			$returnVal['items']['' . $item->getId()]['itemid'] = $item->getId();
			$returnVal['items']['' . $item->getId()]['product'] = $this->getProduct($item->getProductId(), false, false, false);
		}
		return $returnVal;
	}
	public function getWishlistByCode($code) {
		$wishlist = Mage::getModel('wishlist/wishlist')->loadByCode($code);
		if ($wishlist->getId() == "") {
			throw new Exception(self::$invalidWishlistCode);
		}
		$returnVal = $wishlist->toArray();
		$returnVal['items'] = array();
		$itemCollection = $wishlist->getItemCollection();
		foreach($itemCollection as $item) {
			$returnVal['items']['' . $item->getId()] = array();
			$returnVal['items']['' . $item->getId()]['itemid'] = $item->getId();
			$returnVal['items']['' . $item->getId()]['product'] = $this->getProduct($item->getProductId(), false, false, false);
		}
		return $returnVal;
	} 
	//get get3DProducts
	public function get3DProducts($catId, $pid=0 , $type='next', $limit_lower=30, $color='') {
		//return $this->getProducts($catName);
			$productId = intval($pid);
			$limit_lower = intval($limit_lower);
			if ($catId) {
			$catId = intval($catId);
			} else {
				$catId = $this->getCategoryIdByName("Pre-Customized-Products");
			}
			
			$result = array();
			$storeId = Mage::app()->getStore()->getId();
			$visibility = array(
						  Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
						  Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
					  );
			
			$coll = Mage::getModel('catalog/category')->load($catId)
					->getProductCollection()
					->addAttributeToFilter('category_ids',array('finset'=>$catId));
			$coll->addAttributeToFilter('status', 1);
			$coll->addAttributeToFilter('visibility', $visibility);			
			if($color!='')
				$coll->addAttributeToFilter('color_preference', array('like' => '%'. $color .'%'));
			
			$col = $coll;
			$coll->addAttributeToFilter('entity_id', array('gteq' => ''.$pid.''));				
			$coll->getSelect()->order('entity_id', 'asc')->limit($limit_lower);
			//$type = (strtolower($type)=='next') ? 'next' : 'prev';

			/* if($type=='next')	{
				$coll->addAttributeToFilter('entity_id', array('gt' => ''.$pid.''));				
				$coll->getSelect()->order('entity_id', 'asc')->limit($limit_lower);
			}
			if($type=='prev')	{
				// $coll->addAttributeToFilter('entity_id', array('lt' => ''.$pid.''));
				//$coll->setOrder('entity_id' , $dir);
				$coll->getSelect()->order('entity_id', 'asc')->limit($limit_lower); 
				$coll->addAttributeToFilter('entity_id', array('lt' => ''.$pid.''));				
				$coll->addAttributeToSort('entity_id', 'desc');
				$coll->getSelect()->limit($limit_lower);
			} */
			
			foreach($coll as $product):	
				$productD = Mage::getModel("catalog/product")->load($product->getId());			
				$result['products'][''.$product->getId()] = $productD->toArray(); 					
			endforeach;
			
			$pcp = Mage::getModel('catalog/category')->load($catId)->getProductCollection();
			if($color!=''){
			$pcp = $pcp->addAttributeToFilter('color_preference', array('like' => '%'. $color .'%'));
			}
			$pcp->addAttributeToSelect('position');
			$pcp->addAttributeToSort('entity_id', 'asc');
			$pcp->addAttributeToFilter('status', 1);
			$i=0;	$div = "";	$prodArr=''; $page=0;
			
			foreach($pcp as $prod){						
				if(($i)%$limit_lower==0){
					$prodArr[$page]=array('id'=>$prod->getId());
					$page++;
				}
				$i++;		
			}
			$result['count'] = count($pcp);
			$result['type'] = $type;
			$result['array'] = $prodArr;
			
			return $result;
	}
	
    public function getProducts($categoryName="Products",$showRepeats=1,$showEngineered=1,$showPortrait='',$sortByUploadDate=0, $sku='') {
		if (is_numeric($categoryName) == true) {
			$products_cat_id = intval($categoryName);
		} else {
			$products_cat_id = $this->getCategoryIdByName($categoryName);
		}

        $_category = Mage::getModel('catalog/category')->load($products_cat_id);
		if ($_category->getId()=="" || $_category->getId()==0) {
			throw new Exception(self::$invalidCategoryId);
		}
		$visibility = array(
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
                  );
		$prefixForLocalTable = self::$prefixForLocalTable;
        $subs = $_category->getProductCollection()->addAttributeToSelect('position');
		
        $result = array();
        $result['products'] = array();
        $i = 0;
		$designersCategoryId = $this->getDesignerCategoryId();
		$categoryAndDesignerInfo = array();
		$categoryAndDesignerInfo['categories'] = array();
		$categoryAndDesignerInfo['categories'][] = $_category->toArray();
		$path = explode("/", $_category->getPath());
		$pos = array_search($designersCategoryId, $path);
		if ($pos != false) {
//			$subs->getSelect()->joinInner($prefixForLocalTable.'_designer',
//				$prefixForLocalTable.'_designer.product_id = e.entity_id',
//				$prefixForLocalTable.'_designer.design_uses');
//			$subs->getSelect()->query();
		}
		$subs->addAttributeToFilter('visibility', $visibility);				
		if($sku !='') $subs->addAttributeToFilter('sku', array('like' => $sku.'%'));		
		if ($showRepeats==0) $subs->addAttributeToFilter('repeats_design', 0);
		if ($showEngineered==0) $subs->addAttributeToFilter('engineered_design', 0);
		if($showPortrait!='' && $sku =='')$subs->addAttributeToFilter('is_portrait', $showPortrait);
		if ($sortByUploadDate==1)
			$subs->addAttributeToSort('updated_at','desc');
		else
			$subs->addAttributeToSort('position');
		
		if ($pos != false && count($path)>$pos+1) {
			$designerInfo = $this->getDesignerInfo($path[3], true);
			$categoryAndDesignerInfo['designer'] = $designerInfo;
		}
		$prod_ids = array();
        foreach ($subs as $product) {
			$prod_ids[] = $product->getId();
		}
		$designUses = $this->getDesignUses($prod_ids);

        foreach ($subs as $product) {
            $result['products'][''.$product->getId()] = $this->getProduct($product->getId(), $categoryAndDesignerInfo, false, true);
			$result['products'][''.$product->getId()]['category_position'] = $result['products'][''.$product->getId()]['categories'][0]['position'];
			if (array_key_exists('' . $product->getId(), $designUses)) {
				$result['products'][''.$product->getId()]['designuses'] = $designUses['' . $product->getId()];
			}
        }

		$prod_ids = array();

		$prod_positions = $_category->getProductsPosition();
		$positions = array();

		foreach ($result['products'] as $key => $row) {
			$prod_ids['' . $key]  = $row['entity_id'];
			if (array_key_exists($row['entity_id'], $prod_positions) == false) {
				$positions['' . $key] = 0;
			} else {
				$positions['' . $key] = $prod_positions[''. $row['entity_id']];
			}
		}
		
		//array_multisort($positions, SORT_ASC, $prod_ids, SORT_ASC, $result['products']);

        return $result;
    }
	public function getProduct($productId, $categoryAndDesignerInfo=false, $designUses=false, $productOptions=true) {
		$productId = intval($productId);
		$theProduct = Mage::getModel('catalog/product')->load($productId);
		$returnVal = $theProduct->toArray();
		if (is_array($categoryAndDesignerInfo) == true) {
			$returnVal['categories'] = $categoryAndDesignerInfo['categories'];
			if (array_key_exists('designer', $categoryAndDesignerInfo) == true) {
				$returnVal['designer'] = $categoryAndDesignerInfo['designer'];
			}
		} elseif ($categoryAndDesignerInfo==true) {
			$designerInfoAppended = false;
			$catIds = $theProduct->getCategoryCollection()->addAttributeToSelect('name')->addAttributeToSort('level','desc');
			$returnVal['categories'] = array();
			if (count($catIds) > 0) {
				$designersCategoryId = $this->getDesignerCategoryId();
				foreach ($catIds as $cat) {
					$returnVal['categories'][] = $cat->toArray();
					if ($designerInfoAppended==false) {
						$path = explode("/", $cat->getPath());
						$pos = array_search($designersCategoryId, $path);
						if ($pos !== false && count($path)>$pos+1) {
							$designerInfo = $this->getDesignerInfo($path[$pos+1], true);
							$returnVal['designer'] = $designerInfo;
							$designerInfoAppended = true;
						}
					}
				}
			}
		}
		if ($designUses == true) {
			$returnVal['designuses'] = $this->getDesignUses($productId);
		}
        if ($productOptions==true) {
            $returnVal['options'] = $this->getProductOptions($productId);
        }
        $returnVal['stock_item'] = array();
		return $returnVal;
	}
	public function getProductIdBySku($sku) {
		$productId = Mage::getModel('catalog/product')->getIdBySku($sku);
		return $productId;
	}
	// returns array when array of product ids is passed
	// or returns string when just an id of one product is passed
	public function getDesignUses($productIdOrProductArray) {
		if (is_numeric($productIdOrProductArray)==false && is_array($productIdOrProductArray)==false) {
			throw new Exception(self::$invalidProductId);
		}
		$prefixForLocalTable = self::$prefixForLocalTable;
		$designUses = array();
		if (count($productIdOrProductArray) > 0) {
			$allDesignUsesSql = "SELECT product_id, design_uses from ".$prefixForLocalTable."_designer ";
			if (is_array($productIdOrProductArray)) {
				$allDesignUsesSql .= "where product_id IN (".implode(",", $productIdOrProductArray).")";
			} else {
				$allDesignUsesSql .= "where product_id = ".$productIdOrProductArray;
			}
			$rs = mysql_query($allDesignUsesSql);
			$numResults = mysql_num_rows($rs);
			for ($i=0; $i<$numResults; $i++) {
				$row = mysql_fetch_row($rs);
				$designUses['' . $row[0]] = $row[1];
			}
		}
		if (is_array($productIdOrProductArray) == true) {
			return $designUses;
		} else {
			if (array_key_exists('' . $productIdOrProductArray, $designUses)) {
				return $designUses['' . $productIdOrProductArray];
			} else {
				return  "";
			}
		}
	}
	
	public function getDesignerCategoryId() {
        return $this->loadCache(self::$cacheFiveDaysSpan)->__getDesignerCategoryId();
    }
	public function __getDesignerCategoryId() {
		return $this->getCategoryIdByName('Designers');
	}
    public function getCategoryIdByName($name="Products", $id=1) {
        return $this->loadCache(self::$cacheFiveDaysSpan)->__getCategoryIdByName($name, $id);
    }
    public function __getCategoryIdByName($name="Products", $id=1) {
		$id = intval($id);
        $cat = $this->getCategoryByName($name, $id);
		if (isset($cat['entity_id'])) {
			return $cat['entity_id'];
		}
		throw new Exception(self::$invalidCollectionId);
    }
    public function getCategoryByName($name="Products", $id=1) {
        return $this->loadCache()->__getCategoryByName($name, $id);
    }
    public function __getCategoryByName($name="Products", $id=1) {
		$id = intval($id);
        $_category = new Mage_Catalog_Model_Category();
        $_category->load($id);
		
		if ($_category->getName() == $name) {
			 return $_category->toArray();
		}

        $tree = $_category->getCategories($_category->getId(), false, true);
       // $tree = $_category->getChildrenCategories();

	   $returnVal = array();

       foreach ($tree as $child) {
		   $returnVal = array_merge($returnVal, $this->__getCategoryByName($name, $child->getId()));
       }

	   return $returnVal;
    }
	public function sendEmail($toEmail, $fromEmail, $templateCode, $emailData=array(), $subject="", $body = "", $fromName="", $toName="") {
		try {
			if (is_array($emailData)) {
				$data = new Varien_Object($emailData);
			}
			$translate = Mage::getSingleton('core/translate');
			/* @var $translate Mage_Core_Model_Translate */
			$translate->setTranslateInline(false);
			$my_smtp_host = Mage::getStoreConfig('system/smtp/host');  // Take it from Magento backoffice or you can specify it here
			$my_smtp_port = Mage::getStoreConfig('system/smtp/port');    // Take it from Magento backoffice or you can specify it here
			$config = array(
				'ssl' => 'tls', //optional
				'port' => $my_smtp_port, //optional - default 25, 465, 587
				'auth' => 'login', 
				'username' => 'info@roomsbyyou.com',
				'password' => 'custocool'
			);
			if (!($this->isMailTransportActive)) {
				$this->transport = new Zend_Mail_Transport_Smtp($my_smtp_host, $config);
				Zend_Mail::setDefaultTransport($this->transport);
				/*End of added code to specify config*/
			}
			
			$this->_mail = new Zend_Mail('utf-8');
			$mail = new Zend_Mail();
			$mail->setBodyHtml($body);
			$mail->setFrom("no-reply@roomsbyyou.com", $fromName)
				->addTo($toEmail, $toName)
				->setSubject($subject)
				->addHeader('Reply-To', $this->getCustomerEmail() );

			$mail->send();
			/* $mailTemplate = Mage::getModel('core/email_template')
			->load(Mage::getStoreConfig('shareroomwithfriend'));
			$mailTemplate->send($toEmail, null, $emailData);
			
			$mailTemplate = Mage::getModel('core/email_template');
			$mailTemplate->setDesignConfig(array('area' => 'frontend'))
				->setReplyTo($fromEmail)
				->sendTransactional(
						'sharerooms',
						"support@roomsbyyou.com",
						$toEmail,
						null,
						array('data' => $data)
					);
			if ($mailTemplate->getSentSuccess()) {
				throw new Exception();
			} else {
				return true;
			}*/
			//$translate->setTranslateInline(true);
			//$this->addSuccess(Mage::helper('idesigner')->__('An email has been sent to your mailbox'));
		} 
		catch (Exception $e) { 
			//$translate->setTranslateInline(true);
			throw new Exception($e->getMessage());
		}
		/*$mail = new Zend_Mail();
		$mail->setBodyText($body);
		$mail->setFrom($fromEmail, $fromName);
		$mail->addTo($toEmail, $toName);
		$mail->setSubject($subject);
		try {
			$mail->send();
		}
		catch(Exception $ex) {
			throw new Exception($ex->getMessage());
		}*/
		return true;
	}

    public function getCategoryName($id = 1) {
        $id = intval($id);
        if ($id == 0) return "";
		$cat = $this->getCategoryById($id);
        if ($cat != null && isset($cat['name'])) {
            return $cat['name'];
        }
        return "";
	}
	// getCategoryById is another name for getCategoryTree
	public function getCategoryById($id=1, $searchInactiveCategories = false) {
		return $this->getCategoryTree($id, $searchInactiveCategories);
	}
    public function getCategoryTree($id=1, $listInactiveCategories = false) {
        return $this->loadCache(self::$cacheShortTimeSpan)->__getCategoryTree($id, $listInactiveCategories);
    }
    public function __getCategoryTree($id=1, $listInactiveCategories = false) {
		$id = intval($id);
        $_category = new Mage_Catalog_Model_Category();
        $_category->load($id);
        $tree = $_category->getCategories($_category->getId(), false, true);
        $returnVal = $_category->toArray();
        $returnVal['children'] = array();
        foreach ($tree as $trunk) {
			if ($trunk->getIsActive() == true || $listInactiveCategories == true) {
				$returnVal['children'][] = $this->__getCategoryTree($trunk->getId());
			}
        }
        return $returnVal;
    }
    public function getProductOptionCode($options, $optionid) {
		$optionid = intval($optionid);
        $res = "";
        foreach ($options as $option) {
            if ($option->getId() == $optionid) {
                return $option->getCode();
            }
        }
        return $res;
    }
    public function getProductOptionType($options, $optionid) {
		$optionid = intval($optionid);
        $res = "";
        foreach ($options as $option) {
            if ($option->getId() == $optionid) {
                return $option->getType();
            }
        }
        return $res;
    }
    public function getProductOptionValue($options, $optionid, $valueid) {
		$optionid = intval($optionid);
		intval($valueid);
        if (is_int(intval($valueid)) == false ) {
            return $valueid;
        }
        $res = "";
        foreach ($options as $option) {
            if ($option->getId() == $optionid) {
                $values = $option->getValues();
                foreach ($values as $value) {
                    if ($value->getId()==$valueid) {
                        return $value->getTitle();
                    }
                }
            }
        }
        return $res;
    }
    public function getProductOptionTitle($options, $optionid) {
		$optionid = intval($optionid);
        $res = "";
        foreach ($options as $option) {
            if ($option->getId() == $optionid) {
                return $option->getTitle();
            }
        }
        return $res;
    }
    public function getProductOptions($productid) {
		$productid = intval($productid);
        $product = Mage::getModel('catalog/product')->setStoreId(Mage::app()->getStore()->getId())->load($productid);
        $returnVal = array();
        $options = $product->getProductOptionsCollection();
        $returnVal['option_ids']="";
        $option_ids = array();
        $returnVal['options']=array();
        foreach ($options as $option) {
            $option_ids[] = $option->getId();
            $returnVal['options']["option" . $option->getId()] = $option->toArray();
            $values = $option->getValues();
            $vals = array();
            foreach ($values as $value) {
                $vals["value" . $value->getId()] = $value->toArray();
            }
            $returnVal['options']["option" . $option->getId()]['values'] = $vals;
        }
        $returnVal['option_ids'] = implode(",", $option_ids);
        return $returnVal;
    }
    public function getCartItemsCount() {
        // Secret Sauce - Initializes the Session for the FRONTEND
        // Magento uses different sessions for 'frontend' and 'adminhtml'
        Mage::getSingleton('core/session', array('name'=>'frontend'));
        $returnVal = Mage::helper('checkout/cart')->getCart()->getItemsCount();
        return $returnVal;
    }
    public function getCartItemsQuantity() {
        // Secret Sauce - Initializes the Session for the FRONTEND
        // Magento uses different sessions for 'frontend' and 'adminhtml'
        Mage::getSingleton('core/session', array('name'=>'frontend'));
        $returnVal = Mage::helper('checkout/cart')->getCart()->getItemsQty();
        return $returnVal;
    }
	public function getDescendantsOfCategory() {
		
	}
    public function loadCache($lifetime = null) {
        if ($lifetime == null) {
            $lifetime = self::$cacheLifetime;
        }
        //
        $frontendOptions = array('caching' => self::$caching,
                'automatic_serialization' => self::$automatic_serialization,
                'lifetime' => $lifetime,
                'cache_id_prefix' => 'rbyservice',
                'cached_entity' => $this
                );
        $backendOptions = array('cache_dir' => self::$cacheDirectory);
        //
        $backendName = 'File';
        $frontendName = 'Class';
        //
        $rp = realpath(self::$cacheDirectory);
        if (file_exists($rp) == false) {
            mkdir(self::$cacheDirectory, 0777, true);
        }
        //
        $cache = Zend_Cache::factory($frontendName, $backendName, $frontendOptions, $backendOptions);
        //
        return $cache;
    }
    public function cleanCache($secureCall) {
        if ($secureCall != 'rby') {
            throw new Exception('Invalid use of Clean Cache method. Try removeCache(id).');
        }
        $this->loadCache()->clean(Zend_Cache::CLEANING_MODE_ALL);
    }
    public function removeCache($id) {
        try {
            $this->loadCache()->remove($id);
        } catch (Exception $e) {
            throw new Exception('Invalid cache id. Use getCacheIds for a list of ids first. Error: ' . $e->getMessage());
        }
    }
    public function getCacheId($methodName, $parameters = "") {
        $ro = new ReflectionObject($this);
        $cacheId = md5('__' . $ro->getName() . '__' . $methodName . '__' . serialize(explode(",", $parameters)));
        if ($this->loadCache()->test($cacheId)) {
            return $cacheId;
        }
        return 'no cache available for \'' . $methodName . '\' with provided parameters.';
    }
    public function cleanCacheByTags($tags) {
        $this->loadCache()->clean(Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG, explode(",", $tags));
    }
	
	//add PreCustomizedProducts
	
	public function addPreCustomizedProduct($room_id, $name='', $desc='', $img=null, $thumb_img=null, $price=0, $roomtype='', $roomStyle='', $xmlData='', $color=null) {
       $roomid = intval($room_id);
		if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		}
		$customerId = Mage::getSingleton( 'customer/session' )->getId();
		if ($customerId != 5) {
			throw new Exception(self::$loginError);
		}
		return $this->__addPreCustomizedProduct($room_id, $name, $desc, $img, $thumb_img, $price, $roomtype, $roomStyle, $xmlData, $color);
    }
	
	public function __addPreCustomizedProduct($room_id, $name, $desc, $img=null, $thumb_img=null, $price, $roomtype, $roomStyle='', $xmlData='', $color=null) {
				// Add New Product
				$sku = ereg_replace('[^A-Za-z0-9.]', '-', 'RBY-USER-PCD-'.date('m-d-y H:i:s'));
				$urlKey = ereg_replace('[^A-Za-z0-9.]', '-', $name.'-'.rand(0, 15));
				$catId = $this->getCategoryIdByName("Pre-Customized-Products", $id=1);
				if($roomtype=="BeddingEnsemble")$catId = $catId.",".$this->getCategoryIdByName("Bedding Ensembles", $id=1);
				if($roomtype=="DecorativePillow")$catId = $catId.",".$this->getCategoryIdByName("Decorative Pillows", $id=1);
				if($roomtype=="CribEnsemble")$catId = $catId.",".$this->getCategoryIdByName("Crib Ensembles", $id=1);
				
				if($roomStyle=="Contemporary" && $roomtype=="BeddingEnsemble")$catId = $catId.",".$this->getCategoryIdByName("Bedding Ensemble Contemporary", $id=1);
				if($roomStyle=="Classic" && $roomtype=="BeddingEnsemble")$catId = $catId.",".$this->getCategoryIdByName("Bedding Ensemble Classic", $id=1);
				if($roomStyle=="Contemporary" && $roomtype=="CribEnsemble")$catId = $catId.",".$this->getCategoryIdByName("Crib Ensemble Contemporary", $id=1);
				if($roomStyle=="Classic" && $roomtype=="CribEnsemble")$catId = $catId.",".$this->getCategoryIdByName("Crib Ensemble Classic", $id=1);
				//if($roomStyle=="Small")$catId = $catId.",".$this->getCategoryIdByName("Small", $id=1);
				//if($roomStyle=="Medium")$catId = $catId.",".$this->getCategoryIdByName("Medium", $id=1);
				//if($roomStyle=="Large")$catId = $catId.",".$this->getCategoryIdByName("Large", $id=1);
				try 
				{
					$rbyProductModel = Mage::getModel('catalog/product');
					$rbyProductModel->setWebsiteIds(array(Mage::app()->getStore()->getId()));
					$rbyProductModel->setAttributeSetId(4);
					$rbyProductModel->setTypeId('simple');
					$rbyProductModel->setName($name);
					$rbyProductModel->setSku($sku);
					$rbyProductModel->setUrlKey($urlKey);
					$rbyProductModel->setShortDescription($desc);
					$rbyProductModel->setDescription($desc);
					$rbyProductModel->setPrice($price);				
					$rbyProductModel->setWeight(2);
					$rbyProductModel->setStatus(2);
					$rbyProductModel->setTaxClassId('None');
					$rbyProductModel->setPageLayout('one_column');					
					$rbyProductModel->setCategoryIds($catId);					
					$rbyProductModel->setAllow2dCustomizable(0);
					$rbyProductModel->setRoomStyle($roomStyle);
					$rbyProductModel->setRoomId($room_id);
					$rbyProductModel->setColorPreference($color);
					//$rbyProductModel->setXmlData($xmlData);					
					
					$rbyProductModel->addImageToMediaGallery($img, array('image','small_image'), false, false);
					$rbyProductModel->addImageToMediaGallery($thumb_img, array('thumbnail'), false, false);
					$saved = $rbyProductModel->save();
					$lastId = $saved->getId();
									
					//Product Stock
					$this->_saveStock($lastId, 10000);
					return $lastId ;
				} catch (Exception $e) {
					throw new Exception('Error: ' . $e->getMessage());
				}
	}
	
	private function _saveStock($lastId, $objProduct)
	{
			$stockItem = Mage::getModel('cataloginventory/stock_item');
		    $stockItem->loadByProduct($lastId);

		    if (!$stockItem->getId()) {
		        $stockItem->setProductId($lastId)->setStockId(1);
		    }
		    $stockItem->setData('is_in_stock', 1);
		    $savedStock = $stockItem->save();
		    $stockItem->load($savedStock->getId())->setQty($objProduct)->save();
	}
	
	/* public function get3dProductsLabelId($Id){
		$prefixForMagTable = self::$prefixForMagTable;
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$sqlSelect = "select value_id from  " . $prefixForMagTable ."_catalog_product_entity_media_gallery where entity_id=".$Id;
		$path = $read->fetchAll($sqlSelect);
		return $path;
	}

	public function update3dProductsLabel($Id){
		$prefixForMagTable = self::$prefixForMagTable;
		$label = array('large_image','small_image');
		$valueIds = $this->get3dProductsLabelId($Id);
		foreach($valueIds as $k=>$value):
		static $i=0;
		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$sqlUpdate = "update " . $prefixForMagTable ."_catalog_product_entity_media_gallery_value set label= '". $label[$i] ."' where value_id=". $value['value_id'];
		$write->query($sqlUpdate);
		$i++;
		if($i==2)break;		
		endforeach;		
	} */
	
	public function getProductIdByRoomId($roomId) {
		$prefixForMagTable = self::$prefixForMagTable;
		$sqlSelect = " Select product_id from " .
					$prefixForMagTable ."_customizer_data where " .
					$prefixForMagTable ."_customizer_data.room_id =". $roomId ."";		
		
		$rs = mysql_query($sqlSelect);
		if(mysql_num_rows($rs)>0){
			$row = mysql_fetch_row($rs);
			return $row[0];
		}
		else
			return 0;
	}
	
	public function getProductUrlById($pid=0, $cid=0) {
		$product = Mage::getModel('catalog/product')->load($pid);
		$_category = Mage::getModel('catalog/category')->load($cid);
		
		return $url = Mage::getBaseUrl().strtr($_category->getUrlPath(), array('.html'=>''))."/".$product->getUrlPath();
	}
	
	public function get3DProductsByRoomId($roomId) {
		$roomId = intval($roomId);
        if ($roomId == 0) {
            throw new Exception('Error');
        }
		$rooms = $this->_get3DProductsByRoomId($roomId);
		$returnVal = array();
		if (count($rooms['rooms']) > 0) {
			$returnVal['room'] = $rooms['rooms'][0];
		} else { 
			throw new Exception('No Products');
		}
		return $returnVal;
	}
	
	public function _get3DProductsByRoomId($roomId) {
		$prefixForMagTable = self::$prefixForMagTable;
		$sqlSelect = " Select room_id, user_id, thumbnail_path, data, room_title, product_id, is_precustomized from " .
					$prefixForMagTable ."_customizer_data where " .
					$prefixForMagTable ."_customizer_data.room_id =". $roomId ."";		
		
		$rs = mysql_query($sqlSelect);
		$numResults = mysql_num_rows($rs);
		$returnVal = array();
		$returnVal['rooms'] = array();
		for($k=0; $k < $numResults; $k++)
		{
			$rowTag = mysql_fetch_row($rs);
			$item = array();
			$item['room_id'] = $rowTag[0];
			$item['user_id'] = $rowTag[1];
			$item['thumbnail_path'] = $rowTag[2];
			$item['data'] = $rowTag[3];
			$item['room_title'] = $rowTag[4];
			$item['productid'] = $rowTag[5];
			$item['collection_id'] = $rowTag[6];
			$returnVal['rooms'][] = $item;
			
			$category = Mage::getModel("catalog/category")->load($item['collection_id']);						
			$designer = Mage::getModel('catalog/category')->load($category->getParentId());						
			$returnVal['designer_name'] = $designer->getName();	
			$returnVal['collection_name']=$category->getName();				
			$xmlData = $item['data'];
			$p = xml_parser_create();
			xml_parse_into_struct($p, $xmlData, $vals, $index);
			xml_parser_free($p);
			foreach($vals as $key=>$value):	
					if($value['tag']=='PRODUCT' && isset($value['value'])):						
						$product = Mage::getModel('catalog/product')->load($value['value']);
						
						$items[$key]['product_name']=$product->getName();
						$items[$key]['product_price']=$product->getPrice();						
						$items[$key]['pid']=$value['value'];						
						$items[$key]['did']=$vals[$key+1]['value'];
						$items[$key]['qty']=$vals[$key+3]['value'];
						$items[$key]['fabric']=$vals[$key+4]['value'];
						//$key+=3;
					endif;
			endforeach;
			$returnVal['xmlData'] = $items;
		}
		
		return $returnVal;
	}
	//add guest designs to reg user acct
	public function assignNewCategoryToProduct($sessionId, $oldId, $newCatId, $uid) {
		$catId = $oldId;
		//$result = array();
		$category = Mage::getModel("catalog/category"); 
		$category->load($catId); 
		try
		{
			$collection = $category->getProductCollection()->addAttributeToFilter('sku', array('like' => 'RBY-GU-'. $sessionId .'-'.'%'));
			//var_dump($collection);die;
				foreach ($collection as $product) { 
					 //echo $result[] = $product->getId()."<br/>";
					$sku = 'RBY-RU-'. $uid ."-" . ereg_replace('[^A-Za-z0-9.]', '-', now());					 
					if($product->getId()):
						Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
						$prod = Mage::getModel("catalog/product")->load($product->getId());				
						$prod->setSku($sku);
						$prod->setCategoryIds($newCatId);
						$prod->save();						
					endif;
				} 
			
		}catch (Exception $e) {
                throw new Exception('Error: ' . $e->getMessage());
         }
	}
	
	//get My Designs
	public function getUniqId(){
		if(isset($_SESSION['gu-id'])):
			$_SESSION['gu-id'] = $_SESSION['gu-id'];
		else:
			$_SESSION['gu-id']=  strtoupper(uniqid());					
		endif;
		return $_SESSION['gu-id'];
	}
	
	public function getMyDesigns($sku='') {
		if ($this->isLoggedIn() == false) {
			//throw new Exception(self::$loginError);
			$sku = 'RBY-GU-'. $this->getUniqId() .'-';
			$categoryName= "RBYGuest";
			
		}
		else{
				$userId = $this->getCustomerId();
			/* $prefixForLocalTable = self::$prefixForLocalTable;
			$prefixForMagTable = self::$prefixForMagTable; */
				
				$sku = 'RBY-RU-'. $userId .'-';
				$categoryName= "RBYUser";
				//add guest data into user acct
				if(isset($_SESSION['gu-id'])):
					$this->assignNewCategoryToProduct($_SESSION['gu-id'], $oldId='219', $newId='218', $userId);
					unset($_SESSION['gu-id']);
				endif;
		}	
			if (is_numeric($categoryName) == true) {
				$products_cat_id = intval($categoryName);
			} else {
				$products_cat_id = $this->getCategoryIdByName($categoryName);				
			}
			
		$collectionId = intval($products_cat_id);
		if ($collectionId == 0) {
			throw new Exception(self::$invalidCollectionId);
		}
		
		$tree = $this->getProducts($collectionId,$drep=1,$dpat=1,$dport='',$sortByDate=1,$sku);
		$returnVal = array();
		$returnVal['designs'] = $tree['products'];
		return $returnVal;
	}
	
	public function getMyDesignsOld($userId=0, $limit_lower=0, $pid=0)
	{
		if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		}
		$userId = $this->getCustomerId();
		$prefixForLocalTable = self::$prefixForLocalTable;
		$prefixForMagTable = self::$prefixForMagTable;
		
				$sku = 'RBY-RU-'. $userId .'-';
				$categoryName= "RBYUser";
			
			if (is_numeric($categoryName) == true) {
				$products_cat_id = intval($categoryName);
			} else {
				$products_cat_id = $this->getCategoryIdByName($categoryName);				
			}
			
			$_category = Mage::getModel('catalog/category')->load($products_cat_id);
			
			if ($_category->getId()=="" || $_category->getId()==0) {
				throw new Exception(self::$invalidCategoryId);
			}
			
			$visibility = array(
						  Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
						  Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE,
						  Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
					  );
					  
			$subs = $_category->getProductCollection()->addAttributeToSelect('*');
			$subs->addAttributeToFilter('visibility', $visibility)->addAttributeToSelect('e.entity_id');
			
			if($sku !='') $subs->addAttributeToFilter('sku', array('like' => $sku.'%'));
			$subs->addAttributeToSort('updated_at','desc');
			if($limit_lower!=0)$subs->getSelect()->where('e.entity_id > '. $pid .'')->limit($limit_lower);			
			

		$products = $subs;
		
		$result = array();
		
		foreach($products as $product)
		{   
			$result[] = array('name'=>$product->getName(), 'description'=>$product->getDescription(), 'small_image_url'=>$this->getImageByLabel($product->getId(), '10x10'), 'id'=>$product->getId(), 'title'=>$product->getAttributeText('image_tag'), 'large_image_url'=>Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)."catalog/product".$product->getImage(), "delete_url"=>"/uploadartwork/customerdesigns/delete/id/".$product->getId(), "view_url"=>"/uploadartwork/customerdesigns/view/id/".$product->getId(), 'portrate_img'=>$this->getImageByLabel($product->getId(), '24x36')); 
		}
		return $result;		
	}
	// get imageBy Label
	public function getImageByLabel($pid, $type="") {
		$rsDesign =  Mage::getModel('catalog/product')->load($pid); 
		
		if(isset($rsDesign["status"]) && $rsDesign["status"] == "1"){
			$arry = $rsDesign["media_gallery"]["images"];$list_file="";
			$large_image="";$large_file="";	$small_file="";	$file10x10='';$file24x36='';			
			for($e=0; $e < count($arry); $e++ )
			{				
				if($arry[$e]["label"]=="large_image"){
				$large_file = '/media/catalog/product'.$arry[$e]["file"];							
				}
				if($arry[$e]["label"]=="small_image"){
				$small_file = '/media/catalog/product'.$arry[$e]["file"];							
				}
				if($arry[$e]["label"]=="10x10"){
				$file10x10 = '/media/catalog/product'.$arry[$e]["file"];							
				}
				if($arry[$e]["label"]=="20x20"){
				$medium_file = '/media/catalog/product'.$arry[$e]["file"];							
				}
				if($arry[$e]["label"]=="24x24"){
				$list_file = '/media/catalog/product'.$arry[$e]["file"];					
				}
				if($arry[$e]["label"]=="24x36"){
				$file24x36 = '/media/catalog/product'.$arry[$e]["file"];					
				}
			}
			if($type=='10x10')
				return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).$file10x10;
			if($type=='24x36')
				return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).$file24x36;
			else
				return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).$large_file;
		}		
	}
	// delete My design
	public function deleteMyDesigns($id)
    {
		if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		}
		Mage :: app( "default" ) -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );
		
		$id = intval($id);		
		if ($id) {
				$product = Mage::getModel('catalog/product')
                ->load($id);            
			try {
                $product->delete();
               // $this->_getSession()->addSuccess($this->__('Design deleted successfully'));
            }
            catch (Exception $e) {
                throw new Exception('Invalid id. Error: ' . $e->getMessage());
            }
		}		 
		return $this->getMyDesigns();
    }
	
	public function makeArtworkPublic($pid)
    {
		////return $pid;
		try 
		{
			Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
			$product = Mage::getModel('catalog/product')->load($pid);
			$product->setIsPublic(1);		
			$product->save();
			return true;
		}
		catch (Exception $e) {
			throw new Exception('Invalid id. Error: ' . $e->getMessage());
		}
	}
	
	public function update3dProduct($pid, $color='', $room_title='', $room_comment='')
    {
		////return $pid;update3dProduct($productId, $color, $room_title, $room_comment)
		try 
		{
			Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
			$product = Mage::getModel('catalog/product')->load($pid);
			if($color != '')$product->setColorPreference($color);	
			if($room_title != '')$product->setName($room_title);		
			if($room_comment != '')$product->setShortDescription($room_comment);		
			if($room_comment != '')$product->setDescription($room_comment);		
			$product->save();
			return true;
		}
		catch (Exception $e) {
			throw new Exception('Invalid pid. Error: ' . $e->getMessage());
		}
	}
	//service for serch
	public function getSearchResultCollection($text='', $cat_id=0, $type='') {
			// if (is_numeric($categoryName) == true) {
				// $products_cat_id = intval($categoryName);
			// } else {
				// $products_cat_id = $this->getCategoryIdByName($categoryName);				
			// }
			
		/* $collectionId = intval($cat_id);$returnVal = array();
		if ($collectionId == 0) {
			throw new Exception(self::$invalidCollectionId);
		} */
		
		switch($type){
			case "products":
			$tree = $this->getProducts($categoryName="Products",$drep=1,$dpat=1,$dport='',$sortByDate=1,$sku="");			
			$returnVal['products'] = $tree['products'];
			break;
			
			case "designs":
			$tree = $this->getProducts($collectionId,$drep=1,$dpat=1,$dport='',$sortByDate=1,$sku='');
			$returnVal['designs'] = $tree['products'];
			break;
			
			case "rooms":
			$tree = $this->getRoomSearch($text,$text,$text,$text);
			$returnVal['rooms'] = $tree['rooms'];
			break;
			
			default:
			$tree = $this->getProducts($collectionId,$drep=1,$dpat=1,$dport='',$sortByDate=1,$sku='');
			$returnVal['designs'] = $tree['products'];
			break;

		}
		return $returnVal;
	}
	
	public function getRoomSearch($text='', $room_type='', $room_style='', $content_type='Room', $limit=10, $page=0) {
		/* $roomId = intval($roomId);
		if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		}
		$UID = $this->getCustomerId(); */
		$prefixForMagTable = self::$prefixForMagTable;
		$sqlSelect = " Select room_id, user_id, sharing_code, image_path, " .
					" thumbnail_path, created_at, updated_at, is_shared, data, room_title, room_comment, roomtype, roomstyle, roomview , content_type, design_id, product_id from " .
					$prefixForMagTable ."_customizer_data where content_type='". $content_type ."'";
		
		if ($text!='') {
			$sqlSelect .= " and room_title like '%" . $text ."%' or ".
						  "room_comment like '%" . $text ."%' or ".
						  "roomtype like '%" . $room_type ."%' or ".
						  "roomstyle like '%" . $room_style ."%' ";
		}
		
		$rs = mysql_query($sqlSelect);
		$numResults = mysql_num_rows($rs);
		$returnVal = array();
		$returnVal['rooms'] = array();
		for($k=0; $k < $numResults; $k++)
		{
			$rowTag = mysql_fetch_row($rs);
			$item = array();
			$item['room_id'] = $rowTag[0];
			$item['user_id'] = $rowTag[1];
			$item['sharing_code'] = $rowTag[2];
			$item['image_path'] = $rowTag[3];
			$item['thumbnail_path'] = $rowTag[4];
			$item['created_at'] = $rowTag[5];
			$item['updated_at'] = $rowTag[6];
			$item['is_shared'] = $rowTag[7];
			$item['data'] = $rowTag[8];
			$item['room_title'] = $rowTag[9];
			$item['room_comment'] = $rowTag[10];
			$item['roomtype'] = $rowTag[11];
			$item['roomstyle'] = $rowTag[12];
			$item['roomview'] = $rowTag[13];
			$item['contenttype'] = $rowTag[14];			
			$item['designid'] = $rowTag[15];
			$item['productid'] = $rowTag[16];
			$returnVal['rooms'][] = $item;
		}
		
		return $returnVal;
	}
}
?>