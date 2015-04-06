<?php
include_once("ThumbnailImage.php");
require_once 'Zend/Cache.php';
require_once 'Zend/Cache/Backend/ExtendedInterface.php';
require_once 'Zend/Cache/Backend.php';
require_once 'Zend/Cache/Backend/ZendPlatform.php';
class CS_Mservice_Main
{

	static $defaultProductImage = "/media/customizer/no-Event.png";
	static $prefixForLocalTable = "cs";
	static $prefixForMagTable = "csm";
	static $EventImageFolder = "csusers";
	static $EventVideoFolder = "csusers";
	static $EventRecordingFolder = "csusers";
	static $productImageUrl = "";
	static $defaultEventImage = "/upload/blank.jpg";
	static $proflieUrlPrefix = "/csprofile/index/view/id/";
	
	static $notYourProduct = "Invalid Product or Product Owner";
	static $loginError = "Invalid Login or Session Timed Out";
	static $invalidLogin = "Invalid username or password";
	static $invalidShareWithList = "Invalid share with list";
	static $maxRecordCount = 20;
    static $caching = true;
    static $automatic_serialization = true;
    static $cacheDirectory = './var/csservice/';
    static $cacheLifetime = 7200; // 2 hours (in seconds) - this is default
    static $cacheShortTimeSpan = 1200; // 15 minutes (in seconds)
    static $cacheMediumTimeSpan = 2400; //45 minutes (in seconds)
    static $cacheOneDaySpan = 86400; //1 day (in seconds)
    static $cacheFiveDaysSpan = 432000; //5 days (in seconds)
    static $cacheLongTimeSpan = 28800; // 8 hours (in seconds)
    static $major = 1;
    static $minor = 2;
    static $fixed_ver = 1;
    private $isMailTransportActive = false;
    private $transport = "";

    private static $init_max_attendees = 0;

    public function Main()
    {

    }
    public function loadCache($lifetime = null) {
        if ($lifetime == null) {
            $lifetime = self::$cacheLifetime;
        }
        //
        $frontendOptions = array('caching' => self::$caching,
            'automatic_serialization' => self::$automatic_serialization,
            'lifetime' => $lifetime,
            'cache_id_prefix' => 'csservice',
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
        if ($secureCall != 'cs') {
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
	
	public function getToken($appid, $buildnum=0, $seckey="", $user_current_ver="1.0", $user_id=0){
		if($seckey == ""){
			$result = array();
			$major = self::$major;
			$minor = self::$minor;
			$fixed_ver = self::$fixed_ver;
			$result['response'] = "qpOjpKibmqqepA==";
			$result['major'] = $major;
			$result['minor'] = $minor;
			$result['fixed_ver'] = $fixed_ver;
			return $result;
		}
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$xapplication_token = $resource->getTableName('xapplication_token');
		$appid = mysql_real_escape_string($appid);
		$buildnum = (int)mysql_real_escape_string($buildnum);
		
		$xapplication = $resource->getTableName('xapplication');
		$rs_xapplication = $read->fetchRow("SELECT * FROM $xapplication WHERE appid='".$appid."' AND buildnum<=$buildnum");
		if($rs_xapplication['id']>0){
			//$token = hash_hmac('sha256', $appid, $rs_xapplication['secret']);
			//$token = $this->encrypt($appid,$rs_xapplication['secret']);
			$token1 = $this->getRandomString(15);
			$token = $seckey.$token1;
			$rs = $read->fetchRow("SELECT * FROM $xapplication_token WHERE user_id=".$user_id." and appid='".$appid."' and security_key='".$seckey."'");
			if(count($rs['id'])>0){
				$write->query("UPDATE $xapplication_token SET token='".$token1."', updated_at=now() WHERE user_id=".$user_id." and appid='".$appid."' and security_key='".$seckey."'");
			}else{
				$write->query("INSERT INTO $xapplication_token SET user_id=".$user_id.", token='".$token1."', appid='".$appid."',security_key='".$seckey."', created_at=now()");
			}
			$result = array();
			
			$result['response'] = $token1;
			$result['major'] = self::$major;
			$result['minor'] = self::$minor;
			$result['fixed_ver'] = self::$fixed_ver;
			return $result;
		}else{
			return 'Error:003';
		}
	}
	protected function encrypt($string, $key='%key&') {
		$result = '';
		for($i=0; $i<strlen($string); $i++) {
			$char = substr($string, $i, 1);
			$keychar = substr($key, ($i % strlen($key))-1, 1);
			$ordChar = ord($char);
			$ordKeychar = ord($keychar);
			$sum = $ordChar + $ordKeychar;
			$char = chr($sum);
			$result.=$char;
		}
		return base64_encode($result);
	}

	protected function decrypt($string, $key='%key&') {
		$result = '';
		$string = base64_decode($string);
		for($i=0; $i<strlen($string); $i++) {
			$char = substr($string, $i, 1);
			$keychar = substr($key, ($i % strlen($key))-1, 1);
			$ordChar = ord($char);
			$ordKeychar = ord($keychar);
			$sum = $ordChar - $ordKeychar;
			$char = chr($sum);
			$result.=$char;
		}
		return $result;
	}
	
	public function isValidToken($accesstoken,$user_id=0){
		if($accesstoken == "qpOjpKibmqqepA=="){
			return 1;
		}
		if($user_id > 0){
		$seckey = substr($accesstoken,0,24);
		$token = substr($accesstoken,24);
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$xapplication_token = $resource->getTableName('xapplication_token');
		$rs = $read->fetchAll("SELECT * FROM $xapplication_token WHERE user_id=".$user_id." and token='".$token."' and security_key='".$seckey."'");
		$result = array();
		$result['response'] = 1;
		$result['major'] = self::$major;
		$result['minor'] = self::$minor;
		$result['fixed_ver'] = self::$fixed_ver;
		if(count($rs) > 0)
			return $result;
		} else{
			return 0;
		}
	}
	
	public function setTwitterPost(bool $val) {
		$user_id = $this->getLoggedInUserId();
		if ($user_id<=0)
			throw new Exception(self::$loginError);
		$returnVal = array();
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$customer->setStwitter($val);
		$customer->save();
		$returnVal['isLoggedIn'] = true;
		$returnVal['twitterPost'] = $val;
		$returnVal['userId'] = $user_id;
		return $returnVal;
	}
	public function setFacebookPost(bool $val) {
		$user_id = $this->getLoggedInUserId();
		if ($user_id<=0)
			throw new Exception(self::$loginError);
		$returnVal = array();
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$customer->setSfacebook($val);
		$customer->save();
		$returnVal['isLoggedIn'] = true;
		$returnVal['facebookPost'] = $val;
		$returnVal['userId'] = $user_id;
		return $returnVal;
	}
	
	public function getUserInfo($user_id=0,$widgetid=0){
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$returnVal = array();$zero = 0;
		Mage::getSingleton('core/session', array('name'=>'frontend'));
		if($user_id>0){
		    $returnVal['isLoggedIn'] = $this->isLoggedIn();  
			$returnVal['userId'] = $customer->getId();
			$returnVal['view']=Mage::getModel('csservice/csservice')->getCheckinCount($user_id);
			$returnVal['userName'] = $customer->getUsername();
			$returnVal['email'] = $customer->getEmail();
			$returnVal['shortbio'] = $customer->getShortbio();
			if($customer->getVerifiedUser()==1){
				$returnVal['isVerified'] = 1;
			}else{
				$returnVal['isVerified'] = 0;
			}
			$returnVal['location'] = $customer->getLocation();
			$returnVal['awayBanner'] = $customer->getAwayBanner();			
			$returnVal['profile_url'] =  Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).$customer->getUsername();
			$returnVal['firstName'] = $customer->getFirstname();
			$returnVal['lastName'] = $customer->getLastname();
			$returnVal['realname'] = $customer->getFirstname()." ".$customer->getLastname();		 
			$returnVal['profilePicture'] = $this->getProfilePic($user_id);
			$returnVal['profilePicture_30x30'] = $this->getProfilePic($customer->getId());
			$returnVal['canvasImage'] = "http://chattrspace.s3.amazonaws.com/user_bgimages/bgimage".$customer->getBgimage();
			$returnVal['followersCount'] = $this->getFollowersCount($user_id);
			
			if($customer->getTwitterId())
					$returnVal['twitterId'] = intval($customer->getTwitterId());
				else
					$returnVal['twitterId'] = intval($zero);
			$returnVal['twitterUsername'] = $customer->getTwitterUsername();
			
			if($notice = $customer->getStwitter()){
			$a = explode(",",$notice);	
			if(in_array(12,$a)){
				$returnVal['twitterCommentPost'] = 1;
			}else{
				$returnVal['twitterCommentPost'] = 0;
			}
			if(in_array(168,$a)){
				$returnVal['twitterRecordings'] = 1;
			}
			else{
				$returnVal['twitterRecordings'] = 0;
			}
			if(in_array(173,$a)){
				$returnVal['twitterCheckIn'] = 1;
			}
			else{
				$returnVal['twitterCheckIn'] = 0;
			}
			
			}else {
				$returnVal['twitterCommentPost'] = 0;
				$returnVal['twitterRecordings'] = 0;
				$returnVal['twitterCheckIn'] = 0;
			}
					
			if($notice = $customer->getSfacebook()){
			$a = explode(",",$notice);	
			if(in_array(9,$a)){
				$returnVal['facebookCommentPost'] = 1;
			}else{
				$returnVal['facebookCommentPost'] = 0;
			}
			if(in_array(167,$a)){
				$returnVal['facebookRecordings'] = 1;
			}
			else{
				$returnVal['facebookRecordings'] = 0;
			}
			if(in_array(172,$a)){
				$returnVal['facebookCheckIn'] = 1;
			}
			else{
				$returnVal['facebookCheckIn'] = 0;
			}
			}else {
				$returnVal['facebookCommentPost'] = 0;
				$returnVal['facebookRecordings'] = 0;
				$returnVal['facebookCheckIn'] = 0;
			}
				
			$resource = Mage::getSingleton('core/resource');
			$write = $resource->getConnection('core_write');
			$read = $resource->getConnection('core_read');
			$widget_fb_reg = $resource->getTableName('widget_fb_reg');
			$rs = $read->fetchRow("SELECT * FROM $widget_fb_reg WHERE uid='".$customer->getId()."' AND widgetid=".$widgetid);
			if($rs['uid']>0)
				$returnVal['facebookId'] = intval($rs['fbid']);
			else
				$returnVal['facebookId'] = intval($zero);
			$returnVal['facebookUsername'] = $customer->getFacebookUsername();
			
			$returnVal['youtubename'] = $customer->getYoutubename();
						
			$privacy = $customer->getPrivacy();
			$a = explode(",",$privacy);	
			if(in_array(165,$a))
				$disable_comment = true;							
			else
				$disable_comment = false;
				
			if(in_array(166,$a))
				$disable_record = true;							
			else
				$disable_record = false;
			
			$returnVal['disable_comment']= $disable_comment;
			$returnVal['disable_record']= $disable_record;
			
			$returnVal['userAuthToken']= $customer->getUserAuthToken();
			$returnVal['wms_uri']= $this->getWMSURL($customer->getId());
			
			$sfacebook = $customer->getSfacebook();
			$c = explode(',',$sfacebook);
			if(in_array('167',$c,true)){
				$videoRecordingFBPost="true";
			} else{
				$videoRecordingFBPost="false";
			}
			
			if($customer->getYoutubeToken()){
				$youtubeLink="true";
			} else{
				$youtubeLink="false";
			}
						
			if($customer->getTwitterId()){
				$twitterLink="true";
				$sTwitter = $customer->getStwitter();
				$t = explode(',',$sTwitter);
				if(in_array('168',$t,true)){
					$videoRecordingTWTPost="true";
				} else{
					$videoRecordingTWTPost="false";
				}
			} else {
				$twitterLink="false";
				$videoRecordingTWTPost="false";
			}
			
			if($customer->getYoutubeVideoStream() == 1){
				$returnVal['videoRecordingYTPost'] = "true";
			} else {
				$returnVal['videoRecordingYTPost'] = "false";	
			}
			$returnVal['videoRecordingFBPost'] = $videoRecordingFBPost;
			$returnVal['videoRecordingTWTPost'] = $videoRecordingTWTPost;
			$returnVal['twitterLink'] = $twitterLink;
			$returnVal['youtubeLink'] = $youtubeLink;
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$widget_fb_reg = $resource->getTableName('widget_fb_reg');
			$rs = $read->fetchRow("SELECT * FROM $widget_fb_reg WHERE uid='".$user_id."'");
			if((count($rs[id])) && ($user_id > 0)){
				$linkFB="true";
			}else{
				$linkFB="false";
			}
			$returnVal['fbLink'] = $linkFB;
			$returnVal['TimeZone'] = $customer->getTimezone();
		}else{
			$returnVal['isLoggedIn'] = $this->isLoggedIn();
			$customer = Mage::getModel('customer/customer')->load($this->getUserId());
			$returnVal['userId'] = $customer->getId();
			$returnVal['view']=Mage::getModel('csservice/csservice')->getCheckinCount($customer->getId());
			$returnVal['userName'] = $customer->getUsername();
			$returnVal['email'] = $customer->getEmail();
			$returnVal['shortbio'] = $customer->getShortbio();
			if($customer->getVerifiedUser()==1){
				$returnVal['isVerified'] = 1;
			}else{
				$returnVal['isVerified'] = 0;
			}
			$returnVal['location'] = $customer->getLocation();
			$returnVal['awayBanner'] = $customer->getAwayBanner();			
			$returnVal['profile_url'] =  Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).$customer->getUsername();
			$returnVal['firstName'] = $customer->getFirstname();
			$returnVal['lastName'] = $customer->getLastname();
			$returnVal['realname'] = $customer->getFirstname()." ".$customer->getLastname();		 
			$returnVal['profilePicture'] = $this->getProfilePic($customer->getId());
			$returnVal['profilePicture_30x30'] = $this->getProfilePic($customer->getId());
			$returnVal['canvasImage'] = "http://chattrspace.s3.amazonaws.com/user_bgimages/bgimage".$customer->getBgimage();
			$returnVal['followersCount'] = $this->getFollowersCount($customer->getId());
			
			if($customer->getTwitterId())
					$returnVal['twitterId'] = intval($customer->getTwitterId());
				else
					$returnVal['twitterId'] = intval($zero);
			$returnVal['twitterUsername'] = $customer->getTwitterUsername();
			
			if($notice = $customer->getStwitter()){
			$a = explode(",",$notice);	
			if(in_array(12,$a)){
				$returnVal['twitterCommentPost'] = 1;
			}else{
				$returnVal['twitterCommentPost'] = 0;
			}
			if(in_array(168,$a)){
				$returnVal['twitterRecordings'] = 1;
			}
			else{
				$returnVal['twitterRecordings'] = 0;
			}
			if(in_array(173,$a)){
				$returnVal['twitterCheckIn'] = 1;
			}
			else{
				$returnVal['twitterCheckIn'] = 0;
			}
			
			}else {
				$returnVal['twitterCommentPost'] = 0;
				$returnVal['twitterRecordings'] = 0;
				$returnVal['twitterCheckIn'] = 0;
			}
					
			if($notice = $customer->getSfacebook()){
			$a = explode(",",$notice);	
			if(in_array(9,$a)){
				$returnVal['facebookCommentPost'] = 1;
			}else{
				$returnVal['facebookCommentPost'] = 0;
			}
			if(in_array(167,$a)){
				$returnVal['facebookRecordings'] = 1;
			}
			else{
				$returnVal['facebookRecordings'] = 0;
			}
			if(in_array(172,$a)){
				$returnVal['facebookCheckIn'] = 1;
			}
			else{
				$returnVal['facebookCheckIn'] = 0;
			}
			}else {
				$returnVal['facebookCommentPost'] = 0;
				$returnVal['facebookRecordings'] = 0;
				$returnVal['facebookCheckIn'] = 0;
			}
				
			$resource = Mage::getSingleton('core/resource');
			$write = $resource->getConnection('core_write');
			$read = $resource->getConnection('core_read');
			$widget_fb_reg = $resource->getTableName('widget_fb_reg');
			$rs = $read->fetchRow("SELECT * FROM $widget_fb_reg WHERE uid='".$customer->getId()."' AND widgetid=".$widgetid);
			if($rs['uid']>0)
				$returnVal['facebookId'] = intval($rs['fbid']);
			else
				$returnVal['facebookId'] = intval($zero);
			$returnVal['facebookUsername'] = $customer->getFacebookUsername();
			
			$returnVal['youtubename'] = $customer->getYoutubename();
						
			$privacy = $customer->getPrivacy();
			$a = explode(",",$privacy);	
			if(in_array(165,$a))
				$disable_comment = true;							
			else
				$disable_comment = false;
				
			if(in_array(166,$a))
				$disable_record = true;							
			else
				$disable_record = false;
			
			$returnVal['disable_comment']= $disable_comment;
			$returnVal['disable_record']= $disable_record;
			
			$returnVal['userAuthToken']= $customer->getUserAuthToken();
			$returnVal['wms_uri']= $this->getWMSURL($customer->getId());
			
			$sfacebook = $customer->getSfacebook();
			$c = explode(',',$sfacebook);
			if(in_array('167',$c,true)){
				$videoRecordingFBPost="true";
			} else{
				$videoRecordingFBPost="false";
			}
			
			if($customer->getYoutubeToken()){
				$youtubeLink="true";
			} else{
				$youtubeLink="false";
			}
						
			if($customer->getTwitterId()){
				$twitterLink="true";
				$sTwitter = $customer->getStwitter();
				$t = explode(',',$sTwitter);
				if(in_array('168',$t,true)){
					$videoRecordingTWTPost="true";
				} else{
					$videoRecordingTWTPost="false";
				}
			} else {
				$twitterLink="false";
				$videoRecordingTWTPost="false";
			}
			if($customer->getYoutubeVideoStream() == 1){
				$returnVal['videoRecordingYTPost'] = "true";
			} else {
				$returnVal['videoRecordingYTPost'] = "false";	
			}
			$returnVal['videoRecordingFBPost'] = $videoRecordingFBPost;
			$returnVal['videoRecordingTWTPost'] = $videoRecordingTWTPost;
			$returnVal['twitterLink'] = $twitterLink;
			$returnVal['youtubeLink'] = $youtubeLink;
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$widget_fb_reg = $resource->getTableName('widget_fb_reg');
			$rs = $read->fetchRow("SELECT * FROM $widget_fb_reg WHERE uid='".$customer->getId()."'");
			if((count($rs[id])) && ($customer->getId() > 0)){
				$linkFB="true";
			}else{
				$linkFB="false";
			}
			$returnVal['fbLink'] = $linkFB;
			$returnVal['TimeZone'] = $customer->getTimezone();
		}
		return $returnVal;
	}
	
	public function getWMSURL($profile_id){
		if ($profile_id==3 || $profile_id==14288)
			return "rtmp://origin-vevo-00.oncam.com";
		else
			return "rtmp://wms.oncam.com";
	}
	public function getFollowersById($user_id, $current_user_id=0, $status=1, $notify=0,$page=1) {
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$follower = $resource->getTableName('follower');
			$customer_entity = $resource->getTableName('customer_entity');
			
			$select = "select id, follower_id, follow, status, follow_on, notify,(select count(DISTINCT follower_id) from $follower, $customer_entity WHERE follow=".$user_id." and follower_id<>follow and status=".$status." and $customer_entity.entity_id=$follower.follower_id) as count from $follower, $customer_entity WHERE follow=".$user_id." and follower_id<>follow and status=".$status." and $customer_entity.entity_id=$follower.follower_id";
			//$selectcount = "select count(DISTINCT follower_id) as count from $follower, $customer_entity WHERE follow=".$user_id." and follower_id<>follow and status=".$status." and $customer_entity.entity_id=$follower.follower_id";
			$select.=" group by follower_id order by id desc";
			$limit = 15;
			if($page<=0)
				$page=1;
			$page=$page-1;
			if($limit!=0)
				$select.= " limit ".$limit*$page .", " .$limit;
			$follower = $read->fetchAll($select);
			foreach($follower as $k=>$flwr){
					$customer = Mage::getModel('customer/customer')->load($flwr['follower_id']);
					$username = $customer->getUsername();
					$thumbimage = $this->getProfilePic($flwr['follower_id']);
					if($current_user_id > 0){
						$isfollow = $this->isFollow($flwr['follower_id'],$current_user_id);
					
						if($isfollow == 1){
							$isfolow = "true";
						} else {
							$isfolow = "false";
						}
					}
					$item[$k]=array(
						'id'=> $flwr['id'],
						'username'=>$username,
						'name'=>$customer->getFirstname()." ".$customer->getLastname(),
						'user_id'=>$flwr['follower_id'],
						//'short_bio'=>$customer->getShortbio(),
						//'views'=>Mage::getModel('csservice/csservice')->getCheckinCount($flwr['follower_id']),
						//'followerCount'=>$this->getFollowersCount($flwr['follower_id']),
						'thumbimage'=>$thumbimage,
						'notify'=> $flwr['notify'],
						'follow_on'=> $flwr['follow_on'],
						'isFollow'=> $isfolow
					); 
				$count = $flwr['count'];
			}
			if(count($follower)>0){
				$result=array();	
				$result['followers'] = $item;
				$result['count'] = $count;
				if($count > ($limit*($page+1))){
					$result['showMore'] = "true";
				} else {
					$result['showMore'] = "false";
				}
				return $result;
			}
			else{
				$result=array();
				if($count > ($limit*($page+1))){
					$result['showMore'] = "true";
				} else {
					$result['showMore'] = "false";
				}
				return $result;
			}	
	}
	
	public function getWidgetInfoByAuthToken($widget_id=0) {
		$result = array();
		//Mage::getSingleton('core/session', array('name'=>'frontend'));
		//return $this->isLoggedIn();
		if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		} 
		$user_id = $this->_getSession()->getCustomerId();
		$result['userInfo'] = $this->getUserInfo($user_id);
		$result['widgetInfo'] = '';
		if($widget_id>0){
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$widget = $resource->getTableName('widget_info');
			$select = "select $widget.* from $widget WHERE widget_id=$widget_id and is_default=1";
			$rs = $read->fetchRow($select);
			//print_r($rs);
			$numResults = count($rs);
			if($numResults > 0){ 
				$widget_user_id = $rs["user_id"];
				$result['widgetInfo'] = $this->getUserInfo($widget_user_id);
				
			}
		}
		
		//return $user_id = $this->_getSession()->getCustomerId();;
		/* if(!empty($token)){
			//$customer = Mage::getModel('customer/customer')->load($user_id);
			$decoded_token = $this->decode($token);
			//die;
			$encryptedSessionId = Mage::getModel("core/session")->getEncryptedSessionId();
			//if($customer->getUserAuthToken()==$token){
			if($encryptedSessionId == $decoded_token){
				$user_id = $this->_getSession()->getCustomerId();
				$result['userInfo'] = $this->getUserInfo($user_id);
				$result['widgetInfo'] = '';
				if($widget_id>0){
					$resource = Mage::getSingleton('core/resource');
					$read= $resource->getConnection('core_read');
					$widget = $resource->getTableName('widget_info');
					$select = "select $widget.* from $widget WHERE widget_id=$widget_id and is_default=1";
					$rs = $read->fetchRow($select);
					//print_r($rs);
					$numResults = count($rs);
					if($numResults > 0){ 
						$widget_user_id = $rs["user_id"];
						$result['widgetInfo'] = $this->getUserInfo($widget_user_id);
						
					}
				}
			}
			//else
				//return false;
		} */
		return $result;
	}
	
	public function checkUserLoggedInByAuthToken($token='') {
		if(!empty($token)){
			$customer = Mage::getModel('customer/customer')->load($user_id);
			$decoded_token = $this->decode($token);
			$encryptedSessionId = Mage::getModel("core/session")->getEncryptedSessionId();
			//if($customer->getUserAuthToken()==$token){
			if($encryptedSessionId == $decoded_token){
				return true;
			}
			else
				return false;
		}
		return false;
	}
	
	public function isLoggedIn() {
		Mage::getSingleton('core/session', array('name'=>'frontend'));
        $returnVal = Mage::getSingleton( 'customer/session' )->isLoggedIn();
        return $returnVal;
    } 
	
	/* public function isLoggedIn($user_id=0) {
		Mage::getSingleton('core/session', array('name'=>'frontend'));
        $returnVal = Mage::getSingleton( 'customer/session' )->isLoggedIn();
        //return $returnVal;
		if($this->getUserId() != -1)
			return true;
		else
			return false;        
    }  */

    public function getUserId(){
		Mage::getSingleton('core/session', array('name'=>'frontend'));
		if (Mage::getSingleton('customer/session')->isLoggedIn() == false) {
			return -1;
		}
        $returnVal = Mage::getSingleton( 'customer/session' )->getCustomerId();
        return $returnVal;
    }
	public function getWidgetLogToken($token) {
		$resource = Mage::getSingleton('core/resource');
		$read  = $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$widget_user_log = $resource->getTableName('widget_user_log');
		$rs=$read->fetchRow("select * from $widget_user_log Where fc_code='".$token."' AND status=1");
		$returnVal = array();
		if (count($rs['uid'])>0) {
			$customer = Mage::getModel('customer/customer');
			$collection = $customer->getCollection()
					->addAttributeToFilter('entity_id', (string)$rs['uid'])
					->setPageSize(1);
			$existingCustomer = $collection->getFirstItem();
			$session = Mage::getSingleton("customer/session");
			$session->setCustomer($existingCustomer);
			$session->setCustomerAsLoggedIn($existingCustomer);
			$write->query("UPDATE $widget_user_log SET status=0 WHERE fc_code='".$token."' AND status=1");
			$returnVal['userInfo'] = $this->getUserInfo(0);
			return $returnVal;
		} else {
			return 0;
		}
	}
	public function getProfilePic($user_id){
		//return "http://chattrspace.s3.amazonaws.com/profileimgbyid/30x30/".$user_id.".png";
		
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$filename = 'profiles/128x128/'.$customer->getProfilePicture();
		$filename48 = 'profiles/48x48/'.$customer->getProfilePicture();
			//$ext = end(explode('.', basename($filename)));
			$img_url = "http://chattrspace.s3.amazonaws.com/" .$filename;
			$img_url48 = "http://chattrspace.s3.amazonaws.com/" .$filename48;
			if(fopen($img_url,"r")==true){
				$filename = $filename;
				return "http://chattrspace.s3.amazonaws.com/" .$filename;
			} else if(fopen($img_url48,"r")==true){
				//$filename = $filename;
				return "http://chattrspace.s3.amazonaws.com/" .$filename48;
			}else{
				//$cust_id = (($customer->getId())%10).".jpg";
				$fname=$customer->getFirstname();
				$fchar=strtolower(substr($fname,0,1));
				$alphabet="abcdefghijklmnopqrstuvwxyz0123456789";
				$position = strpos($alphabet,$fchar);
				if($position != ""){
					$filename = 'http://chattrspace.s3.amazonaws.com/default/30x30/'.$fchar.'.jpg';
				}
				else{
					$filename = 'http://chattrspace.s3.amazonaws.com/default/30x30/a.jpg';
				}
			}
        return $filename; 
    }
	
	public function getProfilePic48($user_id){
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$filename = 'profiles/128x128/'.$customer->getProfilePicture();
		$filename48 = 'profiles/48x48/'.$customer->getProfilePicture();
			//$ext = end(explode('.', basename($filename)));
			$img_url = "http://chattrspace.s3.amazonaws.com/" .$filename;
			$img_url48 = "http://chattrspace.s3.amazonaws.com/" .$filename48;
			if(fopen($img_url48,"r")==true){
				return "http://chattrspace.s3.amazonaws.com/" .$filename48;
			} else if(fopen($img_url,"r")==true){
				//$filename = $filename;
				return "http://chattrspace.s3.amazonaws.com/" .$filename;
			}else{
				//$cust_id = (($customer->getId())%10).".jpg";
				$fname=$customer->getFirstname();
				$fchar=strtolower(substr($fname,0,1));
				$alphabet="abcdefghijklmnopqrstuvwxyz0123456789";
				$position = strpos($alphabet,$fchar);
				if($position != ""){
					$filename = 'http://chattrspace.s3.amazonaws.com/default/30x30/'.$fchar.'.jpg';
				}
				else{
					$filename = 'http://chattrspace.s3.amazonaws.com/default/30x30/a.jpg';
				}
			}
        return $filename;
    }
	
	public function getLoggedInUserId(){
		return $this->getUserId();
	}

	public function getUserGroupId(){
        $returnVal = Mage::getSingleton( 'customer/session' )->getCustomerGroupId();

        return $returnVal;
    }
	
	public function getUserEmail(){
		//getAttributes
		//Mage::getSingleton('core/session', array('name'=>'frontend'));
		if (Mage::getSingleton( 'customer/session' )->isLoggedIn() == false) {
			return 0;
		}
		return htmlspecialchars(Mage::getSingleton( 'customer/session' )->getCustomer()->getEmail());
	}
    
	public function getProfilePicture(){
		//getAttributes
		//Mage::getSingleton('core/session', array('name'=>'frontend'));
		if (Mage::getSingleton( 'customer/session' )->isLoggedIn() == false) {
			return 0;
		}
		$profilePicture = Mage::getSingleton( 'customer/session' )->getCustomer()->getProfilePicture();
		$profilePicture = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'chattrspace/'.$profilePicture;
		return htmlspecialchars($profilePicture);
	}
	public function getUserNameByUserId($user_id=0){
		$customer = Mage::getSingleton( 'customer/customer' )->load($user_id);
		$username = $customer->getUsername();
        return $username;
    }
	public function getUserName(){
		//Mage::getSingleton('core/session', array('name'=>'frontend'));
        if (Mage::getSingleton( 'customer/session' )->isLoggedIn()) {
            $returnVal = Mage::helper( 'customer/data' )->getCustomerName();
        } else {
            $returnVal = "Guest";
        }
        return $returnVal;
    }
        
	public function login($email, $password,$device_id=0,$imei=0,$type="",$accesstoken=""){
        $returnValLogin = Mage::getSingleton( 'customer/session' )->login($email, $password);
		$returnVal = array();
		$returnVal['result'] = $returnValLogin;
		if ($returnValLogin == true) {
			$user_id = Mage::getSingleton( 'customer/session' )->getCustomerId();
			$deactivecustomers = Mage::getModel('customer/customer')->load($user_id);
			if($deactivecustomers->getIsActive() == 0){
				return "Your account is not active";
			}
			$m_dob=$this->getMDobByUserId($user_id);
			$returnVal['userInfo'] = $this->getUserInfo($user_id);
			$returnVal['dob'] = $m_dob;
			$returnVal['phone'] = $this->getMobileAndDeviceId($user_id, $device_id, $imei, $type);
			$returnVal['userEmail'] = $email;
			$returnVal['view'] = Mage::getModel('csservice/csservice')->getCheckinCount($user_id);
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$widget_fb_reg = $resource->getTableName('widget_fb_reg');
			$rs = $read->fetchRow("SELECT * FROM $widget_fb_reg WHERE uid='".$user_id."'");
			if((count($rs[id])) && ($user_id > 0)){
				$linkFB="true";
			}else{
				$linkFB="false";
			}
			$returnVal['fbLink'] = $linkFB;
			if(($deactivecustomers->getTimezone() == "") || ($deactivecustomers->getTimezone() == "null")){
				$deactivecustomers->setTimezone('America/Los_Angeles');
				$deactivecustomers->save();
			}
			$returnVal['jabberPassword'] = $this->jabberAuth($user_id);
			/////////////////////////////////////////////////
			$seckey = substr($accesstoken,0,24);
			$token = substr($accesstoken,24);
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$write = $resource->getConnection('core_write');
			$xapplication_token = $resource->getTableName('xapplication_token');
			$write->query("update cs_xapplication_token set user_id=".$user_id." WHERE token='".$token."' and security_key='".$seckey."'");
			//=====================================================================
		} else {
			throw new Exception(self::$invalidLogin);
		}
        return $returnVal;
    }
    
	public function logout($deviceToken='',$user_id=0){
		if ($deviceToken != "") {
			$this->unregisterDevice($deviceToken,$user_id);
		}
		$returnValLogin = Mage::getSingleton( 'customer/session' )->logout();
		$returnVal['result'] = true;
		$returnVal['getUserInfo'] = $this->getUserInfo($user_id);
        return $returnVal;
	}
	public function logoutAndroid($imei="",$user_id){
		if ($user_id > 0) {
			$this->unregisterDeviceAndroid($imei,$user_id);
		}
		$returnValLogin = Mage::getSingleton( 'customer/session' )->logout();
		$returnVal['result'] = true;
		$returnVal['getUserInfo'] = $this->getUserInfo($user_id);
        return $returnVal;
	}
	public function followUserById($follower, $following){
		if($follower != $following){
			$resource = Mage::getSingleton('core/resource');
			$write = $resource->getConnection('core_write');
			$table = $resource->getTableName('follower');
			Mage::getModel('csservice/csservice')->updateJabberUser($follower, $following, 1);
			if($this->isfollowing($follower, $following, 1))
				$write->query("update $table set status=1, notify=1, follow_on=now()  WHERE follower_id=".$follower." and follow=".$following);
			else
				$write->query("insert into $table (follower_id, follow, status, follow_on, notify) values(".$follower.", ".$following." ,1, now(), 1)");
			//================================================
			$newsfeed = $resource->getTableName('newsfeed');
			$data['Favorate'] = 'follow';
			if($this->isFollow($follower,$following)){
				$write->query("insert into $newsfeed (user_id, profile_id, cat_id) values(".$follower.", ".$following.",10)");
				$data['Favorate'] = 'Favorate';
			}else{
				$write->query("insert into $newsfeed (user_id, profile_id, cat_id) values(".$follower.", ".$following.",7)");
			}
			//===================================================
			$customer = Mage::getModel('customer/customer')->load($following);	
			$notice = $customer->getNotice();
			$a = explode(",",$notice);	
			if(in_array(18,$a)){
				$this->sendMail($following, $follower);	
			}
			//===========================================================================
			try{
				$notificationType="Online";
				$type = "follow";
				$customer1 = Mage::getModel('customer/customer')->load($follower);	
				$message=$customer1->getFirstname()." ".$customer1->getLastname()." is now following you";
				$shortMsg = $customer1->getFirstname()." followed you";
				$pushNoti = $this->mobile_push_notification_call($following, $notificationType, $message,$follower,$following,0,$type,$shortMsg);
			}catch(Exception $e){

			}
			return $data;
		}
	}
	
	public function unfollowUserById($follower, $following){
			$resource = Mage::getSingleton('core/resource');
			$write = $resource->getConnection('core_write');
			$table = $resource->getTableName('follower');
			
			$write->query("update $table set  status=0, notify=0 WHERE follower_id=".$follower." and follow=".$following."");
			Mage::getModel('csservice/csservice')->updateJabberUser($follower, $following, 0);
			//================================================
			$newsfeed = $resource->getTableName('newsfeed');
			$write->query("insert into $newsfeed (user_id, profile_id, cat_id) values(".$follower.", ".$following.",8)");
			//====================================================
	}
	
	public function isFollowing($follower, $following, $status=1){
			$resource = Mage::getSingleton('core/resource');
			$read = $resource->getConnection('core_read');
			$table = $resource->getTableName('follower');
			
			$select = "select id, follower_id, follow, status, follow_on, notify from $table WHERE follow=".$following." and follower_id=".$follower." and status=".$status."";
			$rs = $read->fetchRow($select);	
			
			if(count($rs['id'])>0)
				return true;
			else
				return false;
	}
	
	public function hasEventAccess($uid, $event_id=0){
			//$event = $this->getNextLiveEvent($event_id);
			$event = $this->getNextLiveEvent();
			$event_id = $event['entity_id'];
			
			$event_attending = $this->getAttendingEvents($uid, $event_id);
			
			if ($event['user_id'] == $uid || $event_attending == true) {
				$event['is_access'] =  true;
			}
			else
				$event['is_access'] =  false;
			
			return $event;
	}
	
	public function isLiveEvent($event_id){ 
			//$uid, unused param
			$event = $this->getEvent($event_id);
			//$today = strtotime(date("Y-m-d h:m"));			
			$event_date = $event->getNewsToDate();
			
			$diff = strtotime($event_date)-time();

			if ($diff > 0) {
				return true;
			}
			else
				return false;
	}
	
	public function getNextLiveEvent(){
			//$event = $this->getEvent($event_id);
			//$today = strtotime(date("Y-m-d h:m"));			
			//$event_date = $event->getNewsToDate();
			
			//$diff = strtotime($event_date)-time();

			$productCount = 1;	 
			$storeId    = Mage::app()->getStore()->getId();      
		 
			/**
			 * Get most viewed product collection
			 */
			/* $products = Mage::getResourceModel('catalog/product_collection')
				->addAttributeToSelect('*')
				->setStoreId($storeId)
				->addStoreFilter($storeId)
				->addFieldToFilter('user_id', array('eq'=> $uid))
				->addFieldToFilter('news_to_date', array('gteq'=> 'now()'))
				->setPageSize($productCount)->setOrder('ordered_qty', 'desc');
					Mage::getSingleton('catalog/product_status')
					->addVisibleFilterToCollection($products);
			Mage::getSingleton('catalog/product_visibility')
					->addVisibleInCatalogFilterToCollection($products); 
		// print_r($products);
		
		->addFieldToFilter('user_id', array('eq'=> $uid))
		  */
			$now = Mage::getModel('core/date')->timestamp(time());
			$dateTime = date('m/d/y H:i:s', $now);

			$events = Mage::getResourceModel('catalog/product_collection')
						->addAttributeToSelect('*')
					   ->addFieldToFilter('news_to_date', array('gteq'=> $dateTime))
					   ->setPageSize($productCount)->setOrder('news_to_date', 'asc')->setOrder('entity_id', 'desc')
					   ->load()->toArray(); 			
			foreach($events as $evt){
				$events = $evt;				
			}
			return $events;
	}
	
	public function getUpcomingEventsByHostId($user_id=0,$page=1){
		$pid=$user_id;
		$productCount = 5;	 
		$storeId    = Mage::app()->getStore()->getId(); 
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		if($time_zone == 'America/Los_Angeles'){
			$now = strtotime('+1 hour')+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime('+1 hour'));
		} else{
			$now = strtotime(now())+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime(now()));
		}
		$events = array();
		if($pid!=0){
			$events = Mage::getResourceModel('catalog/product_collection')
					   ->addAttributeToSelect('*')
					   ->addFieldToFilter('user_id', array('eq'=> $pid))
					   ->addAttributeToFilter('news_to_date', array('gteq' => $date))
					   ->addFieldToFilter('attribute_set_id', 9)
					   ->addAttributeToFilter('status', 1)
					   ->setOrder('news_to_date', 'asc')
					   ->setOrder('entity_id', 'desc');
			$limit = 15;
			if($page<=0)
				$page=1;
			//$page=$page-1; 
			$events = $events->setPageSize($limit)->setPage($page, $limit);
			$lastPage = $events->getLastPageNumber();			
			$events = $events->load()->toArray();
			if($lastPage > $page){
				$showMore = "true";
			} else {
				$showMore = "false";
			}
			$items = array();
			if($lastPage >= $page){
			foreach($events as $k=>$evt){
				$prfix = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'catalog/product/';
					
				if(!$evt['thumbnail'] || $evt['thumbnail']=='no_selection'){
					$evt['thumbnail'] = "placeholder/default/red-curtain2_8.jpg";
				}
				$from = strtotime($evt['news_from_date'])+$timeoffset;
				$to = strtotime($evt['news_to_date'])+$timeoffset;
				if (($now > $from) && ($now < $to)) {
					$isLive="true";
				}
				else{
					$isLive="false";
				}
				if($isLive == "false"){
				$items[$k] = array(
								'id'=>$evt['entity_id'],
								'name'=>$evt['name'],
								'price'=>$evt['price'],
								'description'=>$evt['description'],
								'event_date'=>date('D M d, Y h:i A', strtotime($evt['news_from_date'])+$timeoffset)." ".$abbrev,
								'event_end_date'=>date('D M d, Y h:i A', strtotime($evt['news_to_date'])+$timeoffset)." ".$abbrev,
								'from_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($evt['news_from_date'])))),
								'to_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($evt['news_to_date'])))),
								'from_date3'=>date('m-d-Y H:i:s', strtotime($evt['news_from_date'])+$timeoffset),/*Added By surinder on demand of ajumal*/
								'to_date3'=>date('m-d-Y H:i:s',strtotime($evt['news_to_date'])+$timeoffset),/*Added By surinder on demand of ajumal*/
								'thumbnail'=>$prfix.$evt['thumbnail'],
								'thumb_image'	=> 'http://chattrspace.s3.amazonaws.com/events/711x447/'.$evt['event_image'],
								'location'=>$evt['location'],
								'category'=>$this->getCategoryNameByEventId($evt['entity_id']),
								'url'=>Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).'live-events/'.$evt['url_path'],
								'isEventAccess'=>$this->isEventAccess($pid, $evt['entity_id']),
								'isLive'=> $isLive,
							);
						}
			}
			//$events['server_time'] = date('Y-m-d H:i:s');
			$items['server_time'] = date('Y-m-d H:i:s');
			}
			$evt_count = count($events);
			$result=array();
			$result['data']=$items;
			$result['showMore']=$showMore;
			$result['count']=$evt_count;
			return $result;
		}
		
		//return $events;
	}
	
	public function getCategoryNameByEventId($event_id=0){
		$_categoryName = 'Live Events';
		$product = Mage::getModel('catalog/product')->load($event_id);
		$cat_ids = $product->getCategoryIds();
		if(isset($cat_ids[0])){
			$_categoryName = Mage::getModel('catalog/category')->load($cat_ids[1])->getName();
			$_categoryId = $cat_ids[1];
		}
		return $_categoryName;
	}
	
	public function isEventAccess($uid=0, $event_id=0){
			
		$user_id = $this->_getSession()->getCustomerId();
		$event_attending = $this->getAttendingEvents($user_id, $event_id);
		
		if ($user_id == $uid || $event_attending == true) {
			return  true;
		}
		else
			return  false;
	}
		
	public function getAttendingEvents($uid, $event_id=0, $status=1){
			$resource = Mage::getSingleton('core/resource');
			$read = $resource->getConnection('core_read');
			$table = $resource->getTableName('event_attending');
			
			if($event_id > 0){
				$select = "select $table.* from $table WHERE user_id=".$uid." and event_id=".$event_id."";
				//$rs = $read->fetchRow($select);
				$rs = mysql_query($select);
				$numResults = mysql_num_rows($rs);
				if($numResults>0)
					return true;
				else
					return false;
			}else{
				$select = "select $table.* from $table WHERE user_id=".$uid;
				$rs = $read->fetchAll($select);
				if(count($rs)>0)
					return $rs;
				else
					return '';				
			}			
	}
	//get video
	public function getVideoInfo($video_id) {
		$recording_id = $video_id;
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$videoTable = $resource->getTableName('video');
		
		$select = ' select video_id, title, identifier, description, profile_id, user_id, video_path, thumbnail_path, duration, tags, created_time  from '.$videoTable.' where status = 1 and video_id = '.$recording_id;
		
		$item = array();
		//echo $video['video_id'];
		$rs = mysql_query($select);

        $numResults = mysql_num_rows($rs);
        if($numResults>0){
            $rowTag = mysql_fetch_row($rs);
            if($rowTag[1]!=''){
                list($p1, $p2, $p3, $p4, $title, $ext) = split('[/.-]', $rowTag[6]);
                if($title=='0')
                    $title = $rowTag[1];
                else
                    $title = $rowTag[1].' - Part '.$title;
            }
            else
                $title = 'Chattrspace Video';

            $item['video_id'] = $rowTag[0];
            $item['title'] = $title;
            $item['identifier'] = $rowTag[2];
            $item['description'] = $rowTag[3];
            $item['profile_id'] = $rowTag[4];
            $item['user_id'] = $rowTag[5];
            $item['video_path'] = $rowTag[6];
            //$item['video_path'] = realpath('/mnt/mediafiles/completed').'/'.$rowTag[6];
            $item['thumbnail_path'] = $rowTag[7];
            $item['duration'] = $rowTag[8];
            $item['tags'] = $rowTag[9];
            $item['views'] = $rowTag[10];
            $item['created_on'] = $rowTag[11];
            //$returnVal['recording'][] = $item;	
        }

        return  $item;
    }

    public function getAttributeSetName($id) {
        $attributeSetModel = Mage::getModel("eav/entity_attribute_set");
        $attributeSetModel->load($id);
        return $attributeSetName  = $attributeSetModel->getAttributeSetName();
    }

    public function getEventInfo($event_id) {
        return $this->getEvent($event_id);
    }

    public function getEvent($user_id, $event_id){
        if($user_id > 0){
            $event_id = intval($event_id);
            $customer = Mage::getModel('customer/customer')->load($user_id);
            $time_zone = $customer->getTimezone();
            $abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
            $timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
            $now = strtotime(now())+$timeoffset;
            $theProduct = Mage::getModel('catalog/product')->load($event_id);
            $theProduct = $theProduct->toArray();
            //===================Start Image=====================================
            $Ecustomer = Mage::getModel('customer/customer')->load($theProduct['user_id']);
            $fc = strtolower(substr($Ecustomer->getFirstname(),0,1));
            if($fc == ''){ $fc = rand(1,10); }

			if($theProduct['event_image']=="''")
			$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
			else{
				if($theProduct['event_image']){
					$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$theProduct['event_image'];
					
					if(fopen($img_url,"r")==false)
						$img_url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'catalog/product/'.$theProduct['small_image'];
					else
						$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$theProduct['event_image'];
				}
				else
					$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$theProduct['event_image'];		
			}
			$theProduct['event_image'] = $img_url;
			//===================End Image=====================================
			if($theProduct['user_id'] == $user_id){
				$youHost = "true";
			} else {
				$youHost = "false";
			}
			$theProduct['youHost'] = $youHost;
			$username = $this->getUserNameByUserId($theProduct['user_id']);
			$theProduct['username']=$username;
			$theProduct['price']=number_format($theProduct['price'],2);
			if($theProduct['price'] == 0.00){
				$theProduct['price'] = "Free";
			} else {
				$theProduct['price'] = "$".$theProduct['price'];
			}
			$theProduct['from_date2']= date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($theProduct['news_from_date']))));
			$theProduct['to_date2']= date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($theProduct['news_to_date']))));
			$theProduct['news_from_date']=date('D M d, Y h:i A', strtotime($theProduct['news_from_date'])+$timeoffset)." ".$abbrev;
			$theProduct['news_to_date']=date('D M d, Y h:i A', strtotime($theProduct['news_to_date'])+$timeoffset)." ".$abbrev;
			$from =strtotime($theProduct['news_from_date'])+$timeoffset;
			$to =strtotime($theProduct['news_to_date'])+$timeoffset;
			if (($now > $from) && ($now > $to)) {
				$EventExpire="true";
			}
			else{
				$EventExpire="false";
			}
			$theProduct['EventExpire'] = $EventExpire;
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$rsvp = $resource->getTableName('rsvp');
			$selectSql = "select * from $rsvp WHERE user_id=".$user_id." and event_id=".$event_id." and status=1";			
			$row = $read->fetchAll($selectSql);
			if(count($row)>0)
				$rsvpStatus=1;
			else
				$rsvpStatus=0;
			$theProduct['rsvpStatus']=$rsvpStatus;
			if($theProduct['status'] == 1){
				return $theProduct;
			}else{
				return "Status=".$theProduct['status']." Event is disabled";
			}
		}
	}
	
	public function addViewCountById($id) {
			$event_id = intval($id);
			$theProduct = Mage::getModel('catalog/product')->load($id);
			$viewCount = $theProduct->getViewCount()+1;
			
			$theProduct->setViewCount($viewCount);
			$theProduct->save();
			return $viewCount;
	}
			
	public function getEvents($cat_id, $page=1) {
        $limit=25;
		$time_zone='Europe/London';
		//$session = Mage::getSingleton('customer/session');
		//$time_zone = $session->getCustomer()->getTimezone();
		//$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		//$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		
	  	//$todayDate  = Mage::app()->getLocale()->date()->toString(Varien_Date::DATETIME_INTERNAL_FORMAT);	  
	
		$websiteId = Mage::app()->getWebsite()->getId();
		$storeId = Mage::app()->getStore()->getId();
		
		$visibility = array(	
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
              );

      		$events = Mage::getModel('catalog/category')->load($cat_id)
							->getProductCollection()
							->addAttributeToSelect('*')
							->addAttributeToSelect('category_id')
							->addAttributeToSelect('status')
							->addFieldToFilter('status', 1)
							->addAttributeToFilter('is_expired', array('neq' => 1))
							->addAttributeToSort('news_from_date', 'desc')
							->addAttributeToSort('position', 'desc');	

					$events->addAttributeToFilter('visibility', $visibility)				
							->setPageSize($limit)
							->setPage($page, $limit)							
							->load()->toArray();	

			if(count($events)>0){
				foreach ($events as $k=>$event) {
				//$result[] = $event->toArray();
				$result[$k] = array(
						'id'=> $event['entity_id'],
						'name'=> $event['name'],
						'price'=> number_format($event['price'],2),
						'user_id'=> $event['user_id'],
						'description'=> $event['description'],
						'from_date'=> date('D M d, Y h:i A', strtotime($event['news_from_date'])),
						'to_date'=> date('D M d, Y h:i A',strtotime($event['news_to_date'])),
						'image'			=> $event['event_image'],							
						
					);  
				}
				return $result;
			}
			else
				return 0;
    }


	public function testFileUpload($data='') {
		//check for the posted data and decode it
		if (isset($_POST["data"]) && ($_POST["data"] !="")){
			$data = $_POST["data"];
			$data = base64_decode($data);
			$im = imagecreatefromstring($data);
		}
		//make a file name
		$filename = "test";

		//save the image to the disk
		if (isset($im) && $im != false) {
			$imgFile = $path = realpath('.')."/media/csimages/".$filename.".jpg";

			//delete the file if it already exists
			if(file_exists($imgFile)){
				unlink($imgFile);      
			}

			$result = imagepng($im, $imgFile);
			imagedestroy($im);
			return "/".$filename.".jpg";
		}
		else {
			return 'Error';
		}
	}
	public function getAllVar() {
		$returnVal = array();
		//print_r($_SESSION);
		$returnVal['_SESSION']=$_SESSION;
		$returnVal['_REQUEST']=$_REQUEST;
		$returnVal['_SERVER']=$_SERVER;
		$returnVal['_POST']=$_POST;
		$returnVal['_GET']=$_GET;
		$returnVal['_COOKIE']=$_COOKIE ;
		
		return $returnVal; 
		
	}
	

	public function updateLastPingedTime($checkin_id=0) {
			
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$table = $resource->getTableName('user_activities');
		if($checkin_id!=0){
			$sqlUpdate = " UPDATE $table SET last_pinged_time = '" . date("Y-m-d G:i:s") . "'  WHERE id = " . $checkin_id . ";";
			
			mysql_query($sqlUpdate);
		}
		return $checkin_id;
	}
	
	public function setUserStatusOffline($checkin_id=0) {
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$table = $resource->getTableName('user_activities');
		if($checkin_id!=0){
			$sqlUpdate = " UPDATE $table SET status=0  WHERE id = " . $checkin_id . ";";
			
			mysql_query($sqlUpdate);
		}
		return $checkin_id;
	}
	
	public function userCheckInAndroid($user_id=0, $profile_id=0,$data=null, $event_id=0, $webcam_on=0) {
		if (isset($_POST["data"]) && ($_POST["data"] !="") && isset($_POST["user_id"]) && $_POST["user_id"]>0){
		 $type='check-ins';
		 $group=''; 
		 $user_id = $_POST["user_id"];
		 $profile_id = $_POST["profile_id"];
		 $event_id = $_POST["event_id"];
		 $webcam_on = $_POST["webcam_on"];
		 $mesg="hi";		
		$user_id = intval($user_id);
		$profile_id = intval($profile_id);
		
		/* if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		} */
		//$UID = $this->getUserId();
		$UID = $user_id;
		
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$table = $resource->getTableName('user_activities');
		
			$sqlInsert = " insert into $table(user_id, event_id, profile_id, type_of, group_of, created_on, status, webcam_on, mesg, last_pinged_time) values(" . $UID . ", ". $event_id ."," . $profile_id . ", '" . $type . "', '" . $group . "'
				, '" . date("Y-m-d G:i:s") . "', 1, ". $webcam_on .", '".$mesg."', '" . date("Y-m-d G:i:s") . "');";
			try {
				mysql_query($sqlInsert);
			} catch (Exception $e) {
				throw new Exception("Error while saving : ".$e->getMessage());
			}
			$thelastId = mysql_insert_id();
			
				
				$customer = Mage::getModel('customer/customer')->load($user_id);
				$data = base64_decode($_POST["data"]);
				
				$im = imagecreatefromstring($data);
				if ($im == false) {
					return ' Error: Data is not well formated.';
				}
				$fileName = strtoupper( $user_id . "-" . $this->getRandomString(8) );
			
				if (isset($im) && $im != false) {
			
					$fullFilePath = self::$EventImageFolder . $fileName;
					$image_path = $fullFilePath . '_img.jpg';	
					$path = realpath('.')."/media/csimages/";
					$fullFilePath = $path . $image_path;
					$fpath = Mage::getBaseDir('media') . DS .  'csimages'. DS;
					$fullpath = $fpath.$image_path;
					//return $fullFilePath;
					//if(file_exists($fullFilePath)){
					//	unlink($fullFilePath);      
					//}
					//header('Content-Type: image/png');
					$result = imagepng($im, $fullFilePath);
					imagedestroy($im);
					//save into s3
					$bucketName = 'chattrspace';
					$objectname = 'checkins/'.$image_path;
					$filename = Mage::getModel('uploadjob/amazonS3')
									->putImage( $bucketName, $fullpath, $objectname, 'public');
					unlink($fullFilePath);
					//end s3
						$sqlUpdate = " UPDATE $table SET photo = '".mysql_real_escape_string($image_path)."', group_of='".$thelastId."'  WHERE id = " . $thelastId . ";";
		
					mysql_query($sqlUpdate);
				}
				else {
					//return 'Error';
				}			
		//end when pre-customized product then add a new product
		
		$sqlSelect = " Select profile_id, user_id, type_of, group_of, photo , created_on, status, event_id, webcam_on, mesg, id from $table where id = " . $thelastId . " LIMIT 1;";
		$rs = mysql_query($sqlSelect);

		$numResults = mysql_num_rows($rs);
		$returnVal = array();
		$rowTag = mysql_fetch_row($rs);
		
		$item = array();
		$item['profile_id'] = $rowTag[0];
		$item['user_id'] = $rowTag[1];
		$item['type'] = $rowTag[2];
		$item['group'] = $rowTag[3];
		$item['photo'] = $rowTag[4];
		$item['created_on'] = $rowTag[5];
		$item['status'] = $rowTag[6];
		$item['event_id'] = $rowTag[7];
		$item['webcam_on'] = $rowTag[8];
		$item['mesg'] = $rowTag[9];
		$item['checkin_id'] = $rowTag[10];
		$returnVal['user'] = $item;		
		
			//$fb_id = $this->_getSession()->getCustomer()->getFacebookUid();
			//$fb_code = $this->_getSession()->getCustomer()->getFacebookCode();
			$widget_fb_reg = $resource->getTableName('widget_fb_reg');
			$rs = $read->fetchRow("SELECT * FROM $widget_fb_reg WHERE widgetid=0 AND uid='".$item['user_id']."'");
			$fb_id = $rs['fbid'];
			$fb_code = $rs['fbcode'];
			$twitter_id = $customer->getTwitterId();
			$customer = Mage::getModel('customer/customer')->load($item['profile_id']);
			//$profilePicture = $customer->getProfilePicture();
			$username = $customer->getUsername();
				
			if($fb_id!=""){
				$customer1 = Mage::getModel('customer/customer')->load($user_id);
				$sfacebook = $customer1->getSfacebook();
				$c = explode(',',$sfacebook);
				if(in_array('172',$c,true)){
			$checkin_image='http://chattrspace.s3.amazonaws.com/checkins/'.$rowTag[4];
			//$name='I&#39;m talking with friends RIGHT NOW LIVE face to face at '.Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).''.$username.' -- Join us!';
			$msg = $username. ' is oncam right now. Click the link below to join '.$username.' live from facebook.';
			$name = 'Join '.$username.' live from facebook.';
				$my_url=Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).''.$username;
				$fbAcsessToken = $resource->getTableName('fb_accessToken');
				$rsAccessToken = $read->fetchRow("SELECT * FROM $fbAcsessToken WHERE uid=".$user_id);
				$facebook_id=$rsAccessToken['fbid'];
				$facebook_access_token=$rsAccessToken['access_token'];
			$params = array('access_token'=>$facebook_access_token,'name'=>$name, 'message'=>$msg,'link' => $my_url,'picture' => $checkin_image,'description' => 'oncam is the free, easy, and fun way to be live with friends and followers from anywhere with iPhone, iPad, Android, and Facebook.','caption'=>'http://apps.facebook.com/oncamapp/');
			// 'message'=>$mesg,
			$url = "https://graph.facebook.com/$facebook_id/feed";
				$ch = curl_init();
				curl_setopt_array($ch, array(
				CURLOPT_URL => $url,
				CURLOPT_POSTFIELDS => $params,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_VERBOSE => true
				));
				$result = curl_exec($ch);	
			}
		}	
		if($twitter_id!=""){
			$customer1 = Mage::getModel('customer/customer')->load($user_id);
			$stwitter = $customer1->getStwitter();
			$c = explode(',',$stwitter);
			if(in_array('173',$c,true)){
					
			//$name='I&#39;m talking with friends RIGHT NOW LIVE face to face at '.Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).''.$username.' -- Join us!';
			$name = 'Join '.$username.' live in oncam now onc.am/'.$username;
			try{
				$connection = Mage::getModel('csservice/twitteroauth');
				$initialize = $connection->initializeByUserId($user_id);
				$connection->post('statuses/update', array('status' => $name." oncam" ));
				}  catch (Exception $e) {
					echo "error ".$e->getMessage();
				} 
			}
		}//end post
		if($type=='check-ins' and $profile_id!=$user_id){
			$customer = Mage::getModel('customer/customer')->load($profile_id);	
			$notice = $customer->getNotice();
			$a = explode(",",$notice);	
			if(in_array(157,$a)){
				Mage::getModel('csservice/mail')->sendUserCheckinMail($user_id, $profile_id);
			}
		}		
		$returnVal['new_checkin_id']=$thelastId;
		return $returnVal;
		
	} else {
				return 'Use Form POST';
            }
	}
	public function userCheckInIphone($user_id=0, $profile_id=0,$data=null, $event_id=0, $webcam_on=0) {
		if (isset($_POST["data"]) && ($_POST["data"] !="") && isset($_POST["user_id"]) && $_POST["user_id"]>0){
		 $type='check-ins';
		 $group=''; 
		 $user_id = $_POST["user_id"];
		 $profile_id = $_POST["profile_id"];
		 $event_id = $_POST["event_id"];
		 $webcam_on = $_POST["webcam_on"];
		 $mesg="hi";	
		$user_id = intval($user_id);
		$profile_id = intval($profile_id);
		
		/* if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		} */
		//$UID = $this->getUserId();
		$UID = $user_id;
		
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$table = $resource->getTableName('user_activities');
		//================================================
		$newsfeed = $resource->getTableName('newsfeed');
		$write->query("insert into $newsfeed (user_id, profile_id, cat_id) values(".$user_id.", ".$profile_id.",2)");
		//===================================================
			$sqlInsert = " insert into $table(user_id, event_id, profile_id, type_of, group_of, created_on, status, webcam_on, mesg, last_pinged_time) values(" . $UID . ", ". $event_id ."," . $profile_id . ", '" . $type . "', '" . $group . "'
				, '" . date("Y-m-d G:i:s") . "', 1, ". $webcam_on .", '".$mesg."', '" . date("Y-m-d G:i:s") . "');";
			try {
				mysql_query($sqlInsert);
			} catch (Exception $e) {
				throw new Exception("Error while saving : ".$e->getMessage());
			}
			$thelastId = mysql_insert_id();
			
				
				$customer = Mage::getModel('customer/customer')->load($user_id);
				$encodedData = str_replace(' ','+',$_POST["data"]);
				$decodedData = ""; 
				//for ($i=0; $i < ceil(strlen($_POST["data"])/256); $i++) {
					//$decodedData = $decodedData . base64_decode(substr($_POST["data"],$i*256,256)); 
				//}
				for($i=0, $len=strlen($encodedData); $i<$len; $i+=4){
					$decodedData = $decodedData . base64_decode( substr($encodedData, $i, 4) );
				}
				
				//$decodedData = base64_decode(chunk_split($_POST["data"]));
				$im = imagecreatefromstring($decodedData);
				if ($im == false) {
					return ' Error: Data is not well formated.';
				}
				$fileName = strtoupper( $user_id . "-" . $this->getRandomString(8) );
			
				if (isset($im) && $im != false) {
			
					$fullFilePath = self::$EventImageFolder . $fileName;
					$image_path = $fullFilePath . '_img.jpg';	
					$path = realpath('.')."/media/csimages/";
					$fullFilePath = $path . $image_path;
					$fpath = Mage::getBaseDir('media') . DS .  'csimages'. DS;
					$fullpath = $fpath.$image_path;
					//return $fullFilePath;
					//if(file_exists($fullFilePath)){
					//	unlink($fullFilePath);      
					//}
					//header('Content-Type: image/png');
					$result = imagepng($im, $fullFilePath);
					imagedestroy($im);
					//save into s3
					$bucketName = 'chattrspace';
					$objectname = 'checkins/'.$image_path;
					$filename = Mage::getModel('uploadjob/amazonS3')
									->putImage( $bucketName, $fullpath, $objectname, 'public');
					unlink($fullFilePath);
					//end s3
						$sqlUpdate = " UPDATE $table SET photo = '".mysql_real_escape_string($image_path)."', group_of='".$thelastId."'  WHERE id = " . $thelastId . ";";
		
					mysql_query($sqlUpdate);
				}
				else {
					//return 'Error';
				}			
		//end when pre-customized product then add a new product
		
		$sqlSelect = " Select profile_id, user_id, type_of, group_of, photo , created_on, status, event_id, webcam_on, mesg, id from $table where id = " . $thelastId . " LIMIT 1;";
		$rs = mysql_query($sqlSelect);

		$numResults = mysql_num_rows($rs);
		$returnVal = array();
		$rowTag = mysql_fetch_row($rs);
		
		$item = array();
		$item['profile_id'] = $rowTag[0];
		$item['user_id'] = $rowTag[1];
		$item['type'] = $rowTag[2];
		$item['group'] = $rowTag[3];
		$item['photo'] = $rowTag[4];
		$item['created_on'] = $rowTag[5];
		$item['status'] = $rowTag[6];
		$item['event_id'] = $rowTag[7];
		$item['webcam_on'] = $rowTag[8];
		$item['mesg'] = $rowTag[9];
		$item['checkin_id'] = $rowTag[10];
		$returnVal['user'] = $item;		
		
			//$fb_id = $this->_getSession()->getCustomer()->getFacebookUid();
			//$fb_code = $this->_getSession()->getCustomer()->getFacebookCode();
			$widget_fb_reg = $resource->getTableName('widget_fb_reg');
			$rs = $read->fetchRow("SELECT * FROM $widget_fb_reg WHERE widgetid=0 AND uid='".$item['user_id']."'");
			$fb_id = $rs['fbid'];
			$fb_code = $rs['fbcode'];
			$twitter_id = $customer->getTwitterId();
			$customer = Mage::getModel('customer/customer')->load($item['profile_id']);
			//$profilePicture = $customer->getProfilePicture();
			$username = $customer->getUsername();
				
			if($fb_id!=""){
				$customer1 = Mage::getModel('customer/customer')->load($user_id);
				$sfacebook = $customer1->getSfacebook();
				$c = explode(',',$sfacebook);
				if(in_array('172',$c,true)){
			$checkin_image='http://chattrspace.s3.amazonaws.com/checkins/'.$rowTag[4];
			//$name='I&#39;m talking with friends RIGHT NOW LIVE face to face at '.Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).''.$username.' -- Join us!';
			$msg = $username. ' is oncam right now. Click the link below to join '.$username.' live from facebook.';
			$name = 'Join '.$username.' live from facebook.';
				$my_url=Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).''.$username;
				$fbAcsessToken = $resource->getTableName('fb_accessToken');
				$rsAccessToken = $read->fetchRow("SELECT * FROM $fbAcsessToken WHERE uid=".$user_id);
				$facebook_id=$rsAccessToken['fbid'];
				$facebook_access_token=$rsAccessToken['access_token'];
			$params = array('access_token'=>$facebook_access_token,'name'=>$name, 'message'=>$msg,'link' => $my_url,'picture' => $checkin_image,'description' => 'oncam is the free, easy, and fun way to be live with friends and followers from anywhere with iPhone, iPad, Android, and Facebook.','caption'=>'http://apps.facebook.com/oncamapp/');
			
			$url = "https://graph.facebook.com/$facebook_id/feed";
				$ch = curl_init();
				curl_setopt_array($ch, array(
				CURLOPT_URL => $url,
				CURLOPT_POSTFIELDS => $params,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_VERBOSE => true
				));
				$result = curl_exec($ch);	
			}
		}	
		if($twitter_id!=""){
			$customer1 = Mage::getModel('customer/customer')->load($user_id);
			$stwitter = $customer1->getStwitter();
			$c = explode(',',$stwitter);
			if(in_array('173',$c,true)){
			//$name='I&#39;m talking with friends RIGHT NOW LIVE face to face at '.Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).''.$username.' -- Join us!';
			$name = 'Join '.$username.' live in oncam now onc.am/'.$username;
			try{
				$connection = Mage::getModel('csservice/twitteroauth');
				$initialize = $connection->initializeByUserId($user_id);
				$connection->post('statuses/update', array('status' => $name." oncam" ));
				}  catch (Exception $e) {
					echo "error ".$e->getMessage();
				} 
			}
		}//end post
		if($type=='check-ins' and $profile_id!=$user_id){
			$customer = Mage::getModel('customer/customer')->load($profile_id);	
			$notice = $customer->getNotice();
			$a = explode(",",$notice);	
			if(in_array(157,$a)){
				Mage::getModel('csservice/mail')->sendUserCheckinMail($user_id, $profile_id);
			}
		}		
		$returnVal['new_checkin_id']=$thelastId;
		return $returnVal;
		
	} else {
				return 'Use Form POST';
            }
	}
	
	public function testuserCheckIn($photo=null) {
		
		if ( isset ( $_FILES['image'] ))
		{
          // return $_FILES['image']['name'];
		   // print_r($_FILES['image']);die;
			$fileName = strtoupper( $UID . $thelastId . "-" . $this->getRandomString(8) );
			$fullFilePath = self::$EventImageFolder . $fileName;
			$image_path = $fullFilePath . '_img.jpg';	
			$path = realpath('.')."/media/csimages/";
			$fullFilePath = $path . $image_path;
			move_uploaded_file($_FILES['image']['tmp_name'] , $fullFilePath);
//			
            return $fullFilePath;
		}
		
	}
	
	public function userCheckIn($user_id=0, $profile_id=0, $type='', $group='', $photo=null) {
		
		//return $contentType." --- ".$roomId;
		$user_id = intval($user_id);
		$profile_id = intval($profile_id);
		
		/* if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		} */
		//$UID = $this->getUserId();
		$UID = $user_id;
		
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$table = $resource->getTableName('user_activities');
		//
			$sqlInsert = " insert into $table(user_id, profile_id, type_of, group_of, created_on, status) values(" .
				$UID . ", " . $profile_id . ", '" . $type . "', '" . $group . "'
				, now(), 1);";
			try {
				mysql_query($sqlInsert);
			} catch (Exception $e) {
				throw new Exception("Error while saving : ".$e->getMessage());
			}
			$thelastId = mysql_insert_id();
		
		
		if ( isset ( $_FILES['image'] ))
		{
          // return $_FILES['image']['name'];
		   // print_r($_FILES['image']);die;
			$fileName = strtoupper( $UID . $thelastId . "-" . $this->getRandomString(8) );
			$fullFilePath = self::$EventImageFolder . $fileName;
			$image_path = $fullFilePath . '_img.jpg';	
			$path = realpath('.')."/media/csimages/";
			$fullFilePath = $path . $image_path;
			move_uploaded_file($_FILES['image']['tmp_name'] , $fullFilePath);
//			
            // create thumbnail:
           /*  $thumbnail = new ThumbnailImage($_FILES['image']['tmp_name'], 260, 260, true, 75);
            // move orignal uploaded file:
            move_uploaded_file($_FILES['image']['tmp_name'] , $fullFilePath);
			// save thumbnail
			$fullFilePath = $path . $thumbnail_path;
			$thumbnail->saveTo($fullFilePath, 60); */
			
			$sqlUpdate = " UPDATE $table SET photo = '".mysql_real_escape_string($image_path)."' WHERE id = " . $thelastId . ";";
		
			mysql_query($sqlUpdate);
		}
		
				
		
		//end when pre-customized product then add a new product
		
		$sqlSelect = " Select profile_id, user_id, type_of, group_of, photo , created_on, status from $table where id = " . $thelastId . " LIMIT 1;";
		$rs = mysql_query($sqlSelect);

		$numResults = mysql_num_rows($rs);
		$returnVal = array();
		$rowTag = mysql_fetch_row($rs);
		
		$item = array();
		$item['profile_id'] = $rowTag[0];
		$item['user_id'] = $rowTag[1];
		$item['type'] = $rowTag[2];
		$item['group'] = $rowTag[3];
		$item['photo'] = $rowTag[4];
		$item['created_on'] = $rowTag[5];
		$item['status'] = $rowTag[6];
		$returnVal['user'] = $item;		
		
		return $returnVal;
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
	
	public function userChattring($user_id=0, $profile_id=0, $type='chattering', $group='', $data=null, $event_id=0) {
		return $this->userCheckInHttp($user_id, $profile_id, $type, $group, $data, $event_id);
	}

	//save chattr videos
	public function saveChattrVideos($user_id=0, $profile_id=0, $videofile1='', $videofile2='', $ip_address='', $tags='') {
		
		$user_id = intval($user_id);
		$profile_id = intval($profile_id);
		
		/* if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		} */
		//$UID = $this->getUserId();
		$UID = $user_id;
		
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$table = $resource->getTableName('user_video');
		//
			$sqlInsert = " insert into $table(user_id, profile_id, videofile1, videofile2, ip_address, tags, created_on) values(" .
				$UID . ", " . $profile_id . ", '" . $videofile1 . "', '" . $videofile2 . "'
				'". $ip_address . "', '". $tags ."' , '" . date("Y-m-d H:m:s") . "');";
			try {
				mysql_query($sqlInsert);
			} catch (Exception $e) {
				throw new Exception("Error while saving : ".$e->getMessage());
			}
			$thelastId = mysql_insert_id();
		
		$sqlSelect = " Select profile_id, user_id, videofile1, videofile2, ip_address, tags, created_on from $table where id = " . $thelastId . " LIMIT 1;";
		$rs = mysql_query($sqlSelect);

		$numResults = mysql_num_rows($rs);
		$returnVal = array();
		$rowTag = mysql_fetch_row($rs);
		
		$item = array();
		$item['profile_id'] = $rowTag[0];
		$item['user_id'] = $rowTag[1];
		$item['videofile1'] = $rowTag[2];
		$item['videofile2'] = $rowTag[3];
		$item['ip_address'] = $rowTag[4];
		$item['tags'] = $rowTag[5];
		$item['created_on'] = $rowTag[6];
		$returnVal['video'] = $item;		
		
		return $returnVal;
	}
	
	public function getVideos($user_id=0, $profile_id=0, $video_path='', $ip_address='', $tags='') {
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		$table = $resource->getTableName('video');
		$sqlSelect = " Select video_id, profile_id,user_id,video_path,tags,created_time from $table where video_id>0 order by video_id desc";
		
		if($user_id>0)$sqlSelect.=" and user_id = $user_id";
		if($profile_id>0)$sqlSelect.=" and profile_id = $profile_id";
		if($tags != '')$sqlSelect.=" and profile_id like('%".$tags."%')";
		$sqlSelect.=" ORDER BY `created_time` DESC LIMIT 0,25";
		//echo $sqlSelect;exit;
		$rs = mysql_query($sqlSelect);
		//$numResults = mysql_num_rows($rs);
		//$rowTags = mysql_fetch_row($rs);
		$item = array();
		$i=1;
		while($row=mysql_fetch_assoc($rs)) {
			$item['video_'.$i]=$row;
			$i++;
		}
		return $item;
	}
	public function getTopEvents($user_id, $page=1) {
		if($user_id > 0){
		$cat=3; $limit=10;
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		$now = strtotime(now())+$timeoffset;
			
		$websiteId = Mage::app()->getWebsite()->getId();
		$storeId = Mage::app()->getStore()->getId();
		
		$visibility = array(	
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
              );

      		$events = Mage::getModel('catalog/category')->load($cat)
							->getProductCollection()
							->addAttributeToSelect('*')
							->addAttributeToSelect('category_id')
							->addAttributeToSelect('status')
							->addFieldToFilter('status', 1)
							->addAttributeToFilter('is_expired', array('neq' => 1))
							->addAttributeToSort('news_from_date', 'desc')
							->addAttributeToSort('position', 'desc')
							->addAttributeToFilter('news_to_date', array('gteq' => date('Y-m-d H:i:s')));	
			
			$events->addAttributeToFilter('visibility', $visibility)				
							->setPageSize($limit)
							->setPage($page, $limit)							
							->load()->toArray();
			
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$follower = $resource->getTableName('follower');
			$select = "select follow from $follower WHERE follower_id=".$user_id." and follower_id<>follow and status=1";						
			$followers = $read->fetchAll($select);

			$resultArray = '';
			$str='';
			$c=0;
			foreach($followers as $follower){	$c = $c+1;	
					$str[$c]=$follower['follow'];
					$c = $c+1;
			}
		
			if(count($events)>0){ 
				foreach ($events as $k => $event) {		
				$rsvp = $resource->getTableName('rsvp');
					$selectSql = "select * from $rsvp WHERE user_id=".$user_id." and event_id=".$event['entity_id']." and status=1";			
					$row = $read->fetchAll($selectSql);
					if(count($row)>0)
						$rsvpStatus=1;
					else
						$rsvpStatus=0;
					$from = strtotime($event['news_from_date'])+$timeoffset;
					$to = strtotime($event['news_to_date'])+$timeoffset;
					if (($now > $from) && ($now < $to)) {
						$isLive="true";
					}
					else{
						$isLive="false";
					}
					if((in_array($event['user_id'],$str)) && ($isLive == "true")){
						$myFollowersEvent = "true";
					}
					else {
						$myFollowersEvent = "false";
					}
					
									
				$result[$k] = array(
						'id'=> $event['entity_id'],
						'name'=> $event['name'],
						'price'=> number_format($event['price'],2),
						'user_id'=> $event['user_id'],
						'description'=> $event['description'],
						'from_date'=> date('D M d, Y h:i A', strtotime($event['news_from_date'])+$timeoffset)." ".$abbrev,
						'to_date'=> date('D M d, Y h:i A',strtotime($event['news_to_date'])+$timeoffset)." ".$abbrev,
						'image'	=> $event['event_image'],							
						'islive' => $isLive,
						'myfollowersevent'	=> $myFollowersEvent,
						'rsvpStatus'	=>$rsvpStatus,
					);			
				}
				$result['count'] = $this->getTopEventsCount();
				return $result;
			}
			else
				return 0;
		}
	}
	public function getTopEventsNew($user_id=0,$page=1) {
		if($user_id > 0){
		$cat=3; $limit=5;
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		if($time_zone == 'America/Los_Angeles'){
			$now = strtotime('+1 hour')+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime('+1 hour'));
		} else{
			$now = strtotime(now())+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime(now()));
		}
			
		$websiteId = Mage::app()->getWebsite()->getId();
		$storeId = Mage::app()->getStore()->getId();
		
		$visibility = array(	
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
              );

      		$events = Mage::getModel('catalog/category')->load($cat)
							->getProductCollection()
							->addAttributeToSelect('*')
							->addAttributeToSelect('category_id')
							->addAttributeToSelect('status')
							->addFieldToFilter('status', 1)
							->addAttributeToFilter('is_expired', array('neq' => 1))
							->addAttributeToSort('news_from_date', 'asc')
							//->addAttributeToSort('position', 'desc')
							->addAttributeToFilter('news_to_date', array('gteq' => $date));	
			
			$events->addAttributeToFilter('visibility', $visibility)				
							->setPageSize($limit)
							->setPage($page, $limit)							
							->load()->toArray();
			$lastPage = $events->getLastPageNumber();
			if($lastPage > $page){
				$showMore = "true";
			} else {
				$showMore = "false";
			}
			
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$follower = $resource->getTableName('follower');
			$select = "select follow from $follower WHERE follower_id=".$user_id." and follower_id<>follow and status=1";						
			$followers = $read->fetchAll($select);

			$resultArray = '';
			$str='';
			$c=0;
			foreach($followers as $follower){	$c = $c+1;	
					$str[$c]=$follower['follow'];
					$c = $c+1;
			}
		
			$counter=0;
			if(count($events)>0){ 
			if($lastPage >= $page){
				foreach ($events as $k => $event) { $counter++;
				//===================Start Image=====================================
					$customer = Mage::getModel('customer/customer')->load($event['user_id']);
					$fc = strtolower(substr($customer->getFirstname(),0,1));
					if($fc == ''){ $fc = rand(1,10); }

					if($event['event_image']=="''")
					$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
					else{
						if($event['event_image']){
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];
							
							if(fopen($img_url,"r")==false)
								$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
							else
								$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];
						}
						else
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];		
					}
				//===================End Image=====================================
				$rsvp = $resource->getTableName('rsvp');
					$selectSql = "select * from $rsvp WHERE user_id=".$user_id." and event_id=".$event['entity_id']." and status=1";			
					$row = $read->fetchAll($selectSql);
					if(count($row)>0)
						$rsvpStatus=1;
					else
						$rsvpStatus=0;
						
					$from = strtotime($event['news_from_date'])+$timeoffset;
					$to = strtotime($event['news_to_date'])+$timeoffset;
					if (($now > $from) && ($now < $to)) {
						$isLive="true";
					}
					else{
						$isLive="false";
					}
					if((in_array($event['user_id'],$str)) && ($isLive == "true")){
						$myFollowersEvent = "true";
					}
					else {
						$myFollowersEvent = "false";
					}
				if($myFollowersEvent == "true"){
					if($this->isUserOnline($event['user_id'])){
						$HostIsLive = "true";
					}else{
						$HostIsLive = "false";
					}
					$result[$counter]['followersEvent'] = array(
							'id'=> $event['entity_id'],
							'name'=> $event['name'],
							'price'=> number_format($event['price'],2),
							'user_id'=> $event['user_id'],
							'event_hostedby_username'=> $this->getUserNameByUserId($event['user_id']),
							'description'=> $event['description'],
							'from_date'=> date('D M d, Y h:i A', strtotime($event['news_from_date'])+$timeoffset)." ".$abbrev,
							'to_date'=> date('D M d, Y h:i A',strtotime($event['news_to_date'])+$timeoffset)." ".$abbrev,
							'from_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_from_date'])))),
							'to_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_to_date'])))),
							'image'			=> $img_url,						
							'islive'			=> $isLive,
							'myfollowersevent'			=> $myFollowersEvent,
							'rsvpStatus'	=>$rsvpStatus,
							'category'  => $this->getCategoryNameByEventId($event['entity_id']),
							'from_date1'=> date('m-d-Y H:i:s', strtotime($event['news_from_date'])+$timeoffset),
							'to_date1'=> date('m-d-Y H:i:s',strtotime($event['news_to_date'])+$timeoffset),
							'HostIsLive' => $HostIsLive,
						);			
					}
				}
				$counter1=0;
				foreach ($events as $k => $event) { $counter++;
				//===================Start Image=====================================
					$customer = Mage::getModel('customer/customer')->load($event['user_id']);
					$fc = strtolower(substr($customer->getFirstname(),0,1));
					if($fc == ''){ $fc = rand(1,10); }

					if($event['event_image']=="''")
					$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
					else{
						if($event['event_image']){
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];
							
							if(fopen($img_url,"r")==false)
								$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
							else
								$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];
						}
						else
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];		
					}
				//===================End Image=====================================
					$rsvp = $resource->getTableName('rsvp');
					$selectSql = "select * from $rsvp WHERE user_id=".$user_id." and event_id=".$event['entity_id']." and status=1";			
					$row = $read->fetchAll($selectSql);
					if(count($row)>0)
						$rsvpStatus=1;
					else
						$rsvpStatus=0;
					$from = strtotime($event['news_from_date'])+$timeoffset;
					$to = strtotime($event['news_to_date'])+$timeoffset;
					if (($now > $from) && ($now < $to)) {
						$isLive="true";
					}
					else{
						$isLive="false";
					}
					if((in_array($event['user_id'],$str)) && ($isLive == "true")){
						$myFollowersEvent = "true";
					}
					else {
						$myFollowersEvent = "false";
					}
				if(($isLive == "true") && !(in_array($event['user_id'],$str))){
					if($this->isUserOnline($event['user_id'])){
						$HostIsLive = "true";
					}else{
						$HostIsLive = "false";
					}
					$result[$counter]['live'] = array(
							'id'=> $event['entity_id'],
							'name'=> $event['name'],
							'price'=> number_format($event['price'],2),
							'user_id'=> $event['user_id'],
							'event_hostedby_username'=> $this->getUserNameByUserId($event['user_id']),
							'description'=> $event['description'],
							'from_date'=> date('D M d, Y h:i A', strtotime($event['news_from_date'])+$timeoffset)." ".$abbrev,
							'to_date'=> date('D M d, Y h:i A',strtotime($event['news_to_date'])+$timeoffset)." ".$abbrev,
							'from_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_from_date'])))),
							'to_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_to_date'])))),
							'image'			=> $img_url,						
							'islive'			=> $isLive,
							'myfollowersevent'			=> $myFollowersEvent,
							'rsvpStatus'	=>$rsvpStatus,
							'numberOfLiveUsers'	=>	$this->getNumberOfUserOnline($event['user_id']),
							'category'  => $this->getCategoryNameByEventId($event['entity_id']),
							'from_date1'=> date('m-d-Y H:i:s', strtotime($event['news_from_date'])+$timeoffset),
							'to_date1'=> date('m-d-Y H:i:s',strtotime($event['news_to_date'])+$timeoffset),
							'HostIsLive' => $HostIsLive,
						);			
					}
				}
				$counter2=0;
				
				foreach ($events as $k => $event) { $counter++;
				//===================Start Image=====================================
					$customer = Mage::getModel('customer/customer')->load($event['user_id']);
					$fc = strtolower(substr($customer->getFirstname(),0,1));
					if($fc == ''){ $fc = rand(1,10); }

					if($event['event_image']=="''")
					$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
					else{
						if($event['event_image']){
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];
							
							if(fopen($img_url,"r")==false)
								$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
							else
								$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];
						}
						else
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];		
					}
				//===================End Image=====================================
					$rsvp = $resource->getTableName('rsvp');
					$selectSql = "select * from $rsvp WHERE user_id=".$user_id." and event_id=".$event['entity_id']." and status=1";			
					$row = $read->fetchAll($selectSql);
					if(count($row)>0)
						$rsvpStatus=1;
					else
						$rsvpStatus=0;
					$from = strtotime($event['news_from_date'])+$timeoffset;
					$to = strtotime($event['news_to_date'])+$timeoffset;
					if (($now > $from) && ($now < $to)) {
						$isLive="true";
					}
					else{
						$isLive="false";
					}
					if((in_array($event['user_id'],$str)) && ($isLive == "true")){
						$myFollowersEvent = "true";
					}
					else {
						$myFollowersEvent = "false";
					}
				if($isLive == "false"){									
					$result[$counter]['upcoming'] = array(
							'id'=> $event['entity_id'],
							'name'=> $event['name'],
							'price'=> number_format($event['price'],2),
							'user_id'=> $event['user_id'],
							'event_hostedby_username'=> $this->getUserNameByUserId($event['user_id']),
							'description'=> $event['description'],
							'from_date'=> date('D M d, Y h:i A', strtotime($event['news_from_date'])+$timeoffset)." ".$abbrev,
							'to_date'=> date('D M d, Y h:i A',strtotime($event['news_to_date'])+$timeoffset)." ".$abbrev,
							'from_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_from_date'])))),
							'to_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_to_date'])))),
							'image'			=> $img_url,							
							'islive'			=> $isLive,
							'myfollowersevent'			=> $myFollowersEvent,
							'rsvpStatus'	=>$rsvpStatus,
							'category'  => $this->getCategoryNameByEventId($event['entity_id']),
							'from_date1'=> date('m-d-Y H:i:s', strtotime($event['news_from_date'])+$timeoffset),
							'to_date1'=> date('m-d-Y H:i:s',strtotime($event['news_to_date'])+$timeoffset),
						);			
					}
				}
				}
				$result['onlineUser']= $this->getOnlineUser($page);
				$result['followersEvent'] = $result['followersEvent'];
				$result['live'] = $result['live'];
				$result['upcoming'] = $result['upcoming'];
				$result['showMore'] = $showMore;
				return $result;
			}
			else{
				$result['onlineUser']= $this->getOnlineUser($page);
				return $result;
			}
				
		}
	}
	public function getTopEventsCount() {
		$cat=3; $limit=10;
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		$now = strtotime(now())+$timeoffset;
			
		$websiteId = Mage::app()->getWebsite()->getId();
		$storeId = Mage::app()->getStore()->getId();
		
		$visibility = array(	
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
              );

      		$events = Mage::getModel('catalog/category')->load($cat)
							->getProductCollection()
							->addAttributeToSelect('*')
							->addAttributeToSelect('category_id')
							->addAttributeToSelect('status')
							->addFieldToFilter('status', 1)
							->addAttributeToFilter('is_expired', array('neq' => 1))
							->addAttributeToSort('news_from_date', 'desc')
							->addAttributeToSort('position', 'desc')
							->addAttributeToFilter('news_to_date', array('gteq' => date('Y-m-d H:i:s')));	
			
			$events->addAttributeToFilter('visibility', $visibility)											
							->load()->toArray();
			
			if(count($events)>0)
				return count($events);
			else
				return 0;
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
	
    public function getRandomEvents($count = 0) {
		$count = intval($count);
		if ($count == 0) {
			$count = self::$maxRecordCount;
		}
		$count = min(self::$maxRecordCount, $count);
		
		$categoryId = $this->getCategoryIdByName("Live Events");
		
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
		
		$returnVal = array('Events' => array());

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
			$returnVal['Events']['' . $product->getId()] = $this->getProduct($product->getId(), true, false, false);
			$prod_ids[] = $product->getId();
			$i++;
		}
		$EventUses = $this->getEventUses($prod_ids);
		foreach ($EventUses as $key => $value) {
			$returnVal['Events'][$key]['EventUses'] = $value;
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
	public function getTopSellingEvents() {
		$categoryId = $this->getCategoryIdByName("Live Events");
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
		//$prod_ids = array();
		$returnVal = array('Events' => array());
		foreach ($coll as $product) {
			$returnVal['Events']['' . $product->getId()] = $this->getEvent($product->getId());
			//$prod_ids[] = $product->getId();
		}
		
		return $returnVal;
	}
	
	public function getLatestEvents($count = 0) {
        return $this->loadCache()->__getLatestEvents($count);
    }

	public function __getLatestEvents($count = 0) {
		$count = intval($count);
		if ($count == 0) {
			$count = self::$maxRecordCount;
		}
		$count = min(self::$maxRecordCount, $count);
		$categoryId = $this->getCategoryIdByName("Live Events");
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
                ->addAttributeToSort('repeats_Event', 'desc');
		$coll->getSelect()->where('category_ids IN (' . $cats . ')');

		$coll->setOrder('created_at', 'desc');
		//
		$prod_ids = array();
		$returnVal = array('Events' => array());
		$i = 0;
		foreach ($coll as $product) {
			if ($i >= $count) {
				break;
			}
			$returnVal['Events']['' . $product->getId()] = $this->getProduct($product->getId(), true, false, false);
			$prod_ids[] = $product->getId();
			$i++;
		}
		$EventUses = $this->getEventUses($prod_ids);
		foreach ($EventUses as $key => $value) {
			$returnVal['Events'][$key]['EventUses'] = $value;
		}
		return $returnVal;
	}
	//
	public function getFeaturedEvents($count = 0) {
        return $this->loadCache(self::$cacheLongTimeSpan)->__getFeaturedEvents($count);
    }
	public function __getFeaturedEvents($count = 0) {
		$count = intval($count);
		if ($count == 0) {
			$count = self::$maxRecordCount;
		}
		$count = min(self::$maxRecordCount, $count);
		$categoryId = $this->getCategoryIdByName("Live Events");
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
				->addAttributeToFilter('featured_Event', 1);
		$coll->getSelect()->where('category_ids IN (' . $cats . ')');
		$coll->setOrder('created_at', 'desc');
		//
		$prod_ids = array();
		$returnVal = array('Events' => array());
		$i = 0;
		foreach ($coll as $product) {
			if ($i >= $count) {
				break;
			}
			$returnVal['Events']['' . $product->getId()] = $this->getProduct($product->getId(), true, false, false);
			$prod_ids[] = $product->getId();
			$i++;
		}
		$EventUses = $this->getEventUses($prod_ids);
		foreach ($EventUses as $key => $value) {
			$returnVal['Events'][$key]['EventUses'] = $value;
		}
		return $returnVal;
	}
    //
    
	//get Category image
	/* public function getCategoryImage($cid){
		$EventObj = new Event_Profile_Block_View();
		$rsCatImage = $EventObj->getCategoryImage($cid);
		if(isset($rsCatImage["small_image"]) && $rsCatImage["small_image"]!=''){
			return $rsCatImage["small_image"];
		}else{
			$imgUrl = '';
			$rsCatFirstPID = $EventObj->getFirstEventOfCollection($cid);
			if (count($rsCatFirstPID ) > 0) {
				$rsCatFirstPID = $rsCatFirstPID[0];
				$rsCatData = $EventObj->getEventInfo($rsCatFirstPID['product_id']);
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
	} */
	
	//
	/* public function getEventsByCollection($collectionId,$drep=1,$dpat=1,$dport=0,$ua=0, $sku='') {
		$collectionId = intval($collectionId);
		if ($collectionId == 0) {
			throw new Exception(self::$invalidCollectionId);
		}
		$tree = $this->getProducts($collectionId,$drep,$dpat,$dport,$ua,$sku);
		$returnVal = array();
		$returnVal['Events'] = $tree['products'];
		return $returnVal;
	} */
    
	
	
	//saveVideos
	public function getCategoryIdByName($name="Live Events", $id=1) {
        return $this->loadCache(self::$cacheFiveDaysSpan)->__getCategoryIdByName($name, $id);
    }
    public function __getCategoryIdByName($name="Live Events", $id=1) {
		$id = intval($id);
        $cat = $this->getEventCategoryByName($name, $id);
		if (isset($cat['entity_id'])) {
			return $cat['entity_id'];
		}
		throw new Exception(self::$invalidCollectionId);
    }
    public function getEventCategoryByName($name="Live Events", $id=1) {
        return $this->loadCache()->__getEventCategoryByName($name, $id);
    }
    public function __getEventCategoryByName($name="Live Events", $id=1) {
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
		   $returnVal = array_merge($returnVal, $this->__getEventCategoryByName($name, $child->getId()));
       }

	   return $returnVal;
    }
	
	private function _getSession()
	{
		return Mage::getSingleton('customer/session');
	}
	
	public function saveRecording($category=0, $name='', $desc='', $profile_id=0, $user_id=0, $video_path='', $thumbnail_path='',  $ip_address='', $yt=0, $fb=0, $tags='', $taggedUsers='', $duration=0) {
			//return $category.'***category***'. $name.'***name***'.$desc.'***desc***'.$profile_id.'***profile_id***'.$user_id.'***user_id***'.$video_path.'***video_path***'.$thumbnail_path.'***thumbnail_path***'.$ip_address.'***ip_address***'.$yt.'***yt***'.$fb.'***yt***'.$tags.'***tags***'. $taggedUsers;die;
			$catId = $this->getCategoryIdByName("Chattrspace Videos", $id=1);
			$catId = $catId.','.$category;
			try 
			{	$filename = basename($video_path);
				$video_path = $filename;
				
				$thumbnail_path = basename($thumbnail_path);
				
				$customer = Mage::getModel('customer/customer')->load($user_id);
				$username = $customer->getUsername();
				
				$pcustomer = Mage::getModel('customer/customer')->load($profile_id);
				$pusername = $pcustomer->getUsername();
				
				$new_title = $username .' chatting in '.Mage::getStoreConfig('general/profile/smiley').$pusername. ' in Oncam';
				if($filename!=''){
					list($p1, $p2, $p3, $p4, $part, $ext) = split('[/.-]', $filename);
					if($part=='' || $part=='0' || empty($ext))
						$new_title = $new_title;
					else
						$new_title = $new_title.' - Part '.$part;
				}
				
				$lastId = $this->addVideoInfo($category, $name, $desc, $profile_id, $user_id, $video_path, $thumbnail_path, $ip_address, $tags, $taggedUsers, $yt, $fb, $duration, $new_title);
				
				$identifier = $name."-".$lastId;
				$identifier=ereg_replace('[^A-Za-z0-9.-]', '-', $identifier);
			
				Mage::getModel('uploadjob/youtube')->upload($user_id, $lastId, $video_path, $new_title, $name, $desc, $yt, $fb, $identifier);
				return $lastId;
				//return $lastId ;
			} catch (Exception $e) {
				throw new Exception('Error: ' . $e->getMessage());
			}
	}
	
	//add video/recording info
	public function addVideoInfo($category=0, $title='', $desc='', $profile_id=0, $user_id=0, $video_path='', $thumbnail_path='', $ip_address='', $tags, $taggedUsers='',$yt=0, $fb=0, $duration=0, $filename='test') {
		
		try 
			{
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$table = $resource->getTableName('video');
		
		
		$sqlInsert = " insert into $table (title, description, category, profile_id, user_id, video_path, thumbnail_path, ip_address, created_time, tags, taggedUsers, youtube, facebook, status, duration) values('".$filename."', '".$desc."', ".$category.", ".$profile_id.", ".$user_id.", '".$video_path."', '".$thumbnail_path."', '".$ip_address."',  now(),'".$tags."', '".$taggedUsers."', ".$yt.",  ".$fb.", 1, ".$duration.")";
			
			$write->query($sqlInsert);
			$lastInsertId = $write->lastInsertId();
			
			$customer = Mage::getModel('customer/customer')->load($user_id);
			$new_duration = ($customer->getVideoDuration() + $duration);
			$customer->setVideoDuration($new_duration);
			$customer->save();
			
			$identifier = $title."-".$lastInsertId;
			$identifier=ereg_replace('[^A-Za-z0-9.-]', '-', $identifier);
			$write->query('update '.$table.' set identifier="'.$identifier.'" where video_id='.$lastInsertId);
			return $lastInsertId;
		
		} catch (Exception $e) {
			throw new Exception('Error: ' . $e->getMessage());
		}
		
		//return $returnVal;
	}
		
	//add recording info
	public function addRecordingInfo($recording_id=0, $category=0, $name='', $desc='', $profile_id=0, $user_id=0, $video_path='', $thumbnail_path='', $ip_address='', $tags, $taggedUsers='') {
		$recording = Mage::getModel('catalog/product')->load($recording_id);
		$att_set_name = $this->getAttributeSetName($recording->getAttributeSetId());
		
		$returnVal = array();
		if($recording_id!=0 && $att_set_name=='Videos'){
			$resource = Mage::getSingleton('core/resource');
			//$read = $resource->getConnection('core_read');
			$write = $resource->getConnection('core_write');
			$table = $resource->getTableName('recording');
			
			$sqlInsert = " insert into $table (recording_id, profile_id, user_id, video_path1, file_path1, ip_address, created_on, tags) values(".$recording_id.", ".$profile_id.", ".$user_id.", '".$video_path."', '".$thumbnail_path."', '".$ip_address."',  now(),'".$tags."')";
			
			$write->query($sqlInsert);
			//$rs = mysql_query($sqlInsert);
			return 'success';
		}else{
			
			return 'fail';
		}
		//return $returnVal;
	}
	
	//get video info
	public function getRecordingInfo($recording_id) {
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$videoTable = $resource->getTableName('video');
		
		$select = ' select video_id, title, identifier, description, profile_id, user_id, video_path, thumbnail_path, duration, tags, created_time  from '.$videoTable.' where status = 1 and video_id = '.$recording_id;
		
		$item = array();
		//echo $video['video_id'];
		$rs = mysql_query($select);

		$numResults = mysql_num_rows($rs);
		if($numResults>0){
			$rowTag = mysql_fetch_row($rs);
			if($rowTag[1]!=''){
				list($p1, $p2, $p3, $p4, $title, $ext) = split('[/.-]', $rowTag[6]);
				if($title=='0')
					$title = $rowTag[1];
				else
					$title = $rowTag[1].' - Part '.$title;
			}
			else
				$title = 'Chattrspace Video';
				
			$item['video_id'] = $rowTag[0];
			$item['title'] = $title;
			$item['identifier'] = $rowTag[2];
			$item['description'] = $rowTag[3];			
			$item['profile_id'] = $rowTag[4];
			$item['user_id'] = $rowTag[5];
			$item['video_path'] = $rowTag[6];
			//$item['video_path'] = realpath('/mnt/mediafiles/completed').'/'.$rowTag[6];
			$item['thumbnail_path'] = $rowTag[7];
			$item['duration'] = $rowTag[8];
			$item['tags'] = $rowTag[9];
			$item['views'] = $rowTag[10];
			$item['created_on'] = $rowTag[11];
			//$returnVal['recording'][] = $item;	
		} 
		
		return  $item;  
	}		
	//get recording info
	/* public function getRecordingInfo($recording_id) {
		$recording = Mage::getModel('catalog/product')->load($recording_id);
		$att_set_name = $this->getAttributeSetName($recording->getAttributeSetId());
		
		$returnVal = array();
		if($recording_id!=0 && $att_set_name=='Videos'){
			$returnVal['recordingId'] = $recording->getId();
			$returnVal['userId'] = $recording->getUserId();
			$returnVal['profileId'] = $recording->getProfileId();
			$returnVal['recordingFiles'] = $this->getRecordingData($recording_id);
			//$returnVal['recordingFile2'] = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)."csrecordings/".$recording->getVideoFilePath2();
			$returnVal['recordingName'] = $recording->getName();
			//$returnVal['recordingDuration'] = $recording->getDuration();
			
			$returnVal['status'] = $recording->getStatus();
		}else{
			$returnVal['recordingId'] = 0;
			$returnVal['userId'] = 0;
			$returnVal['profileId'] = 0;
			$returnVal['recordingFiles']='';
			$returnVal['recordingName'] = '';
			$returnVal['status'] = 0;
		}
		return $returnVal;
	} */
	
	public function getRecordingData($recording_id=0, $profile_id=0, $user_id=0, $tags='') {
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		//$write = $resource->getConnection('core_write');
		$table = $resource->getTableName('recording');
		
		$sqlSelect = " Select id, recording_id, profile_id, user_id, video_path1, video_path1, video_path3, video_path4, file_path1, file_path2, file_path3, file_path4, ip_address, tags, views, created_on from $table where recording_id = ".$recording_id;
		
		if($user_id>0)$sqlSelect.=" and user_id = $user_id";
		if($profile_id>0)$sqlSelect.=" and profile_id = $profile_id";
		if($tags != '')$sqlSelect.=" and tags like('%".$tags."%')";
		
		$rs = mysql_query($sqlSelect);

		$numResults = mysql_num_rows($rs);
		$returnVal = array();
		//$rowTag = mysql_fetch_row($rs);
		$prefix = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)."csrecording/";
		for($k=0; $k < $numResults; $k++)
		{
			$rowTag = mysql_fetch_row($rs);
			$item = array();			
			$item['id'] = $rowTag[0];
			$item['recording_id'] = $rowTag[1];
			$item['profile_id'] = $rowTag[2];
			$item['user_id'] = $rowTag[3];
			$item['video_path1'] = $prefix.$rowTag[4];
			$item['video_path2'] = $prefix.$rowTag[5];
			$item['video_path3'] = $prefix.$rowTag[6];
			$item['video_path4'] = $prefix.$rowTag[7];
			$item['file_path1'] = $prefix.'images/'.$rowTag[8];
			$item['file_path2'] = $prefix.'images/'.$rowTag[9];
			$item['file_path3'] = $prefix.'images/'.$rowTag[10];
			$item['file_path4'] = $prefix.'images/'.$rowTag[11];
			$item['ip_address'] = $rowTag[12];
			$item['tags'] = $rowTag[13];
			$item['views'] = $rowTag[14];
			$item['created_on'] = $rowTag[14];
			$returnVal['recording'][] = $item;		
		}
		return $returnVal;
	}
	
	public function getViewCountByRecordingId($id) {
			$id = intval($id);
			$resource = Mage::getSingleton('core/resource');
			$read = $resource->getConnection('core_read');
			//$write = $resource->getConnection('core_write');
			$table = $resource->getTableName('recording');
			
			$sqlSelect = " Select views from $table where id = ".$id;
			$rs = mysql_query($sqlSelect);

			$numResults = mysql_num_rows($rs);
			$rowTag = mysql_fetch_row($rs);
			
			$viewCount = 1;
			if($rowTag[0] > 0){
				$viewCount = $rowTag[0];
			}
			
			return $viewCount;
	}
	
	public function addViewCountByRecordingId($id) {
			$viewCount = $this->getViewCountByRecordingId($id);
			$resource = Mage::getSingleton('core/resource');
			//$read = $resource->getConnection('core_read');
			$write = $resource->getConnection('core_write');
			$table = $resource->getTableName('recording');
			$viewCount = $viewCount+1;
			$write->query("update $table set views=$viewCount where id = ".$id);
			
			return $viewCount;
	}
	
	public function javaServiceCall($saveVideo) {
			//print_r($saveVideo);
			return $saveVideo." Chattrspace";
	}
	
	public function saveYt($un='', $pw='') {
		//$user_id = intval($user_id);
		//return $un;
		if(Mage::getSingleton('customer/session')->isLoggedIn()){
			
			//$username = $this->encode($username);
			$yt_auth = Mage::getModel('uploadjob/uploadjob')->clientLoginAuth($un, $pw);
			if($yt_auth['username']){
				$password = $this->encode($pw);
				
				$user_id = Mage::getSingleton( 'customer/session' )->getCustomerId();
				$customer = Mage::getModel('customer/customer')->load($user_id);
				$customer->setYoutubename($un);
				$customer->setYoutubepassword($pw);
				$customer->save();
				return $user_id;
			}
			return 0;
		}
		else
			return 0;		
	}
	
	public function saveYoutubeInfo($username='', $password='') {
		//$user_id = intval($user_id);
		return Mage::getSingleton('customer/session')->getCustomerId();;
		if(Mage::getSingleton('customer/session')->isLoggedIn()){
			
			//$username = $this->encode($username);
			$password = $this->encode($password);
			
			$user_id = Mage::getSingleton( 'customer/session' )->getCustomerId();
			$customer = Mage::getModel('customer/customer')->load($user_id);
			$customer->setYoutubename($username);
			$customer->setYoutubepassword($password);
			$customer->save();
			return $user_id;
		}
		else
			return 0;		
	}
	
	public function LinkFBUserAndGetInfo($first_name, $last_name, $about_me, $facebook_id, $email, $gender, $username, $userid=0)
    {
		$returnVal = array();
        $resource = Mage::getSingleton('core/resource');
        $read= $resource->getConnection('core_read');
        $write = $resource->getConnection('core_write');
		$widget_fb_reg = $resource->getTableName('widget_fb_reg');
		$rs = $read->fetchRow("SELECT * FROM $widget_fb_reg WHERE widgetid='default' AND uid='".$userid."'");
		if((count($rs[id])) && ($userid > 0)){
			$returnVal['userInfo'] = $this->getUserInfo($userid);
			return $returnVal;
		}
		else{
        $customer = Mage::getModel('customer/customer');
        if($this->checkFbid($facebook_id)){
            $user_id = $this->checkFbid($facebook_id);
            //$returnVal = $this->getUserInfo($user_id);
        }else if ($this->checkEmail($email)) {
            $user_id = $this->checkEmail($email);
            //$returnVal = $this->getUserInfo($user_id);
        }else if ($this->checkUsername($username)) {
            $user_id = $this->checkUsername($username);
            $suffix = rand(11111, 99999);
            $uname = $username.'_'.$suffix;
            
            $websiteId = Mage::app()->getWebsite()->getId();
            $customer->website_id = $websiteId; 
            $customer->store=0;
            $customer->username = $uname;
            $customer->firstname = $first_name;
            $customer->lastname = $last_name;
            $customer->email = $email;
            $customer->sex = $gender;	
            $customer->shortbio = $about_me;
            $randomPassword = $customer->generatePassword();
            $customer->password = $randomPassword;
            $customer->save();
            $user_id = $customer->getId();
            //$returnVal = $this->getUserInfo($lastId);
        }else {
            $websiteId = Mage::app()->getWebsite()->getId();
            $customer->website_id = $websiteId; 
            $customer->store=0;
            $customer->username = $username;
            $customer->firstname = $first_name;
            $customer->lastname = $last_name;
            $customer->email = $email;
            $customer->sex = $gender;	
            $customer->shortbio = $about_me;
            $randomPassword = $customer->generatePassword();
            $customer->password = $randomPassword;
            $customer->save();
            $user_id = $customer->getId();
            //$returnVal = $this->getUserInfo($lastId);
        }
        if($user_id > 0) {
            $customers = Mage::getModel('customer/customer');
            $collection = $customers->getCollection()
                                    ->addAttributeToFilter('entity_id', (string)$user_id)
                                    ->setPageSize(1);
            $existingCustomer = $collection->getFirstItem();
            $session = Mage::getSingleton("customer/session");
            $session->setCustomer($existingCustomer);
            $session->setCustomerAsLoggedIn($existingCustomer);
        }
		$returnVal['userInfo'] = $this->getUserInfo($user_id);
        return $returnVal;
		}
    }
	
	public function LinkTWTUserAndGetInfo($first_name="", $last_name="", $about_me="", $twitter_id, $gender="", $username, $userid=0,$twitterOauthSecret="",$twitterOauthToken="")
    {
		$current_customer = Mage::getModel('customer/customer')->load($userid);
		$returnVal = array();
		$customer = Mage::getModel('customer/customer')->load($userid);
		if($this->checkTWTid($twitter_id)){
            $user_id = $this->checkTWTid($twitter_id);
            return 'Already connected with this user-'.$user_id;
        }
		if(($customer->getTwitterId()) && ($userid > 0)){
			//$returnVal['userInfo'] = $this->getUserInfo($userid);
			return 'Already connected with other twitter account-'.$customer->getTwitterId();
		} else {
		
			$customer->setTwitterId($twitter_id);
			$customer->setStwitter('168');
			$customer->setTwitterUsername($twitterUsername);
			$customer->setTwitterOauthSecret($twitterOauthSecret);
			$customer->setTwitterOauthToken($twitterOauthToken);
			$customer->save();
            $returnVal['userInfo'] = $this->getUserInfo($customer->getId());
			$returnVal['response'] = "Succesfully linked with Twitter";
			return $returnVal;
        }
        
    }
	
	public function unlinkFacebook($user_id) {
		//$user_id = intval($user_id);
		//if(Mage::getSingleton( 'customer/session' )->isLoggedIn()){
			
			//$user_id = Mage::getSingleton( 'customer/session' )->getCustomerId();
			$customer = Mage::getModel('customer/customer')->load($user_id);
			//$customer->setFacebookUid('');
			$customer->setFacebookUsername('');
			//$customer->setFacebookCode('');
			$customer->save();
			
			$resource = Mage::getSingleton('core/resource');
			$write = $resource->getConnection('core_write');
			$widget_fb_reg = $resource->getTableName('widget_fb_reg');
			$write->query("DELETE FROM $widget_fb_reg WHERE widgetid=0 AND uid='".$user_id."'");
			return $user_id;
		//}
		//else
			//return 0;		
	}
	
	public function unlinkTwitter($user_id) {
		//$user_id = intval($user_id);
		//if(Mage::getSingleton( 'customer/session' )->isLoggedIn()){
			//$user_id = Mage::getSingleton( 'customer/session' )->getCustomerId();
			$customer = Mage::getModel('customer/customer')->load($user_id);
			$customer->setTwitterId('');
			$customer->setTwitterUsername('');
			$customer->setTwitterOauthSecret('');
			$customer->setTwitterOauthToken('');
			$customer->setTwitterAccessToken('');
			$customer->save();
			return $user_id;
		//}
		//else
			//return 0;		
	}
	
	public function uploadRecording($username ='moanindia', $password='madhureddy07', $rec_id=0, $fullFilePath='', $filename='test', $title ='Test by Mohan', $description='test description', $type='youtube', $user_id=0) {
		$resource = Mage::getSingleton('core/resource');
		$write = $resource->getConnection('core_write');
		$table = $resource->getTableName('uploadjob');
			
		if($type == 'youtube'){
			
			$sqlInsert = " Insert into $table (recording_id, user_id, attampt, status, type, message, created_time, title, filename, filepath, content) values (".$rec_id.", ".$user_id.", 1, 1, 1, 'request', now(), '".$title."', '".$filename."', '".$fullFilePath."', '".$description."');";
				$write->query($sqlInsert);
				return $lastInsertId = $write->lastInsertId();
				//$write->query("update $table set status=1, notify=1, follow_on=now()  WHERE follower_id=".$follower." and follow=".$followed);
			//$obj = Mage::getModel('uploadjob/youtube');
			//$result = $obj->upload($username, $password, $rec_id, $fullFilePath, $filename, $title , $description);
		}
	}
	
	public function encode($string,$key="chattrspace rocks") {
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
	
	public function decode($string,$key="chattrspace rocks") {
    
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
		return base64_decode(base64_decode($hash));
	}
	//sendUserIsSelectedByHostDuringLiveChatFollowersMail
	
	public function sendLiveChatMail2($pid) {
		if (Mage::getSingleton( 'customer/session' )->isLoggedIn()) {
        $uid = Mage::helper( 'customer/session' )->getCustomerId();
        
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write= $resource->getConnection('core_write');
		$jobs_mail = $resource->getTableName('jobs_mail');
		$sqlInsert = " Insert into jobs_mail (user_id, profile_id, type, schedule, fuction_name, message, created_on, status) values (".$uid.", ".$pid.",'livechat', 0, 'sendUserIsSelectedByHostDuringLiveChatFollowersMail', 'request', now(), 1);";
		$write->query($sqlInsert);
		//$uid = Mage::getModel('csservice/mail')->sendUserIsSelectedByHostDuringLiveChatFollowersMail($uid, $pid)
		return $pid;
		}
		else
			throw new Exception(self::$loginError);
	}
	
	public function sendLiveChatMail($pid, $uid, $template='') {
		//if (Mage::getSingleton( 'customer/session' )->isLoggedIn()) {
        //$uid = Mage::helper( 'customer/session' )->getCustomerId();
        
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write= $resource->getConnection('core_write');
		$jobs_mail = $resource->getTableName('jobs_mail');
		
			$follwer = Mage::getModel('csservice/csservice');
			$followers =$this->getFollowersById($uid);
			$i=0;
			foreach($followers as $follower)
			{	$i++;
				$customer = Mage::getModel('customer/customer')->load($follower['follower_id']);
				$notice = $customer->getNotice();
				$a = explode(",",$notice);	
				
				if($customer->getId() && in_array(159,$a)){
					$sqlInsert = " Insert into jobs_mail (user_id, profile_id, type, schedule, fuction_name, message, created_on, status, mail_to) values (".$uid.", ".$pid.",'livechat', 0, 'sendUserIsSelectedByHostDuringLiveChatFollowersMail2', 'request', now(), 1, ".$customer->getId().")";
					$write->query($sqlInsert);
				}
			}
		
		return $pid;
		//}
		//else
			//throw new Exception(self::$loginError);
	}
	
	public function reportUser($profile_id=0, $data=null, $flagged_userId=0) {
		return $this->reportFeedInHttp($feed_id=0, $reported_by=0, $profile_id, $data, $mesg=null, $type="user", $flagged_userId);
	}
	
	public function reportFeedInHttp($feed_id=0, $reported_by=0, $profile_id=0, $data=null, $mesg=null, $type="feed", $flagged_userId=0) {
		
		$reported_by = intval($reported_by);
		$profile_id = intval($profile_id);
		
		if ($this->isLoggedIn() == false) {
			throw new Exception(self::$loginError);
		} 
		//$UID = $this->getUserId();
		$reported_by = $this->getUserId();
		
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$table = $resource->getTableName('feedreports');
		//
		$item = array();$thelastId=0;
		if($reported_by>0){
			$sqlInsert = " insert into $table(feed_id, reported_by, reported_user, created_time, status, type, profile_id) values(" . $feed_id . ", " . $reported_by . ", " . $flagged_userId . ", '" . date("Y-m-d G:i:s") . "', 1, '".$type."', " . $profile_id . ",);";
			try {
				$write->query($sqlInsert);
			} catch (Exception $e) {
				throw new Exception("Error while saving : ".$e->getMessage());
			}
			$thelastId = $write->lastInsertId();
			
			if (isset($_POST["data"]) && ($_POST["data"] !="")){
				$data = $_POST["data"];
				$data = base64_decode($data);
				$im = imagecreatefromstring($data);
			
				//make a file name
				$fileName = strtoupper( $reported_by . $thelastId . "-" . $this->getRandomString(8) );
				
				//save the image to the disk
				if (isset($im) && $im != false) {
					$fullFilePath = self::$EventImageFolder . $fileName;
					$image_path = $fullFilePath . '_img.jpg';	
					$path = realpath('.')."/media/csreport_images/";
					$fullFilePath = $path . $image_path;
					//delete the file if it already exists
					if(file_exists($fullFilePath)){
						unlink($fullFilePath);      
					}

					$result = imagepng($im, $fullFilePath);
					imagedestroy($im);
					
					
					$sqlUpdate = " UPDATE $table SET filename = '".mysql_real_escape_string($image_path)."' WHERE feedreports_id = " . $thelastId . ";";
		
					mysql_query($sqlUpdate);
				}
				else {
					//return 'Error';
				}
			}
		
		//end when pre-customized product then add a new product
		
		$sqlSelect = " Select feed_id, reported_by, filename, status, created_time, profile_id from $table where feedreports_id = " . $thelastId . " LIMIT 1;";
		$rs = mysql_query($sqlSelect);

		$numResults = mysql_num_rows($rs);
		$returnVal = array();
		$rowTag = mysql_fetch_row($rs);
		
		$item = array();
		$item['feed_id'] = $rowTag[0];
		$item['reported_by'] = $rowTag[1];
		$item['filename'] = $rowTag[2];
		$item['status'] = $rowTag[3];
		$item['created_time'] = $rowTag[4];
		$item['profile_id'] = $rowTag[5];
		
		$returnVal['report'] = $item;		
		//end post
		
		return $returnVal;
		}
		else
			return 'nodata';
	}
	
	//widget code
	public function getWidgetInfo($widget_key='') {
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		//$write = $resource->getConnection('core_write');
		$widget = $resource->getTableName('widget_info');
		
		$sqlSelect = " Select $widget.* from $widget where widget_key = '" . $widget_key."'";
		return $rs = $read->fetchRow($sqlSelect);
		/* if(count($rs['widget_id'])>0){
			$result = $rs;
		}	 */	
		
	}
	
	//widget code
	public function getWidgetDetailedInfo($widget_key='',$user_id=0) {
		$returnVal = array();
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		//$write = $resource->getConnection('core_write');
		$widget = $resource->getTableName('widget_info');
		$sqlSelect = " Select $widget.* from $widget where widget_key = '" . $widget_key."'";
		$rs = $read->fetchRow($sqlSelect);
		$returnVal['widketKey'] = $widget_key;
		$returnVal['userInfo'] = $this->getUserInfo($user_id);
		$returnVal['hostInfo'] = $this->getUserInfo($rs['user_id']);
		$returnVal['widgetInfo'] = $rs;
		return $returnVal;
		/* if(count($rs['widget_id'])>0){
			$result = $rs;
		}	 */	
		
	}
	
	//for check access to a running event for a user and a profile.
	public function hasRunningEventAccess($user_id, $profile_id, $status=1){
		$productCount = 1;	 
		$dateTime = date('Y-m-d H:i:s');
		$dateTime_from = date('Y-m-d H:i:s', strtotime($dateTime)+10*60);
		$dateTime_to = date('Y-m-d H:i:s', strtotime($dateTime)-10*60);

		$events = Mage::getResourceModel('catalog/product_collection')
					->addAttributeToSelect('*')
					->addFieldToFilter('user_id', array('eq'=> $profile_id))
				   ->addFieldToFilter('news_from_date', array('lteq'=> $dateTime_from))
				   ->addFieldToFilter('news_to_date', array('gteq'=> $dateTime_to))
				   ->addAttributeToFilter('status', 1)
				   ->setOrder('news_to_date', 'asc')
				   ->setOrder('entity_id', 'desc')
				   ->setPageSize($productCount)->setOrder('news_to_date', 'asc')->setOrder('entity_id', 'desc')
				   ->load()->toArray(); 			
		foreach($events as $evt){
			$eventsarr = $evt;				
		}
		
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		$table = $resource->getTableName('event_attending');
		$event_id = $eventsarr['entity_id'];
		if($event_id > 0){
			$select = "select count(*) as countrow from $table WHERE user_id=".$user_id." and event_id=".$event_id." and is_visible=1";
			$rs = $read->fetchRow($select);
			$countrow = $rs['countrow'];
			if($countrow>0)
				$eventsarr['is_access'] = true;
			else
				$eventsarr['is_access'] = false;
		}
		else{
			$eventsarr['is_access'] = true;
		}
		return $eventsarr;
	}
	public function updateProfileInfo($user_id,$first_name,$last_name,$short_bio,$location,$dob,$website,$username=""){
		$customer = Mage::getModel('customer/customer')->load($user_id);
		if($username != ""){
					$collection = Mage::getResourceModel('customer/customer_collection')
									->addAttributeToFilter('username', $username);
									
					if(strlen($username) < 6 || strlen($username) > 15){
						return 'ERROR:Your username should be 6 to 15 characters long';
					}elseif (!preg_match('/^([a-zA-Z0-9_]+)$/', $username) ) {
						return 'ERROR:Your username should contain only letters, numbers and underscore';
							
					}
					elseif(Mage::getModel('profile/profile')->checkReserveBadKey($username) == 0){
						return 'ERROR:Choose another username';
					}
					elseif(Mage::getModel('profile/profile')->checkReserveBadKey($username) == 1){
						return 'ERROR:Username already taken';
					}
					elseif( $collection->count() > 0 ){
						return 'ERROR:Your username should be unique';
					}else{
						$customer->setUsername($username); 
					}
		}
		if(Mage::getModel('profile/profile')->checkFirstLastName($first_name) == 0){
			return 'ERROR:Bad words, Choose another firstname';
		} else {
			$customer->setFirstname($first_name);
		}
		if(Mage::getModel('profile/profile')->checkFirstLastName($last_name) == 0){
			return 'ERROR:Bad words, Choose another lastname';
		} else {
			$customer->setLastname($last_name);
		}
		if($dob != ""){
		$dob_arr = explode('/',$dob);
		$month = $dob_arr[0];
		$day = $dob_arr[1];
		$year = $dob_arr[2];
		if($month != '' && $year != '' && $day != '')
		{
			$m_dob=$month."/".$day."/".$year;	
			if(($month==4 || $month==6 || $month==9 || $month==11) && $day ==31){
				return "ERROR:Select Valid Date Of Birth";
			}
			elseif($month == 2){
				$isleap = ($year % 4 == 0 && ($year % 100 != 0 || $year % 400 == 0));
				if ($day> 29 || ($day ==29 && !$isleap)){
					return "ERROR:Select Valid Date Of Birth";
				}
			}elseif($this->getAge($m_dob) < 13) {
				return 'ERROR:age must be greater than or equal to 13 years';
			}
			else{
				$customer->setDob($m_dob);
			}
		} else{
			return 'ERROR:Date of Birth can not be blank';
		}
		}
		if(strlen($short_bio) > 140){
			$short_bio = substr($short_bio,0,140);
		}
		$customer->setShortbio($short_bio);
		$customer->setLocation($location);
		$customer->setweb($website);
		
		try{
			$customer->save();
			//===================================================================
			Mage::getModel('profile/profile')->updateJabberRosterInfo($user_id);
			//===================================================================
			return "Account Information Successfully Updated";
		}catch(Exception $e){
			return "Unsuccess";
		}
	}
	public function getPeopleSearchMservice($search='', $more=0, $limit=10, $last_id=0,$user_id) {
			$search = mysql_real_escape_string(trim($search));
			
			$collection = Mage::getResourceModel('customer/customer_collection')
				->addAttributeToSelect('*')
				->addAttributeToFilter(array(
											array(
												'attribute' => 'username',
												'like'        => '%'.$search.'%',
												),
											array(
												'attribute' => 'firstname',
												'like'        => '%'.$search.'%',
												),
											array(
												'attribute' => 'email',
												'like'        => '%'.$search.'%',
												),
											));
				
			$collection = $collection->setPageSize($limit)->setPage($more, $limit);
			//$lastPage = $collection->getLastPageNumber();
			$collection = $collection->load()->toArray();
			$user = Mage::getSingleton('customer/session');

			foreach($collection as $k=>$user){ //echo "test-".$user['entity_id']; exit;
				$customer = Mage::getModel('customer/customer')->load($user['entity_id']);
				$follower = $this->isFollow($user['entity_id'],$user_id);
				$follwer = $this->getFollowersCount($user['entity_id']);
				$host_count = Mage::getModel('events/events')->getEventHostingCount($user['entity_id']);
				$shortbio=$customer->getShortbio();
				if($follower!=1){
					$status = "Follow";
					$isFollow = "false";
					$mesg = 1;					
				}
				else{ 
					$status = "Unfollow";
					$isFollow = "true";
					$mesg = 2;
				}
				if(Mage::getSingleton('customer/session')->isLoggedIn()){
					$login=1;
				}
				else{
					$login=0;
				}
				$data[$k] = array(
						'id'=>$user['entity_id'],
						'username'=>$user['username'],
						'name'=>$user["firstname"].' '.$user["lastname"],
						'follower'=>$follwer,
						'status'=>$status,
						'isFollow' => $isFollow,
						'login'=>$login,
						'mesg'=>$mesg,
						'events'=>$host_count,
						'shortbio'=>$shortbio,
						'views'=>Mage::getModel('csservice/csservice')->getCheckinCount($user['entity_id']),
						'url'=> '/'.Mage::getModel('csservice/csservice')->_profile_url.$user["username"],
						'image'=>$this->getProfilePic($user['entity_id']),
					); 
			
			}

			
			
			return $data;
	}
	public function getSearchEventPeople($search,$user_id=0, $page=1){
		
		$events = $this->getSearchEvents($cat=3, $price='', $date='', $page, $search, $user_id, $attending=false, $friend='', $limit=10);
		$people = $this->getPeopleSearchMservice($search, $page, $limit=10, 0,$user_id);
		$people_count = Mage::getModel('csservice/csservice')->getPeopleSearchCount($search, 0);
		if($events['total_count'] > 0)
			$event_count = $events['total_count'];
		else
			$event_count = 0;
		$data['events']['data'] = $events; 
		$data['events']['total_count'] = $event_count;
		$data['people']['data'] = $people;
		$data['people']['total_count'] = $people_count; 
		return $data;
	}
	public function getSearchEvents($cat=3, $price='', $date='', $more, $search, $user_id=0, $attending=false, $friend='', $limit=10){  		
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		if($time_zone == 'America/Los_Angeles'){
			$now = strtotime('+1 hour')+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime('+1 hour'));
		} else{
			$now = strtotime(now())+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime(now()));
		}
		$websiteId = Mage::app()->getWebsite()->getId();
			$storeId = Mage::app()->getStore()->getId();
		$visibility = array(	
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
              );

       	$events = Mage::getModel('catalog/category')->load($cat)
							->getProductCollection()
							->addAttributeToSelect('*')
							->addAttributeToSelect('category_id')
							->addAttributeToSelect('status')
							->addFieldToFilter('status', 1)
							->addAttributeToFilter('is_expired', array('neq' => 1))
							->addAttributeToFilter('news_to_date', array('gteq' => $date))
							->addAttributeToSort('news_from_date', 'desc')
							->addAttributeToSort('position', 'desc');

					if($search!=''){
						$events->addAttributeToFilter(array(
											array(
												'attribute' => 'name',
												'like'        => '%'.$search.'%',
												),
											array(
												'attribute' => 'description',
												'like'        => '%'.$search.'%',
												),
											));
					}
					
					$events->addAttributeToFilter('visibility', $visibility)				
							->setPageSize($limit)
							->setPage($more,$limit)							
							->load()->toArray();
					$lastPage = $events->getLastPageNumber();

			$items='';
			if(count($events)>0){
				if($lastPage >= $more){
				foreach($events as $key=>$event){
					$customer = Mage::getModel('customer/customer')->load($event['user_id']);
					$fc = strtolower(substr($customer->getFirstname(),0,1));
					if($fc == ''){ $fc = rand(1,10); }
					$product = Mage::getModel('catalog/product')->load($event['entity_id']);
					$cat_ids = $product->getCategoryIds();
					if(isset($cat_ids[0])){
					$_categoryName = Mage::getModel('catalog/category')->load($cat_ids[1])->getName();
					$_categoryId = $cat_ids[1];
					}
					$prfix = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
					
					if($event['event_image']=="''"){
						$prfix.= 'catalog/product';
						$event['small_image'] = "/placeholder/default/".$fc.".jpg";
						$event['image'] = "/placeholder/default/red-curtain_400x400.jpg";
						$event['thumbnail'] = "/placeholder/default/red-curtain_48x48.jpg";
					}else{
						 if($event['event_image']){
							if( $_SERVER['HTTPS'] || strtolower($_SERVER['HTTPS']) == 'on' )$prfix="https://";else $prfix = "http://";
							$prfix.= 'chattrspace.s3.amazonaws.com/events';
							$event['small_image'] 	= "/135x110/".$event['event_image'];
							$event['image']			= "/400x400/".$event['event_image'];
							$event['thumbnail'] 	= "/48x48/".$event['event_image'];
						 }
						 else
							$prfix.= 'catalog/product';
					}
					$url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).'live-events/'.$event['url_path'];
					$from = strtotime($event['news_from_date'])+$timeoffset;
					$to = strtotime($event['news_to_date'])+$timeoffset;
					if (($now > $from) && ($now < $to)) {
						$isLive="true";
					}
					else{
						$isLive="false";
					}	
					$items['events'][$key]=array(
								'id'=> $event['entity_id'],
								'name'=> $event['name'],
								'special_price'=> number_format($event['special_price'],2),
								'price'=> number_format($event['price'],2),
								'url'=> $url,
								'small_image'=> $prfix.$event['small_image'],
								'image'=> $prfix.$event['image'],
								'thumbnail'=> $prfix.$event['thumbnail'],
								'from_date'=> date('D M d, Y h:i A', strtotime($event['news_from_date'])+$timeoffset)." ".$abbrev,
								'to_date'=> date('D M d, Y h:i A',strtotime($event['news_to_date'])+$timeoffset)." ".$abbrev,
								'from_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_from_date'])))),
								'to_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_to_date'])))),
								'from_date3'=>date('m-d-Y H:i:s', strtotime($event['news_from_date'])+$timeoffset),/*Added By surinder on demand of ajumal*/
								'to_date3'=>date('m-d-Y H:i:s',strtotime($event['news_to_date'])+$timeoffset),/*Added By surinder on demand of ajumal*/
								'hosted_by'=> $customer->getFirstname().' '.$customer->getLastname(),
								'hosted_by_username'=> $customer->getUsername(),
								'user_id'=> $event['user_id'],
								'category_name'=> $_categoryName,
								'category_id'=> $_categoryId,
								'location'=> $event['location'],
								'isLive'			=> $isLive,
					);
				}
				$items['total_count']=Mage::getModel('csservice/list')->getEventCount($cat, $price, $date, $more, $search, 0, $attending, $friend);
				return $items;
				}
			}
			else
				return 0;
   } 
	public function getSearchPeople($search,$page=1){
		$more = $page;
		//$events = Mage::getModel('csservice/list')->getEvents($cat=3, $price='', $date='', $more, $search, $uid=0, $attending=false, $friend='', $limit=25);
		$people = Mage::getModel('csservice/csservice')->getPeopleSearch($search, $more=0, $limit=10, 0);
		//$data['events']['data'] = $events; 
		//$data['people']['data'] = $people;
		return $people;
	}
	public function getSearchEvent($search,$page=1){
		$more = $page;
		$events = Mage::getModel('csservice/list')->getEvents($cat=3, $price='', $date='', $more, $search, $uid=0, $attending=false, $friend='', $limit=25);
		//$people = Mage::getModel('csservice/csservice')->getPeopleSearch($search, $more=0, $limit=10, 0);
		//$data['events']['data'] = $events; 
		//$data['people']['data'] = $people;
		return $events;
	}
	
	public function getFriendsEvents($user_id,$page=1)
   	{   
		$limit=10;
		$time_zone='Europe/London';
		$session = Mage::getSingleton('customer/session');
		$time_zone = $session->getCustomer()->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		
	  	$todayDate  = Mage::app()->getLocale()->date()->toString(Varien_Date::DATETIME_INTERNAL_FORMAT);  
	
		$websiteId = Mage::app()->getWebsite()->getId();
		$storeId = Mage::app()->getStore()->getId();
		$visibility = array(	
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
              );

		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
	   
		$events = Mage::getModel('catalog/category')->load(3)
						->getProductCollection()
						->addAttributeToSelect('*')
						->addAttributeToSelect('category_id')
						->addAttributeToSelect('status')
						->addFieldToFilter('status', 1)
						->addAttributeToFilter('news_to_date', array('gteq' => date('Y-m-d H:i:s')))
						->addAttributeToFilter('is_expired', array('neq' => 1))
						->addAttributeToSort('news_from_date', 'desc');
							
		if($user_id){
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$follower = $resource->getTableName('follower');
			//$user_id = $session->getCustomerId();
			$select = "select follow from $follower WHERE follower_id=".$user_id." and follower_id<>follow and status=1";						
			$followers = $read->fetchAll($select);

			$resultArray = '';
			$str='';
			$c=0;
			foreach($followers as $follower){	$c = $c+1;	
					$str[$c]=$follower['follow'];
					$c = $c+1;
			}
			//print_r($str); exit;
			$events->addAttributeToFilter('user_id', array('in' => $str));
		}
		
		$events->addAttributeToFilter('visibility', $visibility)				
							->setPageSize($limit)
							->setPage($page, $limit)							
							->load()->toArray();
							
		$data = '';

		if(count($events)>0){
				
				foreach($events as $k=>$event){
					$customer = Mage::getModel('customer/customer')->load($event['user_id']);
					$fc = strtolower(substr($customer->getFirstname(),0,1));
					if($fc == ''){ $fc = rand(1,10); }

					if($event['small_image']=='no_selection' || $event['small_image']=='')
					$img_url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'catalog/product/placeholder/default/'.$fc.'.jpg';
					else{
						if($event['event_image'])
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/135x110/'.$event['event_image'];		
						else
							$img_url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'catalog/product/'.$event['small_image'];		
					}
					
							$result[$k] = array(
							'id'=> $event['entity_id'],
							'name'=> $event['name'],
							'price'=> number_format($event['price'],2),
							'user_id'=> $event['user_id'],
							'username'=>$customer->getUsername(),
							'description'=> $event['description'],
							'from_date'=> date('D M d, Y h:i A', strtotime($event['news_from_date'])),
							'to_date'=> date('D M d, Y h:i A',strtotime($event['news_to_date'])),
							'image'			=> $event['event_image'],							
							
							);
				}
				
				$result['total_count']=$this->getFriendsEventsCount($user_id);
				return $result;
			
			}else{
				return 0;
			}
   	}
	public function getFriendsEventsCount($user_id)
   	{   
		$time_zone='Europe/London';
		$session = Mage::getSingleton('customer/session');
		$time_zone = $session->getCustomer()->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		
	  	$todayDate  = Mage::app()->getLocale()->date()->toString(Varien_Date::DATETIME_INTERNAL_FORMAT);  
	
		$websiteId = Mage::app()->getWebsite()->getId();
		$storeId = Mage::app()->getStore()->getId();
		$visibility = array(	
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
              );

		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
	   
		$events = Mage::getModel('catalog/category')->load(3)
						->getProductCollection()
						->addAttributeToSelect('*')
						->addAttributeToSelect('category_id')
						->addAttributeToSelect('status')
						->addFieldToFilter('status', 1)
						->addAttributeToFilter('news_to_date', array('gteq' => date('Y-m-d H:i:s')))
						->addAttributeToFilter('is_expired', array('neq' => 1))
						->addAttributeToSort('news_from_date', 'desc');
							
		if($user_id){
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$follower = $resource->getTableName('follower');
			//$user_id = $session->getCustomerId();
			$select = "select follow from $follower WHERE follower_id=".$user_id." and follower_id<>follow and status=1";						
			$followers = $read->fetchAll($select);

			$resultArray = '';
			$str='';
			$c=0;
			foreach($followers as $follower){	$c = $c+1;	
					$str[$c]=$follower['follow'];
					$c = $c+1;
			}
			//print_r($str); exit;
			$events->addAttributeToFilter('user_id', array('in' => $str));
		}
		
		$events->addAttributeToFilter('visibility', $visibility)->load()->toArray();
		return count($events);
   	}
	public function getPeople($limit){
		
		$people = Mage::getModel('csservice/csservice')->getPeopleSearch('', $more=0, $limit, 0);
		return $people;
	}
	public function getFollowings($user_id, $status=1, $notify=0 ,$page=1, $current_user_id=0) {
		$customer_id = Mage::getSingleton('customer/session')->getCustomer()->getId();
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$follower = $resource->getTableName('follower');
		$customer_entity = $resource->getTableName('customer_entity');
		
		$select = "select *,(select count(DISTINCT follow) from $follower, $customer_entity WHERE follower_id='".$user_id."' and follower_id<>follow and status=".$status." and $customer_entity.entity_id=$follower.follow) as count from $follower, $customer_entity WHERE follower_id='".$user_id."' and follower_id<>follow and status=".$status." and $customer_entity.entity_id=$follower.follow";
		//$selectcount = "select count(DISTINCT follow) as count from $follower, $customer_entity WHERE follower_id='".$user_id."' and follower_id<>follow and status=".$status." and $customer_entity.entity_id=$follower.follow";
		
		$select.=" group by follow order by id desc";
			$limit = 15;
			if($page<=0)
				$page=1;
			$page=$page-1;
			if($limit!=0)
				$select.= " limit ".$limit*$page .", " .$limit;
			$follower = $read->fetchAll($select);
			foreach($follower as $k=>$flwr){
					$customer = Mage::getModel('customer/customer')->load($flwr['follow']);
					$username = $customer->getUsername();
					$thumbimage = $this->getProfilePic($flwr['follow']);
					if($current_user_id > 0){
						$isfollow = $this->isFollow($flwr['follow'],$current_user_id);
					
						if($isfollow == 1){
							$isfolow = "true";
						} else {
							$isfolow = "false";
						}
					}			
					$item[$k]=array(
						'id'=> $flwr['id'],
						'username'=>$username,
						'name'=>$customer->getFirstname()." ".$customer->getLastname(),
						'user_id'=>$flwr['follow'],
						//'short_bio'=>$customer->getShortbio(),
						//'views'=>Mage::getModel('csservice/csservice')->getCheckinCount($flwr['follow']),
						//'followers'=>$this->getFollowersCount($flwr['follow']),
						'thumbimage'=>$thumbimage,
						'notify'=> $flwr['notify'],
						'follow_on'=> $flwr['follow_on'],
						//'isLive'=> $this->isUserOnline($flwr['follow']),
						'isFollow'=> $isfolow,
						'notify_push'=> $flwr['push_notify'],
						'public_call_status_app_closed'=> $flwr['app_closed'],
						'public_call_status_app_open'=> $flwr['app_open'],
						'drop_in_on_me'=> $flwr['drop_in_on_me'],
						'updated_on'=> $flwr['created_on'],
					);
				$count = $flwr['count'];
			}
			//echo count($follower);die;
			if(count($follower)>0){
				$result=array();	
				$result['followers'] = $item;
				$result['count'] = $count;
				if($count > ($limit*($page+1))){
					$result['showMore'] = "true";
				} else {
					$result['showMore'] = "false";
				}
				return $result;
			}
			else
				return 0;
	}
	public function getAge($Birthdate)
	{
			$startdate =  date("Y-m-d G:i:s");
			$enddate = date("Y-m-d G:i:s", strtotime($Birthdate));
			
			$diff =  strtotime($startdate) - strtotime($enddate);
			$time = round(($diff/60/60/24/30/12),0);
			return $time;
	}
	public function isCSKeyword($string) {
		$a=Mage::getModel('profile/profile')->isCSKeyword($string);
		return $a;
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
	public function signup($user_name,$email,$password,$firstname,$lastname,$day,$month,$year,$gender=1,$device_id=0,$imei=0,$type="",$accesstoken="")
    {	
		$is_subscribed=false;
        $session = $this->_getSession();
        if ($session->isLoggedIn()) {
			return 'Login';
        }
        $session->setEscapeMessages(true); // prevent XSS injection in user input
        if ($user_name && $email) {
            $errors = array();
			
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$write = $resource->getConnection('core_write');
			
            if (!$customer = Mage::registry('current_customer')) {
                $customer = Mage::getModel('customer/customer')->setId(null);
            }

            /* @var $customerForm Mage_Customer_Model_Form */
            $customerForm = Mage::getModel('customer/form');
            $customerForm->setFormCode('customer_account_create')
                ->setEntity($customer);

            if($is_subscribed == false){
                $customer->setIsSubscribed(1);
            }
						
            /**
             * Initialize customer group id
             */
            $customer->getGroupId();
			
            try {
					if(preg_match("#.*^(?=.{6,12})(?=.*[a-z])(?=.*[0-9]).*$#", $password)){
						$customer->setPassword($password);
					} else{
						return "ERROR:Password should be alphanumeric.";
					}

                    
                    $customer->setConfirmation(null);
                    				
					if($month != '' && $year != '' && $day != '')
					{
						$m_dob=$month."/".$day."/".$year;	
						if(($month==4 || $month==6 || $month==9 || $month==11) && $day ==31){
							return "ERROR:Select Valid Date Of Birth";
						}
						elseif($month == 2){
							$isleap = ($year % 4 == 0 && ($year % 100 != 0 || $year % 400 == 0));
							if ($day> 29 || ($day ==29 && !$isleap)){
								return "ERROR:Select Valid Date Of Birth";
							}
						}elseif($this->getAge($m_dob) < 13) {
							return 'ERROR:age must be greater than or equal to 13 years';
						}
						else{
							$customer->setDob($m_dob);
						}
					} else{
						return 'ERROR:Date of Birth can not be blank';
					}
					
                $un = $user_name;
				if($un) {
					
					$collection = Mage::getResourceModel('customer/customer_collection')
									->addAttributeToFilter('username', $un);
									
					if(strlen($un) < 6 || strlen($un) > 15){
						return 'ERROR:Your username should be 6 to 15 characters long';
					}elseif (!preg_match('/^([a-zA-Z0-9_]+)$/', $un) ) {
						return 'ERROR:Your username should contain only letters, numbers and underscore';
							
					}
					elseif(Mage::getModel('profile/profile')->checkReserveBadKey($un) == 0){
						return 'ERROR:Choose another username';
					}
					elseif(Mage::getModel('profile/profile')->checkReserveBadKey($un) == 1){
						return 'ERROR:Username already taken';
					}
					elseif( $collection->count() > 0 ){
						return 'ERROR:Your username should be unique';
				}
				else{
					$customer->setUsername($user_name); 
				}
				}
				if(Mage::getModel('profile/profile')->checkFirstLastName($firstname) == 0){
					return 'ERROR:Bad words, Choose another firstname';
				} else {
					$customer->setFirstname($firstname);
				}
				if(Mage::getModel('profile/profile')->checkFirstLastName($lastname) == 0){
					return 'ERROR:Bad words, Choose another lastname';
				} else {
					$customer->setLastname($lastname);
				}
				#check if another customer has it
				$collection = Mage::getResourceModel('customer/customer_collection')
									->addAttributeToFilter('email', $email);
									
				if( !preg_match("/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/", $email) ) {
					//return 'Your username should contain only letters, numbers and underscore';
					return 'ERROR:Doesn\'t look like a valid email';
					
				}
				elseif( $collection->count() > 0 ) {
					return 'ERROR:This email id already exists';
				} else {
					$customer->setEmail($email);
				}
							
				$validationResult = true;
				
                if(true === $validationResult) {
					//set default timezone
					$customer->setTimezone('America/Los_Angeles');
					$customer->setGender($gender);
					$customer->save();
					
					$lastId = $customer->getId();
					//////////////////////////////////////////////////////////////////////
					$resource = Mage::getSingleton('core/resource');
					$write = $resource->getConnection('core_write');
					$widget = $resource->getTableName('widget_info');
					$allowedDomains = "*";
					$chatBgColor = "000000";
					$chatTextColor = "ffffff";
					$height = "470";
					$width = "600";
					$borderColor = "000000";
					$bgPanelColor = "000000";
					$bgColor = "000000";
					$fontColor = "ffffff";
					$toolBarBgColor = "000000";
					$useAdvance="";
					$fbappdomain="";
					$fbappsecret="";
					$fbappid="";
					$widget_title="Default";
					
					$user_id=$lastId;
			 $write->query("insert into $widget (username, allowedDomains, toolBarBgColor, fontColor, bgColor, bgPanelColor,  borderColor, chatBgColor, chatTextColor, widgetWidth, widgetHeight, is_default, created_on, user_id, chat_input, widget_title, fbappid, fbappsecret, fbappdomain, useAdvance) values('".$user_name."', '".$allowedDomains."', '".$toolBarBgColor."', '".$fontColor."', '".$bgColor."', '".$bgPanelColor."', '".$borderColor."', '".$chatBgColor."', '".$chatTextColor."', ".$width.", ".$height.", 1, now(), ".$user_id.", 1, '".$widget_title."', '".$fbappid."', '".$fbappsecret."', '".$fbappdomain."', '".$useAdvance."')");
			 
			 $widget_id = $thelastId = $write->lastInsertId();
			//$widget_key = $this->encode(rand().$user_id.$widget_id.rand());
			$widget_key = $this->uniqueKey($user_id.$widget_id);
			$write->query("update $widget set widget_key='".$widget_key."' WHERE user_id='".$user_id."' and widget_id='".$widget_id."'");
			/////////////////////////////////////////////////////////////////////////////////
					
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
					$returnVal = array();
					$m_dob=$this->getMDobByUserId($user_id);
					$returnVal['userInfo'] = $this->getUserInfo($user_id);
					$returnVal['dob'] = $m_dob;
					$returnVal['phone'] = $this->getMobileAndDeviceId($user_id, $device_id, $imei, $type);
					$returnVal['userEmail'] = $email;
					$returnVal['view'] = Mage::getModel('csservice/csservice')->getCheckinCount($user_id);
					/////////////////////////////////////////////////
					$seckey = substr($accesstoken,0,24);
					$token = substr($accesstoken,24);
					$resource = Mage::getSingleton('core/resource');
					$read= $resource->getConnection('core_read');
					$write = $resource->getConnection('core_write');
					$xapplication_token = $resource->getTableName('xapplication_token');
					$write->query("update cs_xapplication_token set user_id=".$user_id." WHERE token='".$token."' and security_key='".$seckey."'");
					//=====================================================================
					Mage::getModel('profile/profile')->updateJabberRosterInfo($user_id);
					//=================================================================================              
					return 'Successfully Registered.';
                }
            }catch (Exception $e) {
				return $e.'-Cannot save the customer.';
            }
        }

         
    }
	public function getCategoryByName($name="Live Events") {
		$id=3;
		//$id = intval($id);
        $_category = new Mage_Catalog_Model_Category();
        $_category->load($id);
		
		if ($_category->getName() == $name) {
			 return $_category->toArray();
		}

        $tree = $_category->getCategories($_category->getId(), false, true);
       // $tree = $_category->getChildrenCategories();

	   $returnVal = array();

       foreach ($tree as $child) {
		   $returnVal = array_merge($returnVal, $this->__getEventCategoryByName($name, $child->getId()));
       }

	   return $returnVal;
    }
	public function push_notification($deviceTokens, $certFile='/var/websites/oncam_com/webroot/certs/PushDev.p12', $certPass='', $push_method, $alert, $badge, $sound = "default", $message="",$user_id,$notificationType)
	{
		$username = $this->getUserNameByUserId($user_id);
		if ( $notificationType == 'develop' )
			$pemFile='/var/websites/oncam_com/webroot/certs/aps_development.pem';
		else if ( $notificationType == 'Online' )
			$pemFile='/var/websites/oncam_com/webroot/certs/aps_production.pem';
			
		$certPass = '12345'; 
		$sound="default";
		$push_method == 'develop';
		
		$body['aps'] = array(
			'alert' => $message,
			'sound' => ($sound ? $sound : "default"),
			'badge' => ($badge ? $badge : "default"),
			'user_id' =>$user_id,
			'username' => $username
			);
		$ctx = stream_context_create();
		stream_context_set_option($ctx, 'ssl', 'local_cert', $pemFile);
		//stream_context_set_option($ctx, 'ssl', 'passphrase', $certPass);

		if ( $notificationType == 'develop' )
			$ssl_gateway_url = 'ssl://gateway.sandbox.push.apple.com:2195';
		else if ( $notificationType == 'Online' )
			$ssl_gateway_url = 'ssl://gateway.push.apple.com:2195';
		
		if(isset($ssl_gateway_url))
		{
			$fp = stream_socket_client($ssl_gateway_url, $err, $errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);
		}
		
		$payload = json_encode($body);
		/*
		$deviceTokens =array();
		$deviceTokens[0]['device_id'] ='75cc4eeef769523ec9c61b32e0c83eb7d71f4005925715cc0686a13c2d6b712b';
		$deviceTokens[1]['device_id'] ='4d2073c79833ce14b8c4635839f8bf3f47f3bbb8af46c27bf27b7d0e9de97d7c';
		$deviceTokens[2]['device_id'] ='f39c64401b2af316c664fd9c34920ab713bd57c34749c44385fc6fd29e43a0d3';
		$deviceTokens[3]['device_id'] ='6edc2b85921a061220a0c436ca62af7f5f4c55a2a4798787b5147c18d37aef86';
		$deviceTokens[4]['device_id'] ='2fac7c1ea793db6b53693b3cc0baff3b0dfa785bb92341a2a7fb2d3fe99b33f0';
		$deviceTokens[5]['device_id'] ='75cc4eeef769523ec9c61b32e0c83eb7d71f4005925715cc0686a13c2d6b712b';
		*/		
		for($i=0; $i<count($deviceTokens); $i++){
			$deviceIdab = trim($deviceTokens[$i]['device_id']);
			$msg = chr(0).pack('n', 32).pack('H*', str_replace(' ', '',$deviceIdab)).pack('n', strlen($payload)).$payload;
			$result = fwrite($fp, $msg, strlen($msg));
			$arr[] = $deviceIdab;
		}
		fclose($fp);
		return $arr;
	}
	public function gcm_push_notification($deviceTokens,$msg,$user_id) {
		//$temp_id = $_REQUEST['id'];
		//$temp_msg = $_REQUEST['msg'];
		$headers = array(
		 'Content-Type:application/json',
		 'Authorization:key=AIzaSyDYNt9ftmzDT2aExpnyxP6pkmeMkacbQU4'
		);
		
		$count = count($deviceTokens);
		$username = $this->getUserNameByUserId($user_id);
		$arr   = array();
		$arr['data']['user_id'] = $user_id;
		$arr['data']['username'] = $username;
		$arr['data']['msg'] = $msg;
		$arr['data']['count'] = $count;
		$arr['registration_ids'] = array();
		/*
		for ($i = 0; $i < $count; $i++ ) {
			$arr['registration_ids'][$i] = $deviceTokens[$i]["device_id"];
		}*/
		foreach($deviceTokens as $k=>$dc){
			$arr['registration_ids'][$k] = $dc["device_id"];
		}
		//return $arr;	
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,    'https://android.googleapis.com/gcm/send');
		curl_setopt($ch, CURLOPT_HTTPHEADER,  $headers);
		curl_setopt($ch, CURLOPT_POST,    true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($arr));
		try{
		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
		} catch (Exception $e){
			Mage::log($e,null,'gcm.log');
		}
	}
	public function apnstest()
	{
		$certFile="/var/websites/oncam_com/webroot/certs/PushDev.p12";
		$certPass="";
		$push_method="develop";
		$alert=true;
		$badge=true;
		$sound = "default";
		//$deviceToken = str_replace(" ", "", $deviceToken);
		//$device_tokens_array = new array();
		$deviceTokens = array("f081af4cb55d2c22d9de1556bd2c4c7464619f784360c62243b425ca4ddadde1");
		$tmp = array();
		if($alert)
		{
			$tmp['alert'] = $alert;
		}
		if($badge)
		{
			$tmp['badge'] = $badge;
		}
		if($sound)
		{
			$tmp['sound'] = $sound;
		}
		$body['aps'] = $tmp;
		//$body[$custom_key] = $custom_value;
		$ctx = stream_context_create();
		stream_context_set_option($ctx, 'ssl', 'local_cert', $certFile);
		stream_context_set_option($ctx, 'ssl', 'passphrase', $certPass);

		if ( $push_method == 'develop' )
			$ssl_gateway_url = 'ssl://gateway.sandbox.push.apple.com:2195';
		else if ( $push_method == 'live' )
			$ssl_gateway_url = 'ssl://gateway.push.apple.com:2195';
		
		$err="";
		$errstr="";
		if(isset($certFile) && isset($ssl_gateway_url))
		{
			$fp = stream_socket_client($ssl_gateway_url, $err, $errstr, 60, STREAM_CLIENT_CONNECT, $ctx);
		}
		if(!$fp)
		{
			print "Connection failed $err $errstr\n".$ssl_gateway_url.' '.$push_method;
			return FALSE;
		}
		
		$payload = json_encode($body);
		
		
		//$msg = chr(0).chr(0).chr(32).$deviceToken.chr(0).chr(strlen($payload)).$payload;
		//fwrite($fp, $msg);
		//$deviceToken = str_replace(" ", "", $deviceTokens[i]["device_id"]);
		for($i=0; $i<count($deviceTokens); $i++)
		{
				$apnsMessage = chr(0).chr(0).chr(32).pack('H*', str_replace(' ', '', $deviceTokens[i]["device_id"])).chr(0).chr(strlen($payload)).$payload;
				fwrite($apns, $apnsMessage);
		}
		
		fclose($fp);
		return TRUE;
	}
	public function gcmtest($deviceToken="",$msg="hello who r u?") {
		
		$headers = array(
		 'Content-Type:application/json',
		 'Authorization:key=AIzaSyDYNt9ftmzDT2aExpnyxP6pkmeMkacbQU4'
		);
		
		$count = count($deviceTokens);
		$arr   = array();
		$arr['data']['msg'] = $msg; 
		$arr['registration_ids'][0] = 'APA91bEqwMikK9vtG9KHEi13NCedxeOW4bpmqY7IYFAfQaY43YTHWpVbPzbunGgHzP1ys78n0IS5oQ5LTpDJnPNb-xRQLmP0W-jUpL0oM2y2_vMpnFOyN3Ma4w9lvRtng2qSPA4vXafV_-2zEDIJ4o3UxF9JOwGubw';
		/*
		for ($i = 0; $i < $count; $i ++ ) {
			$arr['registration_ids'][$i] = $deviceTokens[$i];
		}*/
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,    'https://android.googleapis.com/gcm/send');
		curl_setopt($ch, CURLOPT_HTTPHEADER,  $headers);
		curl_setopt($ch, CURLOPT_POST,    true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($arr));
		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
	}
	public function registerDeviceV2($deviceToken,$user_id,$osVersion,$deviceMake="",$deviceModel="",$deviceWifiMac="",$deviceLanguage="",$deviceLocation="",$notificationType='Online') {
		$type="iPhone";
		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$read= $resource->getConnection('core_read');
		$device = $resource->getTableName('mobile_device');
		$select="select * from $device where device_id='".$deviceToken."' and notificationType='".$notificationType."'";
		$devicedata = $read->fetchAll($select);
		if(count($devicedata) > 0){
			$sqlInsert = "update $device set user_id=".$user_id.", type='".$type."',os='".$osVersion."',notificationType='".$notificationType."',deviceMake='".$deviceMake."',deviceModel='".$deviceModel."',deviceWifiMac='".$deviceWifiMac."',deviceLanguage='".$deviceLanguage."',deviceLocation='".$deviceLocation."',date_updated=now() where device_id='".$deviceToken."'";
		}
		else{
		$sqlInsert = " Insert into $device(user_id, device_id, type, os, notificationType, date_added, deviceMake, deviceModel, deviceWifiMac, deviceLanguage, deviceLocation) values (".$user_id.", '".$deviceToken."','".$type."','".$osVersion."','".$notificationType."',now(),'".$deviceMake."','".$deviceModel."','".$deviceWifiMac."','".$deviceLanguage."','".$deviceLocation."')";
		}
		$write->query($sqlInsert);
		return "Successfully register device.";
	}
	public function pushNotiOff($deviceToken,$user_id=0) {
		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$read= $resource->getConnection('core_read');
		$device = $resource->getTableName('mobile_device');
		$sqlInsert = "update $device set active=0, date_updated=now() where device_id='".$deviceToken."'";
		
		$write->query($sqlInsert);
		return "Push Notification Off";
	}
	public function pushNotiOn($deviceToken,$user_id=0){
		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$read= $resource->getConnection('core_read');
		$device = $resource->getTableName('mobile_device');
		$sqlInsert = "update $device set active=1 where device_id='".$deviceToken."'";
		
		$write->query($sqlInsert);
		return "Push Notification On";
	}
	//deviceToken,$user_id,$type,$osVersion,$notificationType='Online'
	public function registerDeviceAndroid($imei="",$deviceToken="",$user_id,$osVersion,$deviceMake="",$deviceModel="",$deviceWifiMac="",$deviceLanguage="",$deviceLocation="") {
		$type='Android';
		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$read= $resource->getConnection('core_read');
		$device = $resource->getTableName('mobile_device');
		$select="select * from $device where imei='".$imei."'";
		$devicedata = $read->fetchAll($select);
		if(count($devicedata) > 0){
			$sqlInsert = "update $device set user_id=".$user_id.", type='".$type."',os='".$osVersion."',device_id='".$deviceToken."',deviceMake='".$deviceMake."',deviceModel='".$deviceModel."',deviceWifiMac='".$deviceWifiMac."',deviceLanguage='".$deviceLanguage."',deviceLocation='".$deviceLocation."',date_updated=now() where imei='".$imei."'";
		}
		else{
		$sqlInsert = " Insert into $device(user_id, device_id, imei, type, os, date_added, deviceMake, deviceModel, deviceWifiMac, deviceLanguage, deviceLocation) values (".$user_id.", '".$deviceToken."', '".$imei."','".$type."','".$osVersion."',now(),'".$deviceMake."','".$deviceModel."','".$deviceWifiMac."','".$deviceLanguage."','".$deviceLocation."')";
		}
		$write->query($sqlInsert);
		return "Successfully register device.";
	}
	public function unregisterDevice($deviceToken,$user_id=0) {
		//mark active as 0
		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$device = $resource->getTableName('mobile_device');
		$sqlInsert = "update $device set device_id='', date_updated=now() where user_id='".$user_id."' and device_id='".$deviceToken."' and type='iPhone'";
		$write->query($sqlInsert);
		return "Successfully Unregister device.";
	}
	public function unregisterDeviceAndroid($imei="",$user_id){
		//mark active as 0
		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$device = $resource->getTableName('mobile_device');
		$sqlInsert = "update $device set device_id='', date_updated=now() where user_id='".$user_id."' and imei='".$imei."' and type='Android'";
		$write->query($sqlInsert);
		return "Successfully Unregister device.";
	}
	public function checkFBUserAndGetInfo($first_name, $last_name, $about_me, $facebook_id, $email, $gender, $username, $dob, $FBaccessToken="")
    {
		$username = str_replace(".","",$username);
        /*
        $return_values = array();
        $customer = Mage::getModel('customer/customer');
        $resource = Mage::getSingleton('core/resource');
        $read= $resource->getConnection('core_read');
        $write = $resource->getConnection('core_write');
        $widget_fb_reg = $resource->getTableName('widget_fb_reg');
        $select = "select * from $widget_fb_reg WHERE fbid= ".$facebook_id;
        $rs = $read->fetchRow($select);
        

        return $return_values;
         * 
         */
		$m_email = "";
		$m_dob = "";
        $resource = Mage::getSingleton('core/resource');
        $read= $resource->getConnection('core_read');
        $write = $resource->getConnection('core_write');
        $returnVal = array();
        $customer = Mage::getModel('customer/customer');
        if($this->checkFbid($facebook_id)){
            $user_id = $this->checkFbid($facebook_id);
            //$returnVal = $this->getUserInfo($user_id);
        }else if ($this->checkEmail($email)) {
            $user_id = $this->checkEmail($email);
            //$returnVal = $this->getUserInfo($user_id);
        }else if ($this->checkUsername($username)) {
            $user_id = $this->checkUsername($username);
            $suffix = rand(11111, 99999);
            $uname = $username.'_'.$suffix;
            
            $websiteId = Mage::app()->getWebsite()->getId();
            $customer->website_id = $websiteId; 
            $customer->store=0;
            $customer->username = $uname;
            $customer->firstname = $first_name;
            $customer->lastname = $last_name;
            $customer->email = $email;
            $customer->sex = $gender;	
            $customer->shortbio = $about_me;
            $randomPassword = $customer->generatePassword();
            $customer->password = $randomPassword;
			if($dob){
			$dob_arr = explode('/',$dob);
			$month = $dob_arr[0];
			$day = $dob_arr[1];
			$year = $dob_arr[2];
			if($month != '' && $year != '' && $day != '')
			{
				$m_dob=$month."/".$day."/".$year;	
				if(($month==4 || $month==6 || $month==9 || $month==11) && $day ==31){
					return "ERROR:Select Valid Date Of Birth";
				}
				elseif($month == 2){
					$isleap = ($year % 4 == 0 && ($year % 100 != 0 || $year % 400 == 0));
					if ($day> 29 || ($day ==29 && !$isleap)){
						return "ERROR:Select Valid Date Of Birth";
					}
				}elseif($this->getAge($m_dob) < 13) {
					return 'ERROR:age must be greater than or equal to 13 years';
				}
				else{
					$customer->setDob($m_dob);
				}
			} else{
				return 'ERROR:Date of Birth can not be blank';
			}
			}
            $customer->save();
            $user_id = $customer->getId();
            
             if($oncam_user_id >0){
	        	if($user_id != $oncam_user_id){
	        		return "Error:This Facebook ID is already linked to an existing oncam user. Please check and try again.";
	        	}
	        }

			$user_name = $uname;
					//////////////////////////////////////////////////////////////////////
					$resource = Mage::getSingleton('core/resource');
					$write = $resource->getConnection('core_write');
					$widget = $resource->getTableName('widget_info');
					$allowedDomains = "*";
					$chatBgColor = "000000";
					$chatTextColor = "ffffff";
					$height = "470";
					$width = "600";
					$borderColor = "000000";
					$bgPanelColor = "000000";
					$bgColor = "000000";
					$fontColor = "ffffff";
					$toolBarBgColor = "000000";
					$useAdvance="";
					$fbappdomain="";
					$fbappsecret="";
					$fbappid="";
					$widget_title="Default";
					
					
			 $write->query("insert into $widget (username, allowedDomains, toolBarBgColor, fontColor, bgColor, bgPanelColor,  borderColor, chatBgColor, chatTextColor, widgetWidth, widgetHeight, is_default, created_on, user_id, chat_input, widget_title, fbappid, fbappsecret, fbappdomain, useAdvance) values('".$user_name."', '".$allowedDomains."', '".$toolBarBgColor."', '".$fontColor."', '".$bgColor."', '".$bgPanelColor."', '".$borderColor."', '".$chatBgColor."', '".$chatTextColor."', ".$width.", ".$height.", 1, now(), ".$user_id.", 1, '".$widget_title."', '".$fbappid."', '".$fbappsecret."', '".$fbappdomain."', '".$useAdvance."')");
			 
			 $widget_id = $thelastId = $write->lastInsertId();
			//$widget_key = $this->encode(rand().$user_id.$widget_id.rand());
			$widget_key = $this->uniqueKey($user_id.$widget_id);
			$write->query("update $widget set widget_key='".$widget_key."' WHERE user_id='".$user_id."' and widget_id='".$widget_id."'");
			
			$widget_fb_reg = $resource->getTableName('widget_fb_reg');
			$code=0;
			$write->query("INSERT INTO $widget_fb_reg SET id=NULL, uid='".$user_id."', fbid='".$facebook_id."', fbcode='".$code."', widgetid=".$widget_id.", ip_address='".$_SERVER['REMOTE_ADDR']."', updated_at='".now()."'");
			/////////////////////////////////////////////////////////////////////////////////
			
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
					}
			
			$fbAcsessToken = $resource->getTableName('fb_accessToken');
			$write->query("insert into $fbAcsessToken (uid, fbid, access_token) values('".$user_id."','".$facebook_id."','".$FBaccessToken."')");
        }else {
            $websiteId = Mage::app()->getWebsite()->getId();
            $customer->website_id = $websiteId; 
            $customer->store=0;
            $customer->username = $username;
            $customer->firstname = $first_name;
            $customer->lastname = $last_name;
            $customer->email = $email;
            $customer->sex = $gender;	
            $customer->shortbio = $about_me;
            $randomPassword = $customer->generatePassword();
            $customer->password = $randomPassword;
			if($dob){
			$dob_arr = explode('/',$dob);
			$month = $dob_arr[0];
			$day = $dob_arr[1];
			$year = $dob_arr[2];
			if($month != '' && $year != '' && $day != '')
			{
				$m_dob=$month."/".$day."/".$year;	
				if(($month==4 || $month==6 || $month==9 || $month==11) && $day ==31){
					return "ERROR:Select Valid Date Of Birth";
				}
				elseif($month == 2){
					$isleap = ($year % 4 == 0 && ($year % 100 != 0 || $year % 400 == 0));
					if ($day> 29 || ($day ==29 && !$isleap)){
						return "ERROR:Select Valid Date Of Birth";
					}
				}elseif($this->getAge($m_dob) < 13) {
					return 'ERROR:age must be greater than or equal to 13 years';
				}
				else{
					$customer->setDob($m_dob);
				}
			} else{
				return 'ERROR:Date of Birth can not be blank';
			}
			}
            $customer->save();
            $user_id = $customer->getId();
			$user_name = $username;
					//////////////////////////////////////////////////////////////////////
					$resource = Mage::getSingleton('core/resource');
					$write = $resource->getConnection('core_write');
					$widget = $resource->getTableName('widget_info');
					$allowedDomains = "*";
					$chatBgColor = "000000";
					$chatTextColor = "ffffff";
					$height = "470";
					$width = "600";
					$borderColor = "000000";
					$bgPanelColor = "000000";
					$bgColor = "000000";
					$fontColor = "ffffff";
					$toolBarBgColor = "000000";
					$useAdvance="";
					$fbappdomain="";
					$fbappsecret="";
					$fbappid="";
					$widget_title="Default";
					
					
			 $write->query("insert into $widget (username, allowedDomains, toolBarBgColor, fontColor, bgColor, bgPanelColor,  borderColor, chatBgColor, chatTextColor, widgetWidth, widgetHeight, is_default, created_on, user_id, chat_input, widget_title, fbappid, fbappsecret, fbappdomain, useAdvance) values('".$user_name."', '".$allowedDomains."', '".$toolBarBgColor."', '".$fontColor."', '".$bgColor."', '".$bgPanelColor."', '".$borderColor."', '".$chatBgColor."', '".$chatTextColor."', ".$width.", ".$height.", 1, now(), ".$user_id.", 1, '".$widget_title."', '".$fbappid."', '".$fbappsecret."', '".$fbappdomain."', '".$useAdvance."')");
			 
			 $widget_id = $thelastId = $write->lastInsertId();
			//$widget_key = $this->encode(rand().$user_id.$widget_id.rand());
			$widget_key = $this->uniqueKey($user_id.$widget_id);
			$write->query("update $widget set widget_key='".$widget_key."' WHERE user_id='".$user_id."' and widget_id='".$widget_id."'");
			
			$widget_fb_reg = $resource->getTableName('widget_fb_reg');
			$code=0;
			$write->query("INSERT INTO $widget_fb_reg SET id=NULL, uid='".$user_id."', fbid='".$facebook_id."', fbcode='".$code."', widgetid=".$widget_id.", ip_address='".$_SERVER['REMOTE_ADDR']."', updated_at='".now()."'");
			/////////////////////////////////////////////////////////////////////////////////
					
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
					}
			$fbAcsessToken = $resource->getTableName('fb_accessToken');
			$write->query("insert into $fbAcsessToken (uid, fbid, access_token) values('".$user_id."','".$facebook_id."','".$FBaccessToken."')");
            //$returnVal = $this->getUserInfo($lastId);
        }
        if($user_id > 0) {
            $customers = Mage::getModel('customer/customer');
            $collection = $customers->getCollection()
                                    ->addAttributeToFilter('entity_id', (string)$user_id)
                                    ->setPageSize(1);
            $existingCustomer = $collection->getFirstItem();
            $session = Mage::getSingleton("customer/session");
            $session->setCustomer($existingCustomer);
            $session->setCustomerAsLoggedIn($existingCustomer);
			$m_dob=$this->getMDobByUserId($user_id);
			$phone=$this->getPhoneByUserId($user_id);
			$m_email=$existingCustomer->getEmail();
        }
		$returnVal['userInfo'] = $this->getUserInfo(0);
		$returnVal['dob'] = $m_dob;
		$returnVal['phone'] = $phone;
		$returnVal['userEmail'] = $m_email;
		$returnVal['view'] = Mage::getModel('csservice/csservice')->getCheckinCount($user_id);
		////////////////////////////////////////////////////
		$fbAcsessToken = $resource->getTableName('fb_accessToken');
			$rsAccessToken = $read->fetchAll("SELECT * FROM $fbAcsessToken WHERE uid=".$user_id);
			if(count($rsAccessToken) > 0){
				$write->query("update $fbAcsessToken set access_token='".$FBaccessToken."' where uid='".$user_id."'");
			} else {
			$write->query("insert into $fbAcsessToken (uid, fbid, access_token) values('".$user_id."','".$facebook_id."','".$FBaccessToken."')");
			}
		/////////////////////////////////////////////////
        return $returnVal;
    }

   
	public function checkFBUserAndGetInfo1($first_name, $last_name, $about_me, $facebook_id, $email, $gender, $username, $dob, $FBaccessToken="",$device_id=0,$imei=0,$type="",$accesstoken="", $oncam_user_id = 0)
    {
		$username = str_replace(".","",$username);
		$m_email = "";
		$m_dob = "";
        $resource = Mage::getSingleton('core/resource');
        $read= $resource->getConnection('core_read');
        $write = $resource->getConnection('core_write');
		
        $returnVal = array();
        $customer = Mage::getModel('customer/customer');
        if($this->checkFbid($facebook_id)){
            $user_id = $this->checkFbid($facebook_id);
            //$returnVal = $this->getUserInfo($user_id);
        }else if ($this->checkEmail($email)) {
            $user_id = $this->checkEmail($email);
            if($oncam_user_id >0){
            	if($user_id != $oncam_user_id){
            		return "Error:This Facebook ID is already linked to an existing oncam user. Please check and try again.";
            	}
            }
			$m_dob=$this->getMDobByUserId($user_id);
			$returnVal['userInfo'] = $this->getUserInfo($user_id);
			$returnVal['dob'] = $m_dob;
			$returnVal['phone'] = $this->getMobileAndDeviceId($user_id, $device_id, $imei, $type);
			$returnVal['userEmail'] = $email;
			$returnVal['view'] = Mage::getModel('csservice/csservice')->getCheckinCount($user_id);
			$returnVal['jabberPassword'] = $this->jabberAuth($user_id);
			return $returnVal;
            //$returnVal = $this->getUserInfo($user_id);
        }else if ($this->checkUsername($username)) {
            $user_id = $this->checkUsername($username);
            $suffix = rand(11111, 99999);
            $uname = $username.'_'.$suffix;
            
            $websiteId = Mage::app()->getWebsite()->getId();
            $customer->website_id = $websiteId; 
            $customer->store=0;
            $customer->username = $uname;
            $customer->firstname = $first_name;
            $customer->lastname = $last_name;
			$customer->timezone = 'America/Los_Angeles';
            $customer->email = $email;
            $customer->sex = $gender;	
            $customer->shortbio = $about_me;
            $randomPassword = $customer->generatePassword();
            $customer->password = $randomPassword;
			if($dob){
				$dob_arr = explode('/',$dob);
				$month = $dob_arr[0];
				$day = $dob_arr[1];
				$year = $dob_arr[2];
				if($month != '' && $year != '' && $day != '')
				{
					$m_dob=$month."/".$day."/".$year;	
					if(($month==4 || $month==6 || $month==9 || $month==11) && $day ==31){
						return "ERROR:Select Valid Date Of Birth";
					}
					elseif($month == 2){
						$isleap = ($year % 4 == 0 && ($year % 100 != 0 || $year % 400 == 0));
						if ($day> 29 || ($day ==29 && !$isleap)){
							return "ERROR:Select Valid Date Of Birth";
						}
					}elseif($this->getAge($m_dob) < 13) {
						return 'ERROR:age must be greater than or equal to 13 years';
					}
					else{
						$customer->setDob($m_dob);
					}
				} else{
					return 'ERROR:Date of Birth can not be blank';
				}
			}
            $customer->save();
            $user_id = $customer->getId();
			$user_name = $uname;
					//////////////////////////////////////////////////////////////////////
					$resource = Mage::getSingleton('core/resource');
					$write = $resource->getConnection('core_write');
					$widget = $resource->getTableName('widget_info');
					$allowedDomains = "*";
					$chatBgColor = "000000";
					$chatTextColor = "ffffff";
					$height = "470";
					$width = "600";
					$borderColor = "000000";
					$bgPanelColor = "000000";
					$bgColor = "000000";
					$fontColor = "ffffff";
					$toolBarBgColor = "000000";
					$useAdvance="";
					$fbappdomain="";
					$fbappsecret="";
					$fbappid="";
					$widget_title="Default";
					
					
			 $write->query("insert into $widget (username, allowedDomains, toolBarBgColor, fontColor, bgColor, bgPanelColor,  borderColor, chatBgColor, chatTextColor, widgetWidth, widgetHeight, is_default, created_on, user_id, chat_input, widget_title, fbappid, fbappsecret, fbappdomain, useAdvance) values('".$user_name."', '".$allowedDomains."', '".$toolBarBgColor."', '".$fontColor."', '".$bgColor."', '".$bgPanelColor."', '".$borderColor."', '".$chatBgColor."', '".$chatTextColor."', ".$width.", ".$height.", 1, now(), ".$user_id.", 1, '".$widget_title."', '".$fbappid."', '".$fbappsecret."', '".$fbappdomain."', '".$useAdvance."')");
			 
			 $widget_id = $thelastId = $write->lastInsertId();
			//$widget_key = $this->encode(rand().$user_id.$widget_id.rand());
			$widget_key = $this->uniqueKey($user_id.$widget_id);
			$write->query("update $widget set widget_key='".$widget_key."' WHERE user_id='".$user_id."' and widget_id='".$widget_id."'");
			
			$widget_fb_reg = $resource->getTableName('widget_fb_reg');
			$code=0;
			$write->query("INSERT INTO $widget_fb_reg SET id=NULL, uid='".$user_id."', fbid='".$facebook_id."', fbcode='".$code."', widgetid=".$widget_id.", ip_address='".$_SERVER['REMOTE_ADDR']."', updated_at='".now()."'");
			/////////////////////////////////////////////////////////////////////////////////
			
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
					}
        }else {
            $websiteId = Mage::app()->getWebsite()->getId();
            $customer->website_id = $websiteId; 
            $customer->store=0;
            $customer->username = $username;
            $customer->firstname = $first_name;
            $customer->lastname = $last_name;
			$customer->timezone = 'America/Los_Angeles';
            $customer->email = $email;
            $customer->sex = $gender;	
            $customer->shortbio = $about_me;
            $randomPassword = $customer->generatePassword();
            $customer->password = $randomPassword;
			if($dob){
			$dob_arr = explode('/',$dob);
			$month = $dob_arr[0];
			$day = $dob_arr[1];
			$year = $dob_arr[2];
			if($month != '' && $year != '' && $day != '')
			{
				$m_dob=$month."/".$day."/".$year;	
				if(($month==4 || $month==6 || $month==9 || $month==11) && $day ==31){
					return "ERROR:Select Valid Date Of Birth";
				}
				elseif($month == 2){
					$isleap = ($year % 4 == 0 && ($year % 100 != 0 || $year % 400 == 0));
					if ($day> 29 || ($day ==29 && !$isleap)){
						return "ERROR:Select Valid Date Of Birth";
					}
				}elseif($this->getAge($m_dob) < 13) {
					return 'ERROR:age must be greater than or equal to 13 years';
				}
				else{
					$customer->setDob($m_dob);
				}
			} else{
				return 'ERROR:Date of Birth can not be blank';
			}
			}
            $customer->save();
            $user_id = $customer->getId();
			$user_name = $username;
					//////////////////////////////////////////////////////////////////////
					$resource = Mage::getSingleton('core/resource');
					$write = $resource->getConnection('core_write');
					$widget = $resource->getTableName('widget_info');
					$allowedDomains = "*";
					$chatBgColor = "000000";
					$chatTextColor = "ffffff";
					$height = "470";
					$width = "600";
					$borderColor = "000000";
					$bgPanelColor = "000000";
					$bgColor = "000000";
					$fontColor = "ffffff";
					$toolBarBgColor = "000000";
					$useAdvance="";
					$fbappdomain="";
					$fbappsecret="";
					$fbappid="";
					$widget_title="Default";
					
					
			 $write->query("insert into $widget (username, allowedDomains, toolBarBgColor, fontColor, bgColor, bgPanelColor,  borderColor, chatBgColor, chatTextColor, widgetWidth, widgetHeight, is_default, created_on, user_id, chat_input, widget_title, fbappid, fbappsecret, fbappdomain, useAdvance) values('".$user_name."', '".$allowedDomains."', '".$toolBarBgColor."', '".$fontColor."', '".$bgColor."', '".$bgPanelColor."', '".$borderColor."', '".$chatBgColor."', '".$chatTextColor."', ".$width.", ".$height.", 1, now(), ".$user_id.", 1, '".$widget_title."', '".$fbappid."', '".$fbappsecret."', '".$fbappdomain."', '".$useAdvance."')");
			 
			 $widget_id = $thelastId = $write->lastInsertId();
			//$widget_key = $this->encode(rand().$user_id.$widget_id.rand());
			$widget_key = $this->uniqueKey($user_id.$widget_id);
			$write->query("update $widget set widget_key='".$widget_key."' WHERE user_id='".$user_id."' and widget_id='".$widget_id."'");
			
			$widget_fb_reg = $resource->getTableName('widget_fb_reg');
			$code=0;
			$write->query("INSERT INTO $widget_fb_reg SET id=NULL, uid='".$user_id."', fbid='".$facebook_id."', fbcode='".$code."', widgetid=".$widget_id.", ip_address='".$_SERVER['REMOTE_ADDR']."', updated_at='".now()."'");
			/////////////////////////////////////////////////////////////////////////////////
					
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
					}
        }

        if($oncam_user_id >0){
        	if($user_id != $oncam_user_id){
        		return "Error:This Facebook ID is already linked to an existing oncam user. Please check and try again.";
        	}
        }

        if($user_id > 0) {
			$deactivecustomers = Mage::getModel('customer/customer')->load($user_id);
			if(($deactivecustomers->getTimezone() == "") || ($deactivecustomers->getTimezone() == "null")){
				$deactivecustomers->setTimezone('America/Los_Angeles');
				$deactivecustomers->save();
			}
			if($deactivecustomers->getIsActive() == 0){
				return "Your account is not active";
			}
            $customers = Mage::getModel('customer/customer');
            $collection = $customers->getCollection()
                                    ->addAttributeToFilter('entity_id', (string)$user_id)
                                    ->setPageSize(1);
            $existingCustomer = $collection->getFirstItem();
            $session = Mage::getSingleton("customer/session");
            $session->setCustomer($existingCustomer);
            $session->setCustomerAsLoggedIn($existingCustomer);
			$m_dob=$this->getMDobByUserId($user_id);
			//$phone=$this->getPhoneByUserId($user_id);
			$m_email=$existingCustomer->getEmail();
        }
		$returnVal['userInfo'] = $this->getUserInfo($user_id);
		$returnVal['dob'] = $m_dob;
		$returnVal['phone'] = $this->getMobileAndDeviceId($user_id, $device_id, $imei, $type);
		$returnVal['userEmail'] = $m_email;
		$returnVal['view'] = Mage::getModel('csservice/csservice')->getCheckinCount($user_id);
		////////////////////////////////////////////////////
		$fbAcsessToken = $resource->getTableName('fb_accessToken');
			$rsAccessToken = $read->fetchAll("SELECT * FROM $fbAcsessToken WHERE uid=".$user_id);
			if(count($rsAccessToken) > 0){
				$write->query("update $fbAcsessToken set access_token='".$FBaccessToken."', fbid='".$facebook_id."' where uid='".$user_id."'");
			} else {
			$write->query("insert into $fbAcsessToken (uid, fbid, access_token) values('".$user_id."','".$facebook_id."','".$FBaccessToken."')");
			}
		/////////////////////////////////////////////////
		/////////////////////////////////////////////////
		$seckey = substr($accesstoken,0,24);
		$token = substr($accesstoken,24);
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$xapplication_token = $resource->getTableName('xapplication_token');
		$write->query("update cs_xapplication_token set user_id=".$user_id." WHERE token='".$token."' and security_key='".$seckey."'");
		//=====================================================================
		$returnVal['jabberPassword'] = $this->jabberAuth($user_id);
		//=================================================================================
		Mage::getModel('profile/profile')->updateJabberRosterInfo($user_id);
		//=================================================================================
        return $returnVal;
    }
	private function getMDobByUserId($user_id) {
		$m_dob="";
		if($user_id > 0) {
            $customers = Mage::getModel('customer/customer');
            $collection = $customers->getCollection()
									->addAttributeToSelect('dob')
                                    ->addAttributeToFilter('entity_id', (string)$user_id)
                                    ->setPageSize(1);
            $existingCustomer = $collection->getFirstItem();
            //$session = Mage::getSingleton("customer/session");
            //$session->setCustomer($existingCustomer);
            //$session->setCustomerAsLoggedIn($existingCustomer);
			$m_dob=$existingCustomer->getDob();
        }
        return $m_dob;
	}
	private function getPhoneByUserId($user_id) {
		$phone="";
		if($user_id > 0) {
            $customers = Mage::getModel('customer/customer');
            $collection = $customers->getCollection()
									->addAttributeToSelect('phone')
                                    ->addAttributeToFilter('entity_id', (string)$user_id)
                                    ->setPageSize(1);
            $existingCustomer = $collection->getFirstItem();
			$phone=$existingCustomer->getPhone();
			if($phone != ""){
				return $phone;
			} else {
				return "false";
			}
        }
	}
	public function checkEmail($email){
	$customer = Mage::getModel('customer/customer');
	$collection = $customer->getCollection()
                                ->addAttributeToSelect('*')
                                ->addAttributeToFilter('email', (string)$email)
                                ->setPageSize(1);
	 $uidExist = (bool)$collection->count();
         if($uidExist) {
            foreach ($collection as $k=>$customer) {
                $customer_id =  $customer->getId();
                /*
                echo $customer->getId(). "/";
                 echo $customer->getUsername() ."/";
                echo $customer->getEmail() ."/";
                echo $customer->getFacebookUid() ."/";
                exit;
                 * 
                 */
            }
             return $customer_id;
         } else {
             return false;
         }
    }
    
    public function checkUsername($username){
         
	$customer = Mage::getModel('customer/customer');
	$collection = $customer->getCollection()
                               ->addAttributeToSelect('*')
				->addAttributeToFilter('username', (string)$username)
                        	->setPageSize(1);
	 $uidExist = (bool)$collection->count();
         
         if($uidExist) {
           foreach ($collection as $k=>$customer) {
            $customer_id =  $customer->getId();
            /*
            echo $customer->getUsername() ."/";
            echo $customer->getEmail() ."/";
            echo $customer->getFacebookUid() ."/";
            exit;
             * 
             */
            }
             return $customer_id;
         } else {
             return false;
         }
    }
	
	
    
	public function checkUsername_test() {
                return $this->checkFBUserAndGetInfo('Sanjay', 'Kumar', 'I am a Sanjay', '1212456598', 'sanjay.kumar@eworks.in', 'male', 'sanjaykumar852');
                //return $this->checkFBUserAndGetInfo('test', 'user', 'I am a cool person', '1212456598', 'sagar.gupta@eworks.in', 'male', 'testuser');
                //return $this->checkFBUserAndGetInfo('test', 'user', 'I am a cool person', '643595525', 'sagar.gupta@test.in', 'male', 'testuser');
		//return $this->checkFbid("643595525");
	}
	
   public function checkFbid($fbid){
        /*
	$customer = Mage::getModel('customer/customer');
	$collection = $customer->getCollection()
				->addAttributeToFilter('facebook_uid', (string)$fbid)
				->setPageSize(1); 
	 $uidExist = (bool)$collection->count();
         * 
         */
        $resource = Mage::getSingleton('core/resource');
        $read= $resource->getConnection('core_read');
        $widget_fb_reg = $resource->getTableName('widget_fb_reg');
        $rs = $read->fetchRow("SELECT uid FROM $widget_fb_reg WHERE fbid='".$fbid."'");
        $uidExist = (bool)count($rs['uid']);
         if($uidExist) {
             return $rs['uid'];
         } else {
             return false;
         }
    }
	
	public function uploadCustomerProfileImage($user_id=0,$data=null){
		//$data="";
		if (isset($_POST["data"]) && ($_POST["data"] !="") && isset($_POST["user_id"]) && $_POST["user_id"]!="" && $_POST["user_id"]>0){
				//$data = $_POST["data"];
				$user_id = $_POST["user_id"];
				$customer = Mage::getModel('customer/customer')->load($user_id);
				$data = base64_decode($_POST["data"]);
				
				//$decodedData = base64_decode(chunk_split($_POST["data"]));
				$im = imagecreatefromstring($data);
				if ($im == false) {
					return ' Error: Data is not well formated.';
				}
				$fileName = strtoupper( $user_id . "-" . $this->getRandomString(8) );
			
				if (isset($im) && $im != false) {
			
					$fullFilePath = self::$EventImageFolder . $fileName;
					$image_path = $fullFilePath . '_img.png';	
					$path = realpath('.')."/media/profile/";
					$fullFilePath = $path . $image_path;
					$fpath = Mage::getBaseDir('media') . DS .  'profile'. DS;
					$fullpath = $fpath.$image_path;
					//return $fullFilePath;
					//if(file_exists($fullFilePath)){
					//	unlink($fullFilePath);      
					//}
					header('Content-Type: image/png');
					$result = imagepng($im, $fullFilePath);
					imagedestroy($im);
					//save into s3
					/*
					$bucketName = 'chattrspace';
					$objectname = 'profiles/48x48/'.$image_path;
					$filename = Mage::getModel('uploadjob/amazonS3')
									->putImage( $bucketName, $fullpath, $objectname, 'public');
					
					$bucketName = 'chattrspace';
					$objectname = 'profiles/128x128/'.$image_path;
					$filename = Mage::getModel('uploadjob/amazonS3')
									->putImage( $bucketName, $fullpath, $objectname, 'public');*/
					//============================================================================
					$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($image_path);	
					$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($image_path, 128, 80, "128x80");
					$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($image_path, 128, 128, "128x128",false,$user_id);
					$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($image_path, 30, 30, "30x30");
					$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($image_path, 48, 48, "48x48");
					//============================================================================
					//sleep(15);
					unlink($fullFilePath);
					//end s3
					$customer->setData('profile_picture', $image_path);
					$customer->save();
					return $this->getUserInfo($user_id);
            }else {
				return 'Error in Image Uploading';
            }
		}else {
				return 'Use Form POST';
            }
	}
	public function uploadCustomerProfileImageIphone($user_id=0,$data=null){
		//$data="";
		if (isset($_POST["data"]) && ($_POST["data"] !="") && isset($_POST["user_id"]) && $_POST["user_id"]!="" && $_POST["user_id"]>0){
				//$data = $_POST["data"];
				$user_id = $_POST["user_id"];
				$customer = Mage::getModel('customer/customer')->load($user_id);
				$encodedData = str_replace(' ','+',$_POST["data"]);
				$decodedData = ""; 
				//for ($i=0; $i < ceil(strlen($_POST["data"])/256); $i++) {
					//$decodedData = $decodedData . base64_decode(substr($_POST["data"],$i*256,256)); 
				//}
				for($i=0, $len=strlen($encodedData); $i<$len; $i+=4){
					$decodedData = $decodedData . base64_decode( substr($encodedData, $i, 4) );
				}
				
				//$decodedData = base64_decode(chunk_split($_POST["data"]));
				$im = imagecreatefromstring($decodedData);
				if ($im == false) {
					return ' Error: Data is not well formated.';
				}
				$fileName = strtoupper( $user_id . "-" . $this->getRandomString(8) );
			
				if (isset($im) && $im != false) {
			
					$fullFilePath = self::$EventImageFolder . $fileName;
					$image_path = $fullFilePath . '_img.jpg';	
					$path = realpath('.')."/media/profile/";
					$fullFilePath = $path . $image_path;
					$fpath = Mage::getBaseDir('media') . DS .  'profile'. DS;
					$fullpath = $fpath.$image_path;
					//return $fullFilePath;
					//if(file_exists($fullFilePath)){
					//	unlink($fullFilePath);      
					//}
					//header('Content-Type: image/png');
					$result = imagepng($im, $fullFilePath);
					imagedestroy($im);
					//save into s3
					/*
					$bucketName = 'chattrspace';
					$objectname = 'profiles/48x48/'.$image_path;
					$filename = Mage::getModel('uploadjob/amazonS3')
									->putImage( $bucketName, $fullpath, $objectname, 'public');
					
					$bucketName = 'chattrspace';
					$objectname = 'profiles/128x128/'.$image_path;
					$filename = Mage::getModel('uploadjob/amazonS3')
									->putImage( $bucketName, $fullpath, $objectname, 'public');*/
					//============================================================================
					$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($image_path);	
					$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($image_path, 128, 80, "128x80");
					$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($image_path, 128, 128, "128x128",false,$user_id);
					$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($image_path, 30, 30, "30x30");
					$resizeImageUrl = Mage::getModel('attributemanager/square')->resizeOriginalImageNew($image_path, 48, 48, "48x48");
					//============================================================================
					//sleep(15);
					
					//end s3
					$customer->setData('profile_picture', $image_path);
					$customer->save();
					unlink($fullFilePath);
					return $this->getUserInfo($user_id);
            }else {
				return 'Error in Image Uploading';
            }
		}else {
				return 'Use Form POST';
            }
	}
	public function uploadEventImage($event_id=0,$data=null){
		
		if (isset($_POST["data"]) && ($_POST["data"] !="") && isset($_POST["event_id"]) && $_POST["event_id"]>0){
				$event_id = $_POST["event_id"];
				$product = Mage::getModel('catalog/product')->load($event_id);
				$data = base64_decode($_POST["data"]);
				$im = imagecreatefromstring($data);
			
				$fileName = strtoupper( $user_id . "-" . $this->getRandomString(8) );
			
				if (isset($im) && $im != false) {
			
					$fullFilePath = self::$EventImageFolder . $fileName;
					$image_path = $fullFilePath . '_img.jpg';	
					$path = realpath('.')."/media/csimages/";
					$fullFilePath = $path . $image_path;
			
					if(file_exists($fullFilePath)){
						unlink($fullFilePath);      
					}

					$result = imagepng($im, $fullFilePath);
					imagedestroy($im);
					//save into s3
					$bucketName = 'chattrspace';
					$objectname = 'checkins/'.$image_path;
					$filename = Mage::getModel('uploadjob/amazonS3')
									->putImage( $bucketName, $fullFilePath, $objectname, 'public');					
					//sleep(15);
					unlink($fullFilePath);
					//end s3
					$product->setEventImage($image_path);
					$product->save();
					return "Image Uploaded";
            }else {
				return 'Error in Image Uploading';
            }
		}else {
				return 'Use Form POST';
            }
	}
	public function uploadEventImageIphone1($event_id=0,$data=null){
		
		if (isset($_POST["data"]) && ($_POST["data"] !="") && isset($_POST["event_id"]) && $_POST["event_id"]>0){
				$event_id = $_POST["event_id"];
				$product = Mage::getModel('catalog/product')->load($event_id);
				$encodedData = str_replace(' ','+',$_POST["data"]);
				$decodedData = ""; 
				//for ($i=0; $i < ceil(strlen($_POST["data"])/256); $i++) {
					//$decodedData = $decodedData . base64_decode(substr($_POST["data"],$i*256,256)); 
				//}
				for($i=0, $len=strlen($encodedData); $i<$len; $i+=4){
					$decodedData = $decodedData . base64_decode( substr($encodedData, $i, 4) );
				}
				$im = imagecreatefromstring($decodedData);
			
				$fileName = strtoupper( $user_id . "-" . $this->getRandomString(8) );
			
				if (isset($im) && $im != false) {
			
					$fullFilePath = self::$EventImageFolder . $fileName;
					$image_path = $fullFilePath . '_img.jpg';	
					$path = realpath('.')."/media/csimages/";
					$fullFilePath = $path . $image_path;
			
					if(file_exists($fullFilePath)){
						unlink($fullFilePath);      
					}

					$result = imagepng($im, $fullFilePath);
					imagedestroy($im);
					//save into s3
					$bucketName = 'chattrspace';
					$objectname = 'checkins/'.$image_path;
					$filename = Mage::getModel('uploadjob/amazonS3')
									->putImage( $bucketName, $fullFilePath, $objectname, 'public');					
					//sleep(15);
					unlink($fullFilePath);
					//end s3
					$product->setEventImage($image_path);
					$product->save();
					return "Image Uploaded";
            }else {
				return 'Error in Image Uploading';
            }
		}else {
				return 'Use Form POST';
            }
	}
	public function uploadEventImageIphone($user_id=0,$event_id=0,$data=null){
		
		//if (isset($_POST["data"]) && ($_POST["data"] !="") && isset($_POST["event_id"]) && $_POST["event_id"]>0){
				$event_id = $_POST["event_id"];
				$product = Mage::getModel('catalog/product')->load($event_id);
				$encodedData = str_replace(' ','+',$_POST["data"]);
				$decodedData = ""; 
				//for ($i=0; $i < ceil(strlen($_POST["data"])/256); $i++) {
					//$decodedData = $decodedData . base64_decode(substr($_POST["data"],$i*256,256)); 
				//}
				for($i=0, $len=strlen($encodedData); $i<$len; $i+=4){
					$decodedData = $decodedData . base64_decode( substr($encodedData, $i, 4) );
				}
				$im = imagecreatefromstring($decodedData);
			
				$fileName = strtoupper( $user_id . "-" . $this->getRandomString(8) );
			
				if (isset($im) && $im != false) {
					$image_path = $fileName . '_img.jpg';	
					$path = Mage::getBaseDir('media') . DS .  'event'. DS;
					$fullFilePath = $path . $image_path;
			
					if(file_exists($fullFilePath)){
						unlink($fullFilePath);      
					}

					$result = imagepng($im, $fullFilePath);
					imagedestroy($im);
					//===============================================
					$this->_addImages($product, $image_path, $user_id);
					//===============================================
					return "Image Uploaded";
            }else {
				return 'Error in Image Uploading';
            }
		//}else {
		//		return 'Use Form POST';
		//}
	}
	public function userFavoritedAndEvent($event_id=0,$user_id=0,$is_fav=null){
		$resource = Mage::getSingleton('core/resource');
		$write = $resource->getConnection('core_write');
		$table = $resource->getTableName('favorited_user');
		
		$sqlInsert = " insert into $table(event_id,user_id,is_fav,created_at) values(" . $event_id . ", ". $user_id ."," . $is_fav .",now());";
			try {
				mysql_query($sqlInsert);
				return "Data Inserted";
			} catch (Exception $e) {
				throw new Exception("Error while saving : ".$e->getMessage());
			}
	}
	public function mobileNotification($user_id,$notificationType='Online',$hostUser=0,$fbNotify=0,$twtNotify=0){
		if($user_id >0){
			if($fbNotify == 0 && $twtNotify == 0 ){
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			
			$follower = $resource->getTableName('follower');
			$device = $resource->getTableName('mobile_device');
			Mage::log('User: '.$user_id . ', NT:'.$notificationType,null,'apn.log');
			try {
				//$select = "select device_id from $device where notificationType='".$notificationType."' and type IN ('iPhone','iPad') and device_id!='' and active=1 and user_id IN(select follower_id from $follower WHERE follow=".$user_id." and follower_id<>follow and status=1 group by follower_id)";
				$select = "select cs_mobile_device.device_id from cs_mobile_device JOIN cs_follower ON cs_mobile_device.user_id = cs_follower.follower_id where cs_follower.follow=".$user_id." and cs_follower.follower_id<>cs_follower.follow and cs_follower.status=1 and cs_mobile_device.notificationType='".$notificationType."' and cs_mobile_device.type IN ('iPhone','iPad') and cs_mobile_device.device_id!='' and cs_mobile_device.active=1 group by cs_mobile_device.device_id";
				$deviceTokens = $read->fetchAll($select);
				//return $deviceTokens; exit;
				if(count($deviceTokens) > 0){
					if ($notificationType=='InvitedToStage')
						$this->push_notification($deviceTokens,'/var/websites/oncam_com/webroot/certs/PushDev.p12','', 'develop', '', '', 'default',$this->getUserNameByUserId($user_id) . ' is oncam with ' . $this->getUserNameByUserId($hostUser) . '.',$user_id,$notificationType);
					else{
						if($hostUser > 0){
							$rs = $this->push_notification($deviceTokens,'/var/websites/oncam_com/webroot/certs/PushDev.p12','', 'develop', '', '', 'default',$this->getUserNameByUserId($user_id) . ' is oncam with ' . $this->getUserNameByUserId($hostUser) . '.',$user_id,$notificationType);
						} else {
							$rs = $this->push_notification($deviceTokens,'/var/websites/oncam_com/webroot/certs/PushDev.p12','', 'develop', '', '', 'default',$this->getUserNameByUserId($user_id) . ' is oncam right now.',$user_id,$notificationType);
						}
					}
					//return $rs;
				}
			} catch (Exception $e) {
				Mage::log($e,null,'apn.log');
			}
			try {
				//$select2 = "select device_id from $device where type IN ('Android') and device_id!='' and user_id IN(select follower_id from $follower WHERE follow=".$user_id." and follower_id<>follow and status=1 group by follower_id)";
				$select2 = "select cs_mobile_device.device_id from cs_mobile_device JOIN cs_follower ON cs_mobile_device.user_id = cs_follower.follower_id where cs_follower.follow=".$user_id." and cs_follower.follower_id<>cs_follower.follow and cs_follower.status=1 and cs_mobile_device.type='Android' and cs_mobile_device.device_id!='' group by cs_mobile_device.device_id";
				//$selectTest = "select follower_id from $follower WHERE follow=".$user_id." and follower_id<>follow and status=1 group by follower_id";
				$deviceTokens2 = $read->fetchAll($select2);
				//return $deviceTokens2;
				//var_dump($deviceTokens2);
				if(count($deviceTokens2) > 0){ //Mage::log(count($deviceTokens2),null,'gcm.log');
					if ($notificationType=='InvitedToStage')
						$this->gcm_push_notification($deviceTokens2,$this->getUserNameByUserId($user_id) . ' is oncam with ' . $this->getUserNameByUserId($hostUser) . '.',$user_id);
					else{
						if($hostUser > 0){
							$result=$this->gcm_push_notification($deviceTokens2,$this->getUserNameByUserId($user_id) . ' is oncam with ' . $this->getUserNameByUserId($hostUser) . '.',$user_id);
						} else {
							$result=$this->gcm_push_notification($deviceTokens2,$this->getUserNameByUserId($user_id) . ' is oncam right now.',$user_id);
						}
					}	
						//return $result;
				}
			} catch (Exception $e2) {
				//log to gcmpushlog
				Mage::log($e2,null,'gcm.log');
				//throw new Exception("".$e->getMessage());  
			}
			}
			if($fbNotify == 1)
				$this->mobileNotifyFB($user_id);
			
			if($twtNotify == 1)
			 $this->mobileNotifyTwt($user_id);
		}
	}
	public function mobileNotifyFB($user_id){
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$msg = $this->getUserNameByUserId($user_id). ' is oncam right now. Click the link below to join '.$this->getUserNameByUserId($user_id).' live from facebook.';
		$name = 'Join '.$this->getUserNameByUserId($user_id).' live from facebook.';
		$widget_id = $this->getWidgetIdOfUser($user_id);
		$image = $this->getProfilePic($user_id);
		$fbAcsessToken = $resource->getTableName('fb_accessToken');
		$rsAccessToken = $read->fetchRow("SELECT * FROM $fbAcsessToken WHERE uid=".$user_id);
		$facebook_id=$rsAccessToken[fbid];
		$facebook_access_token=$rsAccessToken[access_token];
		//return $facebook_access_token;
		//$params = array('access_token'=>$facebook_access_token, 'message'=>$msg);
		$params = array('access_token'=>$facebook_access_token, 'message'=>$msg,'name'=>$name,'link' =>'http://apps.facebook.com/oncamapp/?profileid='.$widget_id,'picture' => $image,'description' => 'oncam is the free, easy, and fun way to be live with friends and followers from anywhere with iPhone, iPad, Android, and Facebook.','caption'=>'http://apps.facebook.com/oncamapp/');
		$url = "https://graph.facebook.com/$facebook_id/feed";
		$ch = curl_init();
		curl_setopt_array($ch, array(
		CURLOPT_URL => $url,
		CURLOPT_POSTFIELDS => $params,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_VERBOSE => true
		));
		$result = curl_exec($ch);	
	}
	public function mobileNotifyTwt($user_id){
		try{
		$connection = Mage::getModel('csservice/twitteroauth');
		$initialize = $connection->initializeByUserId($user_id);
		$msg = 'Join '.$this->getUserNameByUserId($user_id).' live in oncam now onc.am/'.$this->getUserNameByUserId($user_id);
		$result = $connection->post('statuses/update', array('status' =>  $msg));
		return $result;
		}  catch (Exception $e) {
			//return "error ".$e->getMessage();
		} 
	}
	public function linkYoutube($user_id,$token){
			$customer=Mage::getSingleton('customer/customer')->load($user_id);
			$customer->setYoutubeToken($token);
			$customer->save();
			return 'successfully link with youtube';
	}
	public function linkYoutubeV3($user_id=0,$code="",$access_token="",$refresh_token=""){
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$table = $resource->getTableName('youtube_access_token');
		$sqlSelect = "select * from $table where user_id=".$user_id;
		$result = $read->fetchAll($sqlSelect);
		if(count($result) > 0){
		
		} else{
			$sqlInsert = "Insert into $table (user_id, code, access_token, refresh_token, enable, created_at) values (".$user_id.", '".$code."','".$access_token."', '".$refresh_token."', 'y', now())";
		$write->query($sqlInsert);
		}
		$customer=Mage::getSingleton('customer/customer')->load($user_id);
		$customer->setYoutubeToken($code);
		$customer->save();
		return 'successfully link with youtube';
	}
	public function unlinkYoutube($user_id){
			$customer = Mage::getModel('customer/customer')->load($user_id);
			$customer->setYoutubeToken('');
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
		return 'successfully unlink with youtube';
	}
	public function getFacebookSetting(){
		if(Mage::getSingleton( 'customer/session' )->isLoggedIn()){
			$sfacebook = Mage::getResourceSingleton('customer/customer')
							->getAttribute('sfacebook')->getSource()->getAllOptions();
			return $sfacebook;
		}
		else
			return 'Not LoggedIn Please Login.';
	}
	public function getTwitterSetting(){
		if(Mage::getSingleton( 'customer/session' )->isLoggedIn()){
			$stwitter = Mage::getResourceSingleton('customer/customer')
							->getAttribute('stwitter')->getSource()->getAllOptions();
			return $stwitter;
		}
		else
			return 'Not LoggedIn Please Login.';
	}
	public function setTwitterSetting($user_id,$value,$checked){
			$customer = Mage::getModel('customer/customer')->load($user_id);
			$stwitter = $customer->getStwitter();
				$c = explode(',',$stwitter);
				
				if($checked=='true'){
					if(!in_array($value,$c,true))
						$c[]=$value;
					$customer->setStwitter(implode(',',$c));
				}else{
					foreach($c as $v){
						if($v!=$value)
							$val[]=$v;
					}
					$customer->setStwitter(implode(',',$val));
				}
				$customer->save();
	}
	public function setFacebookSetting($user_id,$value,$checked){
			$customer = Mage::getModel('customer/customer')->load($user_id);
			$sfacebook = $customer->getSfacebook();
				$c = explode(',',$sfacebook);
				
				if($checked=='true'){
					if(!in_array($value,$c,true))
						$c[]=$value;
					$customer->setSfacebook(implode(',',$c));
					//$customer->setSfacebook($sfacebook,$value);
				}else{
					foreach($c as $v){
						if($v!=$value)
							$val[]=$v;
					}	
					$customer->setSfacebook(implode(',',$val));
					//$customer->setSfacebook('');
				}
				$customer->save();
	}
	public function getThumbnailImageByEventId($event_id) {
			$event_id = intval($event_id);
			$theProduct = Mage::getModel('catalog/product')->load($event_id);
			$theProduct = $theProduct->toArray();
			return "http://chattrspace.s3.amazonaws.com/events/135x110/".$theProduct['event_image'];
	}
	public function getFollowingsCount($user_id) {
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$follower = $resource->getTableName('follower');
		$customer_entity = $resource->getTableName('customer_entity');
			
		$select = "SELECT COUNT( DISTINCT follow ) AS count FROM cs_follower, cs_customer_entity WHERE follower_id =".$user_id." AND follower_id<>follow AND STATUS =1 AND cs_customer_entity.entity_id = cs_follower.follow";
		$result = $read->fetchRow($select);
		return $result['count'];
	}
	public function getFollowersCount($user_id) {
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$follower = $resource->getTableName('follower');
		$customer_entity = $resource->getTableName('customer_entity');
			
		$select = "SELECT COUNT( DISTINCT follower_id ) AS count FROM cs_follower, cs_customer_entity WHERE follow =".$user_id." AND follower_id<>follow AND STATUS =1 AND cs_customer_entity.entity_id = cs_follower.follower_id";
		$result = $read->fetchRow($select);
		return $result['count'];
	}
	public function getUpcomingEventsByHostIdCount($user_id=0){
		$productCount = 5;	 
		$storeId    = Mage::app()->getStore()->getId(); 
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		if($time_zone == 'America/Los_Angeles'){
			$now = strtotime('+1 hour')+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime('+1 hour'));
		} else{
			$now = strtotime(now())+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime(now()));
		}
		$events = array();
		if($user_id!=0){
			$events = Mage::getResourceModel('catalog/product_collection')
					   ->addAttributeToSelect('*')
					   ->addFieldToFilter('user_id', array('eq'=> $user_id))
					   ->addAttributeToFilter('news_to_date', array('gteq' => $date))
					   ->addAttributeToFilter('status', 1)
					   ->addFieldToFilter('attribute_set_id', 9)
					   ->setOrder('news_to_date', 'asc')
					   ->setOrder('entity_id', 'desc')
					   ->load()->toArray();
		$counter=0;			   
		foreach ($events as $k => $event) {			   
			$from = strtotime($event['news_from_date'])+$timeoffset;
			$to = strtotime($event['news_to_date'])+$timeoffset;
			if (($now > $from) && ($now < $to)) {
				
			}else{
				$counter++;
			}
		}
			return $counter;
		}	
	}
	public function getVideoCount($user_id){
		if($user_id > 0){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$videoTable = $resource->getTableName('video');
		$select = ' select video_id, title, identifier, description, profile_id, user_id, video_path, thumbnail_path, duration, tags, created_time  from '.$videoTable.' where isdeleted = 0 and status = 1 and user_id = '.$user_id;
		$rs = mysql_query($select);
		$numResults = mysql_num_rows($rs);		
		return  $numResults; 
		}
	}
	public function getVideoByUserId($user_id, $page=1){
		if($user_id > 0){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$videoTable = $resource->getTableName('video');
		$select = 'select video_id, title, identifier, description, profile_id, user_id, video_path, thumbnail_path, duration, tags, created_time  from '.$videoTable.' where isdeleted = 0 and status = 1 and user_id = '.$user_id.' order by video_id desc';
		
		$limit = 15;
			if($page<=0)
				$page=1;
			$page=$page-1;
			if($limit!=0)
				$select.= ' limit '.$limit*$page.', '.$limit;
				
		$selectcount = 'select * from '.$videoTable.' where isdeleted = 0 and status = 1 and user_id = '.$user_id;
		$rs = $read->fetchAll($select);
		foreach($rs as $k=>$r){
			$item[$k] = array(
							'id'=> $r['video_id'],
							'title'=> $r['title'],
							'description'=> $r['description'],
							'profile_id'=> $r['profile_id'],
							'user_id'=> $r['user_id'],
							'video_path'=> $r['video_path'],
							'thumbnail_path'=> $r['thumbnail_path'],
							'duration'=> $r['duration'],
							'created_time' => $r['created_time'].' GMT',
							'created_time2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($r['created_time'])))),
							);
		}
		$result=array(); 
		$result['video'] = $item;
		$result['video_count'] = count($read->fetchAll($selectcount)); 
		return $result;
		}
		else
			return 0;
	}
	public function getDetailProfileInfoById($user_id, $current_user_id=0, $page=1){
	if($user_id > 0){
		$followers = $this->getFollowersCount($user_id);
		$followings = Mage::getModel('csservice/csservice')->getFollowingCount($user_id);
		$events = $this->getUpcomingEventsByHostIdCount($user_id);
		$videos = $this->getVideoCount($user_id);
		$isfollow = $this->isFollow($user_id,$current_user_id);
		$views = Mage::getModel('csservice/csservice')->getCheckinCount($user_id);
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$shortbio = $customer->getShortbio();
		$sfacebook = $customer->getSfacebook();
		$c = explode(',',$sfacebook);
		if(in_array('167',$c,true)){
			$videoRecordingFBPost="true";
		} else{
			$videoRecordingFBPost="false";
		}
		
		if($customer->getYoutubeToken()){
			$youtubeLink="true";
		} else{
			$youtubeLink="false";
		}
		$privacy = $customer->getPrivacy();
		$a = explode(",",$privacy);
		if(in_array(166,$a,true)){
			$blockOtherUserTorecordInMyProfile="true";
		} else {
			$blockOtherUserTorecordInMyProfile="false";
		}
		
		if($customer->getTwitterId()){
			$twitterLink="true";
			$sTwitter = $customer->getStwitter();
			$t = explode(',',$sTwitter);
			if(in_array('168',$t,true)){
				$videoRecordingTWTPost="true";
			} else{
				$videoRecordingTWTPost="false";
			}
		} else {
			$twitterLink="false";
			$videoRecordingTWTPost="false";
		}
		$result=array();
		
		$result['followers']['count']=$followers;
		$result['followings']['count']= $followings;
		$result['events']['count']= $events;
		$result['videos']['count']= $videos;
		$result['isFollow'] = $isfollow;
		$result['views'] = $views;
		$result['username'] = $customer->getUsername();
		$result['firstName'] = $customer->getFirstname();
		$result['lastName'] = $customer->getLastname();
		$result['location'] = $customer->getLocation();
		$result['profile_pic'] = $this->getProfilePic($user_id);
		$result['profile_pic48'] = $this->getProfilePic48($user_id);
		$result['shortbio'] = $shortbio;
		$result['videoRecordingFBPost'] = $videoRecordingFBPost;
		$result['videoRecordingTWTPost'] = $videoRecordingTWTPost;
		if($customer->getYoutubeVideoStream() == 1){
			$result['videoRecordingYTPost'] = "true";
		} else {
			$result['videoRecordingYTPost'] = "false";	
		}
		$result['twitterLink'] = $twitterLink;
		$result['youtubeLink'] = $youtubeLink;
		$result['blockOtherUserTorecordInMyProfile'] = $blockOtherUserTorecordInMyProfile;
		return $result;
	}
	else
		return 0;
	}
	public function checkTWTUserAndGetInfo($first_name, $last_name, $about_me, $twitter_id, $gender, $username, $dob)
    {
        /*
        $return_values = array();
        $customer = Mage::getModel('customer/customer');
        $resource = Mage::getSingleton('core/resource');
        $read= $resource->getConnection('core_read');
        $write = $resource->getConnection('core_write');
        $widget_fb_reg = $resource->getTableName('widget_fb_reg');
        $select = "select * from $widget_fb_reg WHERE fbid= ".$facebook_id;
        $rs = $read->fetchRow($select);
        
        return $return_values;
         * 
         */
		$m_email="";
		$m_dob="";
		$email=$username."_twitter@chatterspace.com";
        $resource = Mage::getSingleton('core/resource');
        $read= $resource->getConnection('core_read');
        $write = $resource->getConnection('core_write');
        $returnVal = array();
        $customer = Mage::getModel('customer/customer');
        if($this->checkTWTid($twitter_id)){
            $user_id = $this->checkTWTid($twitter_id);
            //$returnVal = $this->getUserInfo($user_id);
        }else if ($this->checkEmail($email)) {
            $user_id = $this->checkEmail($email);
            //$returnVal = $this->getUserInfo($user_id);
        }else if ($this->checkUsername($username)) {
            $user_id = $this->checkUsername($username);
            $suffix = rand(11111, 99999);
            $uname = $username.'_'.$suffix;
            $websiteId = Mage::app()->getWebsite()->getId();
            $customer->website_id = $websiteId; 
            $customer->store=0;
            $customer->username = $uname;
            $customer->firstname = $first_name;
            $customer->lastname = $last_name;
            $customer->email = $email;
            $customer->sex = $gender;	
            $customer->shortbio = $about_me;
            $randomPassword = $customer->generatePassword();
            $customer->password = $randomPassword;
			$dob_arr = explode('/',$dob);
			$month = $dob_arr[0];
			$day = $dob_arr[1];
			$year = $dob_arr[2];
			if($month != '' && $year != '' && $day != '')
			{
				$m_dob=$month."/".$day."/".$year;	
				if(($month==4 || $month==6 || $month==9 || $month==11) && $day ==31){
					return "ERROR:Select Valid Date Of Birth";
				}
				elseif($month == 2){
					$isleap = ($year % 4 == 0 && ($year % 100 != 0 || $year % 400 == 0));
					if ($day> 29 || ($day ==29 && !$isleap)){
						return "ERROR:Select Valid Date Of Birth";
					}
				}elseif($this->getAge($m_dob) < 13) {
					return 'ERROR:age must be greater than or equal to 13 years';
				}
				else{
					$customer->setDob($m_dob);
				}
			} else{
				return 'ERROR:Date of Birth can not be blank';
			}
            $customer->save();
            $user_id = $customer->getId();
            //$returnVal = $this->getUserInfo($lastId);
        }else {
            $websiteId = Mage::app()->getWebsite()->getId();
            $customer->website_id = $websiteId; 
            $customer->store=0;
            $customer->username = $username;
            $customer->firstname = $first_name;
            $customer->lastname = $last_name;
            $customer->email = $email;
            $customer->sex = $gender;	
            $customer->shortbio = $about_me;
            $randomPassword = $customer->generatePassword();
            $customer->password = $randomPassword;
			$dob_arr = explode('/',$dob);
			$month = $dob_arr[0];
			$day = $dob_arr[1];
			$year = $dob_arr[2];
			if($month != '' && $year != '' && $day != '')
			{
				$m_dob=$month."/".$day."/".$year;	
				if(($month==4 || $month==6 || $month==9 || $month==11) && $day ==31){
					return "ERROR:Select Valid Date Of Birth";
				}
				elseif($month == 2){
					$isleap = ($year % 4 == 0 && ($year % 100 != 0 || $year % 400 == 0));
					if ($day> 29 || ($day ==29 && !$isleap)){
						return "ERROR:Select Valid Date Of Birth";
					}
				}elseif($this->getAge($m_dob) < 13) {
					return 'ERROR:age must be greater than or equal to 13 years';
				}
				else{
					$customer->setDob($m_dob);
				}
			} else{
				return 'ERROR:Date of Birth can not be blank';
			}
            $customer->save();
            $user_id = $customer->getId();
            //$returnVal = $this->getUserInfo($lastId);
        }
        if($user_id > 0) {
            $customers = Mage::getModel('customer/customer');
            $collection = $customers->getCollection()
                                    ->addAttributeToFilter('entity_id', (string)$user_id)
                                    ->setPageSize(1);
            $existingCustomer = $collection->getFirstItem();
            $session = Mage::getSingleton("customer/session");
            $session->setCustomer($existingCustomer);
            $session->setCustomerAsLoggedIn($existingCustomer);
			$m_dob=$this->getMDobByUserId($user_id);
			$m_email=$existingCustomer->getEmail();
        }
		$returnVal['userInfo'] = $this->getUserInfo(0);
		$returnVal['dob'] = $m_dob;
		$returnVal['userEmail'] = $m_email;
        return $returnVal;
    }
	public function checkTWTid($twtid){
      	$customer = Mage::getModel('customer/customer');
		$collection = $customer->getCollection()
					->addAttributeToFilter('twitter_id', (string)($twtid))
					->setPageSize(1); 
		$uidExist = (bool)$collection->count();
		if($uidExist) {
			foreach($collection as $user){
				return $user['entity_id'];
			}
		} else {
			return false;
		}	 
		/*
        $resource = Mage::getSingleton('core/resource');
        $read= $resource->getConnection('core_read');
        $widget_twt = $resource->getTableName('widget_user_log');
        $rs = $read->fetchRow("SELECT uid FROM $widget_twt WHERE uid='".$twtid."'");
        $uidExist = (bool)count($rs['uid']);
         if($uidExist) {
             return $rs['uid'];
         } else {
             return false;
         }*/
    }
	public function mritunjaytest($username){//1114003741
		$collection = Mage::getResourceModel('customer/customer_collection')
				->addAttributeToFilter('entity_id', $username)
				->addAttributeToSelect('twitter_id')
				->load()->toArray();
		return $collection;
	}
	public function isUserOnline($user_id){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$table = $resource->getTableName('user_activities');
		$sqlSelect = " Select profile_id, user_id, type_of, group_of, site, photo , created_on, status, id, webcam_on, mesg from $table where status > 0 AND user_id=".$user_id;
			$sqlSelect.=" and type_of = 'check-ins'";
			$now = date("Y-m-d H:i:s");
			$sqlSelect.=" and last_pinged_time >  DATE_ADD(now(), INTERVAL '-02:00' MINUTE_SECOND)";
			$activities = $read->fetchAll($sqlSelect);
			if(count($activities) >0)
				return true;
			else
				return false;
	}
	public function getEventDetailById($event_id){
			$event_id = intval($event_id);
			$theProduct = Mage::getModel('catalog/product')->load($event_id);
			$theProduct = $theProduct->toArray();
			return $theProduct;
	}
	public function getEventCategories(){
		$value =array(0=>'celeb',1=>'spirituality',2=>'business',3=>'schools',4=>'government',5=>'entertainment',6=>'family',7=>'howto',8=>'organizations',9=>'people',10=>'gaming',11=>'products',12=>'music',13=>'news',14=>'nonprofits',15=>'self',16=>'pets',17=>'technology',18=>'sports',19=>'travel');
		$category = Mage::getModel('catalog/category')->load(3);
		$children = $category->getChildren();
		$children = explode(",",$children);
		if (strlen($children[0]) > 0)
			{
			$result=array();
			$i=0;
			 foreach($children as $child)
				{
				 $_child = Mage::getModel('catalog/category')->load($child);
					 //echo $child."==>".$_child->getName()."<br>";
					 
					 $result[$i]['id']=$child;
					 $result[$i]['name']=$_child->getName();
					 $result[$i]['value']=$value[$i];
					 $i++;
				}
				return $result;
			}
			else
				return 0;
	}
	public function findPeopleByCategories($category, $page=1){
		$result =$this->getWhotofollow($user_id=0, $limit=10, $page, $category, $html=false);
		return $result;
	}
	public function findEventByCategories($categoryId, $user_id=0, $page=1){
		$result = $this->getEventsByCategory($categoryId, $user_id, $page, $limit=10);
		return $result;
	}
	public function getSuggestedPeople($user_id, $page=1) {
		
		$limit=10;
		$category='celeb';
		$collection = Mage::getResourceModel('customer/customer_collection')
			->addAttributeToSelect('*')
			->addAttributeToSelect('shortbio')
			->addFieldToFilter('is_suggested', array('gt'=> 0));
			//->addAttributeToFilter('profile_category', array('like' => trim($category).'%')); 
			
			$collection = $collection->setPageSize($limit)->setPage($page, $limit);
			$lastPage = $collection->getLastPageNumber();
			$collection = $collection->load()->toArray();
			if($lastPage > $page){
				$showMore = "true";
			} else {
				$showMore = "false";
			}
			foreach($collection as $k=>$user){ //echo "test-".$user['entity_id']; exit;
				$customer = Mage::getModel('customer/customer')->load($user['entity_id']);
				$follower = $this->isFollow($user['entity_id'], $user_id);
				$follwer = $this->getFollowersCount($user['entity_id']);
				$host_count = Mage::getModel('events/events')->getEventHostingCount($user['entity_id']);
				$shortbio=$customer->getShortbio();
				//return "knkj".$follower;
				if($follower!=1){
					$status = "Follow";
					$mesg = 1;					
				}
				else{ 
					$status = "Unfollow";
					$mesg = 2;
				}
				//if($follower!=1){
				$data[$k] = array(
						'id'=>$user['entity_id'],
						'name'=>$user["firstname"].' '.$user["lastname"],
						'username'=>$user["username"],
						'firstname'=>$user["firstname"],
						'lastname'=>$user["lastname"],
						'follower'=>$follwer,
						'status'=>$status,
						'events'=>$host_count,
						'shortbio'=>$shortbio,
						'profile_url'=> '/'.$user["username"],
						'image'=>$this->getProfilePic($user['entity_id']),
						'showMore'=>$showMore
					);
				//}
			}
			return $data;
	}
	public function RSVP($event_id, $user_id, $event_hosted_id) {
		$_product = Mage::getModel('catalog/product')->load($event_id);
		if($_product->getStatus() == 1){
		$product_qty = Mage::getModel('cataloginventory/stock_item')->loadByProduct($_product)->getQty();
		if($product_qty > 0){
			try{
			$maximum_of_attendees = $_product['maximum_of_attendees']-1;
			$stockData = $_product->getStockData();
			$stockData['qty'] = $product_qty-1;
			$stockData['is_in_stock'] = 1;
			$_product->setStockData($stockData);
			$_product->setMaximumOfAttendees($maximum_of_attendees);
			$_product->save();
			
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$write= $resource->getConnection('core_write');
			$price = 0;
			$event_attending = $resource->getTableName('event_attending');
			$sqlInsert1 = " Insert into $event_attending (event_id, user_id, price, created_on) values (".$event_id.",".$user_id.",".$price." , now());";
			$write->query($sqlInsert1);
			
			
			$rsvp = $resource->getTableName('rsvp');
			$sql = " Insert into $rsvp (user_id, event_id, status, created_at) values (".$user_id.",".$event_id.",1,now());";
			$write->query($sql);
			
			$catid =$event_hosted_id;
			$jobs_mail = $resource->getTableName('jobs_mail');
			$sqlInsert = " Insert into jobs_mail (user_id, type, schedule, fuction_name, message, created_on, status, cat_id, event_id) values (".$user_id.", 'RSVP', 0, 'sendEventRSVPMail', 'request', now(), 1, ".$catid.", ".$event_id.");";
			$write->query($sqlInsert);
			//================================================
			$newsfeed = $resource->getTableName('newsfeed');
			$write->query("insert into $newsfeed (user_id, profile_id, cat_id) values(".$user_id.", ".$event_id.",3)");
			//===================================================
			} catch (Exception $e) {
				return "There is some problem with RSVP.";
			}
		} else{
			return "Spot is full";
		}
		}else{
			return "Sorry, the event has been deleted by host";
		}
	}
	public function followPeople($user_id, $follow_id, $type)
    {            
            $resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$write = $resource->getConnection('core_write');
			$customer_id = $user_id;
			$follow = $follow_id;
				
			$follower = $resource->getTableName('follower');
			$result=array();
						
			if(strtolower($type)=='unfollow'){
				$write->query("update $follower set  status=0, notify=0 WHERE follower_id=".$customer_id." and follow=".$follow);
				$result['status'] = 'Follow';
			}else{ 
				if($this->isFollow($follow, $customer_id))
				$write->query("update $follower set status=1, notify=1, follow_on=now()  WHERE follower_id=".$customer_id." and follow=".$follow);
				else
				$write->query("insert into $follower (follower_id, follow, status, follow_on, notify) values(".$customer_id.", ".$follow." ,1, now(), 1)");
				
				$result['status'] = 'Unfollow';
				
				//sendMail
				$customer = Mage::getModel('customer/customer')->load($follow);	
				$notice = $customer->getNotice();
				$a = explode(",",$notice);	
				if(in_array(18,$a)){
					$this->sendMail($follow, $customer_id);	
				}
			}
			return $result;			
    }
	
	public function RSVPnotifyFB($user_id, $event_id){
		$theProduct = Mage::getModel('catalog/product')->load($event_id);
		$theProduct = $theProduct->toArray();
		$name = $theProduct['name'];
		$description = $theProduct['description'];
		$url_path = "https://www.oncam.com/".$theProduct['url_path'];
		
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		$news_from_date = date('D M d, Y h:i A', strtotime($theProduct['news_from_date'])+$timeoffset)." ".$abbrev;
		$image ="http://chattrspace.s3.amazonaws.com/events/135x110/".$theProduct['event_image'];
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$msg = $this->getUserNameByUserId($user_id). ' has registered a event on oncam.com';
		$fbAcsessToken = $resource->getTableName('fb_accessToken');
		$rsAccessToken = $read->fetchRow("SELECT * FROM $fbAcsessToken WHERE uid=".$user_id);
		$facebook_id=$rsAccessToken[fbid];
		$facebook_access_token=$rsAccessToken[access_token];
//$params = array('access_token'=>$facebook_access_token, 'message'=>$msg);

	$params = array('access_token'=>$facebook_access_token, 'message'=>$msg,'name'=>$name,'link' => $url_path,'picture' => $image,'description' => $news_from_date,'caption'=>'Oncam');
	
	$url = "https://graph.facebook.com/$facebook_id/feed";
		$ch = curl_init();
		curl_setopt_array($ch, array(
		CURLOPT_URL => $url,
		CURLOPT_POSTFIELDS => $params,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_VERBOSE => true
		));
		$result = curl_exec($ch);
		return "Facebook id=".$facebook_id." and facebook accesstoken=".$facebook_access_token;
	}
	public function isFollow($user_id, $id, $status=1) {
	
		$customer_id = $id;
		
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$follower = $resource->getTableName('follower');
		$customer_entity = $resource->getTableName('customer_entity');
		
		$select = "select id, follower_id, follow, status, follow_on, notify from $follower WHERE follow='".$user_id."' and follower_id='".$customer_id."' and follower_id<>follow and status=".$status;
		
		$follower = $read->fetchRow($select);				
		if(count($follower)){
			return $follower['status'];
		}else{
			return 0;
		}
	}
	public function getWhotofollow($user_id, $limit=10, $page=0, $category='celeb', $html=false) {
		$str='';$customer_id =0;
	
		$collection = Mage::getResourceModel('customer/customer_collection')
			->addAttributeToSelect('*')
			->addAttributeToSelect('shortbio')
			->addFieldToFilter('is_suggested', array('gt'=> 0))
			->addAttributeToFilter('profile_category', array('like' => trim($category).'%'));
		if($limit > 0)
			$collection = $collection->setPageSize($limit)->setPage($page, $limit);
			$lastPage = $collection->getLastPageNumber();			
			$collection = $collection->load()->toArray();
			
			if($lastPage > $page){
				$showMore = "true";
			} else {
				$showMore = "false";
			}
			foreach($collection as $k=>$user){ //echo "test-".$user['entity_id']; exit;
				$customer = Mage::getModel('customer/customer')->load($user['entity_id']);
				$follower = Mage::getModel('csservice/csservice')->isFollow($user['entity_id']);
				$follwer = $this->getFollowersCount($user['entity_id']);
				$host_count = Mage::getModel('events/events')->getEventHostingCount($user['entity_id']);
				$shortbio=$customer->getShortbio();
				if($follower!=1){
					$status = "Follow";
					$mesg = 1;					
				}
				else{ 
					$status = "Unfollow";
					$mesg = 2;
				}
				if(Mage::getSingleton('customer/session')->isLoggedIn()){
					$login=1;
				}
				else{
					$login=0;
				}
				$data[$k] = array(
						'id'=>$user['entity_id'],
						'username'=>$user['username'],
						'name'=>$user["firstname"].' '.$user["lastname"],
						'follower'=>$follwer,
						'status'=>$status,
						'login'=>$login,
						'mesg'=>$mesg,
						'events'=>$host_count,
						'shortbio'=>$shortbio,
						'views'=>Mage::getModel('csservice/csservice')->getCheckinCount($user['entity_id']),
						'url'=> '/'.Mage::getModel('csservice/csservice')->_profile_url.$user["username"],
						'image'=>$this->getProfilePic($user['entity_id']),
					); 
			
			}
			$data['showMore'] = $showMore;
			$data['total_count'] = Mage::getModel('csservice/csservice')->getWhotofollowCount($user_id, $category, $html=false);
			return $data;
	}
	public function getEventsByCategory($cat=3, $user_id=0, $more=1, $limit=10)
    {
		$session = Mage::getSingleton('customer/session');
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		$todayDate  = Mage::app()->getLocale()->date()->toString(Varien_Date::DATETIME_INTERNAL_FORMAT);	  
		$websiteId = Mage::app()->getWebsite()->getId();
		$storeId = Mage::app()->getStore()->getId();
		if($time_zone == 'America/Los_Angeles'){
			$now = strtotime('+1 hour')+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime('+1 hour'));
		} else{
			$now = strtotime(now())+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime(now()));
		}
		$visibility = array(	
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
              );

		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');

      		$events = Mage::getModel('catalog/category')->load($cat)
							->getProductCollection()
							->addAttributeToSelect('*')
							->addAttributeToSelect('category_id')
							->addAttributeToSelect('status')
							->addFieldToFilter('status', 1)
							->addAttributeToFilter('is_expired', array('neq' => 1))
							->addAttributeToSort('news_from_date', 'desc')
							->addAttributeToSort('position', 'desc')
							->addAttributeToFilter('news_to_date', array('gteq' => $date));	

					$events->addAttributeToFilter('visibility', $visibility)			
							->setPageSize($limit)
							->setPage($more, $limit)							
							->load()->toArray();	
			$lastPage = $events->getLastPageNumber();			
						
			if($lastPage > $more){
				$showMore = "true";
			} else {
				$showMore = "false";
			}
		
			if(count($events)>0){
			if($lastPage >= $more){
			foreach($events as $key => $event){
			
				$from = strtotime($event['news_from_date'])+$timeoffset;
				$to = strtotime($event['news_to_date'])+$timeoffset;
				if(($now > $from) && ($now < $to)) {
					$isLive="true";
				}else{
					$isLive="false";
				}
				if($this->isUserOnline($event['user_id'])){
					$HostIsLive = "true";
				}else{
					$HostIsLive = "false";
				}	
					if($event['event_image']=="''")
					$img_url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'catalog/product/placeholder/default/red-curtain_48x48.jpg';
					else{
						if($event['event_image'])
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];		
						else
							$img_url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'catalog/product/'.$event['small_image'];		
					}
					$customer = Mage::getModel('customer/customer')->load($event['user_id']);
					$data[$key] = array(
						'small_image'=>$img_url,
						'id'=>$key,
						'user_id'=>$event['user_id'],
						'url'=>$event["url_path"],
						'date'=>date('D M d, Y h:i A', strtotime($event['news_from_date'])+$timeoffset).' '.$abbrev,
						'from_date'=>date('D M d, Y h:i A', strtotime($event['news_from_date'])+$timeoffset).' '.$abbrev,
						'to_date'=>date('D M d, Y h:i A', strtotime($event['news_to_date'])+$timeoffset).' '.$abbrev,
						'from_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_from_date'])))),
						'to_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_to_date'])))),
						'from_date3'=>date('m-d-Y H:i:s', strtotime($event['news_from_date'])+$timeoffset),/*Added By surinder on demand of ajumal*/
						'to_date3'=>date('m-d-Y H:i:s',strtotime($event['news_to_date'])+$timeoffset),/*Added By surinder on demand of ajumal */
						'name'=>$customer->getFirstname().' '.$customer->getLastname(),
						'event_name'=>$event['name'],
						'category_name'=> $this->getCategoryNameByEventId($key),
						'isLive' => $isLive,
						'HostIsLive' => $HostIsLive,
						'hostUsername' => $this->getUserNameByUserId($event['user_id']),
					);
				}
			}
				$result  = array();
				//$result['count'] = Mage::getModel('csservice/list')->getCategoryEventsCount($cat);
				$result['data'] = $data;
				$result['showMore']=$showMore;
				return $result;
				
			}
			else
				return 0;
   }
   public function getTwitterId($user_id=0){
		$customer = Mage::getSingleton( 'customer/customer' )->load($user_id);
		$twitterId = $customer->getTwitterId();
        return $twitterId;
    }
	public function getWidgetIdOfUser($user_id){
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$widget = $resource->getTableName('widget_info');
			$select = "select * from $widget WHERE user_id=".$user_id." and is_default=1";
			$rs = $read->fetchRow($select);
			return $rs['widget_id'];
			
	}
	public function getvideoRecordPrivacy($user_id){
		if($user_id > 0){
			$customer = Mage::getSingleton( 'customer/customer' )->load($user_id);
			$privacy = $customer->getPrivacy();
			$a = explode(",",$privacy);
			//array_push($a, '166');
			if(in_array(166,$a)){
				return "true";
			} else {
				return "false";
			}
		}
	}
	public function videoRecordPrivacyOn($user_id){
	if($user_id > 0){
		$customer = Mage::getSingleton( 'customer/customer' )->load($user_id);
		$privacy = $customer->getPrivacy();
		$a = explode(",",$privacy);
		//array_push($a, '166');
		if(in_array(166,$a)){
		
		} else {
			$a[]=166;
			$str = implode(",",$a);
			$customer->setPrivacy($str);
			$customer->save();
		}
	}
	}
	public function videoRecordPrivacyOff($user_id){
	if($user_id > 0){
		$customer = Mage::getSingleton( 'customer/customer' )->load($user_id);
		$privacy = $customer->getPrivacy();
		$a = explode(",",$privacy);
		//array_push($a, '166');
		if(in_array(166,$a)){
			$key = array_search(166, $a);
			unset($a[$key]);
			$str = implode(",",$a);
			$customer->setPrivacy($str);
			$customer->save();
		} else {
			
		}
	}
	}
	public function getYTUsername($user_id){
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$YTusername = $customer->getYoutubename();
		return $YTusername;
	}
	public function getOnlineUser($page=1){
		$limit = 5;
		if($page<=0)
			$page=1;
		$page=$page-1;			
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$table = $resource->getTableName('user_activities');
		$sqlSelect = " Select profile_id, user_id, type_of, group_of, site, photo , created_on, status, id, webcam_on, mesg from $table where status > 0";
			$sqlSelect.=" and type_of = 'check-ins'";
			$now = date("Y-m-d H:i:s");
			$sqlSelect.=" and last_pinged_time >  DATE_ADD(now(), INTERVAL '-02:00' MINUTE_SECOND) group by user_id";
			if($limit!=0)
				$sqlSelect.= " limit ".$limit*$page.", ".$limit;
			$activities = $read->fetchAll($sqlSelect);
			//return $activities;
			if(count($activities) >0){
				foreach($activities as $k=>$act){
					if($act['user_id'] == $act['profile_id']){
						$isHost="true";
					
					$customer = Mage::getModel('customer/customer')->load($act['user_id']);
					$username = $customer->getUsername();	
					$data[$k] = array(
						'user_id'=>$act['user_id'],
						'profile_id'=>$act['profile_id'],
						'username'=>$username,
						'profile_image'=>$this->getProfilePic($act['user_id']),
						'isHost'=>$isHost,
						'photo'=>$act['photo'],
						'webcam_on'=>$act['webcam_on'],
						'mesg'=>$act['mesg'],
						'type_of'=>$act['type_of'],
						'numberOfLiveUsers'	=>	$this->getNumberOfUserOnline($act['user_id'])
					);
					}
				}
				return $data;
			}	
			else
				return 0;
	}
	public function videoRecordingFBpostOn($user_id){
		if($user_id > 0){
		$customer = Mage::getSingleton( 'customer/customer' )->load($user_id);
		$privacy = $customer->getSfacebook();
		$a = explode(",",$privacy);
		
		if(in_array(167,$a)){
		
		} else {
			$a[]=167;
			$str = implode(",",$a);
			$customer->setSfacebook($str);
			$customer->save();
		}
	}
	}
	public function videoRecordingFBpostOff($user_id){
	if($user_id > 0){
		$customer = Mage::getSingleton( 'customer/customer' )->load($user_id);
		$privacy = $customer->getSfacebook();
		$a = explode(",",$privacy);
		
		if(in_array(167,$a)){
			$key = array_search(167, $a);
			unset($a[$key]);
			$str = implode(",",$a);
			$customer->setSfacebook($str);
			$customer->save();
		} else {
			
		}
	}
	}
	public function videoRecordingTWTpostOn($user_id){
		if($user_id > 0){
		$customer = Mage::getSingleton( 'customer/customer' )->load($user_id);
		$privacy = $customer->getStwitter();
		$a = explode(",",$privacy);
		
		if(in_array(168,$a)){
		
		} else {
			$a[]=168;
			$str = implode(",",$a);
			$customer->setStwitter($str);
			$customer->save();
		}
	}
	}
	public function videoRecordingTWTpostOff($user_id){
	if($user_id > 0){
		$customer = Mage::getSingleton( 'customer/customer' )->load($user_id);
		$privacy = $customer->getStwitter();
		$a = explode(",",$privacy);
		
		if(in_array(168,$a)){
			$key = array_search(168, $a);
			unset($a[$key]);
			$str = implode(",",$a);
			$customer->setStwitter($str);
			$customer->save();
		} else {
			
		}
	}
	}
	public function getSettingsAndroid($user_id, $imei='abc'){
	if($user_id > 0){
		if($imei != "abc"){
		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$read= $resource->getConnection('core_read');
		$device = $resource->getTableName('mobile_device');
		$sqlInsert = "select imei from $device where imei='".$imei."' and device_id!='' and user_id='".$user_id."'";
		
		$data = $read->fetchAll($sqlInsert);
		if(count($data) > 0){
			$pushNoti="true";
		} else {
			$pushNoti="false";
		}
		}
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$sfacebook = $customer->getSfacebook();
		$c = explode(',',$sfacebook);
		if(in_array('167',$c,true)){
			$videoRecordingFBPost="true";
		} else{
			$videoRecordingFBPost="false";
		}
		
		if($customer->getYoutubeToken()){
			$youtubeLink="true";
		} else{
			$youtubeLink="false";
		}
		$privacy = $customer->getPrivacy();
		$a = explode(",",$privacy);
		if(in_array('166',$a,true)){
			$blockUser="true";
		} else {
			$blockUser="false";
		}
		
		if($customer->getTwitterId()){
			$twitterLink="true";
			$sTwitter = $customer->getStwitter();
			$t = explode(',',$sTwitter);
			if(in_array('168',$t,true)){
				$videoRecordingTWTPost="true";
			} else{
				$videoRecordingTWTPost="false";
			}
		} else {
			$twitterLink="false";
			$videoRecordingTWTPost="false";
		}
		$notices = $customer->getNotice();
		$a = explode(",",$notices);
		if(in_array(185,$a)){
			$when_people_follow_go_on = "true";
		}else{
			$when_people_follow_go_on = "false";
		}
		
		if(in_array(183,$a)){
			$when_my_contacts_create_events = "true";
		}else{
			$when_my_contacts_create_events = "false";
		}
		
		if(in_array(181,$a)){
			$when_my_contacts_call_me = "true";
		}else{
			$when_my_contacts_call_me = "false";
		}
		
		if(in_array(179,$a)){
			$when_my_contacts_text_message_me = "true";
		}else{
			$when_my_contacts_text_message_me = "false";
		}
		
		if(in_array(187,$a)){
			$when_my_contacts_dropin_on_me = "true";
		}else{
			$when_my_contacts_dropin_on_me = "false";
		}
		if(in_array(189,$a)){
			$when_people_follow_me = "true";
		}else{
			$when_people_follow_me = "false";
		}
		$result=array();
		if($customer->getYoutubeVideoStream() == 1){
			$result['videoRecordingYTPost'] = "true";
		} else {
			$result['videoRecordingYTPost'] = "false";	
		}
		$result['videoRecordingFBPost'] = $videoRecordingFBPost;
		$result['videoRecordingTWTPost'] = $videoRecordingTWTPost;
		$result['twitterLink'] = $twitterLink;
		$result['youtubeLink'] = $youtubeLink;
		$result['blockOtherUserTorecordInMyProfile'] = $blockUser;
		$result['pushNoti'] = $pushNoti;
		$result['when_people_follow_go_on'] = $when_people_follow_go_on;
		$result['when_my_contacts_create_events'] = $when_my_contacts_create_events;
		$result['when_my_contacts_call_me'] = $when_my_contacts_call_me;
		$result['when_my_contacts_text_message_me'] = $when_my_contacts_text_message_me;
		$result['when_my_contacts_dropin_on_me'] = $when_my_contacts_dropin_on_me;
		$result['when_people_follow_me'] = $when_people_follow_me;
		return $result;
	}
	else
		return 0;
	}
	public function getSettingsIphone($user_id, $device_id='abc'){
	if($user_id > 0){
		if($imei != "abc"){
		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$read= $resource->getConnection('core_read');
		$device = $resource->getTableName('mobile_device');
		$sqlInsert = "select device_id from $device where device_id='".$device_id."' and user_id='".$user_id."'";
		
		$data = $read->fetchAll($sqlInsert);
		if(count($data) > 0){
			$pushNoti="true";
		} else {
			$pushNoti="false";
		}
		}
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$sfacebook = $customer->getSfacebook();
		$c = explode(',',$sfacebook);
		if(in_array('167',$c,true)){
			$videoRecordingFBPost="true";
		} else{
			$videoRecordingFBPost="false";
		}
		
		if($customer->getYoutubeToken()){
			$youtubeLink="true";
		} else{
			$youtubeLink="false";
		}
		$privacy = $customer->getPrivacy();
		$a = explode(",",$privacy);
		if(in_array(166,$a)){ //,true
			$blockUser="true";
		} else {
			$blockUser="false";
		}
		
		if($customer->getTwitterId()){
			$twitterLink="true";
			$sTwitter = $customer->getStwitter();
			$t = explode(',',$sTwitter);
			if(in_array('168',$t,true)){
				$videoRecordingTWTPost="true";
			} else{
				$videoRecordingTWTPost="false";
			}
		} else {
			$twitterLink="false";
			$videoRecordingTWTPost="false";
		}
		$resource = Mage::getSingleton('core/resource');
        $read= $resource->getConnection('core_read');
		$widget_fb_reg = $resource->getTableName('widget_fb_reg');
		$rs = $read->fetchRow("SELECT * FROM $widget_fb_reg WHERE uid='".$user_id."'");
		if((count($rs[id])) && ($user_id > 0)){
			$linkFB="true";
		}else{
			$linkFB="false";
		}
		$notices = $customer->getNotice();
		$a = explode(",",$notices);
		if(in_array(185,$a)){
			$when_people_follow_go_on = "true";
		}else{
			$when_people_follow_go_on = "false";
		}
		
		if(in_array(183,$a)){
			$when_my_contacts_create_events = "true";
		}else{
			$when_my_contacts_create_events = "false";
		}
		
		if(in_array(181,$a)){
			$when_my_contacts_call_me = "true";
		}else{
			$when_my_contacts_call_me = "false";
		}
		
		if(in_array(179,$a)){
			$when_my_contacts_text_message_me = "true";
		}else{
			$when_my_contacts_text_message_me = "false";
		}
		
		if(in_array(187,$a)){
			$when_my_contacts_dropin_on_me = "true";
		}else{
			$when_my_contacts_dropin_on_me = "false";
		}
		if(in_array(189,$a)){
			$when_people_follow_me = "true";
		}else{
			$when_people_follow_me = "false";
		}
		$result=array();
		if($customer->getYoutubeVideoStream() == 1){
			$result['videoRecordingYTPost'] = "true";
		} else {
			$result['videoRecordingYTPost'] = "false";	
		}
		$result['videoRecordingFBPost'] = $videoRecordingFBPost;
		$result['videoRecordingTWTPost'] = $videoRecordingTWTPost;
		$result['twitterLink'] = $twitterLink;
		$result['fbLink'] = $linkFB;
		$result['youtubeLink'] = $youtubeLink;
		$result['blockOtherUserTorecordInMyProfile'] = $blockUser;
		$result['pushNoti'] = $pushNoti;
		//$result['timezone'] = Mage::getModel('core/locale')->getOptionTimezones();
		$result['when_people_follow_go_on'] = $when_people_follow_go_on;
		$result['when_my_contacts_create_events'] = $when_my_contacts_create_events;
		$result['when_my_contacts_call_me'] = $when_my_contacts_call_me;
		$result['when_my_contacts_text_message_me'] = $when_my_contacts_text_message_me;
		$result['when_my_contacts_dropin_on_me'] = $when_my_contacts_dropin_on_me;
		$result['when_people_follow_me'] = $when_people_follow_me;
		return $result;
	}
	else
		return 0;
	}
	public function getMobileToken($user_id=0, $phone=0,$device_id=0){
		$phone = mysql_real_escape_string($phone);
		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$read= $resource->getConnection('core_read');
		$token = $resource->getTableName('mobile_token');
		$select="select count(*) as count from $token where user_id='".$user_id."' and phone='".$phone."'";
		$tokendata = $read->fetchRow($select);
		$tokenkey = rand(11111,99999);
		if($tokendata['count'] > 0){
			$sqlInsert = "update $token set token='".$tokenkey."' where user_id='".$user_id."' and phone='".$phone."'";
		}
		else{
		$select1="select count(*) as count from $token where phone='".$phone."'";
		$tokendata1 = $read->fetchRow($select1);
		if($tokendata1['count'] > 0){
			return "Sorry, that number is already in use by a different person.";
		}
		
		$sqlInsert = " Insert into $token(user_id, token, phone, device_id, created_at) values (".$user_id.", '".$tokenkey."', '".$phone."', '".$device_id."', now())";
		}
		$write->query($sqlInsert);
		//=================================================================================
		$user = "oncam1";
		$password = "bfBBEAcfBRGKHg";
		$api_id = "3423925";
		$baseurl ="http://api.clickatell.com";
	 
		$text = urlencode("your oncam verification code is ".$tokenkey.". close this message and enter the code into oncam to verify your number.");
		$to = ltrim($phone,"00");
		// auth call
		if(substr($to,0,1) == 1){
			$url ="http://54.225.224.11/smsus.php?destination=".$to."&message=".$text;
			$provider = "MM";
		} else{
			$url =$baseurl."/http/sendmsg?user=oncam1&password=bfBBEAcfBRGKHg&api_id=3423925&to=".$to."&text=".$text;
			$provider = "CT";
		}		
		// do sendmsg call
		$ret = file($url);
		$send = explode(":",$ret[0]);
		
		$sqllog="insert into cs_sms_callback(sms_provider, msg_id, type)values('".$provider."', '".$send[1]."',4)";
		$write->query($sqllog);
		
		if ($send[0] == "ID") {
			return $tokenkey."-successnmessage ID: ". $send[1];
		} else {
			return "send message failed";
		}
		//=================================================================================
	}
	public function getMobileTokenAndroid($user_id=0, $phone=0,$device_id=0,$imei=0,$sim=0){
		$phone = mysql_real_escape_string($phone);
		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$read= $resource->getConnection('core_read');
		$token = $resource->getTableName('mobile_token');
		$select="select * from $token where user_id='".$user_id."' and phone='".$phone."'";
		$tokendata = $read->fetchAll($select);
		$tokenkey = rand(11111,99999);
		if(count($tokendata) > 0){
			$sqlInsert = "update $token set token='".$tokenkey."' where user_id='".$user_id."' and phone='".$phone."'";
		}
		else{
		$select1="select count(*) as count from $token where phone='".$phone."'";
		$tokendata1 = $read->fetchRow($select1);
		if($tokendata1['count'] > 0){
			return "Sorry, that number is already in use by a different person.";
		}
		$sqlInsert = " Insert into $token(user_id, token, phone, device_id, imei, sim, created_at) values (".$user_id.", '".$tokenkey."', '".$phone."', '".$device_id."', '".$imei."', '".$sim."', now())";
		}
		$write->query($sqlInsert);
		//=================================================================================
		$user = "oncam1";
		$password = "bfBBEAcfBRGKHg";
		$api_id = "3423925";
		$baseurl ="http://api.clickatell.com";
	 
		$text = urlencode("your oncam verification code is ".$tokenkey.". close this message and enter the code into oncam to verify your number.");
		$to = ltrim($phone,"00");
		// auth call
		if(substr($to,0,1) == 1){
			$url ="http://54.225.224.11/smsus.php?destination=".$to."&message=".$text;
			$provider = "MM";
		} else{
			$url =$baseurl."/http/sendmsg?user=oncam1&password=bfBBEAcfBRGKHg&api_id=3423925&to=".$to."&text=".$text;
			$provider = "CT";
		}
		// do sendmsg call
		$ret = file($url);
		$send = explode(":",$ret[0]);
		
		$sqllog="insert into cs_sms_callback(sms_provider, msg_id, type)values('".$provider."', '".$send[1]."',4)";
		$write->query($sqllog);
		
		if ($send[0] == "ID") {
			return "successnmessage ID: ". $send[1];
		} else {
			return "send message failed";
		}
		//=================================================================================
	}
	public function MobileVerify($user_id=0, $tokenkey=0){
		$tokenkey = mysql_real_escape_string($tokenkey);
		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$read= $resource->getConnection('core_read');
		$token = $resource->getTableName('mobile_token');
		$select="select phone from $token where user_id='".$user_id."' and token='".$tokenkey."'";
		$tokendata = $read->fetchAll($select);
		//return $tokendata;
		if(count($tokendata) > 0){
			$update="update $token set active=1 where user_id='".$user_id."' and token='".$tokenkey."'";
			$write->query($update);
			return "Successfully Registered.";
		}
		else{
			return "Error: Invalid Token.";
		}
		
	}
	public function getMobileAndDeviceId($user_id=0, $device_id=0, $imei=0, $type=0){
		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$read= $resource->getConnection('core_read');
		$token = $resource->getTableName('mobile_token');
		if($type='iPhone'){
			$select="select phone,device_id,imei from $token where user_id='".$user_id."'";
		} else {
			$select="select phone,device_id,imei from $token where user_id='".$user_id."' and imei='".$imei."'";
		}
		$tokendata = $read->fetchAll($select);
		if(count($tokendata) > 0){
			return "true";
		} else{
			return "false";
		}
	}
	public function setContacts($user_id=0,$device_id="deviceid",$myNumber=0,$data=null){
		if (isset($_POST["data"]) && isset($_POST["user_id"]) && $_POST["user_id"]>0){
			$data = $_POST["data"];
			$user_id=$_POST["user_id"];
			$device_id=$_POST["device_id"];
			$myNumber=$_POST["myNumber"];
			//return $data;
			$customer = Mage::getModel('customer/customer')->load($user_id);
			$fname=$customer->getFirstname();
			$lname=$customer->getLastname();
			$e_mail=$customer->getEmail();
			//$data='[   {     "contact3" : "533",     "contact_id" : "212",     "contact4" : " ",     "email1" : "g@s.a",     "email2" : "gghu",     "firstname" : "Aju",     "lastname" : " ",     "email" : "g@j.b",     "contact2" : "8566",     "contact1" : "9566666960"   },   {     "contact3" : " ",     "contact_id" : "213",     "contact4" : " ",     "email1" : " ",     "email2" : " ",     "firstname" : "Aju1",     "lastname" : "Gg",     "email" : "gvj",     "contact2" : " ",     "contact1" : "55"   },   {     "contact3" : " ",     "contact_id" : "214",     "contact4" : " ",     "email1" : " ",     "email2" : " ",     "firstname" : "Spl",     "lastname" : " ",     "email" : " ",     "contact2" : " ",     "contact1" : "222"   },   {     "contact3" : " ",     "contact_id" : "215",     "contact4" : " ",     "email1" : " ",     "email2" : " ",     "firstname" : "Mritunjay",     "lastname" : "Kumar",     "email" : " ",     "contact2" : " ",     "contact1" : "9691588020"   } ]';
			$results=json_decode($data,true);
			//return $results;
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$write= $resource->getConnection('core_write');
			$contactsTab = $resource->getTableName('mobile_contacts');
			//==========================================================================
			$select5 = "select * from $contactsTab WHERE contact1='".$myNumber."' and verified_user_id > 0";
			$rs5 = $read->fetchAll($select5);
			if(count($rs5) > 0){
			
			} else {
			$sql5 = "Insert into $contactsTab (verified_user_id, owner_user_id, device_id,contact_id, firstname, lastname, email, email1, email2, contact1, contact2, contact3, contact4, created_at) values (".$user_id.",".$user_id.",'".$device_id."','".$contact_id."','".$fname."','".$lname."','".$e_mail."','".$email1."','".$email2."','".$myNumber."','".$contact2."','".$contact3."','".$contact4."',now())";
					
			$write->query($sql5);
			}
			//==========================================================================
			$i=0;
			$prefix = substr($myNumber,0,4);
			foreach($results as $c){
							
				$contact_id=mysql_real_escape_string($c['contact_id']);
				$firstname=mysql_real_escape_string($c['firstname']);
				$lastname=mysql_real_escape_string($c['lastname']);
				$email=mysql_real_escape_string($c['email']);
				$email1=mysql_real_escape_string($c['email1']);
				$email2=mysql_real_escape_string($c['email2']);
				$contact1=mysql_real_escape_string($c['contact1']);
				$contact2=mysql_real_escape_string($c['contact2']);
				$contact3=mysql_real_escape_string($c['contact3']);
				$contact4=mysql_real_escape_string($c['contact4']);
				//==============================================================================
				if(substr($contact1,0,1) == " "){
					$contact1 = "00".ltrim($contact1,' ');
				}elseif(substr($contact1,0,1) == "+"){
					$contact1 = "00".ltrim($contact1,'+');
				} elseif( (substr($contact1,0,2) != "00") && (substr($contact1,0,1) == "0") ){
					$contact1 = $prefix.ltrim($contact1,'0');
				} elseif( (substr($contact1,0,2) != "00") && (substr($contact1,0,1) != "0") ){
					$contact1 = $prefix.$contact1;
				}
				
				if(substr($contact2,0,1) == " "){
					$contact2 = "00".ltrim($contact2,' ');
				}elseif(substr($contact2,0,1) == "+"){
					$contact2 = "00".ltrim($contact2,'+');
				} elseif( (substr($contact2,0,2) != "00") && (substr($contact2,0,1) == "0") ){
					$contact2 = $prefix.ltrim($contact2,'0');
				} elseif( (substr($contact2,0,2) != "00") && (substr($contact2,0,1) != "0") ){
					$contact2 = $prefix.$contact2;
				}
				
				if(substr($contact3,0,1) == " "){
					$contact3 = "00".ltrim($contact3,' ');
				}elseif(substr($contact3,0,1) == "+"){
					$contact3 = "00".ltrim($contact3,'+');
				} elseif( (substr($contact3,0,2) != "00") && (substr($contact3,0,1) == "0") ){
					$contact3 = $prefix.ltrim($contact3,'0');
				} elseif( (substr($contact3,0,2) != "00") && (substr($contact3,0,1) != "0") ){
					$contact3 = $prefix.$contact3;
				}
				
				if(substr($contact4,0,1) == " "){
					$contact4 = "00".ltrim($contact4,' ');
				}elseif(substr($contact4,0,1) == "+"){
					$contact4 = "00".ltrim($contact4,'+');
				} elseif( (substr($contact4,0,2) != "00") && (substr($contact4,0,1) == "0") ){
					$contact4 = $prefix.ltrim($contact4,'0');
				} elseif( (substr($contact4,0,2) != "00") && (substr($contact4,0,1) != "0") ){
					$contact4 = $prefix.$contact4;
				}
				//==============================================================================
				$select = "select * from $contactsTab WHERE contact1='".$contact1."' and contact2='".$contact2."' and contact3='".$contact3."' and contact4='".$contact4."'";
				$rs = $read->fetchAll($select);
				if(count($rs) > 0){
				
				} else { 
					/*$contact[$i]['contact_id']=$c['contact_id'];
					$contact[$i]['firstname']=$c['firstname'];
					$contact[$i]['lastname']=$c['lastname'];
					$contact[$i]['email']=$c['email'];
					$contact[$i]['email1']=$c['email1'];
					$contact[$i]['email2']=$c['email2'];
					$contact[$i]['contact1']=$c['contact1'];
					$contact[$i]['contact2']=$c['contact2'];
					$contact[$i]['contact3']=$c['contact3'];
					$contact[$i]['contact4']=$c['contact4'];
					$i++;*/
					$user_id1=0;
					$sql = "Insert into $contactsTab (owner_user_id, device_id,contact_id, firstname, lastname, email, email1, email2, contact1, contact2, contact3, contact4, created_at) values (".$user_id.",'".$device_id."','".$contact_id."','".$firstname."','".$lastname."','".$email."','".$email1."','".$email2."','".$contact1."','".$contact2."','".$contact3."','".$contact4."',now())";
					
					$write->query($sql);
				}
			}
			//===========================================================================
			$oncam_select = "select * from $contactsTab WHERE owner_user_id=".$user_id;
			$oncam_contact = $read->fetchAll($oncam_select);
			foreach($oncam_contact as $oc){
				$value = $oc['contact1'];
				if(is_numeric($value)){
				if($value > 0){
				$select7 = "select * from $contactsTab WHERE ".$value." in (contact1,contact2,contact3,contact4) and owner_user_id!=".$user_id;
				//return $select7;
				$contact7 = $read->fetchAll($select7);
				if(count($contact7) > 0){
				//return $contact7;
				foreach($contact7 as $cs){
					$this->oncamFollowEachOther($user_id, $cs['owner_user_id']);
				}
				}
				}
				}
			}
			foreach($oncam_contact as $oc){
				$value = $oc['contact2'];
				if(is_numeric($value)){
				if($value > 0){
				$select7 = "select * from $contactsTab WHERE ".$value." in (contact1,contact2,contact3,contact4) and owner_user_id!=".$user_id;
				//return $select7;
				$contact7 = $read->fetchAll($select7);
				if(count($contact7) > 0){
				//return $contact7;
				foreach($contact7 as $cs){
					$this->oncamFollowEachOther($user_id, $cs['owner_user_id']);
				}
				}
				}
				}
			}
			foreach($oncam_contact as $oc){
				$value = $oc['contact3'];
				if(is_numeric($value)){
				if($value > 0){
				$select7 = "select * from $contactsTab WHERE ".$value." in (contact1,contact2,contact3,contact4) and owner_user_id!=".$user_id;
				//return $select7;
				$contact7 = $read->fetchAll($select7);
				if(count($contact7) > 0){
				//return $contact7;
				foreach($contact7 as $cs){
					$this->oncamFollowEachOther($user_id, $cs['owner_user_id']);
				}
				}
				}
				}
			}
			foreach($oncam_contact as $oc){
				$value = $oc['contact4'];
				if(is_numeric($value)){
				if($value > 0){
				$select7 = "select * from $contactsTab WHERE ".$value." in (contact1,contact2,contact3,contact4) and owner_user_id!=".$user_id;
				//return $select7;
				$contact7 = $read->fetchAll($select7);
				if(count($contact7) > 0){
				//return $contact7;
				foreach($contact7 as $cs){
					$this->oncamFollowEachOther($user_id, $cs['owner_user_id']);
				}
				}
				}
				}
			}
			
			$selectt = "select * from $contactsTab where owner_user_id=".$user_id." and verified_user_id=".$user_id;
			$mycontact = $read->fetchAll($selectt);
			$myselect7 = "select * from $contactsTab WHERE ".$mycontact[0]['contact1']." in (contact1,contact2,contact3,contact4) and verified_user_id==NULL";
			$mycontact7 = $read->fetchAll($myselect7);
			if(count($mycontact7) > 0){
			//return $contact7;
			foreach($mycontact7 as $cs){
				$this->oncamFollowEachOther($user_id, $cs['owner_user_id']);
			}
			}
			return "Successfully Followed";
			//===========================================================================
		} else {
			return "Use form post method";
		}
	}
	public function oncamFollowEachOther($user_id=0, $follower_id=0){	
		$isfollow = $this->isFollow($user_id,$follower_id);
		$isfollow1 = $this->isFollow($follower_id,$user_id);
		if(($isfollow == 1) && ($isfollow1 == 1)){
		
		} else{
			$a = $this->followUserById($user_id, $follower_id);
			$b = $this->followUserById($follower_id, $user_id);
			$type="contact";
			$customer1 = Mage::getSingleton( 'customer/customer' )->load($user_id);
			$txtMsg = $customer1->getFirstname()." ".$customer1->getLastname()." joined oncam";
			$shortMsg = $customer1->getFirstname()." joined oncam";
			$this->push_notification_iphone_contact($user_id,$txtMsg,0,0,0,$type,$shortMsg);
		}	
	}
	public function push_notification_iphone_contact($user_id,$txtMsg,$caller_id=0,$receiver_id=0,$call_id=0,$type="contact",$shortMsg=""){
		$notificationType="Online";
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$device = $resource->getTableName('mobile_device');
		$select = "select device_id from $device where notificationType='".$notificationType."' and type IN ('iPhone','iPad') and device_id!='' and active=1 and user_id=".$user_id;
		$deviceTokens = $read->fetchAll($select);
		$customer = Mage::getModel('customer/customer')->load($caller_id);	
		$username = $customer->getUsername();
		$pemFile='/var/websites/oncam_com/webroot/certs/aps_production.pem';
		require_once 'Autoload.php';

		// Instanciate a new ApnsPHP_Push object
		$push = new ApnsPHP_Push(
			ApnsPHP_Abstract::ENVIRONMENT_PRODUCTION,$pemFile);

		// Set the Root Certificate Autority to verify the Apple remote peer
		//$push->setRootCertificationAuthority('entrust_root_certification_authority.pem');

		// Increase write interval to 100ms (default value is 10ms).
		// This is an example value, the 10ms default value is OK in most cases.
		// To speed up the sending operations, use Zero as parameter but
		// some messages may be lost.
		 $push->setWriteInterval(100 * 1000);

		// Connect to the Apple Push Notification Service
		$push->connect();
		$i=0;
		for ($i = 0; $i < count($deviceTokens); $i++) {
			// Instantiate a new Message with a single recipient
			$message = new ApnsPHP_Message($deviceTokens[$i]["device_id"]);

			// Set a custom identifier. To get back this identifier use the getCustomIdentifier() method
			// over a ApnsPHP_Message object retrieved with the getErrors() message.
			$message->setCustomIdentifier("Message-Badge-$i");

			// Set badge icon to "3"
			$message->setBadge(4);
			$message->setText($txtMsg);
			$message->setCustomProperty('oncam', array('caller_id' => $caller_id, 
											  'username' => $username, 
											  'receiver_id' => $receiver_id,
											  'call_id' => $call_id,
											  'type' => $type,
											  'shortMsg'=>$shortMsg));
			// Add the message to the message queue
			$push->add($message);
			//$i++;
		}
		
		// Send all messages in the message queue
		@$push->send();

		// Disconnect from the Apple Push Notification Service
		$push->disconnect();

		// Examine the error message container
		//$aErrorQueue = $push->getErrors();
		//if (!empty($aErrorQueue)) {
		//	return $aErrorQueue;
		//}
	}
	/*public function getContactsAndroid($user_id=0,$device_id="deviceid"){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write= $resource->getConnection('core_write');
		$contactsTab = $resource->getTableName('mobile_contacts');
		$oncam_select = "select * from $contactsTab WHERE user_id > 0 and user_id !=".$user_id;
		$oncam_user = $read->fetchAll($oncam_select);
		
		$email_select = "select * from $contactsTab WHERE user_id !=".$user_id." and (email != '(null)' or email1 != '(null)' or email2 != '(null)') limit 0,10";
		$email_user = $read->fetchAll($email_select);
		
		$phone_select = "select * from $contactsTab WHERE user_id !=".$user_id." and (contact1 !='(null)' or contact2 !='(null)' or contact3 !='(null)' or contact4 !='(null)') limit 0,10";
		$phone_user = $read->fetchAll($phone_select);
		$result=array();
		$result['oncam']=$oncam_user;
		$result['email']=$email_user;
		$result['sms']=$phone_user;
		return $result;
	}*/
	public function mobileInviteByEmail($user_id=0, $email="", $content=""){
		require_once "/var/websites/oncam_com/webroot/Mail/Mail.php";
	if (isset($_POST["email"]) && isset($_POST["user_id"]) && $_POST["user_id"]>0){
			$email = $_POST["email"];
			$user_id=$_POST["user_id"];
			$results=json_decode($email,true);
		$username = $this->getUserNameByUserId($user_id);
		$to_name = $username;
		$sender_email = "mail@oncam.com";
		$sender_name = "Oncam";
		if($content == ""){
			$Body='Chat with <a href="https://www.oncam.com/'.$username.'">'.$username.' on Oncam</a>';
		} else{
			$body = $content;
		}
		//$Body='Chat with <a href="https://www.oncam.com/'.$username.'">'.$username.' on Oncam</a>';
		$subject = "Chat on Oncam";
		$host = "email-smtp.us-east-1.amazonaws.com";
		$username = "AKIAJNXR2G6OFLBCPX4A";
		$password = "AhiqsldhWcEq1Ze6bG1WMaP2pdfgrmM0+Xd/PW5z3GJs";
		foreach($results as $c){
			if($c != "(null)"){
			$headers = array ('MIME-Version' => '1.0',
        'Content-Type' => "text/html; charset=ISO-8859-1",'From' => $sender_email,'To' => $c,'Subject' => $subject);
			$smtp = Mail::factory('smtp',
		   array ('host' => $host,
			 'auth' => true,
			 'username' => $username,
			 'password' => $password));
		 
		 $mail = $smtp->send($c, $headers, $Body);
		 $error = array();
		 if (PEAR::isError($mail)) {
		    $error[] = $mail->getMessage();
		  } else {
		   $error[] = "Message successfully sent!";
		  }
		  }
		}
		return $error;
	} else {
			return "Use form post method";
		}
	}
	public function mobileInviteByContacts($user_id=0, $county_code=0, $contact="", $content=""){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		if (isset($_POST["contact"]) && isset($_POST["user_id"]) && $_POST["user_id"]>0){
			$contact = $_POST["contact"];
			$user_id=$_POST["user_id"];
			//return $contact;
			$username = $this->getUserNameByUserId($user_id);
			$results=json_decode($contact,true);
			$baseurl ="http://api.clickatell.com";
			//$text = urlencode('Chat with <a href="https://www.oncam.com/'.$username.'">'.$username.' on Oncam</a>');
			if($content == ""){
				$text = urlencode("Get the free Oncam app at http://oncam.com.  Then go on live with me!");
			} else{
				$text = $content;
			}
			//return $results;
			$prefix = "0091";
			foreach($results as $c){
				if(substr($c,0,1) == " "){
					$c = "00".ltrim($c,' ');
				}elseif(substr($c,0,1) == "+"){
					$c = "00".ltrim($c,'+');
				} elseif( (substr($c,0,2) != "00") && (substr($c,0,1) == "0") ){
					$c = $prefix.ltrim($c,'0');
				} elseif( (substr($c,0,2) != "00") && (substr($c,0,1) != "0") ){
					$c = $prefix.$c;
				}
				
				$to = ltrim($c,"00");
				if(substr($to,0,1) == 1){
					$selectSMS = "select * from cs_sms_stop where `to`=$to";
					$rs = $read->fetchRow($selectSMS);
					if($rs == 0){
						$url ="http://54.225.224.11/smsus.php?destination=".$to."&message=".$text;
					}else{
						return "STOP Active";
					}
					$provider = "MM";
				} else{
					$url =$baseurl."/http/sendmsg?user=oncam1&password=bfBBEAcfBRGKHg&api_id=3423925&to=".$to."&text=".$text;
					$provider = "CT";
				}
				// do sendmsg call
				$ret = file($url);
				$send = explode(":",$ret[0]);
				
				$sqllog="insert into cs_sms_callback(sms_provider, msg_id, type)values('".$provider."', '".$send[1]."',4)";
				$write->query($sqllog);
				if ($send[0] == "ID") {
					$log[] = "successnmessage ID: ". $send[1];
				} else {
					$log[] = "send message failed";
				}
			}
			return $log;
		} else {
			return "Use form post";
		}
	}
	public function mobileInviteByOncam($user_id=0, $oncam=""){
		if(isset($_POST["oncam"]) && isset($_POST["user_id"]) && $_POST["user_id"]>0){
			$oncam = $_POST["oncam"];
			$user_id=$_POST["user_id"];
			$results=json_decode($oncam,true);
			foreach($results as $c){
				$isfollow = $this->isFollow($user_id,$c);
				$isfollow1 = $this->isFollow($c,$user_id);
				if(($isfollow == 1) && ($isfollow1 == 1)){
				
				} else{
					$a = $this->followUserById($user_id, $c);
					$b = $this->followUserById($c, $user_id);
				}
			}
		}else {
			return "Use form post";
		}
	}
	public function getContacts1($data=null){
		if (isset($_POST["data"])){
			$data = $_POST["data"];
			$results=json_decode($data,true);
			//$contacts=array_map('removeQuots',$results);
			return $results[0]['email'];
		} else {
			return "Use form post method";
		}
	}
	public function getContactsJson(){
		$data=array(a=>1,b=>2);
		$results=json_encode($data);
		return $results;
	}
	public function jabberEntryNew(){/*
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$follower = $resource->getTableName('follower');
			$customer_entity = $resource->getTableName('customer_entity');
			
			$select = "select DISTINCT follower_id, follow from $follower, $customer_entity WHERE follower_id<>follow and status=1 and $customer_entity.entity_id=$follower.follower_id";
			//$select = "select * from view_follower";
			//$select.=" group by follower_id order by id desc";			
			$result = $read->fetchAll($select);
			//return $result;
			$con=mysql_connect("chattr-jabbr.cwliz6chxmwt.us-east-1.rds.amazonaws.com","oncam_jabb0vxnT","3VnNsgAeYT5j");
							if(!$con)
							{
							  return 'Could not connect: ' . mysql_error();
							}
							$db_selected = mysql_select_db('oncam_jabber_0vxnT', $con);
							if (!$db_selected)
							{
								return "Could not connect db : " . mysql_error();
							}
			
			
			if(count($result)>0){
				foreach($result as $k=>$rs){
					//$customer = Mage::getModel('customer/customer')->load($flwr['follower_id']);
					$folower = $rs['follower_id'];
					$folow = $rs['follow'];
					$select1 = "select DISTINCT follower_id, follow from $follower where follower_id=".$folow." and follow=".$folower." and status=1";
					$result1 = $read->fetchAll($select1);
					//return $result1; 
					if(count($result1)>0){
						foreach($result1 as $k1=>$rs1){
							$username = $this->getUserNameByUserId($rs1['follower_id']);
							$username1 = $this->getUserNameByUserId($rs1['follow']);
							
							
							$sql="INSERT IGNORE INTO rosterusers (username, jid, nick, subscription, ask, askmessage, server, subscribe, type, created_at) VALUES ('".$rs1['follower_id']."', '".$rs1['follow']."@chatweb.oncam.com', '".$username1."', 'B', 'N', '', 'N', '', 'item', CURRENT_TIMESTAMP)";
							$aaa=mysql_query($sql,$con);
							
							$sql1="INSERT IGNORE INTO rosterusers (username, jid, nick, subscription, ask, askmessage, server, subscribe, type, created_at) VALUES ('".$rs1['follow']."', '".$rs1['follower_id']."@chatweb.oncam.com', '".$username."', 'B', 'N', '', 'N', '', 'item', CURRENT_TIMESTAMP)";
							$bbb=mysql_query($sql1,$con);
							
							$item[] = array(
							'id'=> $rs1['id'],
							'username'=>$username,
							'username1'=>$username1,
							'follower_id'=> $rs1['follower_id'],
							'follow'	=> $rs1['follow'],
							'Error' => $err,
							); 
						}
					}
				}
			}
			return $item;*/
    }
    public function sendOrderMail($order_id){
        $order = new Mage_Sales_Model_Order();
        $incrementId = $order_id;//Mage::getSingleton('checkout/session')->getLastRealOrderId();
        $order->loadByIncrementId($incrementId);
        try{
            $order->sendToSellerNewOrderEmail('mritun.kumar@eworks.in','Saksham Yadav');
        } catch(Exception $e){
            return "error: ".$e;
        }
    }
    public function createEvent($user_id=0, $title=null,$from_date=null,$to_date=null,$data=null,$cat_id=18,$event_id=0) {
        if (isset($_POST["data"]) && ($_POST["data"] !="") && isset($_POST["user_id"]) && ($_POST["user_id"]>0)){
            $user_id = $_POST["user_id"];
            $title = strip_tags($_POST["title"]);
            $from_date = $_POST["from_date"];
            $to_date = $_POST["to_date"];
            $cat_id = $_POST["cat_id"];
            $event_id = $_POST["event_id"];
            if($title == ""){
                return "Error : Title is blank";
            }
            $description="N/A";
            $location="N/A";
            $price=0;
            $no_att=self::$init_max_attendees;
            if($description == "") $description = $title;
            //from 05/28/13 12:26
            //to 05/28/13 20:26
            $sku = ereg_replace('[^A-Za-z0-9.]', '-', date('m-d-y H:i:s'));
            $catId = '3,'.$cat_id;
            if($user_id){
                $customerId = $user_id;
                $sku = 'chattrspace-'. $user_id ."-" . $sku;
                $customer = Mage::getModel('customer/customer')->load($user_id);
                $time_zone = $customer->getTimezone();
                $timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
                $from_date1 = date('Y-m-d H:i:s', strtotime($from_date));
                $to_date1 = date('Y-m-d H:i:s', strtotime($to_date));
                if($to_date1 < $from_date1){
                    return "Error : End Date is less than Start Date";
                }
                if($from_date != ''){
                    $from_date = date('Y-m-d H:i:s', strtotime($from_date) - $timeoffset);
                }
                if($to_date!=''){
                    $to_date = date('Y-m-d H:i:s', strtotime($to_date) - $timeoffset);
                }
                $from_date_array = explode(" ", $from_date);
                $from_array = explode("-", $from_date_array[0]);
                $to_date_array = explode(" ", $to_date);
                $to_array = explode("-", $to_date_array[0]);

                $array_year = array(2020=>142,2019=>143, 2018=>144, 2017=>145, 2016=>146, 2015=>147
                ,2014=>148, 2013=>149, 2012=>150, 2011=>151);

                $array_day = array(01=>129, 02=>128, 03=>127, 04=>126, 05=>125, 06=>124
                ,07=>123, 08=>122, 09=>121, 10=>120
                ,11=>119, 12=>118, 13=>117, 14=>116
                ,15=>115, 16=>114, 17=>113, 18=>112
                ,19=>111, 20=>110, 21=>109, 22=>108
                ,23=>107, 24=>106, 25=>105, 26=>104
                ,27=>103, 28=>102, 29=>101, 30=>100
                ,31=>99);

                $array_month = array (01=>141, 02=>140, 03=>139, 04=>138, 05=>137, 06=>136
                , 07=>135, 08=>134, 09=>133, 10=>132, 11=>131
                , 12=>130);
                $is_weekend=153;
                $d = date('D', mktime(0,0,0,$from_array[1], $from_array[2], $from_array[0]));
                if($d=="Sat" || $d=="Sun")
                    $is_weekend = 152;
                Mage::app()->getLocale()->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_LONG);
                $storeId = Mage::app()->getStore()->getId();
                $filename = '';
                if($event_id > 0){
                    $magentoProductModel= Mage::getModel('catalog/product')->load($event_id);
                    $magentoProductModel->setStoreId($storeId);
                }else{
                    $magentoProductModel= Mage::getModel('catalog/product');
                    $magentoProductModel->setStoreId(0);
                }
                $magentoProductModel->setWebsiteIds(array(1));
                $magentoProductModel->setAttributeSetId(9);
                $magentoProductModel->setTypeId('simple');
                $magentoProductModel->setName($title);
                $magentoProductModel->setProductName($title);
                $magentoProductModel->setSku($sku);
                $magentoProductModel->setUserId($user_id);
                $magentoProductModel->setShortDescription($description);
                $magentoProductModel->setDescription($description);
                $magentoProductModel->setPrice($price);
                $magentoProductModel->setSpecialPrice($vol_price);
                $magentoProductModel->setSalesQty(100);
                $magentoProductModel->setWeight(0);
                $magentoProductModel->setIsExpired(155);
                $magentoProductModel->setLocation($location);
                $magentoProductModel->setVisibility(4);

			$magentoProductModel->setNewsFromDate($from_date);
			$magentoProductModel->setNewsToDate($to_date);
			
			$magentoProductModel->setToDay($to_array[2]);
			$magentoProductModel->setToMonth($to_array[1]);
			$magentoProductModel->setToYear($to_array[0]);
			
			$magentoProductModel->setFromDay($array_day[intval($from_array[2])]);
			$magentoProductModel->setFromMonth($array_month[intval($from_array[1])]);
			$magentoProductModel->setFromYear($array_year[intval($from_array[0])]);
			
			$magentoProductModel->setFromTime($from_date_array[1]);
			$magentoProductModel->setToTime($to_date_array[1]);
			
			$magentoProductModel->setIsWeekend($is_weekend);
			
			$magentoProductModel->setMaximumOfAttendees($no_att);
			
			$magentoProductModel->setStatus(1);
			$magentoProductModel->setTaxClassId('None');
			$magentoProductModel->setCategoryIds($catId);
			//==============================================================================
			$encodedData = str_replace(' ','+',$_POST["data"]);
			$decodedData = ""; 
			
			for($i=0, $len=strlen($encodedData); $i<$len; $i+=4){
				$decodedData = $decodedData . base64_decode( substr($encodedData, $i, 4) );
			}
			$im = imagecreatefromstring($decodedData);
		
			$fileName = strtoupper( $user_id . "-" . $this->getRandomString(8) );
		
			if (isset($im) && $im != false) {
				$image_path = $fileName . '_img.jpg';	
				$path = Mage::getBaseDir('media') . DS .  'event'. DS;
				$fullFilePath = $path . $image_path;
		
			if(file_exists($fullFilePath)){
				//unlink($fullFilePath);      
			}

			$result = imagepng($im, $fullFilePath);
			imagedestroy($im);
			/*
			$bucketName = 'chattrspace';
					$objectname = 'events/135x110/'.$image_path;
					$filename = Mage::getModel('uploadjob/amazonS3')
									->putImage( $bucketName, $fullFilePath, $objectname, 'public');
					
					$bucketName = 'chattrspace';
					$objectname = 'events/711x447/'.$image_path;
					$filename = Mage::getModel('uploadjob/amazonS3')
									->putImage( $bucketName, $fullFilePath, $objectname, 'public');
					//sleep(15);
					unlink($fullFilePath);*/
			}else {
				return 'Error in Image Uploading';
            }		
			//==============================================================================
			$magentoProductModel->setEventImage($image_path);
			//uploadEventImage($event_id,$data=null)
			$this->_addImages($magentoProductModel, $image_path, $user_id);
			$saved = $magentoProductModel->save();
			/* Event Mail Send */
			$lastId = $saved->getId();
			//send mail replace by cron job mail
			//$this->createCronJobsendMail($lastId, $user_id, $cat_id);
			//Magento Stock
			$this->_saveStock($lastId, $no_att);
			//================================================
			$resource = Mage::getSingleton('core/resource');
			$write= $resource->getConnection('core_write');
			$newsfeed = $resource->getTableName('newsfeed');
			$write->query("insert into $newsfeed (user_id, profile_id, cat_id) values(".$user_id.", ".$lastId.",5)");
			//===================================================
			return $lastId;
		}	 
		}else {
				return 'Use Form POST';
            }
	}

	//Save Bank Info to USAepay
	public function saveBankInfo( $user_id=0,$customer_name='', $account_number = null, $routing_number=null, $ach_number=null) 
	{
		
		if (isset($_POST["account_number"]) && isset($_POST["routing_number"]) && isset($_POST["user_id"]) && ($_POST["user_id"]>0)){
			$user_id = $_POST["user_id"];
			$customer_name =  $_POST["your_name"];
			$routing_number =  $_POST["routing_number"];
			$account_number =  $_POST["account_number"];
			$ach_number = $_POST["ach_number"];

			$payment = Mage::getModel('events/Usaepay');

			$usaepay->key = "ass385FA9eqUgajkRN8R0fHEiix9wPV2";
	        $usaepay->account = $account_number; // bank account_number
	        $usaepay->routing = $routing_number; // bank routing_number
	        $usaepay->ach = $ach_number; // bank routing_number
	        // $usaepay->exp = "0914";
	        // $usaepay->cvv2 = "999";
	        // $usaepay->amount = 100;
	        $usaepay->invoice = 1;

			$result = $usaepay->process();

			//return $result;
		}
		else {
				return 'Use Form POST';
            }
	}

	public function createEventAndroid($user_id=0, $title=null,$from_date=null,$to_date=null,$data=null,$youtube_url1=null,$youtube_url2=null,$cat_id=18,$event_id=0){
	if (isset($_POST["data"]) && ($_POST["data"] !="") && isset($_POST["user_id"]) && ($_POST["user_id"]>0)){
		$user_id = $_POST["user_id"];
		$title = strip_tags($_POST["title"]);
		$from_date = $_POST["from_date"];
		$to_date = $_POST["to_date"];
		$cat_id = $_POST["cat_id"];
		$event_id = $_POST["event_id"];
		$youtube_url1 = $_POST["youtube_url1"];
		$youtube_url2 = $_POST["youtube_url2"];
		if($title == ""){
			return "Error : Title is blank";
		}
	$description="N/A"; $location="N/A"; $price=0; $no_att=10000;
            $location="N/A";
            $price=0;
            $no_att=self::$init_max_attendees;
	if($description == "") $description = $title;
		//from 05/28/13 12:26
		//to 05/28/13 20:26
		$sku = ereg_replace('[^A-Za-z0-9.]', '-', date('m-d-y H:i:s'));
		$catId = '3,'.$cat_id;
		if($user_id){
			$customerId = $user_id;
			$sku = 'chattrspace-'. $user_id ."-" . $sku;	
			$customer = Mage::getModel('customer/customer')->load($user_id);
			$time_zone = $customer->getTimezone();
			$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
			$from_date1 = date('Y-m-d H:i:s', strtotime($from_date));
			$to_date1 = date('Y-m-d H:i:s', strtotime($to_date));
			if($to_date1 < $from_date1){
				return "Error : End Date is less than Start Date";
			}
			if($from_date != ''){
				$from_date = date('Y-m-d H:i:s', strtotime($from_date) - $timeoffset);
			}
			if($to_date!=''){
				$to_date = date('Y-m-d H:i:s', strtotime($to_date) - $timeoffset);
			}
			$from_date_array = explode(" ", $from_date);
			$from_array = explode("-", $from_date_array[0]);
			$to_date_array = explode(" ", $to_date);
			$to_array = explode("-", $to_date_array[0]);
				
			$array_year = array(2020=>142,2019=>143, 2018=>144, 2017=>145, 2016=>146, 2015=>147 
									,2014=>148, 2013=>149, 2012=>150, 2011=>151);
									
			$array_day = array(01=>129, 02=>128, 03=>127, 04=>126, 05=>125, 06=>124 
											,07=>123, 08=>122, 09=>121, 10=>120
											,11=>119, 12=>118, 13=>117, 14=>116
											,15=>115, 16=>114, 17=>113, 18=>112
											,19=>111, 20=>110, 21=>109, 22=>108
											,23=>107, 24=>106, 25=>105, 26=>104
											,27=>103, 28=>102, 29=>101, 30=>100
											,31=>99);
											
			$array_month = array (01=>141, 02=>140, 03=>139, 04=>138, 05=>137, 06=>136 
										, 07=>135, 08=>134, 09=>133, 10=>132, 11=>131
										, 12=>130);
			$is_weekend=153;
			$d = date('D', mktime(0,0,0,$from_array[1], $from_array[2], $from_array[0]));
			if($d=="Sat" || $d=="Sun")
				$is_weekend = 152; 
			Mage::app()->getLocale()->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_LONG);
			$storeId = Mage::app()->getStore()->getId();
			$filename = '';
			if($event_id > 0){
				$magentoProductModel= Mage::getModel('catalog/product')->load($event_id);
				$magentoProductModel->setStoreId($storeId);				
			}else{
				$magentoProductModel= Mage::getModel('catalog/product');
				$magentoProductModel->setStoreId(0);
			}
			$magentoProductModel->setWebsiteIds(array(1));
			$magentoProductModel->setAttributeSetId(9);
			$magentoProductModel->setTypeId('simple');
			$magentoProductModel->setName($title);
			$magentoProductModel->setProductName($title);
			$magentoProductModel->setSku($sku);
			$magentoProductModel->setUserId($user_id);
			$magentoProductModel->setShortDescription($description);
			$magentoProductModel->setDescription($description);
			$magentoProductModel->setPrice($price);				
			$magentoProductModel->setSpecialPrice($vol_price);				
			$magentoProductModel->setSalesQty(100);				
			$magentoProductModel->setWeight(0);
			$magentoProductModel->setIsExpired(155);
			$magentoProductModel->setLocation($location);
			$magentoProductModel->setVisibility(4);					
			$magentoProductModel->setVideoFilePath1($youtube_url1);					
			$magentoProductModel->setVideoFilePath1($youtube_url2);					
			//$magentoProductModel->          (4);					
			
			$magentoProductModel->setNewsFromDate($from_date);
			$magentoProductModel->setNewsToDate($to_date);
			
			$magentoProductModel->setToDay($to_array[2]);
			$magentoProductModel->setToMonth($to_array[1]);
			$magentoProductModel->setToYear($to_array[0]);
			
			$magentoProductModel->setFromDay($array_day[intval($from_array[2])]);
			$magentoProductModel->setFromMonth($array_month[intval($from_array[1])]);
			$magentoProductModel->setFromYear($array_year[intval($from_array[0])]);
			
			$magentoProductModel->setFromTime($from_date_array[1]);
			$magentoProductModel->setToTime($to_date_array[1]);
			
			$magentoProductModel->setIsWeekend($is_weekend);
			
			$magentoProductModel->setMaximumOfAttendees($no_att);
			
			$magentoProductModel->setStatus(1);
			$magentoProductModel->setTaxClassId('None');
			$magentoProductModel->setCategoryIds($catId);
			//==============================================================================
			$data = base64_decode($_POST["data"]);
			$im = imagecreatefromstring($data);
		
			$fileName = strtoupper( $user_id . "-" . $this->getRandomString(8) );
		
			if (isset($im) && $im != false) {
				$image_path = $fileName . '_img.jpg';	
				$path = Mage::getBaseDir('media') . DS .  'event'. DS;
				$fullFilePath = $path . $image_path;
		
			if(file_exists($fullFilePath)){
				//unlink($fullFilePath);      
			}

			$result = imagepng($im, $fullFilePath);
			imagedestroy($im);
			/*
			$bucketName = 'chattrspace';
					$objectname = 'events/135x110/'.$image_path;
					$filename = Mage::getModel('uploadjob/amazonS3')
									->putImage( $bucketName, $fullFilePath, $objectname, 'public');
					
					$bucketName = 'chattrspace';
					$objectname = 'events/711x447/'.$image_path;
					$filename = Mage::getModel('uploadjob/amazonS3')
									->putImage( $bucketName, $fullFilePath, $objectname, 'public');
					//sleep(15);
					unlink($fullFilePath);*/
			}else {
				return 'Error in Image Uploading';
            }		
			//==============================================================================
			$magentoProductModel->setEventImage($image_path);
			//uploadEventImage($event_id,$data=null)
			$this->_addImages($magentoProductModel, $image_path, $user_id);
			$saved = $magentoProductModel->save();
			/* Event Mail Send */
			$lastId = $saved->getId();
			//send mail replace by cron job mail
			//$this->createCronJobsendMail($lastId, $user_id, $cat_id);
			//Magento Stock
			$this->_saveStock($lastId, $no_att);
			//================================================
			$resource = Mage::getSingleton('core/resource');
			$write= $resource->getConnection('core_write');
			$newsfeed = $resource->getTableName('newsfeed');
			$write->query("insert into $newsfeed (user_id, profile_id, cat_id) values(".$user_id.", ".$lastId.",5)");
			//===================================================
			return $lastId;
		}	 
		}else {
				return 'Use Form POST';
            }
	}

    public function youtubevalidationAction($youtube_url){
        
        $youtube_url["you1"] = $_POST["youtube_url1"];
        $youtube_url["you2"] = $_POST["youtube_url2"];
        
        if (!empty($youtube_url)){                     
            $result = array (
                youtube1 => $this->isValidYoutubeURL($youtube_url["you1"]),
                youtube2 => $this->isValidYoutubeURL($youtube_url["you2"]),                
            );            
        }        
        return $result; 
    }
    

    public function isValidYoutubeURL($url) {
        
        // Let's check the host first
        $parse = parse_url($url);        
        $host = $parse['host'];
        if (!in_array($host, array('youtube.com', 'www.youtube.com'))) {
            return false;
        }

        $ch = curl_init();
        $oembedURL = 'www.youtube.com/oembed?url=' . urlencode($url).'&format=json';
        curl_setopt($ch, CURLOPT_URL, $oembedURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Silent CURL execution
        $output = curl_exec($ch);
        unset($output);

        $info = curl_getinfo($ch);
        curl_close($ch);
        
        if ($info['http_code'] !== 404)        
            return true;
        else                                   
            return false;
    }


	public function createCronJobsendMail($event_id, $uid, $catid)
    {			
	   try{
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write= $resource->getConnection('core_write');
		$jobs_mail = $resource->getTableName('jobs_mail');
		$sqlInsert = " Insert into jobs_mail (user_id, type, schedule, fuction_name, message, created_on, status, cat_id, event_id) values (".$uid.", 'event', 0, 'sendNewEventMail', 'request', now(), 1, ".$catid.", ".$event_id.");";
		$write->query($sqlInsert);
		} catch (Exception $e) {
				return $e->getMessage();					
		}
	}
	private function _saveStock($lastId, $no_att)
	{
			$stockItem = Mage::getModel('cataloginventory/stock_item');
		    $stockItem->loadByProduct($lastId);

		    if (!$stockItem->getId()) {
		        $stockItem->setProductId($lastId)->setStockId(1);
		    }
		    $stockItem->setData('is_in_stock', 1);
		    $savedStock = $stockItem->save();
		    $stockItem->load($savedStock->getId())->setQty($no_att)->save();
	}
	private function _addImages($objProduct, $filename,$user_id)
	{
		$mediDir = Mage::getBaseDir('media');
		$customerId = $user_id;
		$imagesdir = $mediDir . '/event/';

		if(!file_exists($imagesdir)){
			return false;
		}
		//save into s3
		$bucketName = 'chattrspace';
		$objectName = 'events/home_banner'.'/'.$filename;
		$imagePath = $imagesdir.$filename;
		$filenam1 = Mage::getModel('uploadjob/amazonS3')
						->putImage( $bucketName, $imagePath, $objectName, 'public');
		//end s3 
		
		foreach (new DirectoryIterator($imagesdir) as $fileInfo) {

    		if($fileInfo->isDot() || $fileInfo->isDir()) continue;

    		if($fileInfo->isFile() && $filename==$fileInfo->getFileName()){	

				$resizeImage400 = Mage::getModel('events/events')->resizeOriginalImage($filename, 711, 447, "711x447");
				$resizeImage400 = Mage::getModel('events/events')->resizeOriginalImage($filename, 400, 400, "400x400");	
				$resizeImage48 = Mage::getModel('events/events')->resizeOriginalImage($filename, 48, 48, "48x48");
				$resizeImage135= Mage::getModel('events/events')->resizeOriginalImage($filename, 135, 110, "135x110");
				$objProduct->addImageToMediaGallery($fileInfo->getPathname(), array('image','small_image','thumbnail'), false, false);	
    		}
		}
		//=================================================
		try{
			$notificationType="Online";
			$username = $this->getUserNameByUserId($user_id);
			$message=$username." has created a new event";
			$pushNoti = $this->mobile_push_notification_follower($user_id, $notificationType, $message);
		}catch (Exception $e){
		
		}
		//=================================================
	}
	public function setContactsAndroid($user_id=0,$imei="imei",$myNumber=0,$data=null){
		//return "Hi manoj";
		if (isset($_POST["data"]) && isset($_POST["user_id"]) && ($_POST["user_id"]>0)){
			$data = $_POST["data"];
			$user_id=$_POST["user_id"];
			$imei=$_POST["imei"];
			$myNumber=$_POST["myNumber"];
			//return $data;
			$customer = Mage::getModel('customer/customer')->load($user_id);
			$fname=$customer->getFirstname();
			$lname=$customer->getLastname();
			$e_mail=$customer->getEmail();
			//$data='[   {     "contact3" : "533",     "contact_id" : "212",     "contact4" : " ",     "email1" : "g@s.a",     "email2" : "gghu",     "firstname" : "Aju",     "lastname" : " ",     "email" : "g@j.b",     "contact2" : "8566",     "contact1" : "9566666960"   },   {     "contact3" : " ",     "contact_id" : "213",     "contact4" : " ",     "email1" : " ",     "email2" : " ",     "firstname" : "Aju1",     "lastname" : "Gg",     "email" : "gvj",     "contact2" : " ",     "contact1" : "55"   },   {     "contact3" : " ",     "contact_id" : "214",     "contact4" : " ",     "email1" : " ",     "email2" : " ",     "firstname" : "Spl",     "lastname" : " ",     "email" : " ",     "contact2" : " ",     "contact1" : "222"   },   {     "contact3" : " ",     "contact_id" : "215",     "contact4" : " ",     "email1" : " ",     "email2" : " ",     "firstname" : "Mritunjay",     "lastname" : "Kumar",     "email" : " ",     "contact2" : " ",     "contact1" : "9691588020"   } ]';
			//$data=utf8_encode($data);
			$results=json_decode($data,true);
			//return $results;
			try{
				$resource = Mage::getSingleton('core/resource');
				$read= $resource->getConnection('core_read');
				$write= $resource->getConnection('core_write');
				$contactsTab = $resource->getTableName('mobile_contacts');
				//==========================================================================
				$select5 = "select * from $contactsTab WHERE contact1='".$myNumber."' and verified_user_id > 0";
				$rs5 = $read->fetchAll($select5);
				if(count($rs5) > 0){
					return "Already imported";
				} 
				/*else {
					$sql5 = "Insert into $contactsTab (verified_user_id, owner_user_id, imei,contact_id, firstname, lastname, email, email1, email2, contact1, contact2, contact3, contact4, created_at) values (".$user_id.",".$user_id.",'".$imei."','".$contact_id."','".$fname."','".$lname."','".$e_mail."','".$email1."','".$email2."','".$myNumber."','".$contact2."','".$contact3."','".$contact4."',now())";
							
					$write->query($sql5);
				}*/
				
				//==========================================================================
				$i=0;
				$prefix = substr($myNumber,0,4);
				foreach($results as $c){
								
					$contact_id=mysql_real_escape_string($c['contact_id']);
					$firstname=mysql_real_escape_string($c['firstname']);
					$lastname=mysql_real_escape_string($c['lastname']);
					$email=mysql_real_escape_string($c['email']);
					$email1=mysql_real_escape_string($c['email1']);
					$email2=mysql_real_escape_string($c['email2']);
					$contact1=mysql_real_escape_string($c['contact1']);
					$contact2=mysql_real_escape_string($c['contact2']);
					$contact3=mysql_real_escape_string($c['contact3']);
					$contact4=mysql_real_escape_string($c['contact4']);
					//==============================================================================
					if(substr($contact1,0,1) == " "){
						$contact1 = "00".ltrim($contact1,' ');
					}elseif(substr($contact1,0,1) == "+"){
						$contact1 = "00".ltrim($contact1,'+');
					} elseif( (substr($contact1,0,2) != "00") && (substr($contact1,0,1) == "0") ){
						$contact1 = $prefix.ltrim($contact1,'0');
					} elseif( (substr($contact1,0,2) != "00") && (substr($contact1,0,1) != "0") ){
						$contact1 = $prefix.$contact1;
					}
					
					if(substr($contact2,0,1) == " "){
						$contact2 = "00".ltrim($contact2,' ');
					}elseif(substr($contact2,0,1) == "+"){
						$contact2 = "00".ltrim($contact2,'+');
					} elseif( (substr($contact2,0,2) != "00") && (substr($contact2,0,1) == "0") ){
						$contact2 = $prefix.ltrim($contact2,'0');
					} elseif( (substr($contact2,0,2) != "00") && (substr($contact2,0,1) != "0") ){
						$contact2 = $prefix.$contact2;
					}
					
					if(substr($contact3,0,1) == " "){
						$contact3 = "00".ltrim($contact3,' ');
					}elseif(substr($contact3,0,1) == "+"){
						$contact3 = "00".ltrim($contact3,'+');
					} elseif( (substr($contact3,0,2) != "00") && (substr($contact3,0,1) == "0") ){
						$contact3 = $prefix.ltrim($contact3,'0');
					} elseif( (substr($contact3,0,2) != "00") && (substr($contact3,0,1) != "0") ){
						$contact3 = $prefix.$contact3;
					}
					
					if(substr($contact4,0,1) == " "){
						$contact4 = "00".ltrim($contact4,' ');
					}elseif(substr($contact4,0,1) == "+"){
						$contact4 = "00".ltrim($contact4,'+');
					} elseif( (substr($contact4,0,2) != "00") && (substr($contact4,0,1) == "0") ){
						$contact4 = $prefix.ltrim($contact4,'0');
					} elseif( (substr($contact4,0,2) != "00") && (substr($contact4,0,1) != "0") ){
						$contact4 = $prefix.$contact4;
					}
					//==============================================================================
					$select = "select * from $contactsTab WHERE contact1='".$contact1."' and contact2='".$contact2."' and contact3='".$contact3."' and contact4='".$contact4."'";
					$rs = $read->fetchAll($select);
					if(count($rs) > 0){
					//return "if";
					} else { 
						$user_id1=0;
						$sql = "Insert into $contactsTab (owner_user_id, imei,contact_id, firstname, lastname, email, email1, email2, contact1, contact2, contact3, contact4, created_at) values (".$user_id.",'".$imei."','".$contact_id."','".$firstname."','".$lastname."','".$email."','".$email1."','".$email2."','".$contact1."','".$contact2."','".$contact3."','".$contact4."',now())";
						
						$write->query($sql);
						//return "else";
					}
				}
			}catch (Exception $e){
				return "Error".$e->getMessage();
			}
			return "Successfully Imported";
		} else {
			return "Use form post method";
		}
	}
	public function deleteEvent($event_id=0, $user_id=0){
		$result=array();
    	if($event_id > 0){
			$storeId = Mage::app()->getStore()->getId();
			Mage::getModel('catalog/product_status')->updateProductStatus($event_id, $storeId, Mage_Catalog_Model_Product_Status::STATUS_DISABLED);
			//Mage::getModel('csservice/mail')->sendEventCancellationMail($event_id, $user_id);
			return "Your event has been successfully deleted";
		} else{
			return "Unabled to delete this event!try again.";
		}
    }
	public function deleteVideosById($video_id, $user_id=0)     
    { 
        $resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$videoTable = $resource->getTableName('video');
		$select = 'update '.$videoTable.' set isdeleted = 1 where video_id = '.$video_id.' and user_id='.$user_id;
		try{
			$write->query($select);
			return "Successfully deleted";
		} catch(Exception $e){
			return "Error : not able to delete";
		}
    }
	public function getNumberOfUserOnline($user_id=0){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$table = $resource->getTableName('user_activities');
		$sqlSelect = " Select * from $table where status > 0 and profile_id=".$user_id;
			$sqlSelect.=" and type_of = 'check-ins'";
			$sqlSelect.=" and last_pinged_time >  DATE_ADD(now(), INTERVAL '-02:00' MINUTE_SECOND) group by user_id";
			$activities = $read->fetchAll($sqlSelect);
			//return $activities;
		$count = count($activities);
		if($count > 0){
			//$count = $count-1;
			return $count;
		} else{
			return 0;
		}
	}
	public function forgetPassword($email=""){
		require_once "/var/websites/oncam_com/webroot/Mail/Mail.php";
		$user_id = $this->checkEmail($email);
		if($user_id > 0) {
			$random = $this->getRandomString(7);
			$password = $random.rand(10,99);
			$customer = Mage::getModel('customer/customer')->load($user_id);
			$firstname = $customer->getFirstname();
			$customer->setPassword($password);
			$customer->save();
			$sender_email = "mail@oncam.com";
			$sender_name = "Oncam";
			$subject = "Oncam Password Reset Request";
			$Body = '<html>
<body style="background:#F6F6F6; font-family:"lucida grande",tahoma,verdana,arial,sans-serif;; font-size:12px; margin:0; padding:0;">
<div style="background:#F6F6F6; font-family:"lucida grande",tahoma,verdana,arial,sans-serif;; font-size:12px; margin:0; padding:0;">
<table cellspacing="0" cellpadding="0" border="0" height="100%" width="100%">   
		<tr>
            <td align="center" valign="top" style="padding:20px 0 20px 0">
                <table bgcolor="FFFFFF" cellspacing="0" cellpadding="10" border="0" width="680" style="border:1px solid #E0E0E0;">
				<tr>
					<td valign="top">
						<a href="https://www.oncam.com"><img src="http://www.oncam.com/skin/frontend/default/oncam/images/logo.gif" alt="Oncam"  border="0"/></a>
					</td>
				</tr>
				<tr>
					<td valign="top">
						<div style="float:left;">
							<table style="font-size:13px;border-spacing:0">
							  <tbody><tr>
								<td valign="top">
								  <div style="font-size:15px;font-weight:bold">Dear '.$firstname.',</div>
								</td>
							  </tr>
							  </tbody>
							</table>
						</div>
					</td>
				</tr>
				<tr>
                     <td valign="top">
						<div style="font-family:""lucida grande",tahoma,verdana,arial,sans-serif;;float:left;margin:0 8px 8px 0px;background:#eceff8;border:1px solid #eceff8;padding:5px">
							<table style="font-size:10px;border-spacing:0">
							  <tbody><tr>
								<td valign="top">
								  <div style="font-size:13px;font-weight:bold">As per your request, here is your new Oncam Password.</div>
								</td>
							  </tr>
							  </tbody>
							</table>
						</div>
						<div style="font-family:lucida grande,tahoma,verdana,arial,sans-serif;;margin-top:5px;font-size:14px;">
							   <p style="font-size:14px; font-weight:normal; line-height:20px; margin:0 0 -13px 0;"><strong>Password: '.$password.' </strong></p><br/>
							    <p style="font-size:13px; font-weight:normal; line-height:20px; margin:0 0 -13px 0;">For enhanced security, you should change your password after logging into <a href="https://www.oncam.com">Oncam</a>.</p><br/>
						</div>
					</td>
				</tr>
				<tr>
				   <td bgcolor="#EAEAEA" align="center" style="background:#EAEAEA; text-align:left;"><p style="font-size:12px; margin:0;">Thanks,</p><p style="font-size:12px; margin:0;">The Oncam Team</p></td>
				</tr>
				</table>
				<table  cellspacing="0" cellpadding="10" border="0" bgcolor="FFFFFF" width="680">
					<tbody><tr>
						<td>
						<p style="line-height:1.5em;font-size:13px;font-family:helvetica;color:rgb(204, 204, 204);margin-top:3px;margin-right:3px;margin-bottom:3px;margin-left:3px;padding-top:5px">The message was sent to <a href="mailto:'.$sender_email.'">'.$sender_email.'</a>. If you don&#39;t want to receive these emails from Oncam in the future or have your email address used for friend suggestions, you can <a href="https://www.oncam.com/social/account/notice">unsubscribe</a>. Oncam, Inc. Palo Alto, California, USA</p>
						</td>
					</tr></tbody>
				</table>
            </td>
        </tr>
    </table>	   
</body>
</html>';
			
			$host = "email-smtp.us-east-1.amazonaws.com";
			$username = "AKIAJNXR2G6OFLBCPX4A";
			$password = "AhiqsldhWcEq1Ze6bG1WMaP2pdfgrmM0+Xd/PW5z3GJs";
			$headers = array ('MIME-Version' => '1.0',
        'Content-Type' => "text/html; charset=ISO-8859-1",'From' => $sender_email,'To' => $email,'Subject' => $subject);
			$smtp = Mail::factory('smtp',
		   array ('host' => $host,
			 'auth' => true,
			 'username' => $username,
			 'password' => $password));
		 
		 $mail = $smtp->send($email, $headers, $Body);
		 
		 if (PEAR::isError($mail)) {
		    return $mail->getMessage();
		  } else {
			return "Message successfully sent!";
		  }
		}else{
			return 'ERROR:This email id not exists in database';
		}
		
	}
	public function setProfileShortBio($user_id=0, $short_bio=""){
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$customer->setShortbio($short_bio);
		try{
			$customer->save();
			return "Short bio successfully updated";
		}catch(Exception $e){
			return "Unsuccess";
		}
	}
	public function sendMail($id, $c_userid)
    {	require_once "/var/websites/oncam_com/webroot/Mail/Mail.php";		
	    //$post = $this->getRequest()->getPost();		
		if ($id){
				$customer = Mage::getModel('customer/customer')->load($id); 
				$to_email = $customer->getEmail();
				$to_name = 	$customer->getUsername();
				$webUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
				$skinUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN)."frontend/default/oncam/images/";
				
				$curren_customer = Mage::getModel('customer/customer')->load($c_userid) ;
				$name = $curren_customer->getUsername();
				$c_mail = 	$curren_customer->getEmail();
				
				$cid=$curren_customer->getId();
				
				$follwer = Mage::getModel('csservice/csservice');
                $countfollowers =$follwer->getFollowersCount($cid);
				$recordingCount = $follwer->getRecordingCount($cid);
				$checkins = $follwer->getCheckinCount($cid);
				
				$profilePicture = $curren_customer->getProfilePicture();
				$filename = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'chattrspace/'.$profilePicture;
				$ext = end(explode('.', basename($filename)));
					
				if($ext=='jpg' || $ext=='JPG' || $ext=='png' || $ext=='gif' || $ext=='jpeg' || $ext== 'JPEG')
				{
					$filename = $filename;
				}
				else{
					$cust_id = (($cid)%10).".jpg";
						$filename = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'chattrspace/default/'.$cust_id;
						
				}
				
				$subject = Mage::getStoreConfig('general/profile/smiley').$name.' is following you on Oncam!';
				
				$Body='<html>
<body style="background:#F6F6F6; font-family:"lucida grande",tahoma,verdana,arial,sans-serif;; font-size:12px; margin:0; padding:0;">
<div style="background:#F6F6F6; font-family:"lucida grande",tahoma,verdana,arial,sans-serif;; font-size:12px; margin:0; padding:0;">
<table cellspacing="0" cellpadding="0" border="0" height="100%" width="100%">
        
		<tr>
            <td align="center" valign="top" style="padding:20px 0 20px 0">
               
                <table bgcolor="FFFFFF" cellspacing="0" cellpadding="10" border="0" width="680" style="border:1px solid #E0E0E0;">
                
				<tr>
					<td valign="top">
						<a href="'.$webUrl.'"><img src="'.$skinUrl.'logo1.png" alt="Oncam"  border="0"/></a>
					</td>
				</tr>
				<tr>
                        <td valign="top">
<h2 style="font-family:""lucida grande",tahoma,verdana,arial,sans-serif;;margin:0 0 16px;font-size:18px;font-weight:normal">'.$name.' is following your profile on Oncam.
</h2>

		<div style="font-family:""lucida grande",tahoma,verdana,arial,sans-serif;;float:left;margin:0 8px 8px 0px;background:#eceff8;border:1px solid #eceff8;padding:5px">
							<table style="font-size:10px;border-spacing:0">
							  <tbody><tr>
								
								<td valign="top">
								  <div style="font-size:13px;font-weight:bold">'.$name.'</div>
								</td>
							  </tr>
							  </tbody>
							</table>
						  <table style="margin:10px 0 3px 3px;font-size:10px;border-spacing:0">
							<tbody>
							<tr>
								<td style="padding:0 15px;border-left:1px solid #CCC;font-size:13px;font-weight:bold;color:#333;border:0;padding-left:0">'.$checkins.'</td>
								<td style="padding:0 15px;border-left:1px solid #CCC;font-size:13px;font-weight:bold;color:#333">'.$countfollowers.'</td>
								<td style="padding:0 15px;border-left:1px solid #CCC;font-size:13px;font-weight:bold;color:#333">'.$recordingCount.'</td>
							</tr>
							<tr>
								<th style="padding:0 15px;border-left:1px solid #CCC;text-align:left;color:#888;border:0;padding-left:0">'.Mage::getStoreConfig('general/profile/smiley').'Check-ins</th>
								<th style="padding:0 15px;border-left:1px solid #CCC;text-align:left;color:#888">Followers</th>
							    <th style="padding:0 15px;border-left:1px solid #CCC;text-align:left;color:#888">'.Mage::getStoreConfig('general/profile/smiley').'Recordings</th>
							</tr>
							</tbody>
						  </table>

        </div><div style="font-family:"lucida grande",tahoma,verdana,arial,sans-serif;;margin-top:5px;font-size:14px;">
							   </br>
							   <p style="font-size:14px; font-weight:normal; line-height:20px; margin:0 0 -13px 0;">What is next?</p>
							    <ul style="padding-left:10px;list-style:none">
								  <li>
									<span style="float:left;width:10px;"></span>
									View '.Mage::getStoreConfig('general/profile/smiley').'<a href="'. Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).''.$name.'" target="_blank">'.$name.'&#39;s  space</a>.</li>
								  
								</ul>
								
								<hr></hr>
		</div>
		<p style="font-size:12px; line-height:16px; margin:0;">If you have any questions about your account or any other matter, please feel free to contact us at
							<a href="mailto:mail@oncam.com">mail@oncam.com</a>.</p>
							</td>
							 </tr>
                    <tr>
                        <td bgcolor="#EAEAEA" align="center" style="background:#EAEAEA; text-align:center;"><p style="font-size:12px; margin:0;">Thanks</p><center><p style="font-size:12px; margin:0;">The Oncam Team</p></center></td>
                    </tr>
					
					 </table>
					 <table  cellspacing="0" cellpadding="10" border="0" bgcolor="FFFFFF" width="680">
		<tbody><tr>
					<td>
					<p style="line-height:1.5em;font-size:13px;font-family:helvetica;color:rgb(204, 204, 204);margin-bottom:3px;padding-top:5px">The message was sent to <a href="'.$to_email.'">'.$to_email.'</a>. If you don\'t want to receive these emails from Oncam in the future or have your email address used for friend suggestions, you can <a href="'.$webUrl.'social/account/notice">unsubscribe</a>. Oncam, Inc. Palo Alto, California, USA</p>
					</td>
					</tr></tbody>
					 <table>
            </td>
        </tr>
    </table>
	   
</body>
</html>
';
			$sender_email = "mail@oncam.com";
			$host = "email-smtp.us-east-1.amazonaws.com";
			$username = "AKIAJNXR2G6OFLBCPX4A";
			$password = "AhiqsldhWcEq1Ze6bG1WMaP2pdfgrmM0+Xd/PW5z3GJs";
			$headers = array ('MIME-Version' => '1.0',
        'Content-Type' => "text/html; charset=ISO-8859-1",'From' => $sender_email,'To' => $to_email,'Subject' => $subject);
			$smtp = Mail::factory('smtp',
		   array ('host' => $host,
			 'auth' => true,
			 'username' => $username,
			 'password' => $password));
		 
		 $mail = $smtp->send($to_email, $headers, $Body);
		 if (PEAR::isError($mail)){
		    $msg = false;
		 }else{
			$msg = true;
		  }
		return;
		}
	}
	public function abctest($text="<script>"){
		return htmlspecialchars($text);
	}
	public function allowUserToAccessMyProfileIfFollowing($user_id){
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$privacy = $customer->getPrivacy();
		$a = explode(",",$privacy);
		if(in_array(23,$a)){ //,true
			return "true";
		} else {
			return "false";
		}
	}
	public function isAllowedToAccessHostProfile($host_id,$user_id){
		$allowed = array();
		$host = Mage::getModel('customer/customer')->load($host_id);
		$privacy = $host->getPrivacy();
		$a = explode(",",$privacy);
		if(in_array(23,$a)){
			if($this->isfollowing($host_id, $user_id, 1)){
				$allowed['allowed'] = "true";
			}else{
				$allowed['allowed'] = "false";
			}
		}else{
			$allowed['allowed'] = "true";
		}
		return $allowed;
	}
	public function jabberAuth($user_id){
		$customer = Mage::getModel('customer/customer')->load($user_id);	
		$username = $customer->getUsername();
		//====================================
		$chars = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789";
		mt_srand(10000000*(double)microtime());
		for ($i = 0, $str = '', $lc = strlen($chars)-1; $i < 15; $i++) {
			$str .= $chars[mt_rand(0, $lc)];
		}
		//====================================
		$time=time();
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write= $resource->getConnection('core_write');
		$jabber = $resource->getTableName('jabber_auth');
		$sql = "Insert into $jabber (user_id, username,password,created) values (".$user_id.",'".$username."','".$str."',".$time.")";
		$write->query($sql);
		return $str;
	}
	public function getAllTimezone(){
		$timezones = Mage::getModel('core/locale')->getOptionTimezones();
		return $timezones;
	}
	public function validateTopEventFeed($user_id=0,$pageNo=1) {
		if($user_id > 0){
		$cat=3;$page=1;
		$limit=$pageNo*5;
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		if($time_zone == 'America/Los_Angeles'){
			$now = strtotime('+1 hour')+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime('+1 hour'));
		} else{
			$now = strtotime(now())+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime(now()));
		}
			
		$websiteId = Mage::app()->getWebsite()->getId();
		$storeId = Mage::app()->getStore()->getId();
		
		$visibility = array(	
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
              );

      		$events = Mage::getModel('catalog/category')->load($cat)
							->getProductCollection()
							->addAttributeToSelect('*')
							->addAttributeToSelect('category_id')
							->addAttributeToSelect('status')
							->addFieldToFilter('status', 1)
							->addAttributeToFilter('is_expired', array('neq' => 1))
							->addAttributeToSort('news_from_date', 'asc')
							//->addAttributeToSort('position', 'desc')
							->addAttributeToFilter('news_to_date', array('gteq' => $date));	
			
			$events->addAttributeToFilter('visibility', $visibility)				
							->setPageSize($limit)
							->setPage($page, $limit)							
							->load()->toArray();
			$lastPage = $events->getLastPageNumber();
			if($lastPage > $page){
				$showMore = "true";
			} else {
				$showMore = "false";
			}
			
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$follower = $resource->getTableName('follower');
			$select = "select follow from $follower WHERE follower_id=".$user_id." and follower_id<>follow and status=1";						
			$followers = $read->fetchAll($select);

			$resultArray = '';
			$str='';
			$c=0;
			foreach($followers as $follower){	$c = $c+1;	
					$str[$c]=$follower['follow'];
					$c = $c+1;
			}
		
			$counter=0;
			if(count($events)>0){ 
			if($lastPage >= $page){
				foreach ($events as $k => $event) { $counter++;
				//===================Start Image=====================================
					$customer = Mage::getModel('customer/customer')->load($event['user_id']);
					$fc = strtolower(substr($customer->getFirstname(),0,1));
					if($fc == ''){ $fc = rand(1,10); }

					if($event['event_image']=="''")
					$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
					else{
						if($event['event_image']){
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];
							
							if(fopen($img_url,"r")==false)
								$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
							else
								$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];
						}
						else
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];		
					}
				//===================End Image=====================================
				$rsvp = $resource->getTableName('rsvp');
					$selectSql = "select * from $rsvp WHERE user_id=".$user_id." and event_id=".$event['entity_id']." and status=1";			
					$row = $read->fetchAll($selectSql);
					if(count($row)>0)
						$rsvpStatus=1;
					else
						$rsvpStatus=0;
						
					$from = strtotime($event['news_from_date'])+$timeoffset;
					$to = strtotime($event['news_to_date'])+$timeoffset;
					if (($now > $from) && ($now < $to)) {
						$isLive="true";
					}
					else{
						$isLive="false";
					}
					if((in_array($event['user_id'],$str)) && ($isLive == "true")){
						$myFollowersEvent = "true";
					}
					else {
						$myFollowersEvent = "false";
					}
				if($myFollowersEvent == "true"){
					if($this->isUserOnline($event['user_id'])){
						$HostIsLive = "true";
					}else{
						$HostIsLive = "false";
					}
					$result[$counter]['followersEvent'] = array(
							'id'=> $event['entity_id'],
							'name'=> $event['name'],
							'price'=> number_format($event['price'],2),
							'user_id'=> $event['user_id'],
							'event_hostedby_username'=> $this->getUserNameByUserId($event['user_id']),
							'description'=> $event['description'],
							'from_date'=> date('D M d, Y h:i A', strtotime($event['news_from_date'])+$timeoffset)." ".$abbrev,
							'to_date'=> date('D M d, Y h:i A',strtotime($event['news_to_date'])+$timeoffset)." ".$abbrev,
							'from_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_from_date'])))),
							'to_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_to_date'])))),
							'image'			=> $img_url,						
							'islive'			=> $isLive,
							'myfollowersevent'			=> $myFollowersEvent,
							'rsvpStatus'	=>$rsvpStatus,
							'category'  => $this->getCategoryNameByEventId($event['entity_id']),
							'from_date1'=> date('m-d-Y H:i:s', strtotime($event['news_from_date'])+$timeoffset),
							'to_date1'=> date('m-d-Y H:i:s',strtotime($event['news_to_date'])+$timeoffset),
							'HostIsLive' => $HostIsLive,
						);			
					}
				}
				$counter1=0;
				foreach ($events as $k => $event) { $counter++;
				//===================Start Image=====================================
					$customer = Mage::getModel('customer/customer')->load($event['user_id']);
					$fc = strtolower(substr($customer->getFirstname(),0,1));
					if($fc == ''){ $fc = rand(1,10); }

					if($event['event_image']=="''")
					$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
					else{
						if($event['event_image']){
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];
							
							if(fopen($img_url,"r")==false)
								$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
							else
								$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];
						}
						else
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];		
					}
				//===================End Image=====================================
					$rsvp = $resource->getTableName('rsvp');
					$selectSql = "select * from $rsvp WHERE user_id=".$user_id." and event_id=".$event['entity_id']." and status=1";			
					$row = $read->fetchAll($selectSql);
					if(count($row)>0)
						$rsvpStatus=1;
					else
						$rsvpStatus=0;
					$from = strtotime($event['news_from_date'])+$timeoffset;
					$to = strtotime($event['news_to_date'])+$timeoffset;
					if (($now > $from) && ($now < $to)) {
						$isLive="true";
					}
					else{
						$isLive="false";
					}
					if((in_array($event['user_id'],$str)) && ($isLive == "true")){
						$myFollowersEvent = "true";
					}
					else {
						$myFollowersEvent = "false";
					}
				if(($isLive == "true") && !(in_array($event['user_id'],$str))){
					if($this->isUserOnline($event['user_id'])){
						$HostIsLive = "true";
					}else{
						$HostIsLive = "false";
					}
					$result[$counter]['live'] = array(
							'id'=> $event['entity_id'],
							'name'=> $event['name'],
							'price'=> number_format($event['price'],2),
							'user_id'=> $event['user_id'],
							'event_hostedby_username'=> $this->getUserNameByUserId($event['user_id']),
							'description'=> $event['description'],
							'from_date'=> date('D M d, Y h:i A', strtotime($event['news_from_date'])+$timeoffset)." ".$abbrev,
							'to_date'=> date('D M d, Y h:i A',strtotime($event['news_to_date'])+$timeoffset)." ".$abbrev,
							'from_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_from_date'])))),
							'to_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_to_date'])))),
							'image'			=> $img_url,						
							'islive'			=> $isLive,
							'myfollowersevent'			=> $myFollowersEvent,
							'rsvpStatus'	=>$rsvpStatus,
							'numberOfLiveUsers'	=>	$this->getNumberOfUserOnline($event['user_id']),
							'category'  => $this->getCategoryNameByEventId($event['entity_id']),
							'from_date1'=> date('m-d-Y H:i:s', strtotime($event['news_from_date'])+$timeoffset),
							'to_date1'=> date('m-d-Y H:i:s',strtotime($event['news_to_date'])+$timeoffset),
							'HostIsLive' => $HostIsLive,
						);			
					}
				}
				$counter2=0;
				
				foreach ($events as $k => $event) { $counter++;
				//===================Start Image=====================================
					$customer = Mage::getModel('customer/customer')->load($event['user_id']);
					$fc = strtolower(substr($customer->getFirstname(),0,1));
					if($fc == ''){ $fc = rand(1,10); }

					if($event['event_image']=="''")
					$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
					else{
						if($event['event_image']){
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];
							
							if(fopen($img_url,"r")==false)
								$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
							else
								$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];
						}
						else
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];		
					}
				//===================End Image=====================================
					$rsvp = $resource->getTableName('rsvp');
					$selectSql = "select * from $rsvp WHERE user_id=".$user_id." and event_id=".$event['entity_id']." and status=1";			
					$row = $read->fetchAll($selectSql);
					if(count($row)>0)
						$rsvpStatus=1;
					else
						$rsvpStatus=0;
					$from = strtotime($event['news_from_date'])+$timeoffset;
					$to = strtotime($event['news_to_date'])+$timeoffset;
					if (($now > $from) && ($now < $to)) {
						$isLive="true";
					}
					else{
						$isLive="false";
					}
					if((in_array($event['user_id'],$str)) && ($isLive == "true")){
						$myFollowersEvent = "true";
					}
					else {
						$myFollowersEvent = "false";
					}
				if($isLive == "false"){									
					$result[$counter]['upcoming'] = array(
							'id'=> $event['entity_id'],
							'name'=> $event['name'],
							'price'=> number_format($event['price'],2),
							'user_id'=> $event['user_id'],
							'event_hostedby_username'=> $this->getUserNameByUserId($event['user_id']),
							'description'=> $event['description'],
							'from_date'=> date('D M d, Y h:i A', strtotime($event['news_from_date'])+$timeoffset)." ".$abbrev,
							'to_date'=> date('D M d, Y h:i A',strtotime($event['news_to_date'])+$timeoffset)." ".$abbrev,
							'from_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_from_date'])))),
							'to_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_to_date'])))),
							'image'			=> $img_url,							
							'islive'			=> $isLive,
							'myfollowersevent'			=> $myFollowersEvent,
							'rsvpStatus'	=>$rsvpStatus,
							'category'  => $this->getCategoryNameByEventId($event['entity_id']),
							'from_date1'=> date('m-d-Y H:i:s', strtotime($event['news_from_date'])+$timeoffset),
							'to_date1'=> date('m-d-Y H:i:s',strtotime($event['news_to_date'])+$timeoffset),
						);			
					}
				}
				}
				$result['onlineUser']= $this->getOnlineUser($page);
				$result['followersEvent'] = $result['followersEvent'];
				$result['live'] = $result['live'];
				$result['upcoming'] = $result['upcoming'];
				$result['showMore'] = $showMore;
				return $result;
			}
			else{
				$result['onlineUser']= $this->getOnlineUser($page);
				return $result;
			}
				
		}
	
	}
	public function authenticateCall($user_id, $user_profile_id, $private_call_id=""){
		$username=$this->getUserNameByUserId($user_profile_id);
		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$read= $resource->getConnection('core_read');
		$widget = $resource->getTableName('widget_info');
		$sql = "select * from $widget where user_id = ".$user_profile_id." and widget_id='".$private_call_id."'";
		$result = $read->fetchAll($sql);
		if(count($result) > 0){
			return 1;
		}else{
			return 0;
		}
	}
	public function authenticateDropin($user_id, $user_profile_id, $dropin_call_id=""){
		$username=$this->getUserNameByUserId($user_profile_id);
		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$read= $resource->getConnection('core_read');
		$widget = $resource->getTableName('widget_info');
		$sql = "select * from $widget where user_id = ".$user_profile_id." and widget_id='".$dropin_call_id."'";
		$result = $read->fetchAll($sql);
		if(count($result) > 0){
			return 1;
		}else{
			return 0;
		}
	}
	public function startPrivateCall($user_id, $user_profile_id, $private_call_id="", $type="new"){
		$username=$this->getUserNameByUserId($user_profile_id);
		//$access_token=genrateToken();
		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$read= $resource->getConnection('core_read');
		$widget = $resource->getTableName('widget_info');
		//$device = $resource->getTableName('call_token');
		//$sql = "insert into $device (user_id,user_profile_id,token,created) values ('$user_id', '$user_profile_id','$access_token',NOW())";
		
		//$write->query($sql);
		if($type=='new'){
			$write->query("insert into $widget (username, created_on, user_id, invitee, call_status, is_private) values('".$username."', now(), ".$user_profile_id.", ".$user_id.",1,1)");
			$private_call_id = $thelastId = $write->lastInsertId();
			$widget_key = $this->uniqueKey($user_id.$private_call_id);
			$write->query("update $widget set widget_key='".$widget_key."' WHERE user_id='".$user_profile_id."' and widget_id=".$private_call_id);
			//echo "1";
			$modelURLRewrite = Mage::getModel('core/url_rewrite');
			$modelURLRewrite->setIdPath('csprofile/privatez'.$private_call_id)
				->setTargetPath('csprofile/index/view/username/'.$username)
				->setOptions('')
				->setDescription(null)
				->setRequestPath('privatez'.$private_call_id);
			$modelURLRewrite->save();
		}else{
			$unikey = $read->fetchRow("SELECT invitee from $widget WHERE user_id='".$user_profile_id."' and widget_id=".$private_call_id);
			if($unikey['invitee']!=""){
				$invitee=$unikey['invitee'].",".$user_id;
			}else{
				$invitee=$user_id;
			}
			$write->query("update $widget set invitee='".$invitee."' WHERE user_id='".$user_profile_id."' and widget_id=".$private_call_id);
		}
		$result = array();
		$result['private_call_id'] = $private_call_id;
		//=================================================
		try{
			$notificationType="Online";
			$customer1 = Mage::getModel('customer/customer')->load($user_profile_id);
			$message=$customer1->getFirstname()." ".$customer1->getLastname()." is calling you";
			$shortMsg = $customer1->getFirstname()." private call";
			$type = "missed_call";
			//$pushNoti = $this->mobile_push_notification($user_id, $notificationType, $message);
			$pushNoti = $this->mobile_push_notification_call($user_profile_id, $notificationType, $message,$user_profile_id,$user_id,$private_call_id,$type,$shortMsg);
		}catch (Exception $e){
		
		}
		//=================================================
		//================================================
			$newsfeed = $resource->getTableName('newsfeed');
			$write->query("insert into $newsfeed (user_id, profile_id, cat_id) values(".$user_profile_id.", ".$user_id.",1)");
			//===================================================
		return $result;
	}
	public function startPublicCall($user_profile_id){
		$customer = Mage::getSingleton( 'customer/customer' )->load($user_profile_id);
		$username = $customer->getUsername();

		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$read= $resource->getConnection('core_read');
		$widget = $resource->getTableName('widget_info');

		$write->query("insert into $widget (username, created_on, user_id, invitee, call_status, is_private) values('".$username."', now(), ".$user_profile_id.",'p',1,1)");
		$private_call_id = $thelastId = $write->lastInsertId();
		$widget_key = $this->uniqueKey($private_call_id);
		$write->query("update $widget set widget_key='".$widget_key."' WHERE user_id='".$user_profile_id."' and widget_id=".$private_call_id);
		$modelURLRewrite = Mage::getModel('core/url_rewrite');
		$modelURLRewrite->setIdPath('csprofile/privatez'.$private_call_id)
			->setTargetPath('csprofile/index/view/username/'.$username)
			->setOptions('')
			->setDescription(null)
			->setRequestPath('privatez'.$private_call_id);
		$modelURLRewrite->save();
		$result = array();
		$result['public_call_id'] = $private_call_id;
		//=================================================
		//================================================
		$newsfeed = $resource->getTableName('newsfeed');
		$write->query("insert into $newsfeed (user_id, profile_id, cat_id) values(".$user_profile_id.", ".$user_profile_id.",2)");
		//===================================================
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		$start_date = date('Y-m-d H:i:s',strtotime(now()));
		$end_date = date('Y-m-d H:i:s',strtotime('+30 minutes'));

		$events = Mage::getResourceModel('catalog/product_collection')
							->addAttributeToSelect('entity_id')
							->addAttributeToSelect('hashtag')
							 ->addFieldToFilter('user_id', array('eq'=> $user_profile_id))
							->addFieldToFilter('status', 1)
							->addAttributeToFilter('is_expired', array('neq' => 1))
							->addAttributeToFilter('news_from_date', array('gteq' => $start_date))
							->addAttributeToFilter('news_to_date', array('lteq' => $end_date))
							->addAttributeToSort('news_from_date', 'asc')
							->load()->toArray();
		if(count($events)>0){
			foreach ($events as $k => $event){
				$event_id = $event['entity_id'];
				$hashtag = $event['hashtag'];
			}
			$result['event_id'] = $event_id;
			$result['hashtag'] = $hashtag;
		}
		return $result;
	}
	public function removePrivateCall($user_id, $user_profile_id, $private_call_id, $type="false"){
		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$read= $resource->getConnection('core_read');
		$widget = $resource->getTableName('widget_info');
		$unikey = $read->fetchRow("SELECT invitee from $widget WHERE user_id='".$user_profile_id."' and widget_id='".$private_call_id."'");
		$return="remove";
		if($unikey['invitee']==$user_id){
			$write->query("update $widget set call_status=2 WHERE invitee='".$user_id."' and user_id='".$user_profile_id."' and widget_id=".$private_call_id);
			/*$device = $resource->getTableName('call_token');
			$sql = "DELETE FROM $device WHERE user_id='$user_id' AND user_profile_id='$user_profile_id';";
			$write->query($sql);
			//echo "1";*/
			$uldURLCollection = Mage::getModel('core/url_rewrite')->getResourceCollection();
			$uldURLCollection->getSelect()
				->where('id_path=?', 'csprofile/privatez'.$private_call_id);

			$uldURLCollection->setPageSize(1)->load();

			if ( $uldURLCollection->count() > 0 ) {
				$uldURLCollection->getFirstItem()->delete();
			}
			$return="Call End";
			//setcookie("callAccessToken",'');
		}elseif($user_id=='end'){
			$write->query("update $widget set call_status=2 WHERE user_id='".$user_profile_id."' and widget_id=".$private_call_id);

			$uldURLCollection = Mage::getModel('core/url_rewrite')->getResourceCollection();
			$uldURLCollection->getSelect()
				->where('id_path=?', 'csprofile/privatez'.$private_call_id);

			$uldURLCollection->setPageSize(1)->load();

			if ( $uldURLCollection->count() > 0 ) {
				$uldURLCollection->getFirstItem()->delete();
			}
			$return="Call End";
		}else{
			$users=explode(",",$unikey['invitee']);
			$invitees=array();
			foreach($users as $k=>$v){
				if($user_id!=$v){
					$invitees[]=$v;
				}
			}
			$invitee=implode(",",$invitees);
			$write->query("update $widget set invitee='".$invitee."' WHERE user_id='".$user_profile_id."' and widget_id=".$private_call_id);
		}
		if(isset($type) && $type=="false"){
			$result = array();
			$result['call_status'] = $return;
			return $result;
		}
	}
	public function removePublicCall($user_profile_id, $private_call_id){
		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$read= $resource->getConnection('core_read');
		$widget = $resource->getTableName('widget_info');
		$write->query("update $widget set call_status=2 WHERE invitee='p' and user_id='".$user_profile_id."' and widget_id=".$private_call_id);
		$uldURLCollection = Mage::getModel('core/url_rewrite')->getResourceCollection();
		$uldURLCollection->getSelect()
			->where('id_path=?', 'csprofile/privatez'.$private_call_id);

		$uldURLCollection->setPageSize(1)->load();

		if ( $uldURLCollection->count() > 0 ) {
			$uldURLCollection->getFirstItem()->delete();
		}
		$return="Call End";
		$result = array();
		$result['call_status'] = $return;
		return $result;
	}
	public function startDropinCall($user_id, $user_profile_id, $dropin_call_id="", $type="new"){
		$username=$this->getUserNameByUserId($user_profile_id);
		//$access_token=genrateToken();
		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$read= $resource->getConnection('core_read');
		$widget = $resource->getTableName('widget_info');
		//$device = $resource->getTableName('call_token');
		//$sql = "insert into $device (user_id,user_profile_id,token,created) values ('$user_id', '$user_profile_id','$access_token',NOW())";
		
		//$write->query($sql);
		if($type=='new'){
			$write->query("insert into $widget (username, created_on, user_id, invitee, call_status, is_private, isDropin) values('".$username."', now(), ".$user_profile_id.", ".$user_id.",1,1,1)");
			$dropin_call_id = $thelastId = $write->lastInsertId();
			$widget_key = $this->uniqueKey($user_id.$dropin_call_id);
			$write->query("update $widget set widget_key='".$widget_key."' WHERE user_id='".$user_profile_id."' and widget_id=".$dropin_call_id);
			//echo "1";
			$modelURLRewrite = Mage::getModel('core/url_rewrite');
			$modelURLRewrite->setIdPath('csprofile/privatez'.$dropin_call_id)
				->setTargetPath('csprofile/index/view/username/'.$username)
				->setOptions('')
				->setDescription(null)
				->setRequestPath('privatez'.$dropin_call_id);
			$modelURLRewrite->save();
		}else{
			$unikey = $read->fetchRow("SELECT invitee from $widget WHERE user_id='".$user_profile_id."' and widget_id=".$dropin_call_id);
			if($unikey['invitee']!=""){
				$invitee=$unikey['invitee'].",".$user_id;
			}else{
				$invitee=$user_id;
			}
			$write->query("update $widget set invitee='".$invitee."' WHERE user_id='".$user_profile_id."' and widget_id=".$dropin_call_id);
		}
		$result = array();
		$result['dropin_call_id'] = $dropin_call_id;
		//=====================================================
		try{
			$notificationType="Online";
			$type = "drop_in";
			$customer1 = Mage::getModel('customer/customer')->load($user_profile_id);
			$message=$customer1->getFirstname()." ".$customer1->getLastname()." is trying to drop-in on you.";
			$shortMsg = $customer1->getFirstname()." dropped-in";
			$pushNoti = $this->mobile_push_notification_call($user_id, $notificationType, $message,$user_id,$user_profile_id,$dropin_call_id,$type,$shortMsg);
		}catch (Exception $e){
		
		}
		//=================================================
		return $result;
	}
	public function removeDropinCall($user_id, $user_profile_id, $private_call_id, $type="false"){
		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$read= $resource->getConnection('core_read');
		$widget = $resource->getTableName('widget_info');
		$unikey = $read->fetchRow("SELECT invitee from $widget WHERE user_id='".$user_profile_id."' and widget_id='".$dropin_call_id."'");
		$return="remove";
		if($unikey['invitee']==$user_id){
			$write->query("update $widget set call_status=2, isDropin=0 WHERE invitee='".$user_id."' and user_id='".$user_profile_id."' and widget_id=".$dropin_call_id);
			/*$device = $resource->getTableName('call_token');
			$sql = "DELETE FROM $device WHERE user_id='$user_id' AND user_profile_id='$user_profile_id';";
			$write->query($sql);
			//echo "1";*/
			$uldURLCollection = Mage::getModel('core/url_rewrite')->getResourceCollection();
			$uldURLCollection->getSelect()
				->where('id_path=?', 'csprofile/privatez'.$dropin_call_id);

			$uldURLCollection->setPageSize(1)->load();

			if ( $uldURLCollection->count() > 0 ) {
				$uldURLCollection->getFirstItem()->delete();
			}
			$return="Call End";
			//setcookie("callAccessToken",'');
		}else{
			$users=explode(",",$unikey['invitee']);
			$invitees=array();
			foreach($users as $k=>$v){
				if($user_id!=$v){
					$invitees[]=$v;
				}
			}
			$invitee=implode(",",$invitees);
			$write->query("update $widget set invitee='".$invitee."', isDropin=0 WHERE user_id='".$user_profile_id."' and widget_id=".$dropin_call_id);
		}
		if(isset($type) && $type=="false"){
			$result = array();
			$result['call_status'] = $return;
			return $result;
		}
	}
	public function rejectDropinCall($user_id, $user_profile_id, $dropin_call_id, $type=""){
		$this->removeDropinCall($user_id, $user_profile_id, $dropin_call_id,$type);
	}
	public function cancelDropinCall($user_id, $user_profile_id, $dropin_call_id, $type="false"){
		$this->removeDropinCall($user_id, $user_profile_id, $dropin_call_id,$type);
	}
	public function joinPrivateCall($user_id, $user_profile_id, $private_call_id, $type="false"){
		$user_ids = explode(",", $user_id);		
		for($i = 0; $i < count($user_ids); $i++){
			$this->startPrivateCall($user_ids[$i], $user_profile_id, $private_call_id, $type);
		}		

	}
	public function cancelPrivateCall($user_id, $user_profile_id, $private_call_id, $type="false"){
		//=================================================
		try{
			$notificationType="Online";
			$username = $this->getUserNameByUserId($user_profile_id);
			$message="You have got a missed call from ".$username;
			$pushNoti = $this->mobile_push_notification($user_id, $notificationType, $message);
		}catch (Exception $e){
		
		}
		//=================================================
		$this->removePrivateCall($user_id, $user_profile_id, $private_call_id,$type);
		
	}
	public function rejectPrivateCall($user_id, $user_profile_id, $private_call_id, $type=""){
		$this->removePrivateCall($user_id, $user_profile_id, $private_call_id,$type);
	}
	public function endPrivateCall($user_id='end', $user_profile_id, $private_call_id, $type=""){
		$user_ids = explode(",", $user_id);		
		for($i = 0; $i < count($user_ids); $i++){
			$this->removePrivateCall($user_ids[$i], $user_profile_id, $private_call_id,$type);
		}				
	}
	public function endPublicCall($user_profile_id, $public_call_id){
		$this->removePublicCall($user_profile_id, $public_call_id);
	}
	public function switchCall($user_profile_id, $private_call_id, $type="public"){
		if($type="public"){
			$this->startPrivateCall('p', $user_profile_id, $private_call_id,"");
		}elseif($type="private"){
			$this->removePrivateCall('p', $user_profile_id, $private_call_id,"");
		}else{
			return "Invalid Type";
		}
		
	}
	public function callForwarding($user_id, $user_profile_id, $host_user_id, $private_call_id, $notificationType="Online", $type="false"){
		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$read= $resource->getConnection('core_read');
		$widget = $resource->getTableName('widget_info');
		$unikey = $read->fetchRow("SELECT invitee from $widget WHERE user_id='".$user_profile_id."' and widget_id='".$private_call_id."'");
		$users=explode(",",$unikey['invitee']);
		$invitees=array();
		foreach($users as $k=>$v){
			if($user_id!=$v){
				$invitees[]=$v;
			}
		}
		if(in_array($user_id,$invitees)){
			return "Already invited.";
		} else{
			$username = $this->getUserNameByUserId($user_profile_id);
			$hostname = $this->getUserNameByUserId($host_user_id);
			$message = $username." is forwarding a video call to you with ".$hostname;
			$this->mobile_push_notification($user_id, $notificationType, $message);
		}
	}
	public function getUpcomingEventsOld($user_id=0,$page=1){
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		if($time_zone == 'America/Los_Angeles'){
			$now = strtotime('+1 hour')+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime('+1 hour'));
		} else{
			$now = strtotime(now())+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime(now()));
		}
		$events = array();
			$events = Mage::getResourceModel('catalog/product_collection')
					   ->addAttributeToSelect('*')
					   ->addAttributeToFilter('news_from_date', array('gteq' => $date))
					   ->addFieldToFilter('attribute_set_id', 9)
					   ->addAttributeToFilter('status', 1)
					   ->setOrder('news_from_date', 'asc')
					   ->setOrder('entity_id', 'desc');
			$limit = 15;
			if($page<=0)
				$page=1;
			//$page=$page-1; 
			$events = $events->setPageSize($limit)->setPage($page, $limit);
			$lastPage = $events->getLastPageNumber();			
			$events = $events->load()->toArray();
			if($lastPage > $page){
				$showMore = "true";
			} else {
				$showMore = "false";
			}
			$items = array();
			if($lastPage >= $page){
			foreach($events as $k=>$evt){
				$prfix = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'catalog/product/';
					
				if(!$evt['thumbnail'] || $evt['thumbnail']=='no_selection'){
					$evt['thumbnail'] = "placeholder/default/red-curtain2_8.jpg";
				}
				$items[$k] = array(
								'id'=>$evt['entity_id'],
								'name'=>$evt['name'],
								'price'=>$evt['price'],
								'description'=>$evt['description'],
								'event_date'=>date('D M d, Y h:i A', strtotime($evt['news_from_date'])+$timeoffset)." ".$abbrev,
								'event_end_date'=>date('D M d, Y h:i A', strtotime($evt['news_to_date'])+$timeoffset)." ".$abbrev,
								'from_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($evt['news_from_date'])))),
								'to_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($evt['news_to_date'])))),
								'from_date3'=>date('m-d-Y H:i:s', strtotime($evt['news_from_date'])+$timeoffset),/*Added By surinder on demand of ajumal*/
								'to_date3'=>date('m-d-Y H:i:s',strtotime($evt['news_to_date'])+$timeoffset),/*Added By surinder on demand of ajumal*/
								'thumbnail'=>$prfix.$evt['thumbnail'],
								'thumb_image'	=> 'http://chattrspace.s3.amazonaws.com/events/711x447/'.$evt['event_image'],
								'location'=>$evt['location'],
								'category'=>$this->getCategoryNameByEventId($evt['entity_id']),
								'url'=>Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).'live-events/'.$evt['url_path'],
								'isEventAccess'=>$this->isEventAccess($user_id, $evt['entity_id']),
								'isLive'=> $isLive,
							);
						
			}
			}
			$evt_count = count($events);
			$result=array();
			$result['data']=$items;
			$result['showMore']=$showMore;
			$result['count']=$evt_count;
			return $result;
	}
	public function getLiveEventsOld($user_id=0,$page=1){
		if($user_id > 0){
		$cat=3; $limit=5;
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		if($time_zone == 'America/Los_Angeles'){
			$now = strtotime('+1 hour')+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime('+1 hour'));
		} else{
			$now = strtotime(now())+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime(now()));
		}
			
		$websiteId = Mage::app()->getWebsite()->getId();
		$storeId = Mage::app()->getStore()->getId();
		
		$visibility = array(	
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
              );

      		$events = Mage::getModel('catalog/category')->load($cat)
							->getProductCollection()
							->addAttributeToSelect('*')
							->addAttributeToSelect('category_id')
							->addAttributeToSelect('status')
							->addFieldToFilter('status', 1)
							->addAttributeToFilter('is_expired', array('neq' => 1))
							->addAttributeToSort('news_from_date', 'asc')
							//->addAttributeToSort('position', 'desc')
							->addAttributeToFilter('news_to_date', array('gteq' => $date));	
			
			$events->addAttributeToFilter('visibility', $visibility)				
							->setPageSize($limit)
							->setPage($page, $limit)							
							->load()->toArray();
			$lastPage = $events->getLastPageNumber();
			if($lastPage > $page){
				$showMore = "true";
			} else {
				$showMore = "false";
			}
			
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$follower = $resource->getTableName('follower');
			$select = "select follow from $follower WHERE follower_id=".$user_id." and follower_id<>follow and status=1";						
			$followers = $read->fetchAll($select);

			$resultArray = '';
			$str='';
			$c=0;
			foreach($followers as $follower){	$c = $c+1;	
					$str[$c]=$follower['follow'];
					$c = $c+1;
			}
		
			$counter=0;
			if(count($events)>0){ 
			if($lastPage >= $page){
				foreach ($events as $k => $event) { $counter++;
				//===================Start Image=====================================
					$customer = Mage::getModel('customer/customer')->load($event['user_id']);
					$fc = strtolower(substr($customer->getFirstname(),0,1));
					if($fc == ''){ $fc = rand(1,10); }

					if($event['event_image']=="''")
					$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
					else{
						if($event['event_image']){
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];
							
							if(fopen($img_url,"r")==false)
								$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
							else
								$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];
						}
						else
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];		
					}
				//===================End Image=====================================
				$rsvp = $resource->getTableName('rsvp');
					$selectSql = "select * from $rsvp WHERE user_id=".$user_id." and event_id=".$event['entity_id']." and status=1";			
					$row = $read->fetchAll($selectSql);
					if(count($row)>0)
						$rsvpStatus=1;
					else
						$rsvpStatus=0;
						
					$from = strtotime($event['news_from_date'])+$timeoffset;
					$to = strtotime($event['news_to_date'])+$timeoffset;
					if (($now > $from) && ($now < $to)) {
						$isLive="true";
					}
					else{
						$isLive="false";
					}
					if((in_array($event['user_id'],$str)) && ($isLive == "true")){
						$myFollowersEvent = "true";
					}
					else {
						$myFollowersEvent = "false";
					}
				if($myFollowersEvent == "true"){
					if($this->isUserOnline($event['user_id'])){
						$HostIsLive = "true";
					}else{
						$HostIsLive = "false";
					}
					$result[$counter]['followersLiveEvent'] = array(
							'id'=> $event['entity_id'],
							'name'=> $event['name'],
							'price'=> number_format($event['price'],2),
							'user_id'=> $event['user_id'],
							'event_hostedby_username'=> $this->getUserNameByUserId($event['user_id']),
							'description'=> $event['description'],
							'from_date'=> date('D M d, Y h:i A', strtotime($event['news_from_date'])+$timeoffset)." ".$abbrev,
							'to_date'=> date('D M d, Y h:i A',strtotime($event['news_to_date'])+$timeoffset)." ".$abbrev,
							'from_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_from_date'])))),
							'to_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_to_date'])))),
							'image'			=> $img_url,						
							'islive'			=> $isLive,
							'rsvpStatus'	=>$rsvpStatus,
							'category'  => $this->getCategoryNameByEventId($event['entity_id']),
							'from_date1'=> date('m-d-Y H:i:s', strtotime($event['news_from_date'])+$timeoffset),
							'to_date1'=> date('m-d-Y H:i:s',strtotime($event['news_to_date'])+$timeoffset),
							'HostIsLive' => $HostIsLive,
						);			
					}
				}
				
				}
				$result['live'] = $result['followersLiveEvent'];
				$result['onlineUser']= $this->getOnlineUser($page);
				$result['showMore'] = $showMore;
				return $result;
			}else{
				$result['onlineUser']= $this->getOnlineUser($page);
				return $result;
			}
				
		}
	}
	public function getNewsFeed($user_id, $page=1){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$follower = $resource->getTableName('follower');
		$customer_entity = $resource->getTableName('customer_entity');
		
		$select = "select * from $follower, $customer_entity WHERE follower_id='".$user_id."' and follower_id<>follow and status=1 and $customer_entity.entity_id=$follower.follow";
		$select.=" group by follow order by id desc";
		$followings = $read->fetchAll($select);		
		$c=0;
		foreach($followings as $k=>$flwng){
			$str[$c]=$flwng['follow'];
			$c = $c+1;
		}
		$flwngs = implode(',',$str);
		$privacy = 0; //Everyone
		if(count($str)==0)
			return "You donot have followings";
		
		/*$select_friends = "select * from $follower, $customer_entity WHERE follower_id IN(".$flwngs.") and follow='".$user_id."' and follower_id<>follow and status=1 and $customer_entity.entity_id=$follower.follow";		
		$select_friends.=" group by follow order by id desc";
		$followings_friends = $read->fetchAll($select_friends);		
		$d = 0;
		foreach($followings_friends as $k=>$flwng){
			$str_friends[$d]=$flwng['follower_id'];
			$d = $d+1;
		}
		$flwngs_friends = implode(',',$str_friends);*/

		$sql = "select *,(select count(*) from cs_newsfeed where user_id IN(".$flwngs.") and isDeleted=0) as count from cs_newsfeed where user_id IN(".$flwngs.") and isDeleted=0 order by id desc";
		//===============================================================
		$limit = 15;
		if($page<=0)
			$page=1;
		$page=$page-1;
		
		$sql.= " limit ".$limit*$page .", " .$limit;
		
		$result = $read->fetchAll($sql);
		if(count($result)>0){
		foreach($result as $k=>$rs){
		//if($rs['user_id'] <> $rs['profile_id']){
		if($rs['cat_id'] == 2){
			$action ="Clicks On";
			$msg = $rs['user_id']." clicks on ".$rs['profile_id'];
			$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
			$item['guest'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['user_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			$customer = Mage::getModel('customer/customer')->load($rs['profile_id']);
			$item['host'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['profile_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['profile_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
		}elseif($rs['cat_id'] == 3){
			$action ="RSVP";
			$msg = $rs['user_id']." RSVP event id ".$rs['profile_id'];
			$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
			$item['attender'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['user_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			$item['rsvp'][$k]=array(
						'id'=> $rs['id'],
						'event_id'=>$rs['profile_id'],
						'event'=>$this->getEvent($rs['user_id'], $rs['profile_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
		}elseif($rs['cat_id'] == 4){
			$action ="Livechat";
			$msg = $rs['user_id']." Livechat ".$rs['profile_id'];
			$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
			$item['chatter'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['user_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			$customer = Mage::getModel('customer/customer')->load($rs['profile_id']);
			$item['chatting'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['profile_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['profile_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
		}elseif($rs['cat_id'] == 5){
			$recentE = "select * from cs_comment where item_id=".$rs['profile_id']." and category=1 and isDeleted=0 order by id DESC";
			$recentCommentE = $read->fetchRow($recentE);
			$action ="Created event";
			$msg = $rs['user_id']." Created event id ".$rs['profile_id'];
			$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
			$item['creater'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['user_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			$item['event'][$k]=array(
						'id'=> $rs['id'],
						'event_id'=>$rs['profile_id'],
						'event'=>$this->getEvent($rs['user_id'], $rs['profile_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
						'like_count'=>$rs['like_count'],
						'comment_count'=>$rs['comment_count'],
						'recentComment'=>$recentCommentE,
					);
		}elseif($rs['cat_id'] == 6){
		$recent = "select * from cs_comment where item_id=".$rs['profile_id']." and category=2 and isDeleted=0 order by id DESC";
		$recentComment = $read->fetchRow($recent);
		if($rs['privacy']==0){
			$selectv = "select count(*) as count  from cs_video where video_id =".$rs['profile_id']." and privacy = 0 and status = 1 and isdeleted = 0";
			$rsv = $read->fetchRow($selectv);
			if($rsv['count'] > 0){
			$action ="Recorded video";
			$msg = $rs['user_id']." Recorded video id ".$rs['profile_id'];
			$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
			$item['recorder'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['user_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			$item['video'][$k]=array(
						'id'=> $rs['id'],
						'video_id'=>$rs['profile_id'],
						'video'=>$this->getVideoDetails($rs['profile_id'], 0),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
						'like_count'=>$rs['like_count'],
						'comment_count'=>$rs['comment_count'],
						'recentComment'=>$recentComment,
						'privacy'=>$rs['privacy'],
					);
				}
		}elseif($rs['privacy']==1 && $rs['user_id']==$user_id){
			$selectv = "select count(*) as count  from cs_video where video_id =".$rs['profile_id']." and privacy = 1 and status = 1 and isdeleted = 0";
			$rsv = $read->fetchRow($selectv);
			if($rsv['count'] > 0){
			$action ="Recorded video";
			$msg = $rs['user_id']." Recorded video id ".$rs['profile_id'];
			$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
			$item['recorder'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['user_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			$item['video'][$k]=array(
						'id'=> $rs['id'],
						'video_id'=>$rs['profile_id'],
						'video'=>$this->getVideoDetails($rs['profile_id'], 1),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
						'like_count'=>$rs['like_count'],
						'comment_count'=>$rs['comment_count'],
						'recentComment'=>$recentComment,
						'privacy'=>$rs['privacy'],
					);
				}
		}elseif($rs['privacy']==2){
			$a = $this->isFollowForChat($rs['user_id'], $user_id);
			$b = $this->isFollowForChat($user_id,$rs['user_id']);
			if($a==1 && $b==1){
			$selectv = "select count(*) as count  from cs_video where video_id =".$rs['profile_id']." and privacy = 2 and status = 1 and isdeleted = 0";
			$rsv = $read->fetchRow($selectv);
			if($rsv['count'] > 0){
			$action ="Recorded video";
			$msg = $rs['user_id']." Recorded video id ".$rs['profile_id'];
			$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
			$item['recorder'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['user_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			$item['video'][$k]=array(
						'id'=> $rs['id'],
						'video_id'=>$rs['profile_id'],
						'video'=>$this->getVideoDetails($rs['profile_id'], 2),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
						'like_count'=>$rs['like_count'],
						'comment_count'=>$rs['comment_count'],
						'recentComment'=>$recentComment,
						'privacy'=>$rs['privacy'],
					);
				}
			}	
		}
		}elseif($rs['cat_id'] == 7){
			$action ="Follow";
			$msg = $rs['user_id']." follow ".$rs['profile_id'];
			$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
			$item['follower'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['user_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			$customer = Mage::getModel('customer/customer')->load($rs['profile_id']);
			$item['followed'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['profile_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['profile_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
		}elseif($rs['cat_id'] == 8){
			$action ="Unfollow";
			$msg = $rs['user_id']." unfollow ".$rs['profile_id'];
			$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
			$item['unfollower'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['user_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			$customer = Mage::getModel('customer/customer')->load($rs['profile_id']);
			$item['unfollowed'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['profile_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['profile_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
		}elseif($rs['cat_id'] == 10){
			$action ="Friends";
			$msg = $rs['user_id']." friends with ".$rs['profile_id'];
			$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
			$item['friend'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['user_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			$customer = Mage::getModel('customer/customer')->load($rs['profile_id']);
			$item['friendwith'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['profile_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['profile_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
		}elseif($rs['cat_id'] == 11){
			$selecth = "select receiver_id,live_call_hashtag,type from cs_live_call_hashtag WHERE id=".$rs['profile_id'];
			$hashtags = $read->fetchRow($selecth);
			$action ="hashtag";
			$msg = $rs['user_id']." tagged ".$rs['profile_id'];
			$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
			if($hashtags['type'] == "private"){
			$receiver = Mage::getModel('customer/customer')->load($hashtags['receiver_id']);
			$item['hashtag'][$k]=array(
						'id'=> $rs['id'],
						'host_id'=>$rs['user_id'],
						'host_username'=>$customer->getUsername(),
						'host_firstname'=>$customer->getFirstname(),
						'host_lastname'=>$customer->getLastname(),
						'host_image'=>$this->getProfilePic($rs['user_id']),
						'receiver_id'=>$hashtags['receiver_id'],
						'receiver_username'=>$receiver->getUsername(),
						'receiver_firstname'=>$receiver->getFirstname(),
						'receiver_lastname'=>$receiver->getLastname(),
						'receiver_image'=>$this->getProfilePic($hashtags['receiver_id']),
						'hashtag_id'=>$rs['profile_id'],
						'data'=>$hashtags['live_call_hashtag'],
						'call_type'=>$hashtags['type'],
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			}
			if($hashtags['type'] == "public"){
			$item['hashtag'][$k]=array(
						'id'=> $rs['id'],
						'host_id'=>$rs['user_id'],
						'host_username'=>$customer->getUsername(),
						'host_firstname'=>$customer->getFirstname(),
						'host_lastname'=>$customer->getLastname(),
						'host_image'=>$this->getProfilePic($rs['user_id']),
						'hashtag_id'=>$rs['profile_id'],
						'data'=>$hashtags['live_call_hashtag'],
						'call_type'=>$hashtags['type'],
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			}
		}
			
		//}
			$count = $rs['count'];
		}
		if($count > ($limit*($page+1))){
			$showMore = "true";
		} else {
			$showMore = "false";
		}
		//===============================================================
		$item['showMore'] = $showMore;
		return $item;
	}else{
		return "No records found";
	}
	}
	public function getProfileNewsFeed($profile_id, $current_user_id, $page=1){ // current_user_id is id who is logged in 
		$user_id = $profile_id;
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write = $getResourceModele->getConnection('core_write');
		$follower = $resource->getTableName('follower');
		$customer_entity = $resource->getTableName('customer_entity');

		$follow_status = 1;		
		$follow_result1 = $this->isFollowing($profile_id, $current_user_id);
		$follow_result2 = $this->isFollow($profile_id, $current_user_id);
		if( $follow_result1 && $follow_result2){
			$privacy = "2, 0"; // Favorate;
		}else {
			$privacy = 0; // Everyone;
		}
		if($user_id == $current_user_id){
			$privacy = " 1, 2, 0"; // Me;	
		}
		
		$sql = "select *,(select count(*) from cs_newsfeed where user_id='".$user_id."' and isDeleted=0) as count from cs_newsfeed where user_id='".$user_id."' and privacy IN(".$privacy.") and isDeleted=0 order by id desc";
		
		$limit = 15;
		if($page<=0)
			$page=1;
		$page=$page-1;
		$sql.= " limit ".$limit*$page .", " .$limit;		
		$result = $read->fetchAll($sql);
		if(count($result)>0){	
		foreach($result as $k=>$rs){
		//if($rs['user_id'] <> $rs['profile_id']){
		if($rs['cat_id'] == 2){
			$action ="Clicks On";
			$msg = $rs['user_id']." clicks on ".$rs['profile_id'];
			$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
			$item['guest'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['user_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			$customer = Mage::getModel('customer/customer')->load($rs['profile_id']);
			$item['host'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['profile_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['profile_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
		}elseif($rs['cat_id'] == 3){
			$action ="RSVP";
			$msg = $rs['user_id']." RSVP event id ".$rs['profile_id'];
			$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
			$item['attender'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['user_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			$item['rsvp'][$k]=array(
						'id'=> $rs['id'],
						'event_id'=>$rs['profile_id'],
						'event'=>$this->getEvent($rs['user_id'], $rs['profile_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
		}elseif($rs['cat_id'] == 4){
			$action ="Livechat";
			$msg = $rs['user_id']." Livechat ".$rs['profile_id'];
			$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
			$item['chatter'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['user_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			$customer = Mage::getModel('customer/customer')->load($rs['profile_id']);
			$item['chatting'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['profile_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['profile_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
		}elseif($rs['cat_id'] == 5){
			$recentE = "select * from cs_comment where item_id=".$rs['profile_id']." and category=1 and isDeleted=0 order by id DESC";
			$recentCommentE = $read->fetchRow($recentE);
			$action ="Created event";
			$msg = $rs['user_id']." Created event id ".$rs['profile_id'];
			$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
			$item['creater'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['user_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			$item['event'][$k]=array(
						'id'=> $rs['id'],
						'event_id'=>$rs['profile_id'],
						'event'=>$this->getEvent($rs['user_id'], $rs['profile_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
						'like_count'=>$rs['like_count'],
						'comment_count'=>$rs['comment_count'],
						'recentComment'=>$recentCommentE,
					);
		}elseif($rs['cat_id'] == 6){
		$recent = "select * from cs_comment where item_id=".$rs['profile_id']." and category=2 and isDeleted=0 order by id DESC";
		$recentComment = $read->fetchRow($recent);
		if($rs['privacy']==0){
			$action ="Recorded video";
			$msg = $rs['user_id']." Recorded video id ".$rs['profile_id'];
			$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
			$item['recorder'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['user_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			$item['video'][$k]=array(
						'id'=> $rs['id'],
						'video_id'=>$rs['profile_id'],
						'video'=>$this->getVideoDetails($rs['profile_id'], 0),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
						'like_count'=>$rs['like_count'],
						'comment_count'=>$rs['comment_count'],
						'recentComment'=>$recentComment,
						'privacy'=>$rs['privacy'],
					);
		}elseif($rs['privacy']==1 && $rs['user_id']==$user_id){
			$action ="Recorded video";
			$msg = $rs['user_id']." Recorded video id ".$rs['profile_id'];
			$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
			$item['recorder'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['user_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			$item['video'][$k]=array(
						'id'=> $rs['id'],
						'video_id'=>$rs['profile_id'],
						'video'=>$this->getVideoDetails($rs['profile_id'], 1),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
						'like_count'=>$rs['like_count'],
						'comment_count'=>$rs['comment_count'],
						'recentComment'=>$recentComment,
						'privacy'=>$rs['privacy'],
					);
		}elseif($rs['privacy']==2){
			$a = $this->isFollowForChat($rs['user_id'], $user_id);
			$b = $this->isFollowForChat($user_id,$rs['user_id']);
			if($a==1 && $b==1){
			$action ="Recorded video";
			$msg = $rs['user_id']." Recorded video id ".$rs['profile_id'];
			$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
			$item['recorder'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['user_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			$item['video'][$k]=array(
						'id'=> $rs['id'],
						'video_id'=>$rs['profile_id'],
						'video'=>$this->getVideoDetails($rs['profile_id'], 2),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
						'like_count'=>$rs['like_count'],
						'comment_count'=>$rs['comment_count'],
						'recentComment'=>$recentComment,
						'privacy'=>$rs['privacy'],
					);
			}
		}
		}elseif($rs['cat_id'] == 7){
			$action ="Follow";
			$msg = $rs['user_id']." follow ".$rs['profile_id'];
			$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
			$item['follower'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['user_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			$customer = Mage::getModel('customer/customer')->load($rs['profile_id']);
			$item['followed'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['profile_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['profile_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
		}elseif($rs['cat_id'] == 8){
			$action ="Unfollow";
			$msg = $rs['user_id']." unfollow ".$rs['profile_id'];
			$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
			$item['unfollower'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['user_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			$customer = Mage::getModel('customer/customer')->load($rs['profile_id']);
			$item['unfollowed'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['profile_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['profile_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
		}elseif($rs['cat_id'] == 10){
			$action ="Friends";
			$msg = $rs['user_id']." friends with ".$rs['profile_id'];
			if( $current_user_id == $rs['profile_id']){
				$msg = $rs['user_id']." and ".$rs['profile_id']." are favorites.";
			}

			$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
			$item['friend'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['user_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			$customer = Mage::getModel('customer/customer')->load($rs['profile_id']);
			$item['friendwith'][$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['profile_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['profile_id']),
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
		}elseif($rs['cat_id'] == 11){
			$selecth = "select receiver_id,live_call_hashtag,type from cs_live_call_hashtag WHERE id=".$rs['profile_id'];
			$hashtags = $read->fetchRow($selecth);
			$action ="hashtag";
			$msg = $rs['user_id']." tagged ".$rs['profile_id'];
			$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
			if($hashtags['type'] == "private"){
			$receiver = Mage::getModel('customer/customer')->load($hashtags['receiver_id']);
			$item['hashtag'][$k]=array(
						'id'=> $rs['id'],
						'host_id'=>$rs['user_id'],
						'host_username'=>$customer->getUsername(),
						'host_firstname'=>$customer->getFirstname(),
						'host_lastname'=>$customer->getLastname(),
						'host_image'=>$this->getProfilePic($rs['user_id']),
						'receiver_id'=>$hashtags['receiver_id'],
						'receiver_username'=>$receiver->getUsername(),
						'receiver_firstname'=>$receiver->getFirstname(),
						'receiver_lastname'=>$receiver->getLastname(),
						'receiver_image'=>$this->getProfilePic($hashtags['receiver_id']),
						'hashtag_id'=>$rs['profile_id'],
						'data'=>$hashtags['live_call_hashtag'],
						'call_type'=>$hashtags['type'],
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			}
			if($hashtags['type'] == "public"){
			$item['hashtag'][$k]=array(
						'id'=> $rs['id'],
						'host_id'=>$rs['user_id'],
						'host_username'=>$customer->getUsername(),
						'host_firstname'=>$customer->getFirstname(),
						'host_lastname'=>$customer->getLastname(),
						'host_image'=>$this->getProfilePic($rs['user_id']),
						'hashtag_id'=>$rs['profile_id'],
						'data'=>$hashtags['live_call_hashtag'],
						'call_type'=>$hashtags['type'],
						'action'=>$action,
						'created_on'=>$rs['created_on'],
					);
			}
		}
		$count = $rs['count'];
		}
		if($count > ($limit*($page+1))){
			$showMore = "true";
		} else {
			$showMore = "false";
		}	
		//===============================================================
		$item['showMore'] = $showMore;
		return $item;
	}else{
		return "No records found";
	}
	}
public function uploadCustomerCanvasImageAndroid($user_id=0,$data=null){
		//$data="";
		if (isset($_POST["data"]) && ($_POST["data"] !="") && isset($_POST["user_id"]) && $_POST["user_id"]!="" && $_POST["user_id"]>0){
				//$data = $_POST["data"];
				$user_id = $_POST["user_id"];
				$customer = Mage::getModel('customer/customer')->load($user_id);
				$data = base64_decode($_POST["data"]);
				
				//$decodedData = base64_decode(chunk_split($_POST["data"]));
				$im = imagecreatefromstring($data);
				if ($im == false) {
					return ' Error: Data is not well formated.';
				}
				$fileName = strtoupper( $user_id . "-" . $this->getRandomString(8) );
			
				if (isset($im) && $im != false) {
			
					$img = $fileName.'.jpg';
					$fileName = "bgimage".$img;
					$path = Mage::getBaseDir('media') . DS .  'chattrspace'. DS;
					$fullFilePath = $path.$fileName;
					
					$result = imagepng($im, $fullFilePath);
					imagedestroy($im);
					//Save to S3	
					$bucketName = 'chattrspace';
					$objectname = 'user_bgimages/'.$fileName;
					$filename1 = Mage::getModel('uploadjob/amazonS3')
						->putImage( $bucketName, $fullFilePath, $objectname, 'public');	
					//End Save to S3
					//unlink($fullFilePath);
					$customer->setData('bgimage', $img);
					$customer->save();
					return $this->getUserInfo($user_id);
            }else {
				return 'Error in Image Uploading';
            }
		}else {
				return 'Use Form POST';
            }
	}
	public function uploadCustomerCanvasImageIphone($user_id=0,$data=null){
		if (isset($_POST["data"]) && ($_POST["data"] !="") && isset($_POST["user_id"]) && $_POST["user_id"]!="" && $_POST["user_id"]>0){
				//$data = $_POST["data"];
				$user_id = $_POST["user_id"];
				$customer = Mage::getModel('customer/customer')->load($user_id);
				$data = base64_decode($_POST["data"]);
				
				//$decodedData = base64_decode(chunk_split($_POST["data"]));
				$im = imagecreatefromstring($data);
				if ($im == false) {
					return ' Error: Data is not well formated.';
				}
				$fileName = strtoupper( $user_id . "-" . $this->getRandomString(8) );
			
				if (isset($im) && $im != false) {
			
					$img = $fileName.'.jpg';
					$fileName = "bgimage".$img;
					$path = Mage::getBaseDir('media') . DS .  'chattrspace'. DS;
					$fullFilePath = $path.$fileName;
					
					$result = imagepng($im, $fullFilePath);
					imagedestroy($im);
					//Save to S3	
					$bucketName = 'chattrspace';
					$objectname = 'user_bgimages/'.$fileName;
					$filename1 = Mage::getModel('uploadjob/amazonS3')
						->putImage( $bucketName, $fullFilePath, $objectname, 'public');	
					//End Save to S3
					//unlink($fullFilePath);
					$customer->setData('bgimage', $img);
					$customer->save();
					return $this->getUserInfo($user_id);
            }else {
				return 'Error in Image Uploading';
            }
        }else {
            return 'Use Form POST';
        }
    }
    public function createEventIphone($user_id=0, $title=null,$description=null,$location=null,$hashtag=null,$from_date=null,$to_date=null,$data=null,$event_id=0) {
        if (isset($_POST["data"]) && ($_POST["data"] !="") && isset($_POST["user_id"]) && ($_POST["user_id"]>0)){
            $user_id = $_POST["user_id"];
            $title = strip_tags($_POST["title"]);
            $description = strip_tags($_POST["description"]);
            $location = strip_tags($_POST["location"]);
            $hashtag = strip_tags($_POST["hashtag"]);
            $from_date = $_POST["from_date"];
            $to_date = $_POST["to_date"];
            $cat_id = 18;
            $event_id = $_POST["event_id"];
            if($title == ""){
                return "Error : Title is blank";
            }
            if($description == ""){
                return "Error : Description is blank";
            }
            if($location == ""){
                return "Error : Location is blank";
            }
            if($hashtag=="")
                $hashtag = "oncam";
            $price=0;
            $no_att=self::$init_max_attendees;
            //from 05/28/13 12:26
            //to 05/28/13 20:26
            $sku = ereg_replace('[^A-Za-z0-9.]', '-', date('m-d-y H:i:s'));
            $catId = '3,'.$cat_id;
            if($user_id){
                $customerId = $user_id;
                $sku = 'chattrspace-'. $user_id ."-" . $sku;
                $customer = Mage::getModel('customer/customer')->load($user_id);
                $time_zone = $customer->getTimezone();
                $timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
                $from_date1 = date('Y-m-d H:i:s', strtotime($from_date));
                if($to_date == null){
                    $to_date1 = date('Y-m-d H:i:s', strtotime('+60 minutes',strtotime($from_date)));
                }else{
                    $to_date1 = date('Y-m-d H:i:s', strtotime($to_date));
                }
                if($to_date1 < $from_date1){
                    return "Error : End Date is less than Start Date";
                }
                if($from_date != ''){
                    $from_date = date('Y-m-d H:i:s', strtotime($from_date) - $timeoffset);
                }
                if($to_date == null){
                    $to_date = date('Y-m-d H:i:s', strtotime('+60 minutes',strtotime($from_date)));
                }else{
                    $to_date = date('Y-m-d H:i:s', strtotime($to_date) - $timeoffset);
                }
                $from_date_array = explode(" ", $from_date);
                $from_array = explode("-", $from_date_array[0]);
                $to_date_array = explode(" ", $to_date);
                $to_array = explode("-", $to_date_array[0]);

                $array_year = array(2020=>142,2019=>143, 2018=>144, 2017=>145, 2016=>146, 2015=>147
                ,2014=>148, 2013=>149, 2012=>150, 2011=>151);

                $array_day = array(01=>129, 02=>128, 03=>127, 04=>126, 05=>125, 06=>124
                ,07=>123, 08=>122, 09=>121, 10=>120
                ,11=>119, 12=>118, 13=>117, 14=>116
                ,15=>115, 16=>114, 17=>113, 18=>112
                ,19=>111, 20=>110, 21=>109, 22=>108
                ,23=>107, 24=>106, 25=>105, 26=>104
                ,27=>103, 28=>102, 29=>101, 30=>100
                ,31=>99);

                $array_month = array (01=>141, 02=>140, 03=>139, 04=>138, 05=>137, 06=>136
                , 07=>135, 08=>134, 09=>133, 10=>132, 11=>131
                , 12=>130);
                $is_weekend=153;
                $d = date('D', mktime(0,0,0,$from_array[1], $from_array[2], $from_array[0]));
                if($d=="Sat" || $d=="Sun")
                    $is_weekend = 152;
                Mage::app()->getLocale()->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_LONG);
                $storeId = Mage::app()->getStore()->getId();
                $filename = '';
                if($event_id > 0){
                    $magentoProductModel= Mage::getModel('catalog/product')->load($event_id);
                    $magentoProductModel->setStoreId($storeId);
                }else{
                    $magentoProductModel= Mage::getModel('catalog/product');
                    $magentoProductModel->setStoreId(0);
                }
                $magentoProductModel->setWebsiteIds(array(1));
                $magentoProductModel->setAttributeSetId(9);
                $magentoProductModel->setTypeId('simple');
                $magentoProductModel->setName($title);
                $magentoProductModel->setProductName($title);
                $magentoProductModel->setSku($sku);
                $magentoProductModel->setUserId($user_id);
                $magentoProductModel->setShortDescription($description);
                $magentoProductModel->setDescription($description);
                $magentoProductModel->setPrice($price);
                $magentoProductModel->setSpecialPrice($vol_price);
                $magentoProductModel->setSalesQty(100);
                $magentoProductModel->setWeight(0);
                $magentoProductModel->setIsExpired(155);
                $magentoProductModel->setLocation($location);
                $magentoProductModel->setHashtag($hashtag);
                $magentoProductModel->setVisibility(4);

                $magentoProductModel->setNewsFromDate($from_date);
                $magentoProductModel->setNewsToDate($to_date);

                $magentoProductModel->setToDay($to_array[2]);
                $magentoProductModel->setToMonth($to_array[1]);
                $magentoProductModel->setToYear($to_array[0]);

                $magentoProductModel->setFromDay($array_day[intval($from_array[2])]);
                $magentoProductModel->setFromMonth($array_month[intval($from_array[1])]);
                $magentoProductModel->setFromYear($array_year[intval($from_array[0])]);

                $magentoProductModel->setFromTime($from_date_array[1]);
                $magentoProductModel->setToTime($to_date_array[1]);

                $magentoProductModel->setIsWeekend($is_weekend);

                $magentoProductModel->setMaximumOfAttendees($no_att);

                $magentoProductModel->setStatus(1);
                $magentoProductModel->setTaxClassId('None');
                $magentoProductModel->setCategoryIds($catId);
                //==============================================================================
                //$encodedData = str_replace(' ','+',$_POST["data"]);
                //$decodedData = ""; 

                //for($i=0, $len=strlen($encodedData); $i<$len; $i+=4){
                //	$decodedData = $decodedData . base64_decode( substr($encodedData, $i, 4) );
                //}
                //$im = imagecreatefromstring($decodedData);
                $data = base64_decode($_POST["data"]);
                $im = imagecreatefromstring($data);
                $fileName = strtoupper( $user_id . "-" . $this->getRandomString(8) );

                if (isset($im) && $im != false) {
                    $image_path = $fileName . '_img.jpg';
                    $path = Mage::getBaseDir('media') . DS .  'event'. DS;
                    $fullFilePath = $path . $image_path;

                    if(file_exists($fullFilePath)){
                        //unlink($fullFilePath);      
                    }

			$result = imagepng($im, $fullFilePath);
			imagedestroy($im);
			/*$bucketName = 'chattrspace';
					$objectname = 'events/135x110/'.$image_path;
					$filename = Mage::getModel('uploadjob/amazonS3')
									->putImage( $bucketName, $fullFilePath, $objectname, 'public');
					
					$bucketName = 'chattrspace';
					$objectname = 'events/711x447/'.$image_path;
					$filename = Mage::getModel('uploadjob/amazonS3')
									->putImage( $bucketName, $fullFilePath, $objectname, 'public');
					//sleep(15);
					unlink($fullFilePath);*/
			}else {
				return 'Error in Image Uploading';
            }		
			//==============================================================================
			$magentoProductModel->setEventImage($image_path);
			//uploadEventImage($event_id,$data=null)
			$this->_addImages($magentoProductModel, $image_path, $user_id);
			$saved = $magentoProductModel->save();
			/* Event Mail Send */
			$lastId = $saved->getId();
			//send mail replace by cron job mail
			//$this->createCronJobsendMail($lastId, $user_id, $cat_id);
			//Magento Stock
			$this->_saveStock($lastId, $no_att);
			//================================================
			$resource = Mage::getSingleton('core/resource');
			$write= $resource->getConnection('core_write');
			$newsfeed = $resource->getTableName('newsfeed');
			$write->query("insert into $newsfeed (user_id, profile_id, cat_id) values(".$user_id.", ".$lastId.",5)");
			//===================================================
			try{
			$notificationType="Online";
			$type = "event";
			$customer1 = Mage::getModel('customer/customer')->load($user_id);
			$message=$customer1->getFirstname()." ".$customer1->getLastname()." has created a new event";
			$shortMsg = $customer1->getFirstname()." created event";
			$pushNoti = $this->mobile_push_notification_follower_call($user_id, $notificationType, $message,$user_id,$lastId,0,$type,$shortMsg);
			}catch(Exception $e){
			
			}
			return $lastId;
		}	 
		}else {
				return 'Use Form POST';
            }
	}
	public function createEventAndroidNew($user_id=0, $title=null,$description=null,$location=null,$hashtag=null,$from_date=null,$to_date=null,$data=null,$youtube_url1=null,$youtube_url2=null,$event_id=0){
	if (isset($_POST["data"]) && ($_POST["data"] !="") && isset($_POST["user_id"]) && ($_POST["user_id"]>0)){
		$user_id = $_POST["user_id"];
		$title = strip_tags($_POST["title"]);
		$description = strip_tags($_POST["description"]);
		$location = strip_tags($_POST["location"]);
		$hashtag = strip_tags($_POST["hashtag"]);
		$from_date = $_POST["from_date"];
		$to_date = $_POST["to_date"];
		$cat_id = 18;
		$event_id = $_POST["event_id"];
		$youtube_url1 = $_POST["youtube_url1"];
		$youtube_url2 = $_POST["youtube_url2"];
		if($title == ""){
			return "Error : Title is blank";
		}
		if($description == ""){
			return "Error : Description is blank";
		}
		if($location == ""){
			return "Error : Location is blank";
		}
		if($hashtag=="")
                $hashtag = "oncam";
            $price=0;
            $no_att=self::$init_max_attendees;
            //from 05/28/13 12:26
		//to 05/28/13 20:26
		$sku = ereg_replace('[^A-Za-z0-9.]', '-', date('m-d-y H:i:s'));
		$catId = '3,'.$cat_id;
		if($user_id){
			$customerId = $user_id;
			$sku = 'chattrspace-'. $user_id ."-" . $sku;	
			$customer = Mage::getModel('customer/customer')->load($user_id);
			$time_zone = $customer->getTimezone();
			$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
			$from_date1 = date('Y-m-d H:i:s', strtotime($from_date));
			if($to_date == null){
				$to_date1 = date('Y-m-d H:i:s', strtotime('+60 minutes',strtotime($from_date)));
			}else{
				$to_date1 = date('Y-m-d H:i:s', strtotime($to_date));
			}
			if($to_date1 < $from_date1){
				return "Error : End Date is less than Start Date";
			}
			if($from_date != ''){
				$from_date = date('Y-m-d H:i:s', strtotime($from_date) - $timeoffset);
			}
			if($to_date == null){
				$to_date = date('Y-m-d H:i:s', strtotime('+60 minutes',strtotime($from_date)));
			}else{
				$to_date = date('Y-m-d H:i:s', strtotime($to_date) - $timeoffset);
			}
			$from_date_array = explode(" ", $from_date);
			$from_array = explode("-", $from_date_array[0]);
			$to_date_array = explode(" ", $to_date);
			$to_array = explode("-", $to_date_array[0]);
				
			$array_year = array(2020=>142,2019=>143, 2018=>144, 2017=>145, 2016=>146, 2015=>147 
									,2014=>148, 2013=>149, 2012=>150, 2011=>151);
									
			$array_day = array(01=>129, 02=>128, 03=>127, 04=>126, 05=>125, 06=>124 
											,07=>123, 08=>122, 09=>121, 10=>120
											,11=>119, 12=>118, 13=>117, 14=>116
											,15=>115, 16=>114, 17=>113, 18=>112
											,19=>111, 20=>110, 21=>109, 22=>108
											,23=>107, 24=>106, 25=>105, 26=>104
											,27=>103, 28=>102, 29=>101, 30=>100
											,31=>99);
											
			$array_month = array (01=>141, 02=>140, 03=>139, 04=>138, 05=>137, 06=>136 
										, 07=>135, 08=>134, 09=>133, 10=>132, 11=>131
										, 12=>130);
			$is_weekend=153;
			$d = date('D', mktime(0,0,0,$from_array[1], $from_array[2], $from_array[0]));
			if($d=="Sat" || $d=="Sun")
				$is_weekend = 152; 
			Mage::app()->getLocale()->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_LONG);
			$storeId = Mage::app()->getStore()->getId();
			$filename = '';
			if($event_id > 0){
				$magentoProductModel= Mage::getModel('catalog/product')->load($event_id);
				$magentoProductModel->setStoreId($storeId);				
			}else{
				$magentoProductModel= Mage::getModel('catalog/product');
				$magentoProductModel->setStoreId(0);
			}
			$magentoProductModel->setWebsiteIds(array(1));
			$magentoProductModel->setAttributeSetId(9);
			$magentoProductModel->setTypeId('simple');
			$magentoProductModel->setName($title);
			$magentoProductModel->setProductName($title);
			$magentoProductModel->setSku($sku);
			$magentoProductModel->setUserId($user_id);
			$magentoProductModel->setShortDescription($description);
			$magentoProductModel->setDescription($description);
			$magentoProductModel->setPrice($price);				
			$magentoProductModel->setSpecialPrice($vol_price);				
			$magentoProductModel->setSalesQty(100);				
			$magentoProductModel->setWeight(0);
			$magentoProductModel->setIsExpired(155);
			$magentoProductModel->setLocation($location);
			$magentoProductModel->setHashtag($hashtag);
			$magentoProductModel->setVisibility(4);					
			$magentoProductModel->setVideoFilePath1($youtube_url1);					
			$magentoProductModel->setVideoFilePath1($youtube_url2);					
			
			$magentoProductModel->setNewsFromDate($from_date);
			$magentoProductModel->setNewsToDate($to_date);
			
			$magentoProductModel->setToDay($to_array[2]);
			$magentoProductModel->setToMonth($to_array[1]);
			$magentoProductModel->setToYear($to_array[0]);
			
			$magentoProductModel->setFromDay($array_day[intval($from_array[2])]);
			$magentoProductModel->setFromMonth($array_month[intval($from_array[1])]);
			$magentoProductModel->setFromYear($array_year[intval($from_array[0])]);
			
			$magentoProductModel->setFromTime($from_date_array[1]);
			$magentoProductModel->setToTime($to_date_array[1]);
			
			$magentoProductModel->setIsWeekend($is_weekend);
			
			$magentoProductModel->setMaximumOfAttendees($no_att);
			
			$magentoProductModel->setStatus(1);
			$magentoProductModel->setTaxClassId('None');
			$magentoProductModel->setCategoryIds($catId);
			//==============================================================================
			$data = base64_decode($_POST["data"]);
			$im = imagecreatefromstring($data);
			$fileName = strtoupper( $user_id . "-" . $this->getRandomString(8) );
		
			if (isset($im) && $im != false) {
				$image_path = $fileName . '_img.jpg';	
				$path = Mage::getBaseDir('media') . DS .  'event'. DS;
				$fullFilePath = $path . $image_path;
		
			if(file_exists($fullFilePath)){
				//unlink($fullFilePath);      
			}

			$result = imagepng($im, $fullFilePath);
			imagedestroy($im);
			/*
			$bucketName = 'chattrspace';
					$objectname = 'events/135x110/'.$image_path;
					$filename = Mage::getModel('uploadjob/amazonS3')
									->putImage( $bucketName, $fullFilePath, $objectname, 'public');
					
					$bucketName = 'chattrspace';
					$objectname = 'events/711x447/'.$image_path;
					$filename = Mage::getModel('uploadjob/amazonS3')
									->putImage( $bucketName, $fullFilePath, $objectname, 'public');
					//sleep(15);
					unlink($fullFilePath);*/
			}else {
				return 'Error in Image Uploading';
            }		
			//==============================================================================
			$magentoProductModel->setEventImage($image_path);
			//uploadEventImage($event_id,$data=null)
			$this->_addImages($magentoProductModel, $image_path, $user_id);
			$saved = $magentoProductModel->save();
			/* Event Mail Send */
			$lastId = $saved->getId();
			//send mail replace by cron job mail
			//$this->createCronJobsendMail($lastId, $user_id, $cat_id);
			//Magento Stock
			$this->_saveStock($lastId, $no_att);
			//================================================
			$resource = Mage::getSingleton('core/resource');
			$write= $resource->getConnection('core_write');
			$newsfeed = $resource->getTableName('newsfeed');
			$write->query("insert into $newsfeed (user_id, profile_id, cat_id) values(".$user_id.", ".$lastId.",5)");
			//===================================================
			try{
			$notificationType="Online";
			$username = $this->getUserNameByUserId($user_id);
			$message=$username." has created a new event";
			$pushNoti = $this->mobile_push_notification_follower_call($user_id, $notificationType, $message,$user_id,$lastId);
			}catch(Exception $e){
			
			}
			return $lastId;
		}	 
		}else {
				return 'Use Form POST';
            }
	}
	public function getVideoDetails($video_id, $privacy=0){
		//if($user_id > 0){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$videoTable = $resource->getTableName('video');
		
		$select = 'select video_id, title, identifier, description, profile_id, user_id, video_path, thumbnail_path, duration, tags, created_time, view_count, privacy  from '.$videoTable.' where video_id ='.$video_id.' and privacy = '.$privacy.' and status = 1 and isdeleted = 0';
		
		$rs = $read->fetchAll($select);
		foreach($rs as $k=>$r){
			$item[$k] = array(
							'video_id'=> $r['video_id'],
							'title'=> $r['title'],
							'description'=> $r['description'],
							'profile_id'=> $r['profile_id'],
							'user_id'=> $r['user_id'],
							'video_path'=> $r['video_path'],
							'thumbnail_path'=> $r['thumbnail_path'],
							'duration'=> $r['duration'],
							'view'=> $r['view_count'],
							'privacy' => $r['privacy'],
							'created_time' => $r['created_time'].' GMT',
							'created_time2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($r['created_time'])))),
							);
		}
		return $item;
		//}
		//else
		//return 0;
	}

	public function mobile_push_notification($user_id,$notificationType="Online", $message=""){
		$iphone = $this->push_notification_iphone($user_id,$notificationType, $message);
		$android = $this->push_notification_android($user_id,$message);
	}
	public function mobile_push_notification_follower($user_id,$notificationType="Online", $message=""){
		$iphone = $this->push_notification_follower_iphone($user_id,$notificationType, $message);
		$android = $this->push_notification_follower_android($user_id,$message);
	}
	public function push_notification_iphone($user_id,$notificationType="Online", $message="")
	{
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$device = $resource->getTableName('mobile_device');
		$select = "select device_id from $device where notificationType='".$notificationType."' and type IN ('iPhone','iPad') and device_id!='' and active=1 and user_id=".$user_id;
		$deviceTokens = $read->fetchAll($select);
		$username = $this->getUserNameByUserId($user_id);
		if ( $notificationType == 'develop' )
			$pemFile='/var/websites/oncam_com/webroot/certs/aps_development.pem';
		else if ( $notificationType == 'Online' )
			$pemFile='/var/websites/oncam_com/webroot/certs/aps_production.pem';
			
		$certPass = '12345'; 
		$sound="default";
		$badge="default";		
		$body['aps'] = array(
			'alert' => $message,
			'sound' => ($sound ? $sound : "default"),
			'badge' => ($badge ? $badge : "default"),
			'user_id' =>$user_id,
			'username' => $username
			);
		$ctx = stream_context_create();
		stream_context_set_option($ctx, 'ssl', 'local_cert', $pemFile);
		//stream_context_set_option($ctx, 'ssl', 'passphrase', $certPass);

		if ( $notificationType == 'develop' )
			$ssl_gateway_url = 'ssl://gateway.sandbox.push.apple.com:2195';
		else if ( $notificationType == 'Online' )
			$ssl_gateway_url = 'ssl://gateway.push.apple.com:2195';
		
		if(isset($ssl_gateway_url))
		{
			$fp = stream_socket_client($ssl_gateway_url, $err, $errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);
		}
		
		$payload = json_encode($body);
			
		foreach($deviceTokens as $deviceToken){
			$deviceIdab = trim($deviceToken['device_id']);
			$msg = chr(0).pack('n', 32).pack('H*', str_replace(' ', '',$deviceIdab)).pack('n', strlen($payload)).$payload;
			$result = fwrite($fp, $msg, strlen($msg));
			$arr[] = $deviceIdab;
		}
		fclose($fp);
		return $arr;
	}
	public function push_notification_android($user_id,$message="") {
		$headers = array(
		 'Content-Type:application/json',
		 'Authorization:key=AIzaSyDYNt9ftmzDT2aExpnyxP6pkmeMkacbQU4'
		);
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$device = $resource->getTableName('mobile_device');
		$select = "select device_id from $device where type IN ('Android') and device_id!='' and user_id=".$user_id;
		$deviceTokens = $read->fetchAll($select);
		$count = count($deviceTokens);
		$username = $this->getUserNameByUserId($user_id);
		$arr   = array();
		$arr['data']['user_id'] = $user_id;
		$arr['data']['username'] = $username;
		$arr['data']['msg'] = $message;
		$arr['data']['count'] = $count;
		$arr['registration_ids'] = array();
		
		foreach($deviceTokens as $k=>$dc){
			$arr['registration_ids'][$k] = $dc["device_id"];
		}
		//return $arr;	
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,    'https://android.googleapis.com/gcm/send');
		curl_setopt($ch, CURLOPT_HTTPHEADER,  $headers);
		curl_setopt($ch, CURLOPT_POST,    true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($arr));
		try{
		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
		} catch (Exception $e){
			Mage::log($e,null,'gcm.log');
		}
	}
	public function push_notification_follower_iphone($user_id,$notificationType="Online", $message="")
	{
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$device = $resource->getTableName('mobile_device');
		$follower = $resource->getTableName('follower');
		//$select = "select device_id from $device where notificationType='".$notificationType."' and type IN ('iPhone','iPad') and device_id!='' and active=1 and user_id IN(select follower_id from $follower WHERE follow=".$user_id." and follower_id<>follow and status=1 group by follower_id)";
		
		$select = "select cs_mobile_device.device_id from cs_mobile_device JOIN cs_follower ON cs_mobile_device.user_id = cs_follower.follower_id where cs_follower.follow=".$user_id." and cs_follower.follower_id<>cs_follower.follow and cs_follower.status=1 and cs_mobile_device.notificationType='".$notificationType."' and cs_mobile_device.type IN ('iPhone','iPad') and cs_mobile_device.device_id!='' and cs_mobile_device.active=1 group by cs_mobile_device.device_id";
		$deviceTokens = $read->fetchAll($select);
		$username = $this->getUserNameByUserId($user_id);
		if ( $notificationType == 'develop' )
			$pemFile='/var/websites/oncam_com/webroot/certs/aps_development.pem';
		else if ( $notificationType == 'Online' )
			$pemFile='/var/websites/oncam_com/webroot/certs/aps_production.pem';
			
		$certPass = '12345'; 
		$sound="default";
		$badge="default";		
		$body['aps'] = array(
			'alert' => $message,
			'sound' => ($sound ? $sound : "default"),
			'badge' => ($badge ? $badge : "default"),
			'user_id' =>$user_id,
			'username' => $username
			);
		$ctx = stream_context_create();
		stream_context_set_option($ctx, 'ssl', 'local_cert', $pemFile);
		//stream_context_set_option($ctx, 'ssl', 'passphrase', $certPass);

		if ( $notificationType == 'develop' )
			$ssl_gateway_url = 'ssl://gateway.sandbox.push.apple.com:2195';
		else if ( $notificationType == 'Online' )
			$ssl_gateway_url = 'ssl://gateway.push.apple.com:2195';
		
		if(isset($ssl_gateway_url))
		{
			$fp = stream_socket_client($ssl_gateway_url, $err, $errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);
		}
		
		$payload = json_encode($body);
			
		foreach($deviceTokens as $deviceToken){
			$deviceIdab = trim($deviceToken['device_id']);
			$msg = chr(0).pack('n', 32).pack('H*', str_replace(' ', '',$deviceIdab)).pack('n', strlen($payload)).$payload;
			$result = fwrite($fp, $msg, strlen($msg));
			$arr[] = $deviceIdab;
		}
		fclose($fp);
		return $arr;
	}
	public function push_notification_follower_android($user_id,$message="") {
		$headers = array(
		 'Content-Type:application/json',
		 'Authorization:key=AIzaSyDYNt9ftmzDT2aExpnyxP6pkmeMkacbQU4'
		);
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$device = $resource->getTableName('mobile_device');
		$follower = $resource->getTableName('follower');
		//$select2 = "select device_id from $device where type='Android' and device_id!='' and user_id IN(select follower_id from $follower WHERE follow=".$user_id." and follower_id<>follow and status=1 group by follower_id)";
		$select2 = "select cs_mobile_device.device_id from cs_mobile_device JOIN cs_follower ON cs_mobile_device.user_id = cs_follower.follower_id where cs_follower.follow=".$user_id." and cs_follower.follower_id<>cs_follower.follow and cs_follower.status=1 and cs_mobile_device.type='Android' and cs_mobile_device.device_id!='' group by cs_mobile_device.device_id";
		$deviceTokens = $read->fetchAll($select2);
		$count = count($deviceTokens);
		$username = $this->getUserNameByUserId($user_id);
		$arr   = array();
		$arr['data']['user_id'] = $user_id;
		$arr['data']['username'] = $username;
		$arr['data']['msg'] = $message;
		$arr['data']['count'] = $count;
		$arr['registration_ids'] = array();
		
		foreach($deviceTokens as $k=>$dc){
			$arr['registration_ids'][$k] = $dc["device_id"];
		}
		//return $arr;	
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,    'https://android.googleapis.com/gcm/send');
		curl_setopt($ch, CURLOPT_HTTPHEADER,  $headers);
		curl_setopt($ch, CURLOPT_POST,    true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($arr));
		try{
		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
		} catch (Exception $e){
			Mage::log($e,null,'gcm.log');
		}
	}
	public function when_people_follow_me($user_id, $type){
	if($user_id > 0){
		$customer = Mage::getSingleton( 'customer/customer' )->load($user_id);
		$notices = $customer->getNotice();
		$a = explode(",",$notices);
		if($type == "on"){
			if(in_array(189,$a)){
			
			} else {
				$a[]=189;
				$str = implode(",",$a);
				$customer->setNotice($str);
				$customer->save();
			}
			return "Activated Successfully ";
		}elseif($type == "off"){
			if(in_array(189,$a)){
			$key = array_search(189, $a);
			unset($a[$key]);
			$str = implode(",",$a);
			$customer->setNotice($str);
			$customer->save();
			}
			return "Deactivated Successfully ";
		}
	}
	}
	public function when_people_follow_go_on($user_id, $type){
	if($user_id > 0){
		$customer = Mage::getSingleton( 'customer/customer' )->load($user_id);
		$notices = $customer->getNotice();
		$a = explode(",",$notices);
		if($type == "on"){
			if(in_array(185,$a)){
			
			} else {
				$a[]=185;
				$str = implode(",",$a);
				$customer->setNotice($str);
				$customer->save();
			}
			return "Activated Successfully ";
		}elseif($type == "off"){
			if(in_array(185,$a)){
			$key = array_search(185, $a);
			unset($a[$key]);
			$str = implode(",",$a);
			$customer->setNotice($str);
			$customer->save();
			}
			return "Deactivated Successfully ";
		}
	}
	}
	public function when_my_contacts_create_events($user_id, $type){
	if($user_id > 0){
		$customer = Mage::getSingleton( 'customer/customer' )->load($user_id);
		$notices = $customer->getNotice();
		$a = explode(",",$notices);
		if($type == "on"){
			if(in_array(183,$a)){
			
			} else {
				$a[]=183;
				$str = implode(",",$a);
				$customer->setNotice($str);
				$customer->save();
			}
			return "Activated Successfully ";
		}elseif($type == "off"){
			if(in_array(183,$a)){
			$key = array_search(183, $a);
			unset($a[$key]);
			$str = implode(",",$a);
			$customer->setNotice($str);
			$customer->save();
			}
			return "Deactivated Successfully ";
		}
	}
	}
	public function when_my_contacts_call_me($user_id, $type){
	if($user_id > 0){
		$customer = Mage::getSingleton( 'customer/customer' )->load($user_id);
		$notices = $customer->getNotice();
		$a = explode(",",$notices);
		if($type == "on"){
			if(in_array(181,$a)){
			
			} else {
				$a[]=181;
				$str = implode(",",$a);
				$customer->setNotice($str);
				$customer->save();
			}
			return "Activated Successfully ";
		}elseif($type == "off"){
			if(in_array(181,$a)){
			$key = array_search(181, $a);
			unset($a[$key]);
			$str = implode(",",$a);
			$customer->setNotice($str);
			$customer->save();
			}
			return "Deactivated Successfully ";
		}
	}
	}
	public function when_my_contacts_text_message_me($user_id, $type){
	if($user_id > 0){
		$customer = Mage::getSingleton( 'customer/customer' )->load($user_id);
		$notices = $customer->getNotice();
		$a = explode(",",$notices);
		if($type == "on"){
			if(in_array(179,$a)){
			
			} else {
				$a[]=179;
				$str = implode(",",$a);
				$customer->setNotice($str);
				$customer->save();
			}
			return "Activated Successfully ";
		}elseif($type == "off"){
			if(in_array(179,$a)){
			$key = array_search(179, $a);
			unset($a[$key]);
			$str = implode(",",$a);
			$customer->setNotice($str);
			$customer->save();
			}
			return "Deactivated Successfully ";
		}
	}
	}
	public function when_my_contacts_dropin_on_me($user_id, $type){
	if($user_id > 0){
		$customer = Mage::getSingleton( 'customer/customer' )->load($user_id);
		$notices = $customer->getNotice();
		$a = explode(",",$notices);
		if($type == "on"){
			if(in_array(187,$a)){
			
			} else {
				$a[]=187;
				$str = implode(",",$a);
				$customer->setNotice($str);
				$customer->save();
			}
			return "Activated Successfully ";
		}elseif($type == "off"){
			if(in_array(187,$a)){
			$key = array_search(187, $a);
			unset($a[$key]);
			$str = implode(",",$a);
			$customer->setNotice($str);
			$customer->save();
			}
			return "Deactivated Successfully ";
		}
	}
	}
	public function blockUser($user_id, $block_user_id, $type){
		$resource = Mage::getSingleton('core/resource');
		$write = $resource->getConnection('core_write');
		$read = $resource->getConnection('core_read');
		if($user_id > 0 && $block_user_id > 0){
			if($type == "on"){
				$write->query("insert into cs_block_user (user_id, block_user_id) values(".$user_id.", ".$block_user_id.")");
				return "Blocked Successfully ";
			}elseif($type == "off"){
				$select = "select * from cs_block_user WHERE user_id=".$user_id." and block_user_id=".$block_user_id." and status=1";
				$results = $read->fetchAll($select);
				if(count($results)>0){
				$write->query("update cs_block_user set status=0 where user_id=".$user_id." and block_user_id = ".$block_user_id);
				return "Unblocked Successfully ";
				}
			}
		}
	}
	public function getListBlockedUser($user_id, $page=1){
		$resource = Mage::getSingleton('core/resource');
		$write = $resource->getConnection('core_write');
		$read = $resource->getConnection('core_read');
		$select = "select * from cs_block_user WHERE user_id=".$user_id." and status=1";
		$limit = 15;
		if($page<=0)
			$page=1;
		$page=$page-1;
		$select.= " limit ".$limit*$page .", " .$limit;
		$results = $read->fetchAll($select);
		if(count($results)>0){	
			$count = count($results);
			if($count > ($limit*($page+1))){
				$showMore = "true";
			} else {
				$showMore = "false";
			}
		
		foreach($results as $k=>$rs){
			$customer = Mage::getModel('customer/customer')->load($rs['block_user_id']);
			$item[$k]=array(
						'id'=> $rs['id'],
						'user_id'=>$rs['block_user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'name'=>$customer->getFirstname().' '.$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['block_user_id']),
						'showMore'=>$showMore,
					); 
		}
		$item['showMore'] = $showMore;
		return $item;
	} else{
		return "No records found";
	}
	}
	public function getMyFriendsLiveEvents($user_id=0,$page=1){
		if($user_id > 0){
		$cat=3; $limit=5;
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		if($time_zone == 'America/Los_Angeles'){
			$now = strtotime('+1 hour')+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime('+1 hour'));
		} else{
			$now = strtotime(now())+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime(now()));
		}
			
		$websiteId = Mage::app()->getWebsite()->getId();
		$storeId = Mage::app()->getStore()->getId();
		
		$visibility = array(	
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
              );

      		$events = Mage::getModel('catalog/category')->load($cat)
							->getProductCollection()
							->addAttributeToSelect('*')
							->addAttributeToSelect('category_id')
							->addAttributeToSelect('status')
							->addFieldToFilter('status', 1)
							->addAttributeToFilter('is_expired', array('neq' => 1))
							->addAttributeToSort('news_from_date', 'asc')
							//->addAttributeToSort('position', 'desc')
							->addAttributeToFilter('news_to_date', array('gteq' => $date));	
			
			$events->addAttributeToFilter('visibility', $visibility)				
							->setPageSize($limit)
							->setPage($page, $limit)							
							->load()->toArray();
			$lastPage = $events->getLastPageNumber();
			if($lastPage > $page){
				$showMore = "true";
			} else {
				$showMore = "false";
			}
			
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$follower = $resource->getTableName('follower');
			$select = "select follow from $follower WHERE follower_id=".$user_id." and follower_id<>follow and status=1";						
			$followers = $read->fetchAll($select);

			$resultArray = '';
			$str='';
			$c=0;
			foreach($followers as $follower){	$c = $c+1;	
					$str[$c]=$follower['follow'];
					$c = $c+1;
			}
		
			$counter=0;
			if(count($events)>0){ 
			if($lastPage >= $page){
				foreach ($events as $k => $event) { $counter++;
				//===================Start Image=====================================
					$customer = Mage::getModel('customer/customer')->load($event['user_id']);
					$fc = strtolower(substr($customer->getFirstname(),0,1));
					if($fc == ''){ $fc = rand(1,10); }

					if($event['event_image']=="''")
					$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
					else{
						if($event['event_image']){
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];
							
							if(fopen($img_url,"r")==false)
								$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
							else
								$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];
						}
						else
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];		
					}
				//===================End Image=====================================
				$rsvp = $resource->getTableName('rsvp');
					$selectSql = "select * from $rsvp WHERE user_id=".$user_id." and event_id=".$event['entity_id']." and status=1";			
					$row = $read->fetchAll($selectSql);
					if(count($row)>0)
						$rsvpStatus=1;
					else
						$rsvpStatus=0;
						
					$from = strtotime($event['news_from_date'])+$timeoffset;
					$to = strtotime($event['news_to_date'])+$timeoffset;
					if (($now > $from) && ($now < $to)) {
						$isLive="true";
					}
					else{
						$isLive="false";
					}
					if((in_array($event['user_id'],$str)) && ($isLive == "true")){
						$myFollowersEvent = "true";
					}
					else {
						$myFollowersEvent = "false";
					}
				if($myFollowersEvent == "true"){
					if($this->isUserOnline($event['user_id'])){
						$HostIsLive = "true";
					}else{
						$HostIsLive = "false";
					}
					$result[$counter]['followersLiveEvent'] = array(
							'id'=> $event['entity_id'],
							'name'=> $event['name'],
							'price'=> number_format($event['price'],2),
							'user_id'=> $event['user_id'],
							'event_hostedby_username'=> $this->getUserNameByUserId($event['user_id']),
							'description'=> $event['description'],
							'from_date'=> date('D M d, Y h:i A', strtotime($event['news_from_date'])+$timeoffset)." ".$abbrev,
							'to_date'=> date('D M d, Y h:i A',strtotime($event['news_to_date'])+$timeoffset)." ".$abbrev,
							'from_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_from_date'])))),
							'to_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_to_date'])))),
							'image'			=> $img_url,						
							'islive'			=> $isLive,
							'rsvpStatus'	=>$rsvpStatus,
							'category'  => $this->getCategoryNameByEventId($event['entity_id']),
							'from_date1'=> date('m-d-Y H:i:s', strtotime($event['news_from_date'])+$timeoffset),
							'to_date1'=> date('m-d-Y H:i:s',strtotime($event['news_to_date'])+$timeoffset),
							'HostIsLive' => $HostIsLive,
						);			
					}
				}
				
				}
				$result['followersLiveEvent'] = $result['followersLiveEvent'];
				$result['showMore'] = $showMore;
				return $result;
			}
				
		}
	}
	public function setVideoSpam($user_id, $video_id){
	
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$sql = "select flagcount from cs_video where video_id=".$video_id;
		$result = $read->fetchAll($sql);
		$sqlflag = "select * from cs_videoflag where video_id=".$video_id." and user_id=".$user_id;
		$resultflag = $read->fetchAll($sqlflag);
		if((count($result) > 0) && (count($resultflag) == 0)){
			$flagCount = $result[0]['flagcount']+1;
			$sqlUpdate = "update cs_video set flagcount=".$flagCount." where video_id=".$video_id;
			$write->query($sqlUpdate);
			$sqlInsert = "insert into cs_videoflag (user_id, video_id, created_on) values (".$user_id.",".$video_id.",now());";
			$write->query($sqlInsert);
			return "Video marked as a spam";
		}
	}
	public function getFavouriteContacts($user_id=0){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write= $resource->getConnection('core_write');
		$follower = $resource->getTableName('follower');
		$customer_entity = $resource->getTableName('customer_entity');
		$select = "select * from $follower, $customer_entity WHERE follower_id='".$user_id."' and follower_id<>follow and status=1 and $customer_entity.entity_id=$follower.follow group by follow";
		$follower = $read->fetchAll($select);
		$i = 0;
		foreach($follower as $k=>$flwr){
			$flag = $this->isFollowForChat($user_id, $flwr['follow']);
			if($flag){
				$i = $i+1;
					$customer = Mage::getModel('customer/customer')->load($flwr['follow']);
					$item['contacts'][$k]=array(
						'user_id'=>$flwr['follow'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'email'=>$customer->getEmail(),
						'image'=>$this->getProfilePic($flwr['follow']),
					); 
			}
		}
	//===============================================================================================
		$contactsTab = $resource->getTableName('mobile_contacts');
		$select = "select DISTINCT a.verified_user_id from cs_mobile_contacts a, cs_mobile_contacts b where a.contact1 = b.contact1 and  b.owner_user_id =".$user_id." and b.verified_user_id IS NULL and a.verified_user_id > 0";
		$results = $read->fetchAll($select);
		foreach($results as $k=>$ar){
			$customer = Mage::getModel('customer/customer')->load($ar['verified_user_id']);
			$item['contacts'][$k]=array(
						'user_id'=>$ar['verified_user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'email'=>$customer->getEmail(),
						'image'=>$this->getProfilePic($ar['verified_user_id']),
			);
		}
		return $item;
	}
	public function isFollowForChat($user_id, $id, $status=1) {
	
		$customer_id = $id;
		
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$follower = $resource->getTableName('follower');
		$customer_entity = $resource->getTableName('customer_entity');
		
		$select = "select id from $follower WHERE follow='".$user_id."' and follower_id='".$customer_id."' and follower_id<>follow and status=".$status;
		
		$follower = $read->fetchRow($select);				
		if($follower > 0){
			return 1;
		}
		else {
			return 0;
		}
	}
	public function findPeopleToList($user_id, $search='', $page=1){	

			$limit=15;
			$search = mysql_real_escape_string(trim($search));
			$searchString = strstr($search, ' ', true);
			
			if($searchString !=""){
				$lastString1 = strstr($search, ' ');
				$lastString = ltrim($lastString1);
				$collection = Mage::getResourceModel('customer/customer_collection')
				->addAttributeToSelect('*')
				->addAttributeToFilter(array(
                                            array(
												'attribute' => 'firstname',
												'like'        => $searchString.'%',
												),
											array(
												'attribute' => 'lastname',
												'like'        => $lastString.'%',
												),
												
											));
			}else{
				$collection = Mage::getResourceModel('customer/customer_collection')
					->addAttributeToSelect('*')
					->addAttributeToFilter(array(
												array(
													'attribute' => 'username',
													'like'        => '%'.$search.'%',
													),
	                                            array(
													'attribute' => 'firstname',
													'like'        => '%'.$search.'%',
													),
												array(
													'attribute' => 'lastname',
													'like'        => '%'.$search.'%',
													),
													
												));
			}								
			//Get Total Result without limit
			$c = clone $collection;
			$total_collection = $c->load()->toArray();			
			$total_count = count($total_collection);			

			$collection = $collection->setPageSize($limit)->setPage($page, $limit);
			$lastPage = $collection->getLastPageNumber();
			$inv_collection = $collection->load()->toArray();

			if($lastPage > $page){
				$showMore = "true";
			} else {
				$showMore = "false";
			}
			if($lastPage >= $page){
			foreach($inv_collection as $k=>$user){
				//$customer = Mage::getModel('customer/customer')->load($user['entity_id']);
				$follower = $this->isFollow($user['entity_id'],$user_id);
				if($follower!=1){
					$status = "Follow";
					$isFollow = "false";
					$mesg = 1;					
				}
				else{ 
					$status = "Unfollow";
					$isFollow = "true";
					$mesg = 2;
				}
				$data[$k] = array(
						'id'=>$user['entity_id'],
						'username'=>$user['username'],
						'name'=>$user["firstname"].' '.$user["lastname"],
						'firstname'=>$user['firstname'],
						'lastname'=>$user['lastname'],
						'email'=>$user["email"],
						'shortbio'=>$user["shortbio"],
						'location'=>$user["location"],
						'isFollow' => $isFollow,
						'image'=>$this->getProfilePic($user['entity_id']),						
					); 
			  }
			}
			$data['showMore'] = $showMore;
			$data['totalcount'] = $total_count;			
			return $data;
	}
	public function getReplayVideos($user_id, $page=1){
		if($user_id > 0){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$videoTable = $resource->getTableName('video');
		$select = 'select video_id, title, identifier, description, profile_id, user_id, video_path, thumbnail_path, duration, tags, created_time  from '.$videoTable.' where isdeleted = 0 and status = 1 and show_home=1 order by video_id desc';
		
		$limit = 10;
		if($page<=0)
			$page=1;
		$page=$page-1;
		$select.= ' limit '.$limit*$page.', '.$limit;
		$results = $read->fetchAll($select);
		if(count($results)>0){	
			$count = count($results);
			if($count > ($limit*($page+1))){
				$showMore = "true";
			} else {
				$showMore = "false";
			}
		
		foreach($results as $k=>$r){
			$customer = Mage::getModel('customer/customer')->load($r['user_id']);
			$item[$k] = array(
							'id'=> $r['video_id'],
							'title'=> $r['title'],
							'description'=> $r['description'],
							'profile_id'=> $r['profile_id'],
							'user_id'=> $r['user_id'],
							'username'=> $customer->getUsername(),
							'firstname'=> $customer->getFirstname(),
							'lastname'=> $customer->getLastname(),
							'views'=> Mage::getModel('csservice/csservice')->getCheckinCount($r['user_id']),
							'video_path'=> $r['video_path'],
							'thumbnail_path'=> $r['thumbnail_path'],
							'duration'=> $r['duration'],
							'created_time' => $r['created_time'].' GMT',
							'created_time2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($r['created_time'])))),
							);
		}
		$item['showMore']=$showMore;
		return $item;
		}else
			return 0;
		}	
	}
	public function FindReplayVideos($user_id, $page=1, $search){
		if($user_id > 0){
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		$search = mysql_real_escape_string(trim($search));
		$videoTable = $resource->getTableName('video');
		
		$select ='select * from '.$videoTable.' where title like "%'.$search.'%" AND isdeleted = 0 AND status = 1 order by video_id desc';
		$selectCount ='select count(*) as count from '.$videoTable.' where title like "%'.$search.'%" AND isdeleted = 0 AND status = 1 order by video_id desc';
		
		$limit = 15;
		if($page<=0)
			$page=1;
		$page=$page-1;
		$select.= ' limit '.$limit*$page.', '.$limit;
		$results = $read->fetchAll($select);
		$resultCount = $read->fetchRow($selectCount);
		if($resultCount['count'] > 0){	
			$count = $resultCount['count'];
			if($count > ($limit*($page+1))){
				$showMore = "true";
			} else {
				$showMore = "false";
			}
		
		foreach($results as $k=>$r){
			$customer = Mage::getModel('customer/customer')->load($r['user_id']);
			$item[$k] = array(
							'id'=> $r['video_id'],
							'title'=> $r['title'],
							'description'=> $r['description'],
							'profile_id'=> $r['profile_id'],
							'user_id'=> $r['user_id'],
							'username'=> $customer->getUsername(),
							'firstname'=> $customer->getFirstname(),
							'lastname'=> $customer->getLastname(),
							//'views'=> Mage::getModel('csservice/csservice')->getCheckinCount($r['user_id']),
							'views'=> $r['view_count'],
							'video_path'=> $r['video_path'],
							'thumbnail_path'=> $r['thumbnail_path'],
							'duration'=> $r['duration'],
							'created_time' => $r['created_time'].' GMT',
							'created_time2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($r['created_time'])))),
							);
		}
		$item['showMore']=$showMore;
		return $item;
		}else
			return 0;
		}	
	}
	public function getPeopleList($user_id, $search="", $page=1){
			 $limit=10;
			$collection = Mage::getResourceModel('customer/customer_collection')
				->addAttributeToSelect('*')
				 ->addAttributeToFilter('entity_id', array('neq' => $user_id))
				->addAttributeToFilter(array(
											array(
												'attribute' => 'username',
												'like'        => '%'.$search.'%',
												),
											array(
												'attribute' => 'firstname',
												'like'        => '%'.$search.'%',
												),
											));
			$collection = $collection->setPageSize($limit)->setPage($page, $limit);
			$lastPage = $collection->getLastPageNumber();
			$collection = $collection->load()->toArray();
			if($lastPage > $page){
				$showMore = "true";
			} else {
				$showMore = "false";
			}
			if($lastPage >= $page){
			foreach($collection as $k=>$user){
				//$customer = Mage::getModel('customer/customer')->load($user['entity_id']);
				$follower = $this->isFollow($user['entity_id'],$user_id);
				if($follower!=1){
					$status = "Follow";
					$isFollow = "false";
					$mesg = 1;					
				}
				else{ 
					$status = "Unfollow";
					$isFollow = "true";
					$mesg = 2;
				}
				//===============================================
				$resource = Mage::getSingleton('core/resource');
				$write = $resource->getConnection('core_write');
				$read = $resource->getConnection('core_read');
				$select = "select count(*) as count from cs_block_user WHERE user_id=".$user_id." and block_user_id=".$user['entity_id']." and status=1";
				$results = $read->fetchRow($select);
				if($results['count']>0){
					$blockUser = "true";
				}else{
					$blockUser = "false";
				}
				//===============================================
				$data[$k] = array(
						'id'=>$user['entity_id'],
						'username'=>$user['username'],
						'firstname'=>$user['firstname'],
						'lastname'=>$user['lastname'],
						'name'=>$user['firstname'].' '.$user['lastname'],
						'isFollow' => $isFollow,
						'image'=>$this->getProfilePic($user['entity_id']),
						'block'=>$blockUser,
					); 
			
			}
			}
			$data['showMore'] = $showMore;
			return $data;
	}
	public function getUpcomingEventsHashtag($user_id=0,$hashtag, $page=1){
	$hashtag = urldecode($hashtag);
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		if($time_zone == 'America/Los_Angeles'){
			$now = strtotime('+1 hour')+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime('+1 hour'));
		} else{
			$now = strtotime(now())+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime(now()));
		}
		$events = array();
			$events = Mage::getResourceModel('catalog/product_collection')
					   ->addAttributeToSelect('*')
					   ->addAttributeToFilter('news_from_date', array('gteq' => $date))
					   ->addFieldToFilter('attribute_set_id', 9)
					   ->addAttributeToFilter('status', 1)
					   ->addAttributeToFilter('hashtag', array('eq' => $hashtag));
					$events->setOrder('news_from_date', 'asc');
					$events->setOrder('entity_id', 'desc');
			$limit = 15;
			if($page<=0)
				$page=1;
			//$page=$page-1; 
			$events = $events->setPageSize($limit)->setPage($page, $limit);
			$lastPage = $events->getLastPageNumber();			
			$events = $events->load()->toArray();
			if($lastPage > $page){
				$showMore = "true";
			} else {
				$showMore = "false";
			}
			$items = array();
			if($lastPage >= $page){
			foreach($events as $k=>$evt){
				//===================Start Image=====================================
					$customer = Mage::getModel('customer/customer')->load($evt['user_id']);
					$fc = strtolower(substr($customer->getFirstname(),0,1));
					if($fc == ''){ $fc = rand(1,10); }

					if($evt['event_image']=="''")
					$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
					else{
						if($evt['event_image']){
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$evt['event_image'];
							
							if(fopen($img_url,"r")==false)
								$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
							else
								$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$evt['event_image'];
						}
						else
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$evt['event_image'];		
					}
				//===================End Image=====================================
				$items[$k] = array(
								'id'=>$evt['entity_id'],
								'name'=>$evt['name'],
								'user_id'=>$evt['user_id'],
								'event_hostedby_username'=> $customer->getUsername(),
								'event_hostedby_firstname'=> $customer->getFirstname(),
								'event_hostedby_lastname'=> $customer->getLastname(),
								'price'=>$evt['price'],
								'description'=>$evt['description'],
								'event_date'=>date('D M d, Y h:i A', strtotime($evt['news_from_date'])+$timeoffset)." ".$abbrev,
								'event_end_date'=>date('D M d, Y h:i A', strtotime($evt['news_to_date'])+$timeoffset)." ".$abbrev,
								'from_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($evt['news_from_date'])))),
								'to_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($evt['news_to_date'])))),
								'from_date3'=>date('m-d-Y H:i:s', strtotime($evt['news_from_date'])+$timeoffset),/*Added By surinder on demand of ajumal*/
								'to_date3'=>date('m-d-Y H:i:s',strtotime($evt['news_to_date'])+$timeoffset),/*Added By surinder on demand of ajumal*/
								'thumbnail'=>$img_url,
								'thumb_image'	=> $img_url,
								'location'=>$evt['location'],
								'category'=>$this->getCategoryNameByEventId($evt['entity_id']),
								//'url'=>Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).'live-events/'.$evt['url_path'],
								//'isEventAccess'=>$this->isEventAccess($user_id, $evt['entity_id']),
								'hashtags'=> $evt['hashtag'],
							);
						
			}
			}
			$evt_count = count($events);
			$result=array();
			$result['data']=$items;
			$result['showMore']=$showMore;
			$result['count']=$evt_count;
			return $result;
	}
	public function getLiveEventsHashtag($user_id=0, $hashtag, $page=1) {
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		if($user_id > 0){
		$cat=3; $limit=5;
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		if($time_zone == 'America/Los_Angeles'){
			$now = strtotime('+1 hour')+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime('+1 hour'));
		} else{
			$now = strtotime(now())+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime(now()));
		}
			
		$websiteId = Mage::app()->getWebsite()->getId();
		$storeId = Mage::app()->getStore()->getId();
		
		$visibility = array(	
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
              );

      		$events = Mage::getModel('catalog/category')->load($cat)
							->getProductCollection()
							->addAttributeToSelect('*')
							->addAttributeToSelect('category_id')
							->addAttributeToSelect('status')
							->addFieldToFilter('status', 1)
							->addAttributeToFilter('is_expired', array('neq' => 1))
							->addAttributeToSort('news_from_date', 'asc')
							//->addAttributeToSort('position', 'desc')
							->addAttributeToFilter('news_to_date', array('gteq' => $date));	
			if($hashtag!=''){
				$events->addAttributeToFilter('hashtag', $hashtag);
			}
			$events->addAttributeToFilter('visibility', $visibility)				
							->setPageSize($limit)
							->setPage($page, $limit)							
							->load()->toArray();
			$lastPage = $events->getLastPageNumber();
			if($lastPage > $page){
				$showMore = "true";
			} else {
				$showMore = "false";
			}
			if(count($events)>0){ 
			if($lastPage >= $page){
				
				$counter=0;
				foreach ($events as $k => $event) { $counter++;
				//===================Start Image=====================================
					$customer = Mage::getModel('customer/customer')->load($event['user_id']);
					$fc = strtolower(substr($customer->getFirstname(),0,1));
					if($fc == ''){ $fc = rand(1,10); }

					if($event['event_image']=="''")
					$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
					else{
						if($event['event_image']){
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];
							
							if(fopen($img_url,"r")==false)
								$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
							else
								$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];
						}
						else
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];		
					}
				//===================End Image=====================================
					$rsvp = $resource->getTableName('rsvp');
					$selectSql = "select * from $rsvp WHERE user_id=".$user_id." and event_id=".$event['entity_id']." and status=1";			
					$row = $read->fetchAll($selectSql);
					if(count($row)>0)
						$rsvpStatus=1;
					else
						$rsvpStatus=0;
					$from = strtotime($event['news_from_date'])+$timeoffset;
					$to = strtotime($event['news_to_date'])+$timeoffset;
					if (($now > $from) && ($now < $to)) {
						$isLive="true";
					}
					else{
						$isLive="false";
					}
				if($isLive == "true"){
					if($this->isUserOnline($event['user_id'])){
						$HostIsLive = "true";
					}else{
						$HostIsLive = "false";
					}
					$result[$counter]['live'] = array(
							'id'=> $event['entity_id'],
							'name'=> $event['name'],
							'price'=> number_format($event['price'],2),
							'user_id'=> $event['user_id'],
							'event_hostedby_username'=> $customer->getUsername,
							'event_hostedby_firstname'=> $customer->getFirstname(),
							'event_hostedby_lastname'=> $customer->getLastname(),
							'description'=> $event['description'],
							'from_date'=> date('D M d, Y h:i A', strtotime($event['news_from_date'])+$timeoffset)." ".$abbrev,
							'to_date'=> date('D M d, Y h:i A',strtotime($event['news_to_date'])+$timeoffset)." ".$abbrev,
							'from_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_from_date'])))),
							'to_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_to_date'])))),
							'image'			=> $img_url,						
							'islive'			=> $isLive,
							'rsvpStatus'	=>$rsvpStatus,
							'numberOfLiveUsers'	=>	$this->getNumberOfUserOnline($event['user_id']),
							'category'  => $this->getCategoryNameByEventId($event['entity_id']),
							'from_date1'=> date('m-d-Y H:i:s', strtotime($event['news_from_date'])+$timeoffset),
							'to_date1'=> date('m-d-Y H:i:s',strtotime($event['news_to_date'])+$timeoffset),
							'HostIsLive' => $HostIsLive,
							'hashtags'=> $event['hashtag'],
						);			
					}
				}
				
				}
				//$result['onlineUser']= $this->getOnlineUser($page);
				$result['live'] = $result['live'];
				$result['showMore'] = $showMore;
				return $result;
			}
			else{
				$result['onlineUser']= $this->getOnlineUser($page);
				return $result;
			}
				
		}
	}

	public function getliveinpubliccall($user_id, $event_id =0){
		if ($user_id>0){
			
			$host_id = intval($user_id);
			$event_id = intval($event_id);

			$customer = Mage::getModel('customer/customer')->load($host_id);

			$resource = Mage::getSingleton('core/resource');
			$read = $resource->getConnection('core_read');
			$write = $resource->getConnection('core_write');
			$table = $resource->getTableName('user_activities');
						
			$select ="select
			 * from $table where profile_id=".$host_id." and event_id=".$event_id." and status = 1";
			$livehosts = $read->fetchAll($select);

			$i =0 ;
			if(count($livehosts)>0){
				foreach ($livehosts as $k => $livehost){
					$hosts[$i] = $livehost['user_id'];					
					$i++;
				}				
				$result['livehosts'] = $hosts;
			}
			//get participant = (checkin) - (checkout)	
			$participants = count($result);
			$result['participant'] = $participants;
			// get hashtag
			$hashtags = Mage::getResourceModel('catalog/product_collection')
					//->addAttributeToSelect('entity_id')
					->addAttributeToSelect('hashtag')
					->addFieldToFilter('entity_id', array('eq'=> $event_id))
					->addFieldToFilter('user_id', array('eq'=> $user_id))
					->addFieldToFilter('status', 1)					
					->load()->toArray();
			
			if(count($hashtags)>0){
				foreach ($hashtags as $k => $hashtag){
					$hashtag = $hashtag['hashtag'];					
				}				
				$result['hashtag'] = $hashtag;
			}

			return $result;
				
		}else{
			return false;
		}
	}
	public function getTopHashtag($user_id=0){
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$events = Mage::getResourceModel('catalog/product_collection')
				   ->addAttributeToSelect('hashtag')
				   ->addFieldToFilter('attribute_set_id', 9)
				   ->addFieldToFilter('hashtag', array('neq' => ''))
				   ->addAttributeToFilter('status', 1)
				   ->setOrder('news_from_date', 'asc')
				   ->setOrder('entity_id', 'desc');
		$events = $events->setPageSize(15)->setPage(1, 15);
		$events = $events->load()->toArray();
		if(count($events)>0){
			$items = array();
			foreach($events as $k=>$evt){
				$items[$k] = $evt['hashtag'];
			}
			return $items;
		}else{
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$select = "select hashtag from cs_hashtag limit 0,15";
			$results = $read->fetchAll($select);
			foreach($results as $k=>$rs){
				$items[$k] = $rs["hashtag"];
			}
			return $items;
		}
	}
	public function dropinUser($user_id, $dropin_user_id, $type){
		$resource = Mage::getSingleton('core/resource');
		$write = $resource->getConnection('core_write');
		$read = $resource->getConnection('core_read');
		if($user_id > 0 && $dropin_user_id > 0){
			if($type == "on"){
				$write->query("insert into cs_dropin_user (user_id, dropin_user_id) values(".$user_id.", ".$dropin_user_id.")");
				//================================================
				$newsfeed = $resource->getTableName('newsfeed');
				$write->query("insert into $newsfeed (user_id, profile_id, cat_id) values(".$dropin_user_id.", ".$user_id.",9)");
				//===================================================
				return "drop-in Successfully ";
			}elseif($type == "off"){
				$select = "select * from cs_dropin_user WHERE user_id=".$user_id." and dropin_user_id=".$dropin_user_id." and status=1";
				$results = $read->fetchAll($select);
				if(count($results)>0){
				$write->query("update cs_dropin_user set status=0 where user_id=".$user_id." and dropin_user_id = ".$dropin_user_id);
				return "Drop-out Successfully ";
				}
			}
		}
	}
	
	public function getAutoCompleteHashtag($user_id, $hashtag){
		$events = Mage::getResourceModel('catalog/product_collection')
				   ->addAttributeToSelect('hashtag')
				   ->addFieldToFilter('attribute_set_id', 9)
				   ->addAttributeToFilter('status', 1)
				   ->addAttributeToFilter('hashtag', $hashtag);
		$events = $events->load()->toArray();
		if(count($events)>0){
			$items = array();
			foreach($events as $k=>$evt){
				$items[$k] = $evt['hashtag'];
			}
			return $items;
		}else{
			return "No results found";
		}
	}
	public function getFriendList($user_id){
		$chatresult = Mage::getModel('csservice/csservice')->getFollowingChat($user_id);
		return $chatresult;
	}
	public function setPublicCallNotificationAppClosed($user_id, $notify_user_id, $status){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		if($status == "on"){
			$write->query("update cs_follower set app_closed=1 WHERE follower_id='".$user_id."' and follow='".$notify_user_id."' and status=1");
			return "Notify Successfully ";
		}elseif($status == "off"){
			$write->query("update cs_follower set app_closed=0 WHERE follower_id='".$user_id."' and follow='".$notify_user_id."' and status=1");
			return "Not Notify Successfully ";
		}
	}
	public function setPublicCallNotificationAppOpen($user_id, $notify_user_id, $status){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		if($status == "on"){
			$write->query("update cs_follower set app_open=1 WHERE follower_id='".$user_id."' and follow='".$notify_user_id."' and status=1");
			return "Notify Successfully ";
		}elseif($status == "off"){
			$write->query("update cs_follower set app_open=0 WHERE follower_id='".$user_id."' and follow='".$notify_user_id."' and status=1");
			return "Not Notify Successfully ";
		}
	}
	public function setDropInOnMe($user_id, $following_user_id, $status){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		if($status == "on"){
			$write->query("update cs_follower set drop_in_on_me=1 WHERE follower_id='".$user_id."' and follow='".$following_user_id."' and status=1");
			return "Dropin Successfully ";
		}elseif($status == "off"){
			$write->query("update cs_follower set drop_in_on_me=0 WHERE follower_id='".$user_id."' and follow='".$following_user_id."' and status=1");
			return "Removed Dropin Successfully ";
		}
	}
	public function setPublicCallNotification($user_id, $following_user_id, $type){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		if($type == "on"){
			$write->query("update cs_follower set push_notify=1 WHERE follower_id='".$user_id."' and follow='".$following_user_id."' and status=1");
			return "Notify Successfully ";
		}elseif($type == "off"){
			$write->query("update cs_follower set push_notify=0 WHERE follower_id='".$user_id."' and follow='".$following_user_id."' and status=1");
			return "Not Notify Successfully ";
		}
	}
	public function getRecommendedUsers($user_id, $page=1){
		
		$limit=10;
		$category='celeb';
		$collection = Mage::getResourceModel('customer/customer_collection')
			->addAttributeToSelect('*')
			->addAttributeToSelect('shortbio')
			->addFieldToFilter('is_suggested', array('gt'=> 0));
			//->addAttributeToFilter('profile_category', array('like' => trim($category).'%')); 
			
			$collection = $collection->setPageSize($limit)->setPage($page, $limit);
			$lastPage = $collection->getLastPageNumber();
			$collection = $collection->load()->toArray();
			if($lastPage > $page){
				$showMore = "true";
			} else {
				$showMore = "false";
			}
			if($lastPage >= $page){
			foreach($collection as $k=>$user){ //echo "test-".$user['entity_id']; exit;
				//$customer = Mage::getModel('customer/customer')->load($user['entity_id']);
				$follower = $this->isFollow($user['entity_id'], $user_id);
				//$follwer = Mage::getModel('csservice/csservice')->getFollowersCount($user['entity_id']);
				//$host_count = Mage::getModel('events/events')->getEventHostingCount($user['entity_id']);
				//$shortbio=$customer->getShortbio();
				//return "knkj".$follower;
				if($follower!=1){
					$status = "Follow";
					$mesg = 1;					
				}
				else{ 
					$status = "Unfollow";
					$mesg = 2;
				}
				//if($follower!=1){
				$data[$k] = array(
						'id'=>$user['entity_id'],
						'name'=>$user["firstname"].' '.$user["lastname"],
						'username'=>$user["username"],
						'firstname'=>$user["firstname"],
						'lastname'=>$user["lastname"],
						//'follower'=>$follwer,
						'status'=>$status,
						//'events'=>$host_count,
						//'shortbio'=>$shortbio,
						'profile_url'=> '/'.$user["username"],
						'image'=>$this->getProfilePic($user['entity_id']),
					);
				//}
			}
			}
			$data['showMore'] = $showMore;
			return $data;
	}
	public function getLiveEvents($user_id,$nextRecordType="",$startFrom=0) {
		$cat=3; $limit=15;$page=1;
		if($nextRecordType=="topUsers"){
			return "No more records found";
		}
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		if($time_zone == 'America/Los_Angeles'){
			$now = strtotime('+1 hour')+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime('+1 hour'));
		} else{
			$now = strtotime(now())+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime(now()));
		}
		
		$visibility = array(	
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
              );

      		$events = Mage::getResourceModel('catalog/product_collection')
							->addAttributeToSelect('user_id')
							->addAttributeToSelect('event_image')
							->addAttributeToSelect('news_from_date')
							->addAttributeToSelect('news_to_date')
							->addAttributeToSelect('entity_id')
							->addAttributeToSelect('name')
							->addAttributeToSelect('price')
							->addAttributeToSelect('description')
							->addAttributeToSelect('hashtag')
							->addFieldToFilter('status', 1)
							->addAttributeToFilter('is_expired', array('neq' => 1))
							->addAttributeToSort('news_from_date', 'asc')
							//->addAttributeToSort('position', 'desc')
							->addAttributeToFilter('news_from_date', array('lteq' => $date))
							->addAttributeToFilter('news_to_date', array('gteq' => $date));	
			
			$events->addAttributeToFilter('visibility', $visibility)				
							->setPageSize($limit)
							->setPage($page, $limit)							
							->load()->toArray();
			$lastPage = $events->getLastPageNumber();
			if($lastPage > $page){
				$showMore = "true";
			} else {
				$showMore = "false";
			}
			
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$follower = $resource->getTableName('follower');
			$select = "select follow from $follower WHERE follower_id=".$user_id." and follower_id<>follow and status=1";						
			$followers = $read->fetchAll($select);

			$resultArray = '';
			$str='';
			$c=0;
			foreach($followers as $follower){	$c = $c+1;	
					$str[$c]=$follower['follow'];
					$c = $c+1;
			}
			$upcoming = 0;
			$live = 0;
			$counter=0; 
			if($lastPage >= $page){
				$counter1=0;
				foreach ($events as $k => $event) {
				//===================Start Image=====================================
					$customer = Mage::getModel('customer/customer')->load($event['user_id']);
					$fc = strtolower(substr($customer->getFirstname(),0,1));
					if($fc == ''){ $fc = rand(1,10); }

					if($event['event_image']=="''")
						$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
					else{
						$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];	
					}
				//===================End Image=====================================
					$rsvp = $resource->getTableName('rsvp');
					$selectSql = "select * from $rsvp WHERE user_id=".$user_id." and event_id=".$event['entity_id']." and status=1";			
					$row = $read->fetchAll($selectSql);
					if(count($row)>0)
						$rsvpStatus=1;
					else
						$rsvpStatus=0;
					$from = strtotime($event['news_from_date'])+$timeoffset;
					$to = strtotime($event['news_to_date'])+$timeoffset;
					if (($now > $from) && ($now < $to)) {
						$isLive="true";
					}
					else{
						$isLive="false";
					}
					if((in_array($event['user_id'],$str)) && ($isLive == "true")){
						$myFollowersEvent = 1;
					}
					else {
						$myFollowersEvent = 0;
					}
				if($isLive == "true"){
				$live++;
					if($this->isUserOnline($event['user_id'])){
						$HostIsLive = "true";
					}else{
						$HostIsLive = "false";
					}
					$result[$counter]['live'] = array(
							'id'=> $event['entity_id'],
							'name'=> $event['name'],
							'price'=> number_format($event['price'],2),
							'user_id'=> $event['user_id'],
							'event_hostedby_username'=> $customer->getUsername(),
							'event_hostedby_firstname'=> $customer->getFirstname(),
							'event_hostedby_lastname'=> $customer->getLastname(),
							'description'=> $event['description'],
							'from_date'=> date('D M d, Y h:i A', strtotime($event['news_from_date'])+$timeoffset)." ".$abbrev,
							'to_date'=> date('D M d, Y h:i A',strtotime($event['news_to_date'])+$timeoffset)." ".$abbrev,
							'from_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_from_date'])))),
							'to_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_to_date'])))),
							'image'			=> $img_url,						
							'islive'			=> $isLive,
							'myfollowersevent'			=> $myFollowersEvent,
							'rsvpStatus'	=>$rsvpStatus,
							//'numberOfLiveUsers'	=>	$this->getNumberOfUserOnline($event['user_id']),
							'category'  => $this->getCategoryNameByEventId($event['entity_id']),
							'from_date1'=> date('m-d-Y H:i:s', strtotime($event['news_from_date'])+$timeoffset),
							'to_date1'=> date('m-d-Y H:i:s',strtotime($event['news_to_date'])+$timeoffset),
							'HostIsLive' => $HostIsLive,
							'hashtags'=> $event['hashtag'],
						);			
					}
					$counter++;
				}
				
				}
				
				$result['live'] = $result['live'];
				function compare_lastname($a, $b){
					return strnatcmp($b['myfollowersevent'], $a['myfollowersevent']);
				}
			  // sort alphabetically by name
				usort($result, 'compare_lastname');
				//========================================================================
				$resource = Mage::getSingleton('core/resource');
				$read= $resource->getConnection('core_read');
				$table = $resource->getTableName('user_activities');
				$sqlSelect = " Select profile_id, user_id, type_of, group_of, site, photo , created_on, status, id, webcam_on, mesg from $table where status > 0";
			$sqlSelect.=" and type_of = 'check-ins'";
			$now = date("Y-m-d H:i:s");
			$sqlSelect.=" and last_pinged_time >  DATE_ADD(now(), INTERVAL '-02:00' MINUTE_SECOND) group by user_id";
			if($limit!=0)
				$sqlSelect.= " limit ".$startFrom.", ".$limit;
			$activities = $read->fetchAll($sqlSelect);
			//return $activities;
			//$sqlcount = " Select count(*) as count from $table where status > 0";
			//$sqlcount.=" and type_of = 'check-ins'";
			//$sqlcount.=" and last_pinged_time >  DATE_ADD(now(), INTERVAL '-02:00' MINUTE_SECOND) group by user_id";
			
			//$activitiesCount = $read->fetchRow($sqlcount);
			
				foreach($activities as $k=>$act){ $counter++;
					if($act['user_id'] == $act['profile_id']){ $live++;
					$customer = Mage::getModel('customer/customer')->load($act['user_id']);
					$username = $customer->getUsername();	
					$result[$counter]['live'] = array(
						'id'=> $act['id'],
							'name'=> $username,
							'price'=> '',
							'user_id'=> $act['user_id'],
							'event_hostedby_username'=> $username,
							'description'=> $username." is live on oncam now",
							'from_date'=> '',
							'to_date'=> '',
							'from_date2'=> '',
							'to_date2'=> '',
							'image'	=> $this->getProfilePic($act['user_id']),						
							'islive'=> '',
							'myfollowersevent'=> '',
							'rsvpStatus'=>'',
							'numberOfLiveUsers'	=>	'',
							'category'  => '',
							'from_date1'=> '',
							'to_date1'=> '',
							'HostIsLive' => '',
							'hashtags'=> '',
					);
					}
				}
				//$data['rowcount'] = count($data);
				//$data['totalcount'] = $activitiesCount['count'];
				//========================================================================
				if($live <= 15){
					$tLimit = 15 - $live;
					$result['topUsers'] = $this->getTopUsersGrid($user_id,$tLimit);
					$result['nextRecordType'] = 'topUsers';
					$result['startFrom'] = $tLimit;
				}
				
				return $result;	
	}
	public function getUpcomingEvents($user_id,$nextRecordType="",$startFrom=0){
		$cat=3; $limit=15;$page=1;
		if($nextRecordType=="topUsers"){
			return "No more records found";
		}
		if($nextRecordType=="events"){
			$divide = $startFrom / $limit;
			$page= $divide + 1;
		}
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		if($time_zone == 'America/Los_Angeles'){
			$now = strtotime('+1 hour')+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime('+1 hour'));
		} else{
			$now = strtotime(now())+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime(now()));
		}
		
		$visibility = array(	
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
              );

      		$events = Mage::getResourceModel('catalog/product_collection')
							->addAttributeToSelect('user_id')
							->addAttributeToSelect('event_image')
							->addAttributeToSelect('news_from_date')
							->addAttributeToSelect('news_to_date')
							->addAttributeToSelect('entity_id')
							->addAttributeToSelect('name')
							->addAttributeToSelect('price')
							->addAttributeToSelect('description')
							->addAttributeToSelect('hashtag')
							->addFieldToFilter('status', 1)
							->addAttributeToFilter('is_expired', array('neq' => 1))
							->addAttributeToSort('news_from_date', 'asc')
							//->addAttributeToSort('position', 'desc')
							->addAttributeToFilter('news_from_date', array('gteq' => $date));	
			
			$events->addAttributeToFilter('visibility', $visibility)				
							->setPageSize($limit)
							->setPage($page, $limit)							
							->load()->toArray();
			$lastPage = $events->getLastPageNumber();
			
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			/*$follower = $resource->getTableName('follower');
			$select = "select follow from $follower WHERE follower_id=".$user_id." and follower_id<>follow and status=1";						
			$followers = $read->fetchAll($select);

			$resultArray = '';
			$str='';
			$c=0;
			foreach($followers as $follower){	$c = $c+1;	
					$str[$c]=$follower['follow'];
					$c = $c+1;
			}*/
			$upcoming = 0;
			$live = 0;
			$counter=0; 
			if($lastPage >= $page){
				foreach ($events as $k => $event) {
				//===================Start Image=====================================
					$customer = Mage::getModel('customer/customer')->load($event['user_id']);
					$fc = strtolower(substr($customer->getFirstname(),0,1));
					if($fc == ''){ $fc = rand(1,10); }

					if($event['event_image']=="''")
						$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
					else{
						$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];	
					}
				//===================End Image=====================================
					$rsvp = $resource->getTableName('rsvp');
					$selectSql = "select * from $rsvp WHERE user_id=".$user_id." and event_id=".$event['entity_id']." and status=1";			
					$row = $read->fetchAll($selectSql);
					if(count($row)>0)
						$rsvpStatus=1;
					else
						$rsvpStatus=0;
					$from = strtotime($event['news_from_date'])+$timeoffset;
					$to = strtotime($event['news_to_date'])+$timeoffset;
					if (($now > $from) && ($now < $to)) {
						$isLive="true";
					}
					else{
						$isLive="false";
					}
					/*
					if(in_array($event['user_id'],$str)){
						$myFollowersEvent = 1;
					}
					else {
						$myFollowersEvent = 0;
					}*/
				if($isLive == "false"){
					$upcoming++;
					$result[$counter]['upcoming'] = array(
							'id'=> $event['entity_id'],
							'name'=> $event['name'],
							'price'=> number_format($event['price'],2),
							'user_id'=> $event['user_id'],
							'event_hostedby_username'=> $customer->getUsername(),
							'event_hostedby_firstname'=> $customer->getFirstname(),
							'event_hostedby_lastname'=> $customer->getLastname(),
							'description'=> $event['description'],
							'from_date'=> date('D M d, Y h:i A', strtotime($event['news_from_date'])+$timeoffset)." ".$abbrev,
							'to_date'=> date('D M d, Y h:i A',strtotime($event['news_to_date'])+$timeoffset)." ".$abbrev,
							'from_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_from_date'])))),
							'to_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_to_date'])))),
							'image'			=> $img_url,							
							'islive'			=> $isLive,
							//'myfollowersevent'			=> $myFollowersEvent,
							'rsvpStatus'	=>$rsvpStatus,
							'category'  => $this->getCategoryNameByEventId($event['entity_id']),
							'from_date1'=> date('m-d-Y H:i:s', strtotime($event['news_from_date'])+$timeoffset),
							'to_date1'=> date('m-d-Y H:i:s',strtotime($event['news_to_date'])+$timeoffset),
							'hashtags'=> $event['hashtag'],
							'spots_remaining'=> $event['maximum_of_attendees'],
						);			
					}
					$counter++;
				}
				}
				
				$result['upcoming'] = $result['upcoming'];
				/*
				function compare_lastname($a, $b){
					return strnatcmp($b['myfollowersevent'], $a['myfollowersevent']);
				}
			  // sort alphabetically by name
				usort($result, 'compare_lastname');
				*/
				if($upcoming == 15){
					$result['nextRecordType'] = 'events';
					$result['startFrom'] = $upcoming;
					return $result;
				}
				if($upcoming < 15){
					$tLimit = 15 - $upcoming;
					$result['topUsers'] = $this->getTopUsersGrid($user_id,$tLimit);
					$result['nextRecordType'] = 'topUsers';
					$result['startFrom'] = $tLimit;
					return $result;
				}				
	}
	public function setYoutubeLiveStream($user_id=0, $url="", $action=""){
	$resource = Mage::getSingleton('core/resource');
	$read= $resource->getConnection('core_read');
	$write= $resource->getConnection('core_write');
	require_once '/var/websites/oncam_com/webroot/youtube/google-api-php-client/src/Google_Client.php';
	require_once '/var/websites/oncam_com/webroot/youtube/google-api-php-client/src/contrib/Google_YouTubeService.php';
	$youtubeToken='cs_youtube_access_token';
	$rs = $read->fetchRow("SELECT * FROM $youtubeToken WHERE user_id='".$user_id."'");
	if($rs > 0){
	$refesh_token=$rs['refresh_token'];
	$data = array(
    'client_id' => '962768365297.apps.googleusercontent.com',
    'client_secret' => 'AjWArSbhoPoexASxnVzHUSUQ',
    'refresh_token' => $refesh_token,
    'grant_type' => 'refresh_token');

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://accounts.google.com/o/oauth2/token');
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	$result = curl_exec($ch);
	curl_close($ch);
	$obj = json_decode($result);

$tokenId=$obj->{'access_token'}; 
$obj->{'refresh_token'}=$refesh_token;



// Set your cached access token. Remember to replace $_SESSION with a real database or memcached.
session_start();
 
// Connect to the Account you want to upload the video to (Note: When Remembering your access code you only need to do this once)
$client = new Google_Client();
$client->setApplicationName('oncam');
$client->setClientId('962768365297.apps.googleusercontent.com');
$client->setClientSecret('AjWArSbhoPoexASxnVzHUSUQ');
$client->setRedirectUri('https://www.oncam.com/youtube/youtubev3.php');
$client->setDeveloperKey('AIzaSyD5le4gPmoBW_tbp6W70D3cLSrfq6HDZvA');
 
// Load the Youtube Service Library
$youtube = new Google_YouTubeService($client);

//test refresh Token

 $_SESSION['token']=json_encode($obj);
//echo $_SESSION['token']=$result;
if (isset($_SESSION['token']))
{
    $client->setAccessToken($_SESSION['token']);
}
//return $_SESSION['token'];
// Check if access token successfully acquired
if ($client->getAccessToken()) {
  try {
    // Create a snippet with title, scheduled start and end times.
    $broadcastSnippet = new Google_LiveBroadcastSnippet();
    $broadcastSnippet->setTitle('New Broadcast');
    $broadcastSnippet->setScheduledStartTime('2034-01-30T00:00:00.000Z');
    $broadcastSnippet->setScheduledEndTime('2034-01-31T00:00:00.000Z');

    // Create LiveBroadcastStatus with privacy status.
    $status = new Google_LiveBroadcastStatus();
    $status->setPrivacyStatus('private');

    // Create the insert request
    $broadcastInsert = new Google_LiveBroadcast();
    $broadcastInsert->setSnippet($broadcastSnippet);
    $broadcastInsert->setStatus($status);
    $broadcastInsert->setKind('youtube#liveBroadcast');

    // Execute the request and return an object containing information about the new broadcast
    $broadcastsResponse = $youtube->liveBroadcasts->insert('snippet,status',
        $broadcastInsert, array());

    // Create a snippet with title.
    $streamSnippet = new Google_LiveStreamSnippet();
    $streamSnippet->setTitle('New Stream');

    // Create content distribution network with format and ingestion type.
    $cdn = new Google_LiveStreamCdn();
    $cdn->setFormat("1080p");
    $cdn->setIngestionType('rtmp');

    // Create the insert request
    $streamInsert = new Google_LiveStream();
    $streamInsert->setSnippet($streamSnippet);
    $streamInsert->setCdn($cdn);
    $streamInsert->setKind('youtube#liveStream');
    $streamsResponse = $youtube->liveStreams->insert('snippet,cdn',
        $streamInsert, array());

    // Execute the request and return an object containing information about the bound broadcast
    $bindBroadcastResponse = $youtube->liveBroadcasts->bind(
        $broadcastsResponse['id'],'id,contentDetails',
        array(
            'streamId' => $streamsResponse['id'],
        ));

    //$htmlBody .= "<h3>Added Broadcast</h3><ul>";
    //$htmlBody .= sprintf('<li>%s published at %s (%s)</li>',
    //    $broadcastsResponse['snippet']['title'],
    //    $broadcastsResponse['snippet']['publishedAt'],
    //    $broadcastsResponse['id']);
    //$htmlBody .= '</ul>';

   // $htmlBody .= "<h3>Added Stream</h3><ul>";
    //$htmlBody .= sprintf('<li>%s (%s)</li>',
    //    $streamsResponse['snippet']['title'],
    //    $streamsResponse['id']);
    //$htmlBody .= '</ul>';

    //$htmlBody .= "<h3>Bound Broadcast</h3><ul>";
    //$htmlBody .= sprintf('<li>Broadcast (%s) was bound to stream (%s).</li>',
    //    $bindBroadcastResponse['id'],
    //    $bindBroadcastResponse['contentDetails']['boundStreamId']);
    //$htmlBody .= '</ul>';
	//return $htmlBody;
	return "You are not a authorized user";
  } catch (Google_ServiceException $e) {
	return "You are not a authorized user";
    //$htmlBody .= sprintf('<p>A service error occurred: <code>%s</code></p>',
    //    htmlspecialchars($e->getMessage()));
  } catch (Google_Exception $e) {
	return "You are not a authorized user";
    //$htmlBody .= sprintf('<p>An client error occurred: <code>%s</code></p>',
    //    htmlspecialchars($e->getMessage()));
  }

  $_SESSION['token'] = $client->getAccessToken();
} else {
  // If the user hasn't authorized the app, initiate the OAuth flow
  $state = mt_rand();
  $client->setState($state);
  $_SESSION['state'] = $state;

  $authUrl = $client->createAuthUrl();
  return "You are not a authenticated user";
}
}else{
	return "You are not connected with youtube";
}
   }
   public function getFavouritePeopleAndFollowing($user_id=0,$page=1){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write= $resource->getConnection('core_write');
		$contactsTab = $resource->getTableName('mobile_contacts');
		//$select = "select DISTINCT verified_user_id from cs_mobile_contacts WHERE contact1 IN (SELECT contact1 FROM cs_mobile_contacts WHERE owner_user_id =".$user_id." AND verified_user_id IS NULL ) and verified_user_id > 0";
		$select = "select DISTINCT a.verified_user_id from cs_mobile_contacts a, cs_mobile_contacts b where a.contact1 = b.contact1 and  b.owner_user_id =".$user_id." and b.verified_user_id IS NULL and a.verified_user_id > 0";
		
		$results = $read->fetchAll($select);
		/*
		$select1 = "select DISTINCT verified_user_id from cs_mobile_contacts WHERE contact1 IN (SELECT contact2 FROM cs_mobile_contacts WHERE owner_user_id =".$user_id." AND verified_user_id IS NULL ) and verified_user_id > 0";
		$results1 = $read->fetchAll($select1);
		
		$select2 = "select DISTINCT verified_user_id from cs_mobile_contacts WHERE contact1 IN (SELECT contact3 FROM cs_mobile_contacts WHERE owner_user_id =".$user_id." AND verified_user_id IS NULL ) and verified_user_id > 0";
		$results2 = $read->fetchAll($select2);
		
		$select3 = "select DISTINCT verified_user_id from cs_mobile_contacts WHERE contact1 IN (SELECT contact4 FROM cs_mobile_contacts WHERE owner_user_id =".$user_id." AND verified_user_id IS NULL ) and verified_user_id > 0";
		$results3 = $read->fetchAll($select3);
		$all_result = array_merge($results,$results1,$results2,$results3);*/
		foreach($results as $k=>$ar){
			$dropin_select = "select * from cs_dropin_user WHERE user_id=".$user_id." and dropin_user_id=".$ar['verified_user_id']."  and status=1";
			$dropin_results = $read->fetchAll($dropin_select);
			if(count($dropin_results)>0){
				$dropin_status = "true";
			}else{
				$dropin_status = "false";
			}
			$item['contacts'][$k]=array(
						'user_id'=>$ar['verified_user_id'],
						'username'=>$this->getUserNameByUserId($ar['verified_user_id']),
						'image'=>$this->getProfilePic($ar['verified_user_id']),
						'dropin'=>$dropin_status,
			);
		}
		$status=1; $notify=0;
		$customer_id = Mage::getSingleton('customer/session')->getCustomer()->getId();
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$follower = $resource->getTableName('follower');
		$customer_entity = $resource->getTableName('customer_entity');
		
		$select = "select * from $follower, $customer_entity WHERE follower_id='".$user_id."' and follower_id<>follow and status=".$status." and $customer_entity.entity_id=$follower.follow";
		$selectcount = "select * from $follower, $customer_entity WHERE follower_id='".$user_id."' and follower_id<>follow and status=".$status." and $customer_entity.entity_id=$follower.follow";
		if($notify==1){
				$select.= " and notify=".$notify."";
				$selectcount.= " and notify=".$notify."";
		}
				$select.=" group by follow order by id desc";
				$selectcount.=" group by follow order by id desc";
			$limit = 15;
			if($page<=0)
				$page=1;
			$page=$page-1;
			if($limit!=0)
				$select.= " limit ".$limit*$page .", " .$limit;
			$follower = $read->fetchAll($select);
			foreach($follower as $k=>$flwr){
			$dropin_select = "select * from cs_dropin_user WHERE user_id=".$user_id." and dropin_user_id=".$flwr['follow']."  and status=1";
			$dropin_results = $read->fetchAll($dropin_select);
			if(count($dropin_results)>0){
				$dropin_status = "true";
			}else{
				$dropin_status = "false";
			}
					$customer = Mage::getModel('customer/customer')->load($flwr['follow']);
					$username = $customer->getUsername();
					$thumbimage = $this->getProfilePic($flwr['follow']);
										
					$item['following'][$k]=array(
						'id'=> $flwr['id'],
						'username'=>$username,
						'name'=>$customer->getFirstname()." ".$customer->getLastname(),
						'user_id'=>$flwr['follow'],
						'short_bio'=>$customer->getShortbio(),
						'views'=>Mage::getModel('csservice/csservice')->getCheckinCount($flwr['follow']),
						'followers'=>$this->getFollowersCount($flwr['follow']),
						'thumbimage'=>$thumbimage,
						'notify'=> $flwr['notify'],
						'follow_on'=> $flwr['follow_on'],
						'isLive'=> $this->isUserOnline($flwr['follow']),
						'notify_push'=> $flwr['push_notify'],
						'dropin'=>$dropin_status,
					); 
			}
			
			$count = count($read->fetchAll($selectcount));
			if($count > ($limit*($page+1))){
				$item['showMore'] = "true";
			} else {
				$item['showMore'] = "false";
			}
			
			return $item;
		
	}
	public function blockBadUsers($user_id){
		$deactivecustomers = Mage::getModel('customer/customer')->load($user_id);
		$deactivecustomers->setIsActive(0);
		$deactivecustomers->save();
	}
	public function getProfileGridData($profile_id, $current_user_id, $nextRecordType="",$startFrom=0) {
		$user_id = $profile_id;
		if($nextRecordType=="topUsers"){
			return "No more records found";
		}
		$cat=3; $limit=15;$page=1;

		/*$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$follower = $resource->getTableName('follower');
		$customer_entity = $resource->getTableName('customer_entity');

		$follow_result1 = $this->isFollowing($profile_id, $current_user_id);
		$follow_result2 = $this->isFollow($profile_id, $current_user_id);
		if( $follow_result1 && $follow_result2){
			$privacy = "2, 0"; // Favorate;
		}else {
			$privacy = 0; // Everyone;
		}
		if($user_id == $current_user_id){
			$privacy = " 1, 2, 0"; // Me;	
		}*/

		//========================================================================
		if($nextRecordType=='replays'){
					
					$result['replays'] = $this->getVideoGrid($user_id,15, $privacy, $startFrom,$page);
					$vTotal = $startFrom + $result['replays']['rowcount'];
					$vLeft = $result['replays']['video_count'];
					if($vTotal < $vLeft){
						$result['nextRecordType'] = 'replays';
						$result['startFrom'] = $vTotal;
					}
					if($result['replays']['rowcount'] < 15){
						$fLimit = 15 - $result['replays']['rowcount'];
						$result['followings'] = $this->getFollowingsGrid($user_id,$fLimit);
						$gTotal = $result['replays']['rowcount']+$result['followings']['rowcount'];
						if($gTotal < 15){
						$tLimit = 15 - $gTotal;
							$result['topUsers'] = $this->getTopUsersGrid($user_id,$tLimit);
							$result['nextRecordType'] = 'topUsers';
							$result['startFrom'] = $tLimit;
						}
					}
					return $result;
				}
		//================================================================================
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		if($time_zone == 'America/Los_Angeles'){
			$now = strtotime('+1 hour')+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime('+1 hour'));
		} else{
			$now = strtotime(now())+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime(now()));
		}
		
		$visibility = array(	
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
              );

      		$events = Mage::getResourceModel('catalog/product_collection')
							->addAttributeToSelect('user_id')
							->addAttributeToSelect('event_image')
							->addAttributeToSelect('news_from_date')
							->addAttributeToSelect('news_to_date')
							->addAttributeToSelect('entity_id')
							->addAttributeToSelect('name')
							->addAttributeToSelect('price')
							->addAttributeToSelect('description')
							->addAttributeToSelect('hashtag')
							->addFieldToFilter('status', 1)
							->addFieldToFilter('user_id', array('eq'=> $user_id))
							->addAttributeToFilter('is_expired', array('neq' => 1))
							->addAttributeToSort('news_from_date', 'asc')
							//->addAttributeToSort('position', 'desc')
							->addAttributeToFilter('news_to_date', array('gteq' => $date));	
			
			$events->addAttributeToFilter('visibility', $visibility)				
							->setPageSize($limit)
							->setPage($page, $limit)							
							->load()->toArray();
			$lastPage = $events->getLastPageNumber();
			if($lastPage > $page){
				$showMore = "true";
			} else {
				$showMore = "false";
			}
			
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$resultArray = '';
			$str='';
			$c=0;
			$upcoming = 0;
			$live = 0;
			$counter=0; 
			if($lastPage >= $page){
				$counter1=0;
				foreach ($events as $k => $event) { $counter++;
				//===================Start Image=====================================
					$customer = Mage::getModel('customer/customer')->load($event['user_id']);
					$fc = strtolower(substr($customer->getFirstname(),0,1));
					if($fc == ''){ $fc = rand(1,10); }

					if($event['event_image']=="''")
						$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
					else{
						$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];		
					}
				//===================End Image=====================================
					$from = strtotime($event['news_from_date'])+$timeoffset;
					$to = strtotime($event['news_to_date'])+$timeoffset;
					if (($now > $from) && ($now < $to)) {
						$isLive="true";
					}
					else{
						$isLive="false";
					}
					
				if($isLive == "true"){
				$live++;
					/*if($this->isUserOnline($event['user_id'])){
						$HostIsLive = "true";
					}else{
						$HostIsLive = "false";
					}*/
					$result[$counter]['live'] = array(
							'id'=> $event['entity_id'],
							'name'=> $event['name'],
							'price'=> number_format($event['price'],2),
							'user_id'=> $event['user_id'],
							'event_hostedby_username'=> $customer->getUsername(),
							'event_hostedby_firstname'=> $customer->getFirstname(),
							'event_hostedby_lastname'=> $customer->getLastname(),
							'description'=> $event['description'],
							'from_date'=> date('D M d, Y h:i A', strtotime($event['news_from_date'])+$timeoffset)." ".$abbrev,
							'to_date'=> date('D M d, Y h:i A',strtotime($event['news_to_date'])+$timeoffset)." ".$abbrev,
							'from_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_from_date'])))),
							'to_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_to_date'])))),
							'image'			=> $img_url,						
							'islive'			=> $isLive,
							//'numberOfLiveUsers'	=>	$this->getNumberOfUserOnline($event['user_id']),
							//'category'  => $this->getCategoryNameByEventId($event['entity_id']),
							'from_date1'=> date('m-d-Y H:i:s', strtotime($event['news_from_date'])+$timeoffset),
							'to_date1'=> date('m-d-Y H:i:s',strtotime($event['news_to_date'])+$timeoffset),
							//'HostIsLive' => $HostIsLive,
							'hashtags'=> $event['hashtag'],
						);			
					}
				}
				$counter2=0;
				
				foreach ($events as $k => $event) { $counter++;
				//===================Start Image=====================================
					$customer = Mage::getModel('customer/customer')->load($event['user_id']);
					$fc = strtolower(substr($customer->getFirstname(),0,1));
					if($fc == ''){ $fc = rand(1,10); }

					if($event['event_image']=="''")
						$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
					else{
						$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];		
					}
				//===================End Image=====================================
					$from = strtotime($event['news_from_date'])+$timeoffset;
					$to = strtotime($event['news_to_date'])+$timeoffset;
					if (($now > $from) && ($now < $to)) {
						$isLive="true";
					}
					else{
						$isLive="false";
					}
					
				if($isLive == "false"){
					$upcoming++;
					$result[$counter]['upcoming'] = array(
							'id'=> $event['entity_id'],
							'name'=> $event['name'],
							'price'=> number_format($event['price'],2),
							'user_id'=> $event['user_id'],
							'event_hostedby_username'=> $customer->getUsername(),
							'description'=> $event['description'],
							'from_date'=> date('D M d, Y h:i A', strtotime($event['news_from_date'])+$timeoffset)." ".$abbrev,
							'to_date'=> date('D M d, Y h:i A',strtotime($event['news_to_date'])+$timeoffset)." ".$abbrev,
							'from_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_from_date'])))),
							'to_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_to_date'])))),
							'image'			=> $img_url,							
							'islive'			=> $isLive,
							//'category'  => $this->getCategoryNameByEventId($event['entity_id']),
							'from_date1'=> date('m-d-Y H:i:s', strtotime($event['news_from_date'])+$timeoffset),
							'to_date1'=> date('m-d-Y H:i:s',strtotime($event['news_to_date'])+$timeoffset),
							'hashtags'=> $event['hashtag'],
						);			
					}
				}
				}
				
				$result['live'] = $result['live'];
				$result['upcoming'] = $result['upcoming'];
				$LU = $live+$upcoming;
				if($LU < 15){
					$vLimit = 15-$LU;
					$result['replays'] = $this->getVideoGrid($user_id,$vLimit);
					$luv = $LU + $result['replays']['rowcount'];
					$vLeft = $result['replays']['video_count'];
					if($vLimit <= $vLeft){
						$result['nextRecordType'] = 'replays';
						$result['startFrom'] = $vLimit;
						return $result;
					}elseif($luv < 15){
						$fLimit = 15 - $luv;
						$result['followings'] = $this->getFollowingsGrid($user_id,$fLimit);
						$gTotal = $luv+$result['followings']['rowcount'];
						if($gTotal <= 15){
						$tLimit = 15 - $gTotal;
							$result['topUsers'] = $this->getTopUsersGrid($user_id,$tLimit);
							$result['nextRecordType'] = 'topUsers';
							$result['startFrom'] = $tLimit;
						}
					}
				}
				//$result['LU'] = $LU;
				//$result['vLimit'] = $vLimit;
				//$result['luv'] = $luv;
				//$result['vLeft'] = $vLeft;
				//$result['fLimit'] = $fLimit;
				//$result['gTotal'] = $gTotal;
				//$result['tLimit'] = $tLimit;
				//$result['vrowcount'] = $result['replays']['rowcount'];
				//$result['showMore'] = $showMore;
				return $result;		
	}
	public function getVideoGrid($user_id, $limit=5, $privacy, $startFrom=0,$page=1){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$videoTable = $resource->getTableName('video');
		$select = 'select video_id, title, description, profile_id, user_id, video_path, thumbnail_path, duration, created_time,(select count(*) from '.$videoTable.' where isdeleted = 0 and status = 1 and user_id = '.$user_id.') as count  from '.$videoTable.' where isdeleted = 0 and status = 1 and user_id = '.$user_id.' and privacy ='.$privacy.' order by video_id desc';
		
		//$limit = 15;
			if($page<=0)
				$page=1;
			$page=$page-1;
			if($limit!=0)
				$select.= ' limit '.$startFrom.', '.$limit;
				
		//$selectcount = 'select count(*) as count from '.$videoTable.' where isdeleted = 0 and status = 1 and user_id = '.$user_id;
		$rs = $read->fetchAll($select);
		foreach($rs as $k=>$r){
			$item[$k] = array(
							'id'=> $r['video_id'],
							'title'=> $r['title'],
							'description'=> $r['description'],
							'profile_id'=> $r['profile_id'],
							'user_id'=> $r['user_id'],
							'video_path'=> $r['video_path'],
							'thumbnail_path'=> $r['thumbnail_path'],
							'duration'=> $r['duration'],
							'created_time' => $r['created_time'].' GMT',
							'created_time2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($r['created_time'])))),
							);
			$count = $r['count'];
		}
		$item['rowcount'] = count($item);
		$item['video_count'] = $count; 
		return $item;
	}
	public function getFollowingsGrid($user_id,$limit=5,$page=1) {
		 $status=1; $notify=0;
		$customer_id = Mage::getSingleton('customer/session')->getCustomer()->getId();
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$follower = $resource->getTableName('follower');
		$customer_entity = $resource->getTableName('customer_entity');
		
		$select = "select id,follow from $follower, $customer_entity WHERE follower_id='".$user_id."' and follower_id<>follow and status=".$status." and $customer_entity.entity_id=$follower.follow group by follow";
			if($page<=0)
				$page=1;
			$page=$page-1;
			if($limit!=0)
				$select.= " limit ".$limit*$page .", " .$limit;
			$follower = $read->fetchAll($select);
			foreach($follower as $k=>$flwr){
					$count = $this->getFollowersCount($flwr['follow']);
					$customer = Mage::getModel('customer/customer')->load($flwr['follow']);
					$username = $customer->getUsername();
					$thumbimage = $this->getProfilePic($flwr['follow']);
										
					$item[$k]=array(
						'id'=> $flwr['id'],
						'username'=>$username,
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'name'=>$customer->getFirstname()." ".$customer->getLastname(),
						'user_id'=>$flwr['follow'],
						'thumbimage'=>$thumbimage,
						//'isLive'=> $this->isUserOnline($flwr['follow']),
						'Followers_count' => $count,
						'views'=>Mage::getModel('csservice/csservice')->getCheckinCount($flwr['follow']),
					);
					
			}
			 function compare_lastname($a, $b){
				return strnatcmp($b['Followers_count'], $a['Followers_count']);
			}
		  // sort alphabetically by name
			usort($item, 'compare_lastname');
				$item['rowcount']=count($item);
				return $item;
	}
	public function getTopUsersGrid($user_id,$limit) {
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
				
		$select = "select count( DISTINCT follower_id) as total, follow from cs_follower, cs_customer_entity WHERE follower_id<>follow and status=1 and cs_customer_entity.entity_id=cs_follower.follow";
		$select.=" group by follow ORDER BY `total`  DESC limit ".$limit;
		$topresults = $read->fetchAll($select);
			foreach($topresults as $k=>$user){ //echo "test-".$user['entity_id']; exit;
				$customer = Mage::getModel('customer/customer')->load($user['follow']);
				$follower = $this->isFollow($user['follow'], $user_id);
				$follwer = $this->getFollowersCount($user['follow']);
				//$host_count = Mage::getModel('events/events')->getEventHostingCount($user['follow']);
				//$shortbio=$customer->getShortbio();
				//return "knkj".$follower;
				if($follower!=1){
					$status = "Follow";
					$mesg = 1;					
				}
				else{ 
					$status = "Unfollow";
					$mesg = 2;
				}
				//if($follower!=1){
				$result[$k] = array(
						'id'=>$user['follow'],
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'username'=>$customer->getUsername(),
						'Followers_count'=>$follwer,
						'views'=>Mage::getModel('csservice/csservice')->getCheckinCount($user['follow']),
						'status'=>$status,
						//'events'=>$host_count,
						//'shortbio'=>$shortbio,
						'profile_url'=> '/'.$customer->getUsername(),
						'image'=>$this->getProfilePic($user['follow']),
					);
				//}
			}
		return $result;
	}
	public function getOnlineUserGrid($limit=5,$startFrom=0){	
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$table = $resource->getTableName('user_activities');
		$sqlSelect = " Select profile_id, user_id, type_of, group_of, site, photo , created_on, status, id, webcam_on, mesg from $table where status > 0";
			$sqlSelect.=" and type_of = 'check-ins'";
			$now = date("Y-m-d H:i:s");
			$sqlSelect.=" and last_pinged_time >  DATE_ADD(now(), INTERVAL '-02:00' MINUTE_SECOND) group by user_id";
			if($limit!=0)
				$sqlSelect.= " limit ".$startFrom.", ".$limit;
			$activities = $read->fetchAll($sqlSelect);
			//return $activities;
			$sqlcount = " Select count(*) as count from $table where status > 0";
			$sqlcount.=" and type_of = 'check-ins'";
			$sqlcount.=" and last_pinged_time >  DATE_ADD(now(), INTERVAL '-02:00' MINUTE_SECOND) group by user_id";
			
			$activitiesCount = $read->fetchRow($sqlcount);
			
				foreach($activities as $k=>$act){
					if($act['user_id'] == $act['profile_id']){
						$isHost="true";
					$customer = Mage::getModel('customer/customer')->load($act['user_id']);
					$username = $customer->getUsername();	
					$data[$k] = array(
						'id'=> $act['entity_id'],
							'name'=> $username,
							'price'=> '',
							'user_id'=> $act['user_id'],
							'event_hostedby_username'=> $username,
							'event_hostedby_firstname'=> $customer->getFirstname(),
							'event_hostedby_lastname'=> $customer->getLastname(),
							'description'=> $username." is live on oncam now",
							'from_date'=> '',
							'to_date'=> '',
							'from_date2'=> '',
							'to_date2'=> '',
							'image'	=> $this->getProfilePic($act['user_id']),						
							'islive'=> '',
							'myfollowersevent'=> '',
							'rsvpStatus'=>'',
							'numberOfLiveUsers'	=>	'',
							'category'  => '',
							'from_date1'=> '',
							'to_date1'=> '',
							'HostIsLive' => '',
							'hashtags'=> '',
					);
					}
				}
				$data['rowcount'] = count($data);
				$data['totalcount'] = $activitiesCount['count'];
				return $data;
	}
	public function pushNotificationTest($user_id) {
		$headers = array(
		 'Content-Type:application/json',
		 'Authorization:key=AIzaSyDYNt9ftmzDT2aExpnyxP6pkmeMkacbQU4'
		);
		//$resource = Mage::getSingleton('core/resource');
		//$read= $resource->getConnection('core_read');
		//$device = $resource->getTableName('mobile_device');
		//$select = "select device_id from $device where type IN ('Android') and device_id!='' and user_id=".$user_id;
		//$deviceTokens = $read->fetchAll($select);
		//$count = count($deviceTokens);
		$username = $this->getUserNameByUserId($user_id);
		$arr   = array();
		$arr['data']['user_id'] = $user_id;
		$arr['data']['username'] = $username;
		$arr['data']['msg'] = "Hello Oncam";
		$arr['data']['count'] = $count;
		$arr['registration_ids'] = array();
		
		//foreach($deviceTokens as $k=>$dc){
			//$arr['registration_ids'][$k] = $dc["device_id"];
		//}
		//return $arr;	
		$arr['registration_ids'][0] = "APA91bG6Z6xH71I-3rv4gNKzI9nZRWvT96tN3EW235Rk3r8IIhHg8oSyBbDdjsGb7rDBKOpViHmyg3zV0T4_ogC_cL6-jqk7fXNjZTPl6EToLuTK_ViHXcdb7gSoEv4GlTlF8ZbG2waHeehHI9F67u5OW618926aAw";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,    'https://android.googleapis.com/gcm/send');
		curl_setopt($ch, CURLOPT_HTTPHEADER,  $headers);
		curl_setopt($ch, CURLOPT_POST,    true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($arr));
		try{
		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
		} catch (Exception $e){
			Mage::log($e,null,'gcm.log');
		}
	}
	public function getProfileImageByProfileId($profile_id){
		$item = array();
		$item['image128'] = $this->getProfilePic($profile_id);
		$item['image48'] = $this->getProfilePic48($profile_id);
		return $item;
	}
	public function createHashTagByEventId($user_id=0,$hashtag=null,$event_id=0){
	if (isset($_POST["user_id"]) && ($_POST["user_id"]>0)){
		$user_id = $_POST["user_id"];
		$customer = Mage::getSingleton( 'customer/customer' )->load($user_id);
		$username = $customer->getUsername();
		$firstname = $customer->getFirstname();
		$lastname = $customer->getLastname();
		$title = $username;
		$description = $firstname." ".$lastname." is live on oncam";
		$location = $customer->getLocation();
		if($location == ""){
			$location = "N/A";
		}
		$hashtag = strip_tags($_POST["hashtag"]);
		//======================================================================
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		$start_date = date('Y-m-d H:i:s',strtotime(now()));
		$end_date = date('Y-m-d H:i:s',strtotime('+30 minutes'));
		
		$from_date = date("m/d/y H:i",strtotime(now())+$timeoffset);
		$to_date = date("m/d/y H:i", strtotime('+30 minutes')+$timeoffset);
		//======================================================================
		$cat_id = 18;
		$event_id = $_POST["event_id"];
		//===================================================================================
		if($event_id == 0){
		$events = Mage::getModel('catalog/category')->load(3)
							->getProductCollection()
							->addAttributeToSelect('*')
							 ->addFieldToFilter('user_id', array('eq'=> $user_id))
							->addAttributeToSelect('category_id')
							->addAttributeToSelect('status')
							->addFieldToFilter('status', 1)
							->addAttributeToFilter('is_expired', array('neq' => 1))
							->addAttributeToSort('news_from_date', 'asc')
							->addAttributeToFilter('news_from_date', array('gteq' => $start_date))
							->addAttributeToFilter('news_to_date', array('lteq' => $end_date))
							->load()->toArray();
		if(count($events)>0){
			foreach ($events as $k => $event){
			$event_id = $event['entity_id'];
			}
		}
		}
		//===================================================================================
		
	$price=0; $no_att=10000;
		//from 05/28/13 12:26
		//to 05/28/13 20:26
		$sku = ereg_replace('[^A-Za-z0-9.]', '-', date('m-d-y H:i:s'));
		$catId = '3,'.$cat_id;
		if($user_id){
			$customerId = $user_id;
			$sku = 'chattrspace-'. $user_id ."-" . $sku;	
			$customer = Mage::getModel('customer/customer')->load($user_id);
			$time_zone = $customer->getTimezone();
			$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
			$from_date1 = date('Y-m-d H:i:s', strtotime($from_date));
			$to_date1 = date('Y-m-d H:i:s', strtotime($to_date));
			if($to_date1 < $from_date1){
				return "Error : End Date is less than Start Date";
			}
			if($from_date != ''){
				$from_date = date('Y-m-d H:i:s', strtotime($from_date) - $timeoffset);
			}
			if($to_date!=''){
				$to_date = date('Y-m-d H:i:s', strtotime($to_date) - $timeoffset);
			}
			$from_date_array = explode(" ", $from_date);
			$from_array = explode("-", $from_date_array[0]);
			$to_date_array = explode(" ", $to_date);
			$to_array = explode("-", $to_date_array[0]);
				
			$array_year = array(2020=>142,2019=>143, 2018=>144, 2017=>145, 2016=>146, 2015=>147 
									,2014=>148, 2013=>149, 2012=>150, 2011=>151);
									
			$array_day = array(01=>129, 02=>128, 03=>127, 04=>126, 05=>125, 06=>124 
											,07=>123, 08=>122, 09=>121, 10=>120
											,11=>119, 12=>118, 13=>117, 14=>116
											,15=>115, 16=>114, 17=>113, 18=>112
											,19=>111, 20=>110, 21=>109, 22=>108
											,23=>107, 24=>106, 25=>105, 26=>104
											,27=>103, 28=>102, 29=>101, 30=>100
											,31=>99);
											
			$array_month = array (01=>141, 02=>140, 03=>139, 04=>138, 05=>137, 06=>136 
										, 07=>135, 08=>134, 09=>133, 10=>132, 11=>131
										, 12=>130);
			$is_weekend=153;
			$d = date('D', mktime(0,0,0,$from_array[1], $from_array[2], $from_array[0]));
			if($d=="Sat" || $d=="Sun")
				$is_weekend = 152; 
			Mage::app()->getLocale()->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_LONG);
			$storeId = Mage::app()->getStore()->getId();
			$filename = '';
			if($event_id > 0){
				$magentoProductModel= Mage::getModel('catalog/product')->load($event_id);
				$magentoProductModel->setStoreId($storeId);				
			}else{
				$magentoProductModel= Mage::getModel('catalog/product');
				$magentoProductModel->setStoreId(0);
			}
			$magentoProductModel->setWebsiteIds(array(1));
			$magentoProductModel->setAttributeSetId(9);
			$magentoProductModel->setTypeId('simple');
			$magentoProductModel->setName($title);
			$magentoProductModel->setProductName($title);
			$magentoProductModel->setSku($sku);
			$magentoProductModel->setUserId($user_id);
			$magentoProductModel->setShortDescription($description);
			$magentoProductModel->setDescription($description);
			$magentoProductModel->setPrice($price);				
			$magentoProductModel->setSpecialPrice($vol_price);				
			$magentoProductModel->setSalesQty(100);				
			$magentoProductModel->setWeight(0);
			$magentoProductModel->setIsExpired(155);
			$magentoProductModel->setLocation($location);
			$magentoProductModel->setHashtag($hashtag);
			$magentoProductModel->setVisibility(4);					
			
			$magentoProductModel->setNewsFromDate($from_date);
			$magentoProductModel->setNewsToDate($to_date);
			
			$magentoProductModel->setToDay($to_array[2]);
			$magentoProductModel->setToMonth($to_array[1]);
			$magentoProductModel->setToYear($to_array[0]);
			
			$magentoProductModel->setFromDay($array_day[intval($from_array[2])]);
			$magentoProductModel->setFromMonth($array_month[intval($from_array[1])]);
			$magentoProductModel->setFromYear($array_year[intval($from_array[0])]);
			
			$magentoProductModel->setFromTime($from_date_array[1]);
			$magentoProductModel->setToTime($to_date_array[1]);
			
			$magentoProductModel->setIsWeekend($is_weekend);
			
			$magentoProductModel->setMaximumOfAttendees($no_att);
			
			$magentoProductModel->setStatus(1);
			$magentoProductModel->setTaxClassId('None');
			$magentoProductModel->setCategoryIds($catId);
			//==============================================================================
				
			//==============================================================================
			$magentoProductModel->setEventImage("''");
			$saved = $magentoProductModel->save();
			/* Event Mail Send */
			$lastId = $saved->getId();
			//send mail replace by cron job mail
			//$this->createCronJobsendMail($lastId, $user_id, $cat_id);
			//Magento Stock
			$this->_saveStock($lastId, $no_att);
			//================================================
			$resource = Mage::getSingleton('core/resource');
			$write= $resource->getConnection('core_write');
			$newsfeed = $resource->getTableName('newsfeed');
			$write->query("insert into $newsfeed (user_id, profile_id, cat_id) values(".$user_id.", ".$lastId.",5)");
			//===================================================
			return $lastId;
		}	 
		}else {
				return 'Use Form POST';
            }
	}
	public function hashtagAutoComplete($user_id, $searchString){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$select = "select hashtag from cs_hashtag where hashtag like '".$searchString."%' limit 0,10";
		$results = $read->fetchAll($select);
		foreach($results as $k=>$rs){
			$result[$k] = $rs["hashtag"];
		}
		return $result;
	}
	public function getRsvpEventsByUserId($user_id,$page=1){
		$pid=$user_id;
		$productCount = 5;	 
		$storeId    = Mage::app()->getStore()->getId(); 
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		if($time_zone == 'America/Los_Angeles'){
			$now = strtotime('+1 hour')+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime('+1 hour'));
		} else{
			$now = strtotime(now())+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime(now()));
		}
		$events = array();
		if($pid!=0){
			$events = Mage::getResourceModel('catalog/product_collection')
					   ->addAttributeToSelect('*')
					   ->addAttributeToFilter('news_from_date', array('gteq' => $date))
					   ->addFieldToFilter('attribute_set_id', 9)
					   ->addAttributeToFilter('status', 1)
					   ->setOrder('news_to_date', 'asc')
					   ->setOrder('entity_id', 'desc');
			$limit = 15;
			if($page<=0)
				$page=1;
			//$page=$page-1; 
			$events = $events->setPageSize($limit)->setPage($page, $limit);
			$lastPage = $events->getLastPageNumber();			
			$events = $events->load()->toArray();
			if($lastPage > $page){
				$showMore = "true";
			} else {
				$showMore = "false";
			}
			$items = array();
			if($lastPage >= $page){
			foreach($events as $k=>$evt){
				$prfix = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'catalog/product/';
					
				if(!$evt['thumbnail'] || $evt['thumbnail']=='no_selection'){
					$evt['thumbnail'] = "placeholder/default/red-curtain2_8.jpg";
				}
				$from = strtotime($evt['news_from_date'])+$timeoffset;
				$to = strtotime($evt['news_to_date'])+$timeoffset;
				
				
					$resource = Mage::getSingleton('core/resource');
					$read= $resource->getConnection('core_read');
					$rsvp = $resource->getTableName('rsvp');
					$selectSql = "select count(*) as count from $rsvp WHERE user_id=".$user_id." and event_id=".$evt['entity_id']." and status=1";			
					$rowCount = $read->fetchRow($selectSql);
					if($rowCount['count'] > 0){
				$items[$k] = array(
								'id'=>$evt['entity_id'],
								'name'=>$evt['name'],
								'price'=>$evt['price'],
								'description'=>$evt['description'],
								'event_date'=>date('D M d, Y h:i A', strtotime($evt['news_from_date'])+$timeoffset)." ".$abbrev,
								'event_end_date'=>date('D M d, Y h:i A', strtotime($evt['news_to_date'])+$timeoffset)." ".$abbrev,
								'from_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($evt['news_from_date'])))),
								'to_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($evt['news_to_date'])))),
								'from_date3'=>date('m-d-Y H:i:s', strtotime($evt['news_from_date'])+$timeoffset),/*Added By surinder on demand of ajumal*/
								'to_date3'=>date('m-d-Y H:i:s',strtotime($evt['news_to_date'])+$timeoffset),/*Added By surinder on demand of ajumal*/
								'thumbnail'=>$prfix.$evt['thumbnail'],
								'thumb_image'	=> 'http://chattrspace.s3.amazonaws.com/events/711x447/'.$evt['event_image'],
								'location'=>$evt['location'],
								'category'=>$this->getCategoryNameByEventId($evt['entity_id']),
								'url'=>Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).'live-events/'.$evt['url_path'],
								'isEventAccess'=>$this->isEventAccess($pid, $evt['entity_id']),
							);
						}		
			}
			
			}
			$items['showMore']=$showMore;
			return $items;
		}
	}
	public function videoRecordingYTpost($user_id, $status){
		if($user_id > 0){
			$customer = Mage::getSingleton( 'customer/customer' )->load($user_id);
			if($status == "on"){
				$customer->setYoutubeVideoStream(1);
				$customer->save();
				return "You enabled video recording youtube post";
			}elseif($status == "off"){
				$customer->setYoutubeVideoStream(0);
				$customer->save();
				return "You disabled video recording youtube post";
			}
		}
	}
	public function deleteProfileNewsFeed($user_id, $newsFeed_id){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		$sql = "update cs_newsfeed set isDeleted=1 where id =".$newsFeed_id." and user_id=".$user_id;
		try{
			$write->query($sql);
			return "Successfully deleted";
		}catch (Exception $e) {
            throw new Exception("Error: ".$e->getMessage());
        }
	}
	public function getUserProfileFeedData($user_id,$profile_id, $page=1){
		$results = array();
		$results['userInfo'] = $this->getUserInfoForProfile($user_id);
		$results['profileInfo'] = $this->getDetailProfileInfoByIdForProfile($profile_id, $user_id);
		$results['ProfileNewsFeed'] = $this->getProfileNewsFeed($profile_id, $user_id, $page);
		return $results;
	}
	public function getUserProfileGridData($user_id,$profile_id,$nextRecordType="",$startFrom=0){
		$results = array();
		$results['userInfo'] = $this->getUserInfoForProfile($user_id);
		$results['profileInfo'] = $this->getDetailProfileInfoByIdForProfile($profile_id, $user_id);
		$results['profileGrid'] = $this->getProfileGridData($profile_id, $user_id, $nextRecordType, $startFrom);
		return $results;
	}
	public function getUserInfoForProfile($user_id){
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$returnVal = array();
		if($user_id>0){
			$returnVal['userId'] = $customer->getId();
			//$returnVal['view']=Mage::getModel('csservice/csservice')->getCheckinCount($user_id);
			$returnVal['userName'] = $customer->getUsername();
			//$returnVal['email'] = $customer->getEmail();
			//$returnVal['shortbio'] = $customer->getShortbio();
			if($customer->getVerifiedUser()==1){
				$returnVal['isVerified'] = 1;
			}else{
				$returnVal['isVerified'] = 0;
			}
			$returnVal['location'] = $customer->getLocation();
			//$returnVal['awayBanner'] = $customer->getAwayBanner();			
			//$returnVal['profile_url'] =  Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB).$customer->getUsername();
			$returnVal['firstName'] = $customer->getFirstname();
			$returnVal['lastName'] = $customer->getLastname();
			$returnVal['realname'] = $customer->getFirstname()." ".$customer->getLastname();		 
			$returnVal['profilePicture'] = $this->getProfilePic($user_id);
			$returnVal['profilePicture_30x30'] = $this->getProfilePic($user_id);
			//$returnVal['canvasImage'] = "http://chattrspace.s3.amazonaws.com/user_bgimages/bgimage".$customer->getBgimage();
			//$returnVal['followersCount'] = $this->getFollowersCount($user_id);
			//$returnVal['followingsCount'] = $this->getFollowingsCount($user_id);
		}
		return $returnVal;
	}
	public function getDetailProfileInfoByIdForProfile($user_id, $current_user_id=0){
	if($user_id > 0){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$followers = $this->getFollowersCount($user_id);
		$followings = $this->getFollowingsCount($user_id);
		//$events = $this->getUpcomingEventsByHostIdCount($user_id);
		//$videos = $this->getVideoCount($user_id);
		$isfollow = $this->isFollow($user_id,$current_user_id);
		$profileOwnerFollow = $this->isFollow($current_user_id,$user_id);
		$views = Mage::getModel('csservice/csservice')->getCheckinCount($user_id);
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$shortbio = $customer->getShortbio();
		$result=array();
		if($customer->getVerifiedUser()==1){
			$result['isVerified'] = 1;
		}else{
			$result['isVerified'] = 0;
		}
		/*
				$dropin_select = "select count(*) as count from cs_dropin_user WHERE user_id=".$user_id." and dropin_user_id=".$current_user_id."  and status=1";
				$dropin_results = $read->fetchRow($dropin_select);
				if($dropin_results['count']>0){
					$dropin_status = 1;
				}else{
					$dropin_status = 0;
				}
		*/
		$result['followersCount']=$followers;
		$result['followingsCount']= $followings;
		//$result['events']['count']= $events;
		//$result['videos']['count']= $videos;
		$result['isFollow'] = $isfollow;
		$result['profileOwnerFollow'] = $profileOwnerFollow;
		//$result['dropinGranted'] = $dropin_status;
		$result['views'] = $views;
		$result['username'] = $customer->getUsername();
		$result['firstName'] = $customer->getFirstname();
		$result['lastName'] = $customer->getLastname();
		$result['location'] = $customer->getLocation();
		$result['profile_pic'] = $this->getProfilePic($user_id);
		//$result['profile_pic48'] = $this->getProfilePic48($user_id);
		$result['canvasImage'] = "http://chattrspace.s3.amazonaws.com/user_bgimages/bgimage".$customer->getBgimage();
		$result['shortbio'] = $shortbio;
		return $result;
	}
	}
	public function getTopUsers($user_id,$limit) {
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		
		$select = "select count( DISTINCT follower_id) as total, follow from cs_follower, cs_customer_entity WHERE follower_id<>follow and status=1 and cs_customer_entity.entity_id=cs_follower.follow";
		$select.=" group by follow ORDER BY `total`  DESC limit ".$limit;
		$topresults = $read->fetchAll($select);
			foreach($topresults as $k=>$user){ //echo "test-".$user['entity_id']; exit;
				$customer = Mage::getModel('customer/customer')->load($user['follow']);
				$follower = $this->isFollow($user['follow'], $user_id);
				//$follwer = $this->getFollowersCount($user['follow']);
				
				if($follower!=1){
					$status = "Follow";				
				}
				else{ 
					$status = "Unfollow";
				}
				$result[$k] = array(
						'id'=>$user['follow'],
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'username'=>$customer->getUsername(),
						//'Followers_count'=>$follwer,
						//'views'=>Mage::getModel('csservice/csservice')->getCheckinCount($user['follow']),
						'status'=>$status,
						'profile_url'=> '/'.$customer->getUsername(),
						'image'=>$this->getProfilePic($user['follow']),
					);
			}
		return $result;
	}
	public function setLiveCallHashtag($host_id,$receiver_id=0,$live_call_hashtag,$callType){
		$resource = Mage::getSingleton('core/resource');
		$write = $resource->getConnection('core_write');
		$read = $resource->getConnection('core_read');
		if($host_id > 0){
			$receiver_ids = explode(",", $receiver_id);	
			for($i = 0; $i < count($receiver_ids); $i++){
				$write->query("insert into cs_live_call_hashtag (host_id,receiver_id, live_call_hashtag,type) values(".$host_id.", ".$receiver_ids[$i].", '".$live_call_hashtag."', '".$callType."')");
				$thelastId = $write->lastInsertId();
				//================================================
				$newsfeed = $resource->getTableName('newsfeed');
				$write->query("insert into $newsfeed (user_id, profile_id, cat_id) values(".$host_id.", ".$thelastId.",11)");
			}	
			//===================================================
			return "Successfully created.";
		}
	}
	public function getLiveCallHashtag($host_id){
		$resource = Mage::getSingleton('core/resource');
		$write = $resource->getConnection('core_write');
		$read = $resource->getConnection('core_read');
		if($host_id > 0){
			$select = "select * from cs_live_call_hashtag WHERE host_id=".$host_id;
			$results = $read->fetchAll($select);
			return $results;
		}else{
			return "No results found.";
		}
	}
	public function getLiveUpcomingReplaysPeopleBeforeSearch($user_id){
		$results = array();
		$results['LiveTab'] = $this->getLiveTopHashtag($user_id);
		$results['UpcomingTab'] = $this->getUpcomingTopHashtag($user_id);
		$results['VideoTab'] = $this->getReplayVideosHome($user_id);
		$results['PeopleTab'] = $this->getTopUsers($user_id,15);
		return $results;
	}
	public function getLiveUpcomingReplaysPeopleSearch($user_id, $searchString, $page=1){
		$results = array();
		$results['LiveTab'] = $this->getSearchLiveEvents($user_id, $searchString, $page,15);
		$results['UpcomingTab'] = $this->getSearchUpcomingEvents($user_id, $searchString, $page,15);
		$results['VideoTab'] = $this->FindReplayVideos($user_id, $page, $searchString);
		$results['PeopleTab'] = $this->findPeopleToList($user_id, $searchString, $page);
		return $results;
	}
	public function getReplayVideosHome($user_id){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$videoTable = $resource->getTableName('video');
		$select = 'select video_id, title, identifier, description, profile_id, user_id, video_path, thumbnail_path, duration, tags, created_time  from '.$videoTable.' where isdeleted = 0 and status = 1 and show_home=1 order by video_id desc limit 0,15';
		$results = $read->fetchAll($select);
		
		foreach($results as $k=>$r){
			$customer = Mage::getModel('customer/customer')->load($r['user_id']);
			$item[$k] = array(
							'id'=> $r['video_id'],
							'title'=> $r['title'],
							'description'=> $r['description'],
							'profile_id'=> $r['profile_id'],
							'user_id'=> $r['user_id'],
							'username'=> $customer->getUsername(),
							'firstname'=> $customer->getFirstname(),
							'lastname'=> $customer->getLastname(),
							'views'=> Mage::getModel('csservice/csservice')->getCheckinCount($r['user_id']),
							'video_path'=> $r['video_path'],
							'thumbnail_path'=> $r['thumbnail_path'],
							'duration'=> $r['duration'],
							'created_time' => $r['created_time'].' GMT',
							'created_time2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($r['created_time'])))),
							);
		}
		return $item;	
	}
	public function getSearchUpcomingEvents($user_id=0, $search, $page=1, $limit=10){  		
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		if($time_zone == 'America/Los_Angeles'){
			$now = strtotime('+1 hour')+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime('+1 hour'));
		} else{
			$now = strtotime(now())+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime(now()));
		}
		$visibility = array(	
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
              );

       	$events = Mage::getResourceModel('catalog/product_collection')
							->addAttributeToSelect('user_id')
							->addAttributeToSelect('event_image')
							->addAttributeToSelect('news_from_date')
							->addAttributeToSelect('news_to_date')
							->addAttributeToSelect('entity_id')
							->addAttributeToSelect('name')
							->addAttributeToSelect('price')
							->addAttributeToSelect('description')
							->addAttributeToSelect('hashtag')
							->addFieldToFilter('status', 1)
							->addAttributeToFilter('news_to_date', array('gteq' => $date))
							->addAttributeToFilter(array(
											array(
												'attribute' => 'name',
												'like'        => '%'.$search.'%',
												),
											array(
												'attribute' => 'description',
												'like'        => '%'.$search.'%',
												),
											array(
												'attribute' => 'hashtag',
												'like'        => '%'.$search.'%',
												),
											))
							->addAttributeToSort('news_from_date', 'desc');
					
		$events->addAttributeToFilter('visibility', $visibility)				
							->setPageSize($limit)
							->setPage($page,$limit)							
							->load()->toArray();
		$lastPage = $events->getLastPageNumber();

		if($lastPage >= $page){
			foreach($events as $key=>$event){
				//===================Start Image=====================================
					$customer = Mage::getModel('customer/customer')->load($event['user_id']);
					$fc = strtolower(substr($customer->getFirstname(),0,1));
					if($fc == ''){ $fc = rand(1,10); }

					if($event['event_image']=="''")
					$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
					else{
						if($event['event_image']){
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];
							
							if(fopen($img_url,"r")==false)
								$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
							else
								$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];
						}
						else
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];		
					}
				//===================End Image=====================================
				$from = strtotime($event['news_from_date'])+$timeoffset;
				$to = strtotime($event['news_to_date'])+$timeoffset;
				if (($now > $from) && ($now < $to)) {
					$isLive="true";
				}
				else{
					$isLive="false";
				}	
				$items[$key]=array(
							'id'=> $event['entity_id'],
							'name'=> $event['name'],
							'price'=> number_format($event['price'],2),
							'small_image'=> $img_url,
							'image'=> $img_url,
							'thumbnail'=> $img_url,
							'from_date'=> date('D M d, Y h:i A', strtotime($event['news_from_date'])+$timeoffset)." ".$abbrev,
							'to_date'=> date('D M d, Y h:i A',strtotime($event['news_to_date'])+$timeoffset)." ".$abbrev,
							'from_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_from_date'])))),
							'to_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_to_date'])))),
							'from_date3'=>date('m-d-Y H:i:s', strtotime($event['news_from_date'])+$timeoffset),/*Added By surinder on demand of ajumal*/
							'to_date3'=>date('m-d-Y H:i:s',strtotime($event['news_to_date'])+$timeoffset),/*Added By surinder on demand of ajumal*/
							'hosted_by'=> $customer->getFirstname().' '.$customer->getLastname(),
							'hosted_by_username'=> $customer->getUsername(),
							'user_id'=> $event['user_id'],
							'category_name'=> $this->getCategoryNameByEventId($event['entity_id']),
							'location'=> $event['location'],
							'isLive'			=> $isLive,
				);
			}
			return $items;
		}
	} 
	public function getSearchLiveEvents($user_id=0, $search, $page=1, $limit=10){  		
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		if($time_zone == 'America/Los_Angeles'){
			$now = strtotime('+1 hour')+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime('+1 hour'));
		} else{
			$now = strtotime(now())+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime(now()));
		}
		
		$visibility = array(	
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                      Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
              );

       	$events = Mage::getResourceModel('catalog/product_collection')
							->addAttributeToSelect('user_id')
							->addAttributeToSelect('event_image')
							->addAttributeToSelect('news_from_date')
							->addAttributeToSelect('news_to_date')
							->addAttributeToSelect('entity_id')
							->addAttributeToSelect('name')
							->addAttributeToSelect('price')
							->addAttributeToSelect('description')
							->addAttributeToSelect('hashtag')
							->addFieldToFilter('status', 1)
							->addAttributeToFilter('news_from_date', array('lteq' => $date))
							->addAttributeToFilter('news_to_date', array('gteq' => $date))
							//->addAttributeToFilter('news_to_date', array('gteq' => $date))
							->addAttributeToFilter(array(
											array(
												'attribute' => 'name',
												'like'        => '%'.$search.'%',
												),
											array(
												'attribute' => 'description',
												'like'        => '%'.$search.'%',
												),
											array(
												'attribute' => 'hashtag',
												'like'        => '%'.$search.'%',
												),
											))
							->addAttributeToSort('news_from_date', 'desc');
					
		$events->addAttributeToFilter('visibility', $visibility)				
							->setPageSize($limit)
							->setPage($page,$limit)							
							->load()->toArray();
		$lastPage = $events->getLastPageNumber();

		if($lastPage >= $page){
			foreach($events as $key=>$event){
				//===================Start Image=====================================
					$customer = Mage::getModel('customer/customer')->load($event['user_id']);
					$fc = strtolower(substr($customer->getFirstname(),0,1));
					if($fc == ''){ $fc = rand(1,10); }

					if($event['event_image']=="''")
					$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
					else{
						if($event['event_image']){
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];
							
							if(fopen($img_url,"r")==false)
								$img_url = 'http://chattrspace.s3.amazonaws.com/default/160x90/'.$fc.'.jpg';
							else
								$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];
						}
						else
							$img_url = 'http://chattrspace.s3.amazonaws.com/events'.'/400x400/'.$event['event_image'];		
					}
				//===================End Image=====================================
				$from = strtotime($event['news_from_date'])+$timeoffset;
				$to = strtotime($event['news_to_date'])+$timeoffset;
				if (($now > $from) && ($now < $to)) {
					$isLive="true";
				}
				else{
					$isLive="false";
				}	
				$items[$key]=array(
							'id'=> $event['entity_id'],
							'name'=> $event['name'],
							'price'=> number_format($event['price'],2),
							'small_image'=> $img_url,
							'image'=> $img_url,
							'thumbnail'=> $img_url,
							'from_date'=> date('D M d, Y h:i A', strtotime($event['news_from_date'])+$timeoffset)." ".$abbrev,
							'to_date'=> date('D M d, Y h:i A',strtotime($event['news_to_date'])+$timeoffset)." ".$abbrev,
							'from_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_from_date'])))),
							'to_date2'=> date(DATE_RFC3339, strtotime(date('Y-m-d H:i:s', strtotime($event['news_to_date'])))),
							'from_date3'=>date('m-d-Y H:i:s', strtotime($event['news_from_date'])+$timeoffset),/*Added By surinder on demand of ajumal*/
							'to_date3'=>date('m-d-Y H:i:s',strtotime($event['news_to_date'])+$timeoffset),/*Added By surinder on demand of ajumal*/
							'hosted_by'=> $customer->getFirstname().' '.$customer->getLastname(),
							'hosted_by_username'=> $customer->getUsername(),
							'user_id'=> $event['user_id'],
							'category_name'=>$this->getCategoryNameByEventId($event['entity_id']),
							'location'=> $event['location'],
							'isLive'			=> $isLive,
				);
			}
			return $items;
		}
	}
	public function retrievePassword($email){
		if(is_numeric($email)){
			$resource = Mage::getSingleton('core/resource');
			$write= $resource->getConnection('core_write');
			$read= $resource->getConnection('core_read');
			$token = $resource->getTableName('mobile_token');
			$select="select user_id from $token where phone LIKE '%".$email."'";
			$result = $read->fetchRow($select);
			if($result['user_id'] > 0){
				$customer = Mage::getModel('customer/customer')->load($result['user_id']);
				if ($customer->getId()) {
					try {
						$newResetPasswordLinkToken = Mage::helper('customer')->generateResetPasswordLinkToken();
						$customer->changeResetPasswordLinkToken($newResetPasswordLinkToken);
						$customer->sendPasswordResetConfirmationEmail();
						return "We have sent an email to your account to reset your password.";
					} catch (Exception $exception) {
						return $exception->getMessage();
					}
				}
			}else{
				return "Phone no. is not registered.";
			}
        }elseif ($email != "") {
            if (!Zend_Validate::is($email, 'EmailAddress')) {
				return "Invalid email address.";
            }
            /** @var $customer Mage_Customer_Model_Customer */
            $customer = Mage::getModel('customer/customer')
                ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
                ->loadByEmail($email);

            if ($customer->getId()) {
                try {
                    $newResetPasswordLinkToken = Mage::helper('customer')->generateResetPasswordLinkToken();
                    $customer->changeResetPasswordLinkToken($newResetPasswordLinkToken);
                    $customer->sendPasswordResetConfirmationEmail();
					return "you will receive an email with a link to reset your password.";
                } catch (Exception $exception) {
                    return $exception->getMessage();
                }
            }else{
				return "Email id is not exist.";
			}
        }
    }
	////////////////// Email/PhoneNo. Login New Requirement Start ////////////////////// 
	public function loginNew($email, $password,$device_id=0,$imei=0,$type="",$accesstoken=""){
		if(is_numeric($email)){
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$token = $resource->getTableName('mobile_token');
			$select="select user_id from $token where phone='00".$email."'";
			$result = $read->fetchRow($select);
			if($result['user_id'] > 0){
				$customer = Mage::getModel('customer/customer')->load($result['user_id']);
				$email = $customer->getEmail();
			}else{
				throw new Exception(self::$invalidLogin);
			}
		}
		
        $returnValLogin = Mage::getSingleton( 'customer/session' )->login($email, $password);
		$returnVal = array();
		$returnVal['result'] = $returnValLogin;
		if ($returnValLogin == true) {
			$user_id = Mage::getSingleton( 'customer/session' )->getCustomerId();
			$deactivecustomers = Mage::getModel('customer/customer')->load($user_id);
			if($deactivecustomers->getIsActive() == 0){
				return "Your account is not active";
			}
			$m_dob=$this->getMDobByUserId($user_id);
			$returnVal['userInfo'] = $this->getUserInfo($user_id);
			$returnVal['dob'] = $m_dob;
			$returnVal['phone'] = $this->getMobileAndDeviceId($user_id, $device_id, $imei, $type);
			$returnVal['userEmail'] = $email;
			$returnVal['view'] = Mage::getModel('csservice/csservice')->getCheckinCount($user_id);
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$widget_fb_reg = $resource->getTableName('widget_fb_reg');
			$rs = $read->fetchRow("SELECT * FROM $widget_fb_reg WHERE uid='".$user_id."'");
			if((count($rs[id])) && ($user_id > 0)){
				$linkFB="true";
			}else{
				$linkFB="false";
			}
			$returnVal['fbLink'] = $linkFB;
			if(($deactivecustomers->getTimezone() == "") || ($deactivecustomers->getTimezone() == "null")){
				$deactivecustomers->setTimezone('America/Los_Angeles');
				$deactivecustomers->save();
			}
			$returnVal['jabberPassword'] = $this->jabberAuth($user_id);
			/////////////////////////////////////////////////
			$seckey = substr($accesstoken,0,24);
			$token = substr($accesstoken,24);
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$write = $resource->getConnection('core_write');
			$xapplication_token = $resource->getTableName('xapplication_token');
			$write->query("update cs_xapplication_token set user_id=".$user_id." WHERE token='".$token."' and security_key='".$seckey."'");
			//=====================================================================
		} else {
			throw new Exception(self::$invalidLogin);
		}
        return $returnVal;
    }
	
	public function registerDevice($deviceToken,$user_id,$type,$os,$notificationType='Online') {
		$resource = Mage::getSingleton('core/resource');
		$write= $resource->getConnection('core_write');
		$read= $resource->getConnection('core_read');
		$device = $resource->getTableName('mobile_device');
		$select="select * from $device where device_id='".$deviceToken."' and notificationType='".$notificationType."'";
		$devicedata = $read->fetchAll($select);
		if(count($devicedata) > 0){
			$sqlInsert = "update $device set user_id=".$user_id.", type='".$type."',os='".$os."',notificationType='".$notificationType."',date_updated=now() where device_id='".$deviceToken."'";
		}
		else{
		$sqlInsert = " Insert into $device(user_id, device_id, type, os, notificationType, date_added) values (".$user_id.", '".$deviceToken."','".$type."','".$os."','".$notificationType."',now())";
		}
		$write->query($sqlInsert);
		return "Successfully register device.";
	}
	public function setReportInappropriate($user_id, $newsfeed_id){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		try{
			$sql = "select * from cs_newsfeed where id = ".$newsfeed_id." and isDeleted=0";
			$result = $read->fetchRow($sql);
			if($result > 0){
				$sqlInsert = "insert into cs_report_inappropriate(newsfeed_id, reporter, reported_to, item, cat_id,type) values(".$newsfeed_id.", ".$user_id.", ".$result['user_id'].", ".$result['profile_id'].", ".$result['cat_id'].",'newsfeed')";
				$write->query($sqlInsert);
				return "Successfully reported, admin will review the report";
			}
		}catch(Exception $exception) {
			return $exception->getMessage();
		}
	}
	public function increaseVideoViewCount($user_id, $video_id,$ip_address){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		try{
			$sql = "select * from cs_most_viewed_video where video_id = ".$video_id." and user_id='".$user_id."'";
			$result = $read->fetchRow($sql);
			if($result == 0){
				$sqlInsert = "insert into cs_most_viewed_video(user_id, video_id, ip_address) values(".$user_id.", ".$video_id.", '".$ip_address."')";
				$write->query($sqlInsert);
				return "Successfully Incremented";
			}else{
				return "Already Exist";
			}
		}catch(Exception $exception) {
			return $exception->getMessage();
		}
	}
	public function getUpcomingTopHashtag($user_id){
		$items = array();
		$counter = 0;
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		
		$select = "select count( DISTINCT follower_id) as total, follow from cs_follower, cs_customer_entity WHERE follower_id<>follow and status=1 and cs_customer_entity.entity_id=cs_follower.follow";
		$select.=" group by follow ORDER BY `total`  DESC limit 10";
		$topresults = $read->fetchAll($select);
		$c=0;
		foreach($topresults as $k=>$user){
			$str[$c]=$user['follow'];
			$c = $c+1;
		}
		$topUsers = implode(',',$str);
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		if($time_zone == 'America/Los_Angeles'){
			$now = strtotime('+1 hour')+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime('+1 hour'));
		} else{
			$now = strtotime(now())+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime(now()));
		}
		$events = Mage::getResourceModel('catalog/product_collection')
				   ->addAttributeToSelect('hashtag')
				   ->addFieldToFilter('attribute_set_id', 9)
				   ->addFieldToFilter('user_id',array('in'=>array($topUsers)))
				   ->addAttributeToFilter('news_from_date', array('gteq' => $date))
				   ->addFieldToFilter('hashtag', array('neq' => ''))
				   ->addAttributeToFilter('status', 1)
				   ->setOrder('news_from_date', 'asc')
				   ->load()->toArray();
		if(count($events)>0){
			foreach($events as $k=>$evt){ $counter++;
				$items[$counter] = $evt['hashtag'];
			}
		}
		//===================================================================================================
		$select = "select count( DISTINCT user_id) as total, event_id from cs_rsvp WHERE status=1";
		$select.=" group by event_id ORDER BY `total`  DESC limit 10";
		$topRsvp = $read->fetchAll($select);
		$c=0;
		foreach($topRsvp as $k=>$e){
			$str1[$c]=$e['event_id'];
			$c = $c+1;
		}
		$topRsvpStr = implode(',',$str1);
				
		$events = Mage::getResourceModel('catalog/product_collection')
				   ->addAttributeToSelect('hashtag')
				   ->addFieldToFilter('attribute_set_id', 9)
				   ->addFieldToFilter('entity_id',array('in'=>array($topRsvpStr)))
				   ->addAttributeToFilter('news_from_date', array('gteq' => $date))
				   ->addFieldToFilter('hashtag', array('neq' => ''))
				   ->addAttributeToFilter('status', 1)
				   ->setOrder('news_from_date', 'asc')
				   ->load()->toArray();
		if(count($events)>0){
			foreach($events as $k=>$evt){ $counter++;
				$items[$counter] = $evt['hashtag'];
			}
		}
		//================================================================================================
		$events = Mage::getResourceModel('catalog/product_collection')
				   ->addAttributeToSelect('hashtag')
				   ->addFieldToFilter('attribute_set_id', 9)
				   ->addAttributeToFilter('news_from_date', array('gteq' => $date))
				   ->addFieldToFilter('hashtag', array('neq' => ''))
				   ->addAttributeToFilter('status', 1)
				   ->setOrder('news_from_date', 'asc')
				   ->setPageSize(10)
					->setPage(1,10)
				   ->load()->toArray();
		if(count($events)>0){
			foreach($events as $k=>$evt){ $counter++;
				$items[$counter] = $evt['hashtag'];
			}
		}
		if(count($items) > 0){
			return array_unique($items);
		}else{
			$select = "select hashtag from cs_hashtag limit 0,15";
			$results = $read->fetchAll($select);
			foreach($results as $k=>$rs){
				$data[$k] = $rs["hashtag"];
			}
			return $data;
		}
	}
	public function getLiveTopHashtag($user_id){
		$items = array();
		$counter = 0;
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		
		$select = "select count( DISTINCT follower_id) as total, follow from cs_follower, cs_customer_entity WHERE follower_id<>follow and status=1 and cs_customer_entity.entity_id=cs_follower.follow";
		$select.=" group by follow ORDER BY `total`  DESC limit 10";
		$topresults = $read->fetchAll($select);
		$c=0;
		foreach($topresults as $k=>$user){
			$str[$c]=$user['follow'];
			$c = $c+1;
		}
		$topUsers = implode(',',$str);
		$customer = Mage::getModel('customer/customer')->load($user_id);
		$time_zone = $customer->getTimezone();
		$abbrev = Mage::getModel('events/events')->getAbbrevation($time_zone);
		$timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
		if($time_zone == 'America/Los_Angeles'){
			$now = strtotime('+1 hour')+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime('+1 hour'));
		} else{
			$now = strtotime(now())+$timeoffset;
			$date = date('Y-m-d H:i:s',strtotime(now()));
		}
		$events = Mage::getResourceModel('catalog/product_collection')
				   ->addAttributeToSelect('hashtag')
				   ->addFieldToFilter('attribute_set_id', 9)
				   ->addFieldToFilter('user_id',array('in'=>array($topUsers)))
				  ->addAttributeToFilter('news_from_date', array('lteq' => $date))
					->addAttributeToFilter('news_to_date', array('gteq' => $date))	
				   ->addFieldToFilter('hashtag', array('neq' => ''))
				   ->addAttributeToFilter('status', 1)
				   ->setOrder('news_from_date', 'asc')
				   ->load()->toArray();
		if(count($events)>0){
			foreach($events as $k=>$evt){ $counter++;
				$items[$counter] = $evt['hashtag'];
			}
		}
		//===================================================================================================
		$select = "select count( DISTINCT user_id) as total, event_id from cs_rsvp WHERE status=1";
		$select.=" group by event_id ORDER BY `total`  DESC limit 10";
		$topRsvp = $read->fetchAll($select);
		$c=0;
		foreach($topRsvp as $k=>$e){
			$str1[$c]=$e['event_id'];
			$c = $c+1;
		}
		$topRsvpStr = implode(',',$str1);
				
		$events = Mage::getResourceModel('catalog/product_collection')
				   ->addAttributeToSelect('hashtag')
				   ->addFieldToFilter('attribute_set_id', 9)
				   ->addFieldToFilter('entity_id',array('in'=>array($topRsvpStr)))
				  ->addAttributeToFilter('news_from_date', array('lteq' => $date))
					->addAttributeToFilter('news_to_date', array('gteq' => $date))
				   ->addFieldToFilter('hashtag', array('neq' => ''))
				   ->addAttributeToFilter('status', 1)
				   ->setOrder('news_from_date', 'asc')
				   ->load()->toArray();
		if(count($events)>0){
			foreach($events as $k=>$evt){ $counter++;
				$items[$counter] = $evt['hashtag'];
			}
		}
		//================================================================================================
		$events = Mage::getResourceModel('catalog/product_collection')
				   ->addAttributeToSelect('hashtag')
				   ->addFieldToFilter('attribute_set_id', 9)
				   ->addAttributeToFilter('news_from_date', array('lteq' => $date))
					->addAttributeToFilter('news_to_date', array('gteq' => $date))
				   ->addFieldToFilter('hashtag', array('neq' => ''))
				   ->addAttributeToFilter('status', 1)
				   ->setOrder('news_from_date', 'asc')
				   ->setPageSize(10)
					->setPage(1,10)
				   ->load()->toArray();
		if(count($events)>0){
			foreach($events as $k=>$evt){ $counter++;
				$items[$counter] = $evt['hashtag'];
			}
		}
		if(count($items) > 0){
			return array_unique($items);
		}else{
			$select = "select hashtag from cs_hashtag limit 0,15";
			$results = $read->fetchAll($select);
			foreach($results as $k=>$rs){
				$data[$k] = $rs["hashtag"];
			}
			return $data;
		}
	}
    public function callNotification($type="message",$caller_id,$receiver_id,$host_id=0,$text="",$callid=0,$hashtag="") {
        Mage::log("CallNotification($type, $caller_id, $receiver_id, $host_id, $text, $callid, $hashtag) called", null, 'crm.notification.log');
        try {
            $notificationType="Online";
            switch ($type) {
                case "call" :
                    $type = "call";
                    $customer1 = Mage::getModel('customer/customer')->load($caller_id);
                    $message="Call from ".$customer1->getFirstname()." ".$customer1->getLastname();
                    $shortMsg = $customer1->getFirstname()." calling";
                    $this->mobile_push_notification_call($receiver_id, $notificationType, $message,$caller_id,$receiver_id,$callid,$type,$shortMsg);
                case "noanswer" :
                    $type = "missed_call";
                    $customer1 = Mage::getModel('customer/customer')->load($caller_id);
                    $message="You missed a call from ".$customer1->getFirstname()." ".$customer1->getLastname();
                    $shortMsg = $customer1->getFirstname()." missed call";
                    $this->mobile_push_notification_call($receiver_id, $notificationType, $message,$caller_id,$receiver_id,$callid,$type,$shortMsg);
                case "publiccall" :
                    $type = "public_call";
                    $customer1 = Mage::getModel('customer/customer')->load($caller_id);
                    $message=$customer1->getFirstname()." ".$customer1->getLastname()." started a public call";
                    $shortMsg = $customer1->getFirstname()." public call";
                    $this->mobile_push_notification_follower_call($caller_id, $notificationType, $message,$caller_id,$receiver_id,$callid,$type,$shortMsg);
                case "forwardcall" :
                    $type = "forward_call";
                    $customer1 = Mage::getModel('customer/customer')->load($caller_id);
                    $message=$customer1->getFirstname()." ".$customer1->getLastname()." is forwarding a call";
                    $shortMsg = $customer1->getFirstname()." forwarded call";
                    $this->mobile_push_notification_follower_call($caller_id, $notificationType, $message,$caller_id,$receiver_id,$callid,$type,$shortMsg);
                case "textmessage" :
                    $type = "text_message";
                    $customer1 = Mage::getModel('customer/customer')->load($caller_id);
                    if($text == ""){
                        $message=$customer1->getFirstname()." ".$customer1->getLastname()." sent you a message";
                        $shortMsg = $customer1->getFirstname()." sent message";
                    }else{
                        $message=$customer1->getFirstname()." ".$customer1->getLastname().": ".$text;
                        $shortMsg = $customer1->getFirstname()." ".$text;
                    }
                    $this->mobile_push_notification_call($receiver_id, $notificationType, $message,$caller_id,$receiver_id,$callid,$type,$shortMsg);
                case "dropin" :
                case "dropin_notf" :
                    $customer1 = Mage::getModel('customer/customer')->load($caller_id);
                    $message=$customer1->getFirstname()." ".$customer1->getLastname()." is trying to drop-in on you.";
                    $shortMsg = $customer1->getFirstname()." is dropped-in on you";
                    $this->mobile_push_notification_call($receiver_id, $notificationType, $message,$caller_id,$receiver_id,$callid,$type,$shortMsg);
                default :
                    return "Error: unknown type {$type}";
            }
        } catch (Exception $e) {
            return "Error ".$e->getMessage();
        }
    }
	public function checkUserIsHost($user_id, $event_id){
		$events = Mage::getResourceModel('catalog/product_collection')
				   ->addAttributeToSelect('user_id')
				   ->addFieldToFilter('attribute_set_id', 9)
				   ->addFieldToFilter('entity_id', $event_id)
				   ->addFieldToFilter('user_id', $user_id)
				   ->addAttributeToFilter('status', 1)
				   ->load()->toArray();
		if(count($events)>0){
			return "true";
		}else{
			return "false";
		}
	}
	public function getListBlockedUnblockedUser($user_id, $search="", $page=1){
		$resource = Mage::getSingleton('core/resource');
		$read = $resource->getConnection('core_read');
		$counter = 0;
		
			$select = "select * from cs_block_user WHERE user_id=".$user_id." and status=1";
			$results = $read->fetchAll($select);
			if($search == ""){
				if($page == 1){
			foreach($results as $rs){
				$customer = Mage::getModel('customer/customer')->load($rs['block_user_id']);
				$str[$counter]=$rs['block_user_id'];
				$item[$counter]=array(
							'user_id'=>$rs['block_user_id'],
							'username'=>$customer->getUsername(),
							'firstname'=>$customer->getFirstname(),
							'lastname'=>$customer->getLastname(),
							'name'=>$customer->getFirstname().' '.$customer->getLastname(),
							'image'=>$this->getProfilePic($rs['block_user_id']),
							'blocked'=>'true',
						);
				$counter++;
			}
			}		
			$limit=10;
			$collection = Mage::getResourceModel('customer/customer_collection')
				->addAttributeToSelect('*')
				->addAttributeToFilter('entity_id', array('nin' => $str))
				->addAttributeToFilter('entity_id', array('neq' => $user_id));
			$collection = $collection->setPageSize($limit)->setPage($page, $limit);
			$collection = $collection->load()->toArray();
			
			foreach($collection as $user){
				$item[$counter] = array(
						'user_id'=>$user['entity_id'],
						'username'=>$user['username'],
						'firstname'=>$user['firstname'],
						'lastname'=>$user['lastname'],
						'name'=>$user['firstname'].' '.$user['lastname'],
						'image'=>$this->getProfilePic($user['entity_id']),
						'blocked'=>'false',
					);
				$counter++;
			}
			$item['showMore'] = "true";
			return $item;
		}else{
			foreach($results as $rs1){
				$str[$counter]=$rs1['block_user_id'];
				$counter++;
			}
			$limit=10;
			$collection = Mage::getResourceModel('customer/customer_collection')
			->addAttributeToSelect('*')
			->addAttributeToFilter('entity_id', array('nin' => $str))
			->addAttributeToFilter('entity_id', array('neq' => $user_id))
			->addAttributeToFilter(array(
										array(
											'attribute' => 'username',
											'like'        => '%'.$search.'%',
											),
										array(
											'attribute' => 'firstname',
											'like'        => '%'.$search.'%',
											),
										));
			$collection = $collection->setPageSize($limit)->setPage($page, $limit);
			$lastPage = $collection->getLastPageNumber();
			$collection = $collection->load()->toArray();
			if($lastPage > $page){
				$showMore = "true";
			} else {
				$showMore = "false";
			}
			if($lastPage >= $page){
			foreach($collection as $k=>$user){
				$item[$k] = array(
						'user_id'=>$user['entity_id'],
						'username'=>$user['username'],
						'firstname'=>$user['firstname'],
						'lastname'=>$user['lastname'],
						'name'=>$user['firstname'].' '.$user['lastname'],
						'image'=>$this->getProfilePic($user['entity_id']),
						'blocked'=>'false',
					); 
			
			}
			}
			$item['showMore'] = $showMore;
			return $item;
		}
	}
	public function getTopRsvp($user_id=0){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		
		$select = "select count( DISTINCT user_id) as total, event_id from cs_rsvp WHERE status=1";
		$select.=" group by event_id ORDER BY `total`  DESC limit 10";
		$topRsvp = $read->fetchAll($select);
		return $topRsvp;
	}
	public function setHashtagByEventId($event_id, $hashtag){
		Mage::app()->getLocale()->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_LONG);
		$storeId = Mage::app()->getStore()->getId();
		$magentoProductModel= Mage::getModel('catalog/product')->load($event_id);
		$magentoProductModel->setStoreId($storeId);
		$magentoProductModel->setHashtag($hashtag);
		try{
			$magentoProductModel->save();
			return "Successfully updated.";
		}catch(Exception $e){
			return $e->getMessage();
		}
		
	}
	public function userCheckout($host_id, $user_id, $room_name){
		try{
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$write = $resource->getConnection('core_write');
			$sql = "update cs_user_activities set status=0 where user_id=$user_id and profile_id=$host_id";
			$write->query($sql);
		}catch(Exception $e){
			return "Error: ".$e->getMessage();
		}
		return "Successfully checkout";
	}
	public function mobile_push_notification_call($user_id,$notificationType="Online", $message="",$caller_id=0,$receiver_id=0,$call_id=0,$type="",$shortMsg=""){
		$iphone = $this->push_notification_iphone_call($user_id,$notificationType, $message,$caller_id,$receiver_id,$call_id,$type,$shortMsg);
		$android = $this->push_notification_android_call($user_id,$message,$caller_id,$receiver_id,$call_id,$type,$shortMsg);
	}
	public function mobile_push_notification_follower_call($user_id,$notificationType="Online", $message="",$caller_id=0,$receiver_id=0,$call_id=0,$type="",$shortMsg=""){
		$iphone = $this->push_notification_follower_iphone_call($user_id,$notificationType, $message,$caller_id,$receiver_id,$call_id,$type,$shortMsg);
		$android = $this->push_notification_follower_android_call($user_id,$message,$caller_id,$receiver_id,$call_id,$type,$shortMsg);
	}
	public function push_notification_iphone_call($user_id,$notificationType="Online", $message="",$caller_id=0,$receiver_id=0,$call_id=0,$type="",$shortMsg="")
	{
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$device = $resource->getTableName('mobile_device');
		$select = "select device_id from $device where notificationType='".$notificationType."' and type IN ('iPhone','iPad') and device_id!='' and active=1 and user_id=".$user_id;
		$deviceTokens = $read->fetchAll($select);
		$username = $this->getUserNameByUserId($caller_id);
		if ( $notificationType == 'develop' )
			$pemFile='/var/websites/oncam_com/webroot/certs/aps_development.pem';
		else if ( $notificationType == 'Online' )
			$pemFile='/var/websites/oncam_com/webroot/certs/aps_production.pem';
			
		$certPass = '12345'; 
		$sound="default";
		$badge="default";		
		$body['aps'] = array(
			'alert' => $message,
			'sound' => ($sound ? $sound : "default"),
			'badge' => ($badge ? $badge : "default"),
			);
		$body['oncam'] = array('caller_id' =>$caller_id,
						'username' =>$username,
						'receiver_id' => $receiver_id,
						'call_id' => $call_id,
						'type' => $type,
						'shortMsg'=>$shortMsg);
						
		$write = $resource->getConnection('core_write');
		$customer = Mage::getModel('customer/customer')->load($caller_id);
		$write->query("INSERT INTO `cs_pushnotification_cron` (`caller_id`, `call_id`, `username_of_caller`, `fullname_of_caller`, `receiver_id`, `type`, `message`, `short_msg`, `device`, `status`) VALUES ( ".$caller_id.",'".$call_id."' ,'".$username."', '".$customer->getFirstname()." ".$customer->getLastname()."', ".$receiver_id.", '".$type."', '".$message."', '".$shortMsg."', 'iphone', 1);");				
		$ctx = stream_context_create();
		stream_context_set_option($ctx, 'ssl', 'local_cert', $pemFile);
		//stream_context_set_option($ctx, 'ssl', 'passphrase', $certPass);

		if ( $notificationType == 'develop' )
			$ssl_gateway_url = 'ssl://gateway.sandbox.push.apple.com:2195';
		else if ( $notificationType == 'Online' )
			$ssl_gateway_url = 'ssl://gateway.push.apple.com:2195';
		
		if(isset($ssl_gateway_url))
		{
			$fp = stream_socket_client($ssl_gateway_url, $err, $errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);
		}
		
		$payload = json_encode($body);
			
		foreach($deviceTokens as $deviceToken){
			$deviceIdab = trim($deviceToken['device_id']);
			$msg = chr(0).pack('n', 32).pack('H*', str_replace(' ', '',$deviceIdab)).pack('n', strlen($payload)).$payload;
			$result = fwrite($fp, $msg, strlen($msg));
			$arr[] = $deviceIdab;
		}
		fclose($fp);
		return $arr;
	}
	public function push_notification_android_call($user_id,$message="",$caller_id=0,$receiver_id=0,$call_id=0,$type="",$shortMsg="") {
		$headers = array(
		 'Content-Type:application/json',
		 'Authorization:key=AIzaSyDYNt9ftmzDT2aExpnyxP6pkmeMkacbQU4'
		);
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$device = $resource->getTableName('mobile_device');
		$select = "select device_id from $device where type IN ('Android') and device_id!='' and user_id=".$user_id;
		$deviceTokens = $read->fetchAll($select);
		$count = count($deviceTokens);
		$customer = Mage::getModel('customer/customer')->load($caller_id);
		$username = $customer->getUsername();
		$arr   = array();
		$arr['data']['type'] = $type;
		$arr['data']['caller_id'] = $caller_id;
		$arr['data']['username'] = $username;
		$arr['data']['receiver_id'] = $receiver_id;
		$arr['data']['call_id'] = $call_id;
		$arr['data']['msg'] = $message;
		$arr['data']['shortMsg'] = $shortMsg;
		$arr['data']['fullname'] = $customer->getFirstname()." ".$customer->getLastname();
		$arr['data']['count'] = $count;
		$arr['registration_ids'] = array();
		
		$write = $resource->getConnection('core_write');
		$write->query("INSERT INTO `cs_pushnotification_cron` (`caller_id`, `call_id`, `username_of_caller`, `fullname_of_caller`, `receiver_id`, `type`, `message`, `short_msg`, `device`, `status`) VALUES ( ".$caller_id.",'".$call_id."' ,'".$username."', '".$customer->getFirstname()." ".$customer->getLastname()."', ".$receiver_id.", '".$type."', '".$message."', '".$shortMsg."', 'android', 1);");	
		
		foreach($deviceTokens as $k=>$dc){
			$arr['registration_ids'][$k] = $dc["device_id"];
		}
		//return $arr;	
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,    'https://android.googleapis.com/gcm/send');
		curl_setopt($ch, CURLOPT_HTTPHEADER,  $headers);
		curl_setopt($ch, CURLOPT_POST,    true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($arr));
		try{
		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
		} catch (Exception $e){
			Mage::log($e,null,'gcm.log');
		}
	}
	public function push_notification_follower_iphone_call($user_id,$notificationType="Online", $message="",$caller_id=0,$receiver_id=0,$call_id=0,$type="",$shortMsg="")
	{
		$txtMsg = $message;
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$device = $resource->getTableName('mobile_device');
		$follower = $resource->getTableName('follower');
		//$select = "select device_id from $device where notificationType='".$notificationType."' and type IN ('iPhone','iPad') and device_id!='' and active=1 and user_id IN(select follower_id from $follower WHERE follow=".$user_id." and follower_id<>follow and status=1 group by follower_id)";
		
		$select = "select cs_mobile_device.device_id from cs_mobile_device JOIN cs_follower ON cs_mobile_device.user_id = cs_follower.follower_id where cs_follower.follow=".$user_id." and cs_follower.follower_id<>cs_follower.follow and cs_follower.status=1 and cs_mobile_device.notificationType='".$notificationType."' and cs_mobile_device.type IN ('iPhone','iPad') and cs_mobile_device.device_id!='' and cs_mobile_device.active=1 group by cs_mobile_device.device_id";
		$deviceTokens = $read->fetchAll($select);
		$username = $this->getUserNameByUserId($caller_id);
		if ( $notificationType == 'develop' )
			$pemFile='/var/websites/oncam_com/webroot/certs/aps_development.pem';
		else if ( $notificationType == 'Online' )
			$pemFile='/var/websites/oncam_com/webroot/certs/aps_production.pem';
		
		require_once 'Autoload.php';

		// Instanciate a new ApnsPHP_Push object
		$push = new ApnsPHP_Push(
			ApnsPHP_Abstract::ENVIRONMENT_PRODUCTION,$pemFile);

		// Set the Root Certificate Autority to verify the Apple remote peer
		//$push->setRootCertificationAuthority('entrust_root_certification_authority.pem');

		// Increase write interval to 100ms (default value is 10ms).
		// This is an example value, the 10ms default value is OK in most cases.
		// To speed up the sending operations, use Zero as parameter but
		// some messages may be lost.
		 $push->setWriteInterval(100 * 1000);

		// Connect to the Apple Push Notification Service
		$push->connect();
		$i=0;
		for ($i = 0; $i < count($deviceTokens); $i++) {
			// Instantiate a new Message with a single recipient
			$message = new ApnsPHP_Message($deviceTokens[$i]["device_id"]);

			// Set a custom identifier. To get back this identifier use the getCustomIdentifier() method
			// over a ApnsPHP_Message object retrieved with the getErrors() message.
			$message->setCustomIdentifier("Message-Badge-$i");

			// Set badge icon to "3"
			$message->setBadge(4);
			$message->setText($txtMsg);
			$message->setCustomProperty('oncam', array('caller_id' => $caller_id, 
											  'username' => $username, 
											  'receiver_id' => $receiver_id,
											  'call_id' => $call_id,
											  'type' => $type,
											  'shortMsg'=>$shortMsg));
			// Add the message to the message queue
			$push->add($message);
			//$i++;
		}
		$write = $resource->getConnection('core_write');
		$customer = Mage::getModel('customer/customer')->load($caller_id);
		$write->query("INSERT INTO `cs_pushnotification_cron` (`caller_id`, `call_id`, `username_of_caller`, `fullname_of_caller`, `receiver_id`, `type`, `message`, `short_msg`, `device`, `status`) VALUES ( ".$caller_id.",'".$call_id."' ,'".$username."', '".$customer->getFirstname()." ".$customer->getLastname()."', ".$receiver_id.", '".$type."', '".$txtMsg."', '".$shortMsg."', 'iphone', 1);");
		// Send all messages in the message queue
		@$push->send();

		// Disconnect from the Apple Push Notification Service
		$push->disconnect();

		// Examine the error message container
		//$aErrorQueue = $push->getErrors();
		//if (!empty($aErrorQueue)) {
		//	return $aErrorQueue;
		//}
	}
	public function push_notification_follower_android_call($user_id,$message="",$caller_id=0,$receiver_id=0,$call_id=0,$type="",$shortMsg="") {
		$headers = array(
		 'Content-Type:application/json',
		 'Authorization:key=AIzaSyDYNt9ftmzDT2aExpnyxP6pkmeMkacbQU4'
		);
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$device = $resource->getTableName('mobile_device');
		$follower = $resource->getTableName('follower');
		//$select2 = "select device_id from $device where type='Android' and device_id!='' and user_id IN(select follower_id from $follower WHERE follow=".$user_id." and follower_id<>follow and status=1 group by follower_id)";
		$select2 = "select cs_mobile_device.device_id from cs_mobile_device JOIN cs_follower ON cs_mobile_device.user_id = cs_follower.follower_id where cs_follower.follow=".$user_id." and cs_follower.follower_id<>cs_follower.follow and cs_follower.status=1 and cs_mobile_device.type='Android' and cs_mobile_device.device_id!='' group by cs_mobile_device.device_id";
		$deviceTokens = $read->fetchAll($select2);
		$count = count($deviceTokens);
		$customer = Mage::getModel('customer/customer')->load($caller_id);
		$username = $customer->getUsername();
		$arr   = array();
		$arr['data']['type'] = $type;
		$arr['data']['caller_id'] = $caller_id;
		$arr['data']['username'] = $username;
		$arr['data']['receiver_id'] = $receiver_id;
		$arr['data']['call_id'] = $call_id;
		$arr['data']['msg'] = $message;
		$arr['data']['shortMsg'] = $shortMsg;
		$arr['data']['fullname'] = $customer->getFirstname()." ".$customer->getLastname();
		$arr['data']['count'] = $count;
		$arr['registration_ids'] = array();
		
		$write = $resource->getConnection('core_write');
		$write->query("INSERT INTO `cs_pushnotification_cron` (`caller_id`, `call_id`, `username_of_caller`, `fullname_of_caller`, `receiver_id`, `type`, `message`, `short_msg`, `device`, `status`) VALUES ( ".$caller_id.",'".$call_id."' ,'".$username."', '".$customer->getFirstname()." ".$customer->getLastname()."', ".$receiver_id.", '".$type."', '".$message."', '".$shortMsg."', 'android', 1);");	
		
		foreach($deviceTokens as $k=>$dc){
			$arr['registration_ids'][$k] = $dc["device_id"];
		}
		//return $arr;	
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,    'https://android.googleapis.com/gcm/send');
		curl_setopt($ch, CURLOPT_HTTPHEADER,  $headers);
		curl_setopt($ch, CURLOPT_POST,    true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($arr));
		try{
		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
		} catch (Exception $e){
			Mage::log($e,null,'gcm.log');
		}
	}

	// while user is in live recording mode, when click upload button of video, Pre-save the privacy 
	public function presetVideoPrivacy($user_id, $privacy=0){ // 0-Everyone, 1-Me, 2-Favourite
		try{
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$write= $resource->getConnection('core_write');			
			$SavePreVideoPrivacy = "insert into cs_pre_video_privacy(user_id,privacy) values ($user_id,$privacy)";;
			$write->query($SavePreVideoPrivacy);
			$thelastId = $write->lastInsertId();
			return $thelastId;
		}catch(Exception $e) {
			return $e->getMessage();
		}		
	}

	public function setVideoPrivacy($user_id, $video_id, $privacy=0){ // 0-Everyone, 1-Me, 2-Favourite
		try{
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$write= $resource->getConnection('core_write');
			$newsfeedTable = $resource->getTableName('newsfeed');
			$selectFeed = "update $newsfeedTable set privacy=$privacy where profile_id=$video_id and user_id=$user_id and cat_id=6";
			$write->query($selectFeed);
			$selectVideo = "update cs_video set privacy=$privacy where video_id=$video_id and user_id=$user_id";
			$write->query($selectVideo);
			return "Successfully privacy updated";
		}catch(Exception $e) {
			return $e->getMessage();
		}		
	}
	public function setVideoViewed($user_id, $video_id){
		try{
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$write= $resource->getConnection('core_write');
			$select = "select * from cs_video_view_count where video_id=$video_id and user_id=$user_id";
			$result = $read->fetchRow($select);
			if($result == 0){
				$selectVideo = "insert into cs_video_view_count(user_id,video_id) values ($user_id,$video_id)";
				$write->query($selectVideo);
				
				$select1 = "select view_count from cs_video where video_id=$video_id";
				$result1 = $read->fetchRow($select1);
				$count = $result1['view_count'] + 1;
				
				$selectVid = "update cs_video set view_count=$count where video_id=$video_id";
				$write->query($selectVid);
				return "Successfully view updated";
			}
		}catch(Exception $e) {
			return $e->getMessage();
		}		
	}
	public function getFavouriteFollowingFollower($user_id, $page=1){
		$data = array();
		if($page == 1){
			$data['favourites'] = $this->getFavouriteContacts($user_id);
		}
		$data['followings'] = $this->getFollowings($user_id, $page);
		$data['followers'] = $this->getFollowersById($user_id, $page);
		return $data;
	}
	public function sendNotificationAddedUserGroup($added_id, $caller_id=0, $group_id, $type){
		if($_POST["caller_id"] > 0){
			$notificationType="Online";
			$type = "AddedUserGroupCall";
			$caller_id = $_POST["caller_id"];
			$added_id = $_POST["added_id"];

			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$device = $resource->getTableName('mobile_device');
			$select = "select device_id from $device where notificationType='".$notificationType."' and type IN ('iPhone','iPad') and device_id!='' and active=1 and user_id='".$added_id."'";
			$deviceToken = $read->fetchrow($select);

			//get groupname
			$select_group = "select widget_title from cs_widget_info where id='".$group_id."'";
			$group = $read->fetchrow($select_group);

			$added_user = Mage::getSingleton( 'customer/customer' )->load($caller_id);
			$txtMsg = "You were added into the <Group ".$group["widget_title"].">";
			$username = $added_user->getUsername();	
			$shortMsg = $customer->getFirstname()." added you their call";			
			if ( $notificationType == 'develop' )
				$pemFile='/var/websites/oncam_com/webroot/certs/aps_development.pem';
			else if ( $notificationType == 'Online' )
				$pemFile='/var/websites/oncam_com/webroot/certs/aps_production.pem';
			if($_POST["type"] == "android"){
				$abc = $this->push_notification_android_call($added_id,$txtMsg,$caller_id,0,0,$type,$shortMsg);
				return "Push Notification sent";
			}
			if($_POST["type"] == "IOS"){
				require_once 'Autoload.php';

				// Instanciate a new ApnsPHP_Push object
				$push = new ApnsPHP_Push(
					ApnsPHP_Abstract::ENVIRONMENT_PRODUCTION,$pemFile);

				// Set the Root Certificate Autority to verify the Apple remote peer
				//$push->setRootCertificationAuthority('entrust_root_certification_authority.pem');

				// Increase write interval to 100ms (default value is 10ms).
				// This is an example value, the 10ms default value is OK in most cases.
				// To speed up the sending operations, use Zero as parameter but
				// some messages may be lost.
				 $push->setWriteInterval(100 * 1000);

				// Connect to the Apple Push Notification Service
				$push->connect();				
				
				// Instantiate a new Message with a single recipient
				$message = new ApnsPHP_Message($deviceToken["device_id"]);

				// Set a custom identifier. To get back this identifier use the getCustomIdentifier() method
				// over a ApnsPHP_Message object retrieved with the getErrors() message.
				$message->setCustomIdentifier("Message-Badge-0");

				// Set badge icon to "3"
				$message->setBadge(4);
				$message->setText($txtMsg);
				$message->setCustomProperty('oncam', array('caller_id' => $caller_id, 
												  'username' => $username, 
												  'receiver_id' => 0,
												  'call_id' => 0,
												  'type' => $type,
												  'shortMsg'=>$shortMsg));
				// Add the message to the message queue
				$push->add($message);					
				

				$write = $resource->getConnection('core_write');
				$customer = Mage::getModel('customer/customer')->load($caller_id);
				$write->query("INSERT INTO `cs_pushnotification_cron` (`caller_id`, `call_id`, `username_of_caller`, `fullname_of_caller`, `receiver_id`, `type`, `message`, `short_msg`, `device`, `status`) VALUES ( ".$caller_id.",'0' ,'".$username."', '".$customer->getFirstname()." ".$customer->getLastname()."', 0, '".$type."', '".$txtMsg."', '".$shortMsg."', 'iphone', 1);");
				
				// Send all messages in the message queue
				@$push->send();

				// Disconnect from the Apple Push Notification Service
				$push->disconnect();
                }

			}else {
				return false;
			}
	}
	public function sendInvitationJoinLivePublicCall($invitee,$caller_id=0,$type){
		if($_POST["caller_id"] > 0){
		$notificationType="Online";
		$type = "JoinLivePublicCall";
		$caller_id = $_POST["caller_id"];
		$invitee = $_POST["invitee"];
		//$customer = Mage::getSingleton( 'customer/customer' )->load($_POST["caller_id"]);
		
		//$allInvitee = implode(',',$_POST["invitee"]);
		$customer = Mage::getSingleton( 'customer/customer' )->load($caller_id);
		
		$allInvitee = implode(',',$invitee);
		$txtMsg = $customer->getFirstname()." invites you to join a live public call";
		$shortMsg = $customer->getFirstname()." join their call";
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$device = $resource->getTableName('mobile_device');
		$select = "select device_id from $device where notificationType='".$notificationType."' and type IN ('iPhone','iPad') and device_id!='' and active=1 and user_id IN($allInvitee)";
		$deviceTokens = $read->fetchAll($select);
		$username = $customer->getUsername();
		if ( $notificationType == 'develop' )
			$pemFile='/var/websites/oncam_com/webroot/certs/aps_development.pem';
		else if ( $notificationType == 'Online' )
			$pemFile='/var/websites/oncam_com/webroot/certs/aps_production.pem';
		if($_POST["type"] == "android"){
			$abc = $this->push_notification_follower_android_call($caller_id,$txtMsg,0,0,0,$type,$shortMsg);
			return "Push Notification sent";
		}
		if($_POST["type"] == "IOS"){
		require_once 'Autoload.php';

		// Instanciate a new ApnsPHP_Push object
		$push = new ApnsPHP_Push(
			ApnsPHP_Abstract::ENVIRONMENT_PRODUCTION,$pemFile);

		// Set the Root Certificate Autority to verify the Apple remote peer
		//$push->setRootCertificationAuthority('entrust_root_certification_authority.pem');

		// Increase write interval to 100ms (default value is 10ms).
		// This is an example value, the 10ms default value is OK in most cases.
		// To speed up the sending operations, use Zero as parameter but
		// some messages may be lost.
		 $push->setWriteInterval(100 * 1000);

		// Connect to the Apple Push Notification Service
		$push->connect();
		$i=0;
		for ($i = 0; $i < count($deviceTokens); $i++) {
			// Instantiate a new Message with a single recipient
			$message = new ApnsPHP_Message($deviceTokens[$i]["device_id"]);

			// Set a custom identifier. To get back this identifier use the getCustomIdentifier() method
			// over a ApnsPHP_Message object retrieved with the getErrors() message.
			$message->setCustomIdentifier("Message-Badge-$i");

			// Set badge icon to "3"
			$message->setBadge(4);
			$message->setText($txtMsg);
			$message->setCustomProperty('oncam', array('caller_id' => $caller_id, 
											  'username' => $username, 
											  'receiver_id' => 0,
											  'call_id' => 0,
											  'type' => $type,
											  'shortMsg'=>$shortMsg));
			// Add the message to the message queue
			$push->add($message);
			//$i++;
		}
		
		$write = $resource->getConnection('core_write');
		$customer = Mage::getModel('customer/customer')->load($caller_id);
		$write->query("INSERT INTO `cs_pushnotification_cron` (`caller_id`, `call_id`, `username_of_caller`, `fullname_of_caller`, `receiver_id`, `type`, `message`, `short_msg`, `device`, `status`) VALUES ( ".$caller_id.",'0' ,'".$username."', '".$customer->getFirstname()." ".$customer->getLastname()."', 0, '".$type."', '".$txtMsg."', '".$shortMsg."', 'iphone', 1);");
		
		// Send all messages in the message queue
		@$push->send();

		// Disconnect from the Apple Push Notification Service
		$push->disconnect();

		// Examine the error message container
		//$aErrorQueue = $push->getErrors();
		//if (!empty($aErrorQueue)) {
		//	return $aErrorQueue;
		//}
		}
		}else {
				return 'Use Form POST';
		}
	}
	/*
	public function getImageList($gt,$lt){
			 $limit=10000;
			$collection = Mage::getResourceModel('customer/customer_collection')
				->addAttributeToSelect('entity_id')
				->addFieldToFilter('entity_id', array('gt'=> $gt))
				->addFieldToFilter('entity_id', array('lt'=> $lt));
				//->addAttributeToFilter('entity_id', $username)
			//$collection = $collection->setPageSize($limit)->setPage($page, $limit);
			$collection = $collection->load()->toArray();
			
			foreach($collection as $k=>$user){
			$old = $this->getProfilePic($user['entity_id']);
			$new = "/var/websites/oncam_com/webroot/profileimgbyid/30x30/".$user['entity_id'].".png";
			if(!copy($old, $new)){
				$data[$k] = array(
						'id'=>$user['entity_id'],
					); 
			}
			$data['last'] = $user['entity_id'];
			}
			return $data;
	}
	public function oncamrosterinfo1($start,$end){
	try{
				//$customer = Mage::getModel('customer/customer')->load($user['entity_id']);
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
				$sql = "select id,jid from oncamrosterinfo where id > ".$start." and id < ".$end;
				$aaa=mysql_query($sql,$con);
				while($row = mysql_fetch_array($aaa))
				{
					list($id, $txt) = split('[@]', $row['jid']);
					$customer = Mage::getModel('customer/customer')->load($id);
					$firstname = $customer->getFirstname();
					$lastname = $customer->getLastname();
					$sqlupdate="update oncamrosterinfo set firstname='".$firstname."',lastname='".$lastname."' where id='".$row['id']."'";
					$bbb=mysql_query($sqlupdate,$con);
					$lastid = $row['id'];
				}
				mysql_close($con);
			
		}catch(Exception $e){
			return "Error: ".$e->getMessage();
		}
			return $lastid;
	}
	public function updateRosterUser($start,$end){
		$resource = Mage::getSingleton('core/resource');
		$write = $resource->getConnection('core_write');
		$read = $resource->getConnection('core_read');
		$sql='SELECT follower_id,follow FROM cs_follower_tmp where id > '.$start.' and id < '.$end;
		$results=$read->fetchAll($sql);
		$data=array();
		foreach($results as $k=>$r){
			$data[]=$r;
			$customerChat = Mage::getModel('customer/customer')->load($r['follower_id']);
			$chatUser = $customerChat->getUsername();
			$customerChat1 = Mage::getModel('customer/customer')->load($r['follow']);
			$chatUser1 = $customerChat1->getUsername();
			$con=mysql_connect("chattr-jabbr.cwliz6chxmwt.us-east-1.rds.amazonaws.com","chattr_process2","h6fnf5.p4-");
			$db_selected = mysql_select_db('chattr_process2', $con);
			$sql="INSERT IGNORE INTO rosterusers (username, jid, nick, subscription, ask, askmessage, server, subscribe, type) VALUES ('".$r['follower_id']."', '".$r['follow']."@chatweb.oncam.com', '".$chatUser1."', 'T', 'I', '', 'N', '', 'item')";
			$aaa=mysql_query($sql,$con);
			
			$sql1="INSERT IGNORE INTO rosterusers (username, jid, nick, subscription, ask, askmessage, server, subscribe, type) VALUES ('".$r['follow']."', '".$r['follower_id']."@chatweb.oncam.com', '".$chatUser."', 'F', 'O', '', 'N', '', 'item')";
			$bbb=mysql_query($sql1,$con);

		}
		return $data;
	}
	public function followUserByIdBy20($start, $end, $follower){
		$resource = Mage::getSingleton('core/resource');
		$write = $resource->getConnection('core_write');
		$table = $resource->getTableName('follower');
		for($i=$start;$i<=$end;$i += 2){
			if($i < 39071 || $i > $follower && $follower!=39571 && $i!=39571){
				$following=$i;
				Mage::getModel('csservice/csservice')->updateJabberUser($follower, $following, 1);
				if($this->isfollowing($follower, $following, 1))
					$write->query("update $table set status=1, notify=1, follow_on=now()  WHERE follower_id=".$follower." and follow=".$following);
				else
					$write->query("insert ignore into $table (follower_id, follow, status, follow_on, notify) values(".$follower.", ".$following." ,1, now(), 1)");
				//================================================
				$newsfeed = $resource->getTableName('newsfeed');
				if($this->isFollow($following,$follower)){
					$write->query("insert ignore into $newsfeed (user_id, profile_id, cat_id) values(".$follower.", ".$following.",10)");
				}else{
					$write->query("insert ignore into $newsfeed (user_id, profile_id, cat_id) values(".$follower.", ".$following.",7)");
				}
				//===================================================
				$customer = Mage::getModel('customer/customer')->load($following);	
				$notice = $customer->getNotice();
				$a = explode(",",$notice);	
				if(in_array(18,$a)){
					$this->sendMail($following, $follower);	
				}
				//===========================================================================
				try{
					$notificationType="Online";
					$username = $this->getUserNameByUserId($follower);
					$message=$username." following you";
					$pushNoti = $this->mobile_push_notification($following, $notificationType, $message);
				}catch(Exception $e){
				
				}
			}
		}
	}
	public function updateTestUser($start, $end, $id){
		$num=$id;
		$con=mysql_connect("chattr-jabbr.cwliz6chxmwt.us-east-1.rds.amazonaws.com","chattr_process2","h6fnf5.p4-");
		$resource = Mage::getSingleton('core/resource');
		$write = $resource->getConnection('core_write');
		$widget = $resource->getTableName('widget_info');
		$db_selected = mysql_select_db('chattr_process2', $con);
		for($i=$start;$i<=$end;$i += 2){
			if($i!=39571){
				$id=sprintf("%04s", $num);
				$first_name='first'.$id;
				$last_name='last'.$id;
				$email='testuser'.$id.'@oncamtest.com';
				$username='testuser'.$id;
				
				$customer = Mage::getModel('customer/customer')->load($i);
				
				$oldusername=$customer->getUsername();
				
				$customer->setFirstname($first_name);
				$customer->setLastname($last_name);
				$customer->setUsername($username);
				$customer->setEmail($email);
				$customer->save();
				
				$write->query("update $widget set username='".$username."' WHERE user_id='".$i."'");

				$uldURLCollection = Mage::getModel('core/url_rewrite')->getResourceCollection();
				$uldURLCollection->getSelect()
					->where('id_path=?', 'csprofile/'.$username);
				$uldURLCollection->setPageSize(1)->load();
				
				if ( $uldURLCollection->count() > 0 ) {
					$uldURLCollection->getFirstItem()->delete();
				}
				
				$modelURLRewrite = Mage::getModel('core/url_rewrite');
				$modelURLRewrite->setIdPath('csprofile/'.$username)
					->setTargetPath('csprofile/index/view/username/'.$username)
					->setOptions('')
					->setDescription(null)
					->setRequestPath($username);
				$modelURLRewrite->save();
				
				$sql="update oncamrosterinfo set firstname='".$first_name."',lastname='".$last_name."' where jid='".$i."@chatweb.oncam.com'";
				mysql_query($sql,$con);
				$sql1="update rosterusers set nick='".$username."' where nick='".$oldusername."'";
				mysql_query($sql1,$con);
				$num=$num+1;
			}
		}
	}*/
	public function updateRosterUser($start,$end){
		$resource = Mage::getSingleton('core/resource');
		$write = $resource->getConnection('core_write');
		$read = $resource->getConnection('core_read');
		$sql='SELECT follower_id,follow FROM cs_follower where status=1 and  id > '.$start.' and id < '.$end;
		$results=$read->fetchAll($sql);
		$data=array();
		$con=mysql_connect("chattr-jabbr.cwliz6chxmwt.us-east-1.rds.amazonaws.com","chattr_process2","h6fnf5.p4-");
		$db_selected = mysql_select_db('chattr_process2', $con);
		foreach($results as $k=>$r){
			$isfollow = $this->isFollow($r['follower_id'],$r['follow']);
			$customerChat = Mage::getModel('customer/customer')->load($r['follower_id']);
			$chatUser = $customerChat->getUsername();
			$customerChat1 = Mage::getModel('customer/customer')->load($r['follow']);
			$chatUser1 = $customerChat1->getUsername();
			if($chatUser !='' && $chatUser1 != ''){
				if($isfollow == 1 ){
					$sql="INSERT IGNORE INTO rosterusers_new (username, jid, nick, subscription, ask, askmessage, server, subscribe, type) VALUES ('".$r['follower_id']."', '".$r['follow']."@chatweb.oncam.com', '".$chatUser1."', 'B', 'N', '', 'N', '', 'item')";
					$aaa=mysql_query($sql,$con);
					
					$sql1="INSERT IGNORE INTO rosterusers_new (username, jid, nick, subscription, ask, askmessage, server, subscribe, type) VALUES ('".$r['follow']."', '".$r['follower_id']."@chatweb.oncam.com', '".$chatUser."', 'B', 'N', '', 'N', '', 'item')";
					$bbb=mysql_query($sql1,$con);
				}else{
					$sql="INSERT IGNORE INTO rosterusers_new (username, jid, nick, subscription, ask, askmessage, server, subscribe, type) VALUES ('".$r['follower_id']."', '".$r['follow']."@chatweb.oncam.com', '".$chatUser1."', 'T', 'I', '', 'N', '', 'item')";
					$aaa=mysql_query($sql,$con);
					
					$sql1="INSERT IGNORE INTO rosterusers_new (username, jid, nick, subscription, ask, askmessage, server, subscribe, type) VALUES ('".$r['follow']."', '".$r['follower_id']."@chatweb.oncam.com', '".$chatUser."', 'F', 'O', '', 'N', '', 'item')";
					$bbb=mysql_query($sql1,$con);
				}
			}

		}
		//return $data;
	}
	public function pushtesting(){

		$deviceToken = 'e2ec777a382b2841aa3d5bec6b0a302a4358e10fd1bdce39855c6525260d8226'; //add-hoc
		//$deviceToken = 'c4551eb846d4686266f5f6a9484c7b78d0908657466672c41d92050f95ae5bf1'; //joe
		$message = 'Mritunjay is testing';

		//$apnsHost = 'gateway.sandbox.push.apple.com';
		//$apnsCert = 'aps_development.pem';

		$apnsHost = 'gateway.push.apple.com';
		$apnsCert = '/var/websites/oncam_com/webroot/certs/aps_production.pem';

		$apnsPort = 2195;

		$payload = array('aps' => array('alert' => $message, 'badge' => 0, 'sound' => 'default'));
		$payload = json_encode($payload);

		$streamContext = stream_context_create();
		stream_context_set_option($streamContext, 'ssl', 'local_cert', $apnsCert);

		$apns = stream_socket_client('ssl://'.$apnsHost.':'.$apnsPort, $error, $errorString, 2, STREAM_CLIENT_CONNECT, $streamContext);

        if($apns)
        {
            $apnsMessage = chr(0).chr(0).chr(32).pack('H*', str_replace(' ', '', $deviceToken)).chr(0).chr(strlen($payload)).$payload;
            fwrite($apns, $apnsMessage);
            fclose($apns);
        }
    }
    public function createPrivateEventIphone($user_id=0, $title=null,$description=null,$location=null,$hashtag=null,$from_date=null,$to_date=null,$data=null,$event_id=0,$invitee="",$permission=1) {
        if (isset($_POST["data"]) && ($_POST["data"] !="") && isset($_POST["user_id"]) && ($_POST["user_id"]>0)){
            $user_id = $_POST["user_id"];
            $title = strip_tags($_POST["title"]);
            $description = strip_tags($_POST["description"]);
            $location = strip_tags($_POST["location"]);
            $hashtag = strip_tags($_POST["hashtag"]);
            $from_date = $_POST["from_date"];
            $to_date = $_POST["to_date"];
            $invitee = $_POST["invitee"];
            $permission = $_POST["permission"];
            $cat_id = 18;
            $event_id = $_POST["event_id"];
            if($title == ""){
                return "Error : Title is blank";
            }
            if($description == ""){
                return "Error : Description is blank";
            }
            if($location == ""){
                return "Error : Location is blank";
            }
            if($hashtag=="")
                $hashtag = "oncam";
            $price=0;
            $no_att=self::$init_max_attendees;
            //from 05/28/13 12:26
            //to 05/28/13 20:26
            $sku = ereg_replace('[^A-Za-z0-9.]', '-', date('m-d-y H:i:s'));
            $catId = '3,'.$cat_id;
            if($user_id){
                $customerId = $user_id;
                $sku = 'chattrspace-'. $user_id ."-" . $sku;
                $customer = Mage::getModel('customer/customer')->load($user_id);
                $time_zone = $customer->getTimezone();
                $timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
                $from_date1 = date('Y-m-d H:i:s', strtotime($from_date));
                if($to_date == null){
                    $to_date1 = date('Y-m-d H:i:s', strtotime('+60 minutes',strtotime($from_date)));
                }else{
                    $to_date1 = date('Y-m-d H:i:s', strtotime($to_date));
                }
                if($to_date1 < $from_date1){
                    return "Error : End Date is less than Start Date";
                }
                if($from_date != ''){
                    $from_date = date('Y-m-d H:i:s', strtotime($from_date) - $timeoffset);
                }
                if($to_date == null){
                    $to_date = date('Y-m-d H:i:s', strtotime('+60 minutes',strtotime($from_date)));
                }else{
                    $to_date = date('Y-m-d H:i:s', strtotime($to_date) - $timeoffset);
                }
                $from_date_array = explode(" ", $from_date);
                $from_array = explode("-", $from_date_array[0]);
                $to_date_array = explode(" ", $to_date);
                $to_array = explode("-", $to_date_array[0]);

                $array_year = array(2020=>142,2019=>143, 2018=>144, 2017=>145, 2016=>146, 2015=>147
                ,2014=>148, 2013=>149, 2012=>150, 2011=>151);

                $array_day = array(01=>129, 02=>128, 03=>127, 04=>126, 05=>125, 06=>124
                ,07=>123, 08=>122, 09=>121, 10=>120
                ,11=>119, 12=>118, 13=>117, 14=>116
                ,15=>115, 16=>114, 17=>113, 18=>112
                ,19=>111, 20=>110, 21=>109, 22=>108
                ,23=>107, 24=>106, 25=>105, 26=>104
                ,27=>103, 28=>102, 29=>101, 30=>100
                ,31=>99);

                $array_month = array (01=>141, 02=>140, 03=>139, 04=>138, 05=>137, 06=>136
                , 07=>135, 08=>134, 09=>133, 10=>132, 11=>131
                , 12=>130);
                $is_weekend=153;
                $d = date('D', mktime(0,0,0,$from_array[1], $from_array[2], $from_array[0]));
                if($d=="Sat" || $d=="Sun")
                    $is_weekend = 152;
                Mage::app()->getLocale()->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_LONG);
                $storeId = Mage::app()->getStore()->getId();
                $filename = '';
                if($event_id > 0){
                    $magentoProductModel= Mage::getModel('catalog/product')->load($event_id);
                    $magentoProductModel->setStoreId($storeId);
                }else{
                    $magentoProductModel= Mage::getModel('catalog/product');
                    $magentoProductModel->setStoreId(0);
                }
                $magentoProductModel->setWebsiteIds(array(1));
                $magentoProductModel->setAttributeSetId(9);
                $magentoProductModel->setTypeId('simple');
                $magentoProductModel->setName($title);
                $magentoProductModel->setProductName($title);
                $magentoProductModel->setSku($sku);
                $magentoProductModel->setUserId($user_id);
                $magentoProductModel->setShortDescription($description);
                $magentoProductModel->setDescription($description);
                $magentoProductModel->setPrice($price);
                $magentoProductModel->setSpecialPrice($vol_price);
                $magentoProductModel->setSalesQty(100);
                $magentoProductModel->setWeight(0);
                $magentoProductModel->setIsExpired(155);
                $magentoProductModel->setLocation($location);
                $magentoProductModel->setHashtag($hashtag);
                $magentoProductModel->setInvitee($invitee);
                $magentoProductModel->setPermission($permission);
                $magentoProductModel->setVisibility(4);

                $magentoProductModel->setNewsFromDate($from_date);
                $magentoProductModel->setNewsToDate($to_date);

                $magentoProductModel->setToDay($to_array[2]);
                $magentoProductModel->setToMonth($to_array[1]);
                $magentoProductModel->setToYear($to_array[0]);

                $magentoProductModel->setFromDay($array_day[intval($from_array[2])]);
                $magentoProductModel->setFromMonth($array_month[intval($from_array[1])]);
                $magentoProductModel->setFromYear($array_year[intval($from_array[0])]);

                $magentoProductModel->setFromTime($from_date_array[1]);
                $magentoProductModel->setToTime($to_date_array[1]);

                $magentoProductModel->setIsWeekend($is_weekend);

                $magentoProductModel->setMaximumOfAttendees($no_att);

                $magentoProductModel->setStatus(1);
                $magentoProductModel->setTaxClassId('None');
                $magentoProductModel->setCategoryIds($catId);
                //==============================================================================
                //$encodedData = str_replace(' ','+',$_POST["data"]);
                //$decodedData = ""; 

                //for($i=0, $len=strlen($encodedData); $i<$len; $i+=4){
                //	$decodedData = $decodedData . base64_decode( substr($encodedData, $i, 4) );
                //}
                //$im = imagecreatefromstring($decodedData);
                $data = base64_decode($_POST["data"]);
                $im = imagecreatefromstring($data);
                $fileName = strtoupper( $user_id . "-" . $this->getRandomString(8) );

                if (isset($im) && $im != false) {
                    $image_path = $fileName . '_img.jpg';
                    $path = Mage::getBaseDir('media') . DS .  'event'. DS;
                    $fullFilePath = $path . $image_path;

                    if(file_exists($fullFilePath)){
                        //unlink($fullFilePath);      
                    }

			$result = imagepng($im, $fullFilePath);
			imagedestroy($im);
			/*$bucketName = 'chattrspace';
					$objectname = 'events/135x110/'.$image_path;
					$filename = Mage::getModel('uploadjob/amazonS3')
									->putImage( $bucketName, $fullFilePath, $objectname, 'public');
					
					$bucketName = 'chattrspace';
					$objectname = 'events/711x447/'.$image_path;
					$filename = Mage::getModel('uploadjob/amazonS3')
									->putImage( $bucketName, $fullFilePath, $objectname, 'public');
					//sleep(15);
					unlink($fullFilePath);*/
			}else {
				return 'Error in Image Uploading';
            }		
			//==============================================================================
			$magentoProductModel->setEventImage($image_path);
			//uploadEventImage($event_id,$data=null)
			$this->_addImages($magentoProductModel, $image_path, $user_id);
			$saved = $magentoProductModel->save();
			/* Event Mail Send */
			$lastId = $saved->getId();
			//send mail replace by cron job mail
			//$this->createCronJobsendMail($lastId, $user_id, $cat_id);
			//Magento Stock
			$this->_saveStock($lastId, $no_att);
			//================================================
			$resource = Mage::getSingleton('core/resource');
			$write= $resource->getConnection('core_write');
			$newsfeed = $resource->getTableName('newsfeed');
			$write->query("insert into $newsfeed (user_id, profile_id, cat_id) values(".$user_id.", ".$lastId.",5)");
			//===================================================
			try{
			$notificationType="Online";
			$type = "event";
			$customer1 = Mage::getModel('customer/customer')->load($user_id);
			$message=$customer1->getFirstname()." ".$customer1->getLastname()." has created a new event";
			$shortMsg = $customer1->getFirstname()." created event";
			$pushNoti = $this->mobile_push_notification_follower_call($user_id, $notificationType, $message,$user_id,$lastId,0,$type,$shortMsg);
			}catch(Exception $e){
			
			}
			return $lastId;
		}	 
		}else {
				return 'Use Form POST';
            }
	}
	public function createPrivateEventAndroid($user_id=0, $title=null,$description=null,$location=null,$hashtag=null,$from_date=null,$to_date=null,$data=null,$event_id=0,$invitee="",$permission=1){
	if (isset($_POST["data"]) && ($_POST["data"] !="") && isset($_POST["user_id"]) && ($_POST["user_id"]>0)){
		$user_id = $_POST["user_id"];
		$title = strip_tags($_POST["title"]);
		$description = strip_tags($_POST["description"]);
		$location = strip_tags($_POST["location"]);
		$hashtag = strip_tags($_POST["hashtag"]);
		$from_date = $_POST["from_date"];
		$to_date = $_POST["to_date"];
		$invitee = $_POST["invitee"];
		$permission = $_POST["permission"];
		$cat_id = 18;
		$event_id = $_POST["event_id"];
		if($title == ""){
			return "Error : Title is blank";
		}
		if($description == ""){
			return "Error : Description is blank";
		}
		if($location == ""){
			return "Error : Location is blank";
		}
            if($hashtag=="")
                $hashtag = "oncam";
            $price=0;
            $no_att=self::$init_max_attendees;
            //from 05/28/13 12:26
            //to 05/28/13 20:26
            $sku = ereg_replace('[^A-Za-z0-9.]', '-', date('m-d-y H:i:s'));
            $catId = '3,'.$cat_id;
            if($user_id){
                $customerId = $user_id;
                $sku = 'chattrspace-'. $user_id ."-" . $sku;
                $customer = Mage::getModel('customer/customer')->load($user_id);
                $time_zone = $customer->getTimezone();
                $timeoffset = Mage::getModel('core/date')->calculateOffset($time_zone);
                $from_date1 = date('Y-m-d H:i:s', strtotime($from_date));
                if($to_date == null){
                    $to_date1 = date('Y-m-d H:i:s', strtotime('+60 minutes',strtotime($from_date)));
                }else{
                    $to_date1 = date('Y-m-d H:i:s', strtotime($to_date));
                }
                if($to_date1 < $from_date1){
                    return "Error : End Date is less than Start Date";
                }
                if($from_date != ''){
                    $from_date = date('Y-m-d H:i:s', strtotime($from_date) - $timeoffset);
                }
                if($to_date == null){
                    $to_date = date('Y-m-d H:i:s', strtotime('+60 minutes',strtotime($from_date)));
                }else{
                    $to_date = date('Y-m-d H:i:s', strtotime($to_date) - $timeoffset);
                }
                $from_date_array = explode(" ", $from_date);
                $from_array = explode("-", $from_date_array[0]);
                $to_date_array = explode(" ", $to_date);
                $to_array = explode("-", $to_date_array[0]);

                $array_year = array(2020=>142,2019=>143, 2018=>144, 2017=>145, 2016=>146, 2015=>147
                ,2014=>148, 2013=>149, 2012=>150, 2011=>151);

                $array_day = array(01=>129, 02=>128, 03=>127, 04=>126, 05=>125, 06=>124
                ,07=>123, 08=>122, 09=>121, 10=>120
                ,11=>119, 12=>118, 13=>117, 14=>116
                ,15=>115, 16=>114, 17=>113, 18=>112
                ,19=>111, 20=>110, 21=>109, 22=>108
                ,23=>107, 24=>106, 25=>105, 26=>104
                ,27=>103, 28=>102, 29=>101, 30=>100
                ,31=>99);

                $array_month = array (01=>141, 02=>140, 03=>139, 04=>138, 05=>137, 06=>136
                , 07=>135, 08=>134, 09=>133, 10=>132, 11=>131
                , 12=>130);
                $is_weekend=153;
                $d = date('D', mktime(0,0,0,$from_array[1], $from_array[2], $from_array[0]));
                if($d=="Sat" || $d=="Sun")
                    $is_weekend = 152;
                Mage::app()->getLocale()->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_LONG);
                $storeId = Mage::app()->getStore()->getId();
                $filename = '';
                if($event_id > 0){
                    $magentoProductModel= Mage::getModel('catalog/product')->load($event_id);
                    $magentoProductModel->setStoreId($storeId);
                }else{
                    $magentoProductModel= Mage::getModel('catalog/product');
                    $magentoProductModel->setStoreId(0);
                }
                $magentoProductModel->setWebsiteIds(array(1));
                $magentoProductModel->setAttributeSetId(9);
                $magentoProductModel->setTypeId('simple');
                $magentoProductModel->setName($title);
                $magentoProductModel->setProductName($title);
                $magentoProductModel->setSku($sku);
                $magentoProductModel->setUserId($user_id);
                $magentoProductModel->setShortDescription($description);
                $magentoProductModel->setDescription($description);
                $magentoProductModel->setPrice($price);
                $magentoProductModel->setSpecialPrice($vol_price);
                $magentoProductModel->setSalesQty(100);
                $magentoProductModel->setWeight(0);
                $magentoProductModel->setIsExpired(155);
                $magentoProductModel->setLocation($location);
                $magentoProductModel->setHashtag($hashtag);
                $magentoProductModel->setInvitee($invitee);
                $magentoProductModel->setPermission($permission);
                $magentoProductModel->setVisibility(4);

                $magentoProductModel->setNewsFromDate($from_date);
                $magentoProductModel->setNewsToDate($to_date);

                $magentoProductModel->setToDay($to_array[2]);
                $magentoProductModel->setToMonth($to_array[1]);
                $magentoProductModel->setToYear($to_array[0]);

                $magentoProductModel->setFromDay($array_day[intval($from_array[2])]);
                $magentoProductModel->setFromMonth($array_month[intval($from_array[1])]);
                $magentoProductModel->setFromYear($array_year[intval($from_array[0])]);

                $magentoProductModel->setFromTime($from_date_array[1]);
                $magentoProductModel->setToTime($to_date_array[1]);

                $magentoProductModel->setIsWeekend($is_weekend);

                $magentoProductModel->setMaximumOfAttendees($no_att);

                $magentoProductModel->setStatus(1);
                $magentoProductModel->setTaxClassId('None');
                $magentoProductModel->setCategoryIds($catId);
                //==============================================================================
                $data = base64_decode($_POST["data"]);
                $im = imagecreatefromstring($data);
                $fileName = strtoupper( $user_id . "-" . $this->getRandomString(8) );

                if (isset($im) && $im != false) {
                    $image_path = $fileName . '_img.jpg';
                    $path = Mage::getBaseDir('media') . DS .  'event'. DS;
                    $fullFilePath = $path . $image_path;

                    if(file_exists($fullFilePath)){
                        //unlink($fullFilePath);      
                    }

			$result = imagepng($im, $fullFilePath);
			imagedestroy($im);
			/*
			$bucketName = 'chattrspace';
					$objectname = 'events/135x110/'.$image_path;
					$filename = Mage::getModel('uploadjob/amazonS3')
									->putImage( $bucketName, $fullFilePath, $objectname, 'public');
					
					$bucketName = 'chattrspace';
					$objectname = 'events/711x447/'.$image_path;
					$filename = Mage::getModel('uploadjob/amazonS3')
									->putImage( $bucketName, $fullFilePath, $objectname, 'public');
					//sleep(15);
					unlink($fullFilePath);*/
			}else {
				return 'Error in Image Uploading';
            }		
			//==============================================================================
			$magentoProductModel->setEventImage($image_path);
			//uploadEventImage($event_id,$data=null)
			$this->_addImages($magentoProductModel, $image_path, $user_id);
			$saved = $magentoProductModel->save();
			/* Event Mail Send */
			$lastId = $saved->getId();
			//send mail replace by cron job mail
			//$this->createCronJobsendMail($lastId, $user_id, $cat_id);
			//Magento Stock
			$this->_saveStock($lastId, $no_att);
			//================================================
			$resource = Mage::getSingleton('core/resource');
			$write= $resource->getConnection('core_write');
			$newsfeed = $resource->getTableName('newsfeed');
			$write->query("insert into $newsfeed (user_id, profile_id, cat_id) values(".$user_id.", ".$lastId.",5)");
			//===================================================
			try{
			$notificationType="Online";
			$username = $this->getUserNameByUserId($user_id);
			$message=$username." has created a new event";
			$pushNoti = $this->mobile_push_notification_follower_call($user_id, $notificationType, $message,$user_id,$lastId);
			}catch(Exception $e){
			
			}
			return $lastId;
		}	 
		}else {
				return 'Use Form POST';
            }
	}
	public function setLike($user_id, $item_id, $item){ //COMMENT 1-Event, 2-Video
		try{
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$write= $resource->getConnection('core_write');
			$select = "select * from cs_like where item_id=$item_id and category=$item and user_id=$user_id";
			$result = $read->fetchRow($select);
			if($result == 0){
				$selectLike = "insert into cs_like(user_id,item_id,category) values ($user_id,$item_id,$item)";
				$write->query($selectLike);
				
				$select1 = "select like_count from cs_newsfeed where profile_id=$item_id";
				$result1 = $read->fetchRow($select1);
				$count = $result1['like_count'] + 1;
				
				$selectL = "update cs_newsfeed set like_count=$count where profile_id=$item_id";
				$write->query($selectL);
				return "Successfully Liked";
			}
		}catch(Exception $e) {
			return $e->getMessage();
		}		
	}
	public function setUnlike($user_id, $item_id, $item){ //COMMENT 1-Event, 2-Video
		try{
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$write= $resource->getConnection('core_write');
			$select = "select * from cs_like where item_id=$item_id and category=$item and user_id=$user_id";
			$result = $read->fetchRow($select);
			if($result == 1){
				$selectLike = "update cs_like set status=0 where item_id=$item_id and category=$item and user_id=$user_id";
				$write->query($selectLike);
				
				$select1 = "select like_count from cs_newsfeed where profile_id=$item_id";
				$result1 = $read->fetchRow($select1);
				$count = $result1['like_count'] - 1;
				
				$selectL = "update cs_newsfeed set like_count=$count where profile_id=$item_id";
				$write->query($selectL);
				return "Successfully Unliked";
			}
		}catch(Exception $e) {
			return $e->getMessage();
		}		
	}
	public function setComment($user_id, $item_id, $item, $comment){ //COMMENT 1-Event, 2-Video
		try{
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$write= $resource->getConnection('core_write');
			$select = "select * from cs_comment where item_id=$item_id and category=$item and user_id=$user_id";
			$result = $read->fetchRow($select);
			if($result == 0){
				$selectComment = "insert into cs_comment(user_id,item_id,category,comment) values ($user_id,$item_id,$item,'$comment')";
				$write->query($selectComment);
				
				$select1 = "select comment_count from cs_newsfeed where profile_id=$item_id";
				$result1 = $read->fetchRow($select1);
				$count = $result1['comment_count'] + 1;
				
				$selectC = "update cs_newsfeed set comment_count=$count where profile_id=$item_id";
				$write->query($selectC);
				return "Successfully Commented";
			}
		}catch(Exception $e) {
			return $e->getMessage();
		}		
	}
	public function deleteComment($user_id, $item_id, $item){ //COMMENT 1-Event, 2-Video
		try{
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$write= $resource->getConnection('core_write');
			$select = "select * from cs_comment where item_id=$item_id and category=$item and user_id=$user_id";
			$result = $read->fetchRow($select);
			if($result == 1){
				$selectComment = "delete from cs_comment where item_id=$item_id and category=$item and user_id=$user_id";
				$write->query($selectComment);
				
				$select1 = "select comment_count from cs_newsfeed where profile_id=$item_id";
				$result1 = $read->fetchRow($select1);
				$count = $result1['comment_count'] - 1;
				
				$selectC = "update cs_newsfeed set comment_count=$count where profile_id=$item_id";
				$write->query($selectC);
				return "Successfully Commented";
			}
		}catch(Exception $e) {
			return $e->getMessage();
		}		
	}
	public function getLike($user_id, $item_id, $item,$page=1){ //COMMENT 1-Event, 2-Video
		try{
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$write= $resource->getConnection('core_write');
			$select = "select *,(select count(id) from cs_like where item_id=$item_id and category=$item and status=1) as count from cs_like where item_id=$item_id and category=$item and status=1";
			$limit = 10;
			if($page<=0)
				$page=1;
			$page=$page-1;
			$select.= " limit ".$limit*$page .", " .$limit;
			$results = $read->fetchAll($select);
			foreach($results as $k=>$rs){
				$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
				$data[$k] = array(
						'id'=> $rs['id'],
						'user_id'=>$rs['user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['user_id']),
						'liked_on'=>$rs['liked_on'],
						);
				$count = $rs['count'];
			}
			if($count > ($limit*($page+1))){
				$data['showMore'] = "true";
			} else {
				$data['showMore'] = "false";
			}
		}catch(Exception $e) {
			return $e->getMessage();
		}
		return $data;
	}
	public function getComment($user_id, $item_id, $item,$page=1){ //COMMENT 1-Event, 2-Video
		try{
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$write= $resource->getConnection('core_write');
			$select = "select id,user_id,item_id,category,comment,status,isDeleted,commented_on,(select count(id) from cs_comment where item_id=$item_id and category=$item and isDeleted=0) as count from cs_comment where item_id=$item_id and category=$item and isDeleted=0";
			$limit = 10;
			if($page<=0)
				$page=1;
			$page=$page-1;
			$select.= " limit ".$limit*$page .", " .$limit;
			$results = $read->fetchAll($select);
			foreach($results as $k=>$rs){
				$customer = Mage::getModel('customer/customer')->load($rs['user_id']);
				$data[$k] = array(
						'id'=> $rs['id'],
						'user_id'=>$rs['user_id'],
						'username'=>$customer->getUsername(),
						'firstname'=>$customer->getFirstname(),
						'lastname'=>$customer->getLastname(),
						'image'=>$this->getProfilePic($rs['user_id']),
						'comment'=>$rs['comment'],
						'commented_on'=>$rs['commented_on'],
						);
				$count = $rs['count'];
			}
			if($count > ($limit*($page+1))){
				$data['showMore'] = "true";
			} else {
				$data['showMore'] = "false";
			}
		}catch(Exception $e) {
			return $e->getMessage();
		}
		return $data;
	}
	public function setReportInappropriateProfile($user_id, $profile_id){
		$resource = Mage::getSingleton('core/resource');
		$read= $resource->getConnection('core_read');
		$write = $resource->getConnection('core_write');
		try{
				$sqlInsert = "insert into cs_report_inappropriate(newsfeed_id, reporter, reported_to, item, cat_id,type) values(0, ".$user_id.", ".$profile_id.", 0, 0,'profile')";
				$write->query($sqlInsert);
				return "Successfully reported, admin will review the report";
			
		}catch(Exception $exception) {
			return $exception->getMessage();
		}
	}

	
	public function setGroupName($group_id, $host_id, $name) {
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $widget = $resource->getTableName('widget_info');
        $write->query("UPDATE $widget SET widget_title = '$name' WHERE id = $group_id AND user_id = $host_id");
        return "Group name updated";
    }
    public function setGroupPhoto($group_id=0, $host_id=0, $data=null) {
        array_map(function($key) {
            if ( ! (isset($_POST[$key]) && ($_POST['key'] != ""))) {
                return 'Use Form POST';
            }
        }, array('group_id', 'host_id', 'data'));

        $group_id = $_POST['group_id'];
        $host_id = $_POST['host_id'];
        $data = $_POST['data'];

        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $read = $resource->getConnection('core_read');
        $widget = $resource->getTableName('widget_info');

        $rows = $read->fetchOne("SELECT COUNT(*) as 'cnt' FROM $widget WHERE id = $group_id AND user_id = $host_id");
        if ($rows['cnt'] != 1) {
            return 'Incorrect group_id or/and host_id';
        }

        $customer = Mage::getModel('customer/customer')->load($user_id);
        $data = base64_decode($_POST["data"]);
        $im = imagecreatefromstring($data);
        if ( ! (isset($im) && ($im == false))) {
            return ' Error: Data is not well formated.';
        }
        $fileName = strtoupper( $user_id . "-" . $this->getRandomString(8) );
        $img = $fileName.'.jpg';
        $fileName = "bgimage".$img;
        $path = Mage::getBaseDir('media') . DS .  'groupimg_tmp'. DS;
        $fullFilePath = $path.$fileName;

        imagepng($im, $fullFilePath);
        imagedestroy($im);
        $bucketName = 'chattrspace';
        $objectname = 'group_bgimages/'.$fileName;
        $filename = Mage::getModel('uploadjob/amazonS3')
            ->putImage( $bucketName, $fullFilePath, $objectname, 'public');
        $write->query("UPDATE $widget SET parameters = '$filename' WHERE id = $group_id AND user_id = $host_id");
        return "Group photo updated";
    }

	/*public function uploadAudioMessage($user_id, $message_id, $upload_name="audio_message"){
		if($user_id>0 && $message_id >0){
			$resource = Mage::getSingleton('core/resource');
			$write = $resource->getConnection('core_write');
			$table = $resource->getTableName('uploadaudio');	

			$upload_data = $this->uploadfile($upload_name);

			if($upload_data == false){
				return "upload error";
			}

			$sqlInsert = " Insert into $table (user_id, message_id, audio_url, audio_file_name, audio_type ,audio_size) values (".$user_id.",".$message_id.",'".$upload_data['url']."', '".$upload_data['name']."', '".$upload_data['type']."', ".$upload_data['size'].");";
			$result = $write->query($sqlInsert);
			
			if($result){
				return 'success';
			}else{
				return 'upload failed';
			}
			
		}else{
			return "upload error";
		}				
	}*/

	public function uploadAudioMessage($user_id, $message_id, $data=null, $datatype ="wav"){

		if (isset($_POST["data"]) && ($_POST["data"] !="") && isset($_POST["user_id"]) && $_POST["user_id"]!="" && $_POST["user_id"]>0){
			$user_id = $_POST["user_id"];
			$customer = Mage::getModel('customer/customer')->load($user_id);
			$data = base64_decode($_POST["data"]);
			
		    $fileName = strtoupper( $user_id . "-" . $this->getRandomString(8) );	
		    $audio_file_name = $fileName.$datatype;

		    $path = Mage::getBaseDir('media') . DS .  'audio'. DS;
			$fullFilePath = $path.$audio_file_name;

			if(file_put_contents( $fullFilePath, $data)){

				$resource = Mage::getSingleton('core/resource');
				$write = $resource->getConnection('core_write');
				$table = $resource->getTableName('uploadaudio');	

				$sqlInsert = " Insert into $table (user_id, message_id, audio_url, audio_type) values (".$user_id.",".$message_id.",'".$audio_file_name."', '".$datatype."');";
				$result = $write->query($sqlInsert);
				
				if($result){
					return 'success';
				}else{
					return 'upload failed';
				}

			}else{
				return 'upload failed=';
			}
			
		}else{
			return "upload error";
		}
      }				

	public function uploadfile($upload_name){		        
		if (!empty($_FILES[$upload_name])) {
		    $audio_message = $_FILES[$upload_name];

		    if ($audio_message["error"] !== UPLOAD_ERR_OK) {
		        return false;		        
		    }

		    // ensure a safe filename
		    $name = preg_replace("/[^A-Z0-9._-]/i", "_", $audio_message["name"]);

		    // don't overwrite an existing file
		    $i = 0;
		    $parts = pathinfo($name);

		    $path = realpath('.')."/media/audio/";
		    while (file_exists($path . $name)) {
		        $i++;
		        $name = $parts["filename"] . "-" . $i . "." . $parts["extension"];
		    }

		    // preserve file from temporary directory
		    $success = move_uploaded_file($audio_message["tmp_name"],
		        $path . $name);
		    if (!$success) { 
		        return false;		        
		    }

		    // set proper permissions on the new file
		    chmod($path . $name, 0644);

		    $data = array(
		    		'url' => $path . $name,
		    		'name' =>$audio_message['name'],
		    		'type' => $audio_message['type'],
		    		'size' => $audio_message['size'],
		    	);
		    return $data;

		}

	}

	public function downloadAudioMessage($user_id, $message_id){
		if($user_id>0 && $message_id >0 ){
			$resource = Mage::getSingleton('core/resource');
			$read = $resource->getConnection('core_read');
			$table = $resource->getTableName('uploadaudio');	

			$sqlSelect = " Select * from $table where user_id='" . $user_id."' and message_id='".$message_id."'";
			return $result = $read->fetchRow($sqlSelect);
			
			if($result){
				$audio_url = Mage::getBaseUrl(�media�).'/audio/'.$result['audio_url'];
				return ;
			}else{
				return 'download error';
			}
			
		}else{
			return "dowload error";
		}		

	}
}
?>