<?php

namespace MiniOrange\TwoFA\Helper;

use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Url;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\App\ResourceConnection;
use Magento\User\Model\UserFactory;
use MiniOrange\TwoFA\Helper\Curl;
use MiniOrange\TwoFA\Helper\TwoFAConstants;
use MiniOrange\TwoFA\Helper\Data;
use Magento\User\Model\ResourceModel\User\CollectionFactory as UserCollectionFactory;


/**
 * This class contains some common Utility functions
 * which can be called from anywhere in the module. This is
 * mostly used in the action classes to get any utility
 * function or data from the database.
 */
class TwoFAUtility extends Data
{
    protected $adminSession;
    protected $customerSession;
    protected $authSession;
    protected $cacheTypeList;
    protected $resource;
    protected $cacheFrontendPool;
    protected $fileSystem;
    protected $logger;
    protected $reinitableConfig;
    protected $coreSession;
    private $userCollectionFactory;
    protected $productMetadata;


    public function __construct(
        ScopeConfigInterface $scopeConfig,
        UserFactory $adminFactory,
        CustomerFactory $customerFactory,
        UrlInterface $urlInterface,
        WriterInterface $configWriter,
        \Magento\Framework\App\ResourceConnection $resource,
        Repository $assetRepo,
        \Magento\Backend\Helper\Data $helperBackend,
        Url $frontendUrl,
        \Magento\Backend\Model\Session $adminSession,
        Session $customerSession,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\Framework\App\Config\ReinitableConfigInterface $reinitableConfig,
        \Magento\Framework\Session\SessionManagerInterface $coreSession,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool,
        UserCollectionFactory $userCollectionFactory,
        \Psr\Log\LoggerInterface $logger,
        File $fileSystem,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata

    ) {
                        $this->adminSession = $adminSession;
                        $this->customerSession = $customerSession;
                        $this->authSession = $authSession;
                        $this->cacheTypeList = $cacheTypeList;
                        $this->resource = $resource;
                        $this->cacheFrontendPool = $cacheFrontendPool;
                        $this->fileSystem = $fileSystem;
                        $this->logger = $logger;
                        $this->reinitableConfig = $reinitableConfig;
                        $this->coreSession = $coreSession;
                        $this->userCollectionFactory = $userCollectionFactory;
                        $this->productMetadata = $productMetadata;

                       parent::__construct(
                           $scopeConfig,
                           $adminFactory,
                           $customerFactory,
                           $urlInterface,
                           $configWriter,
                           $assetRepo,
                           $helperBackend,
                           $frontendUrl
                       );
    }

    /**
     * This function returns phone number as a obfuscated
     * string which can be used to show as a message to the user.
     *
     * @param $phone references the phone number.
     * @return string
     */
    public function getHiddenPhone($phone)
    {
        $hidden_phone = 'xxxxxxx' . substr($phone, strlen($phone) - 3);
        return $hidden_phone;
    }

    /**
     * This function checks if a value is set or
     * empty. Returns true if value is empty
     *
     * @return True or False
     * @param $value //references the variable passed.
     */
    public function isBlank($value)
    {
        if (! isset($value) || empty($value)) {
            return true;
        }
        return false;
    }

    public function getCompleteSession() {
        $this->coreSession->start();
        $sessionValue = $this->coreSession->getMyTestValue();
        return $sessionValue !== null ? $sessionValue : array();
    }

    public function getSessionValue( $key ){
        $sessionValueArray = $this->getCompleteSession();
        return isset( $sessionValueArray[ $key ] ) ? $sessionValueArray[ $key ] : null ;
    }

    public function setSessionValue( $key, $value ){
        $sessionValueArray = $this->getCompleteSession();
        $sessionValueArray[ $key ] = $value;
        $this->coreSession->setMyTestValue( $sessionValueArray );
    }



   /** check if customer registered in magento or not
   *
   */
  public function isCustomerRegistered(){

    $details = $this->getCustomerDetails();
    return ! isset( $details['email'] ) && ( $details['email'] === NULL || empty($details['email']) ) ? false : true;
 }

  /** get registered customer details from DB
    *
    */
    public function get_admin_role_name()
    {   $collection = $this->userCollectionFactory->create();
       $userid= $this->getSessionValue('admin_user_id');
       if($userid==NULL && $this->authSession->isLoggedIn()) {
        $adminUser = $this->authSession->getUser();
        $userid = $adminUser->getId();
       }
        $collection->addFieldToFilter('main_table.user_id',  $userid);
        $userData = $collection->getFirstItem();
        $user_all_information= $userData->getData();
       $admin_user_role= $user_all_information['role_name'];
        return   $admin_user_role;
    }

   public function getCustomerDetails(){

   $email = $this->getStoreConfig(TwoFAConstants::CUSTOMER_EMAIL);
   $customer_key= $this->getStoreConfig(TwoFAConstants::CUSTOMER_KEY);
   $api_key = $this->getStoreConfig(TwoFAConstants::API_KEY);
   $customer_token = $this->getStoreConfig(TwoFAConstants::TOKEN);

   $details = array (
   'email'=> $email,
   'customer_Key'=> $customer_key,
   'api_Key'=> $api_key,
   'token'=> $customer_token
    );

    return $details;
   }



    /**
     * This function checks if cURL has been installed
     * or enabled on the site.
     *
     * @return True or False
     */
    public function isCurlInstalled()
    {
        if (in_array('curl', get_loaded_extensions())) {
            return 1;
        } else {
            return 0;
        }
    }


    /**
     * This function checks if the phone number is in the correct format or not.
     *
     * @param $phone refers to the phone number entered
     * @return bool
     */
    public function validatePhoneNumber($phone)
    {
        if (!preg_match(MoIDPConstants::PATTERN_PHONE, $phone, $matches)) {
            return false;
        } else {
            return true;
        }
    }


    /**
     * This function is used to obfuscate and return
     * the email in question.
     *
     * @param $email //refers to the email id to be obfuscated
     * @return string obfuscated email id.
     */
    public function getHiddenEmail($email)
    {
        if (!isset($email) || trim($email)==='') {
            return "";
        }

        $emailsize = strlen($email);
        $partialemail = substr($email, 0, 1);
        $temp = strrpos($email, "@");
        $endemail = substr($email, $temp-1, $emailsize);
        for ($i=1; $i<$temp; $i++) {
            $partialemail = $partialemail . 'x';
        }

        $hiddenemail = $partialemail . $endemail;

        return $hiddenemail;
    }
/***
 * @return \Magento\Backend\Model\Session
 */
    public function getAdminSession()
    {
        return $this->adminSession;
    }

    /**
     * set Admin Session Data
     *
     * @param $key
     * @param $value
     * @return
     */
    public function setAdminSessionData($key, $value)
    {
        return $this->adminSession->setData($key, $value);
    }


    public function getImageUrl($image)
    {
        return $this->assetRepo->getUrl(TwoFAConstants::MODULE_DIR.TwoFAConstants::MODULE_IMAGES.$image);
    }

   /** 2fa methods for admin
   */
   public function tfaMethodArray(){
    return array(
        'OOS'=>array(
            "name"=>"OTP Over SMS",
            "description" => "Enter the One Time Passcode sent to your phone to login."
        ),
        'OOE'=>array(
            "name"=>"OTP Over Email",
            "description" => "Enter the One Time Passcode sent to your email to login."
        ),
        'OOSE'=>array(
            "name"=>"OTP Over SMS and Email",
            "description" => "Enter the One Time Passcode sent to your phone and email to login."
        ),
       'GoogleAuthenticator'=>array(
             "name"=>"Google Authenticator",
              "description" => "Enter the soft token from the account in your Google Authenticator App to login."
         ),
         'MicrosoftAuthenticator'=>array(
            "name"=>"Microsoft Authenticator",
             "description" => "You have to scan the QR code from Microsoft Authenticator App and enter code generated by app to login. Supported in Smartphones only."
        ),
        'OktaVerify'=>array(
            "name"=>"Okta Verify",
             "description" => "You have to scan the QR code from Okta Verify App and enter code generated by app to login. Supported in Smartphones only."
        ),
        'DuoAuthenticator'=>array(
            "name"=>"Duo Authenticator",
             "description" => "You have to scan the QR code from Duo Authenticator App and enter code generated by app to login. Supported in Smartphones only."
        ),
        'AuthyAuthenticator'=>array(
            "name"=>"Authy Authenticator",
             "description" => "You have to scan the QR code from Authy Authenticator App and enter code generated by app to login. Supported in Smartphones only."
        ),
        'LastPassAuthenticator'=>array(
            "name"=>"LastPass Authenticator",
             "description" => "You have to scan the QR code from LastPass Authenticator App and enter code generated by app to login. Supported in Smartphones only."
        ),
        'QRCodeAuthenticator'=>array(
            "name"=>"QR Code Authentication",
             "description" => "You have to scan the QR Code from your phone using miniOrange Authenticator App to login. Supported in Smartphones only."
        ),
         'KBA'=>array(
            "name"=>"Security Questions (KBA)",
             "description" => "You have to answers some knowledge based security questions which are only known to you to authenticate yourself."
        ),
        'OOP'=>array(
            "name"=>"OTP Over Phone",
             "description" => "You will receive a one time passcode via phone call. You have to enter the otp on your screen to login. Supported in Smartphones, Feature Phones."
        ),
        'YubikeyHardwareToken'=>array(
            "name"=>"Yubikey Hardware Token",
             "description" => "You can press the button on your yubikey Hardware token which generate a random key. You can use that key to authenticate yourself."
        ),
        'PushNotificationsr'=>array(
            "name"=>"Push Notifications",
             "description" => "You will receive a push notification on your phone. You have to ACCEPT or DENY it to login. Supported in Smartphones only."
        ),
        'SoftToken'=>array(
            "name"=>"Soft Token",
             "description" => "You have to enter passcode generated by miniOrange Authenticator App to login. Supported in Smartphones only."
        ),
        'EmailVerification'=>array(
            "name"=>"Email Verification",
             "description" => "You will receive an email with link. You have to click the ACCEPT or DENY link to verify your email. Supported in Desktops, Laptops, Smartphones."
        ),
    );
    }

//get info if first user
	public static function isFirstUser($id){
		$details = self::getCustomerDetails();

		return $details['jid']==$id;
	}

    public function AuthenticatorUrl(){
        $this->log_debug("Inside authenticator url");
        if($this->getSessionValue(TwoFAConstants:: ADMIN_IS_INLINE)){
            $username = $this->getSessionValue(TwoFAConstants:: ADMIN_USERNAME);
        }else{
            $username = $this->getCurrentAdminUser()->getUsername();
        }



       //if admin is not created then create new user
       $row = $this->getMoTfaUserDetails('miniorange_tfa_users',$username);

       $secret = $this->getAuthenticatorSecret( $username );
       $secret_already_set=$this->getSessionValue(TwoFAConstants::PRE_SECRET);
       if($this->getSessionValue(TwoFAConstants:: ADMIN_IS_INLINE)){
        $secret_already_set = $this->getSessionValue(TwoFAConstants:: ADMIN_SECRET);
    }
       if( (!is_array( $row ) || sizeof( $row ) <= 0)) {
        if($secret_already_set==NULL){
            $secret =$this->generateRandomString();
            $this->setSessionValue(TwoFAConstants::PRE_SECRET,$secret);

        }else{
            $secret=$secret_already_set;
        }
       }
        $issuer = $this->AuthenticatorIssuer();
        $url = "otpauth://totp/";
	    $url .= $username."?secret=".$secret."&issuer=".$issuer;
	    return $url;
    }

    public function AuthenticatorCustomerUrl(){
        $this->log_debug("inside authenticator customer url");
        $email = $_COOKIE['mousername'];
        if( is_null( $email ) ) {
            return false;
        } else {

            $secret_already_set =  $this->getSessionValue('customer_inline_secret');
                   if($secret_already_set==NULL){
                       $secret =$this->generateRandomString();
                        $this->setSessionValue('customer_inline_secret',$secret);
                   }else{
                    $secret=$secret_already_set;
                   }

            $issuer = $this->AuthenticatorIssuer();
            $url = "otpauth://totp/";
            $url .= $email."?secret=".$secret."&issuer=".$issuer;
            return $url;
        }
    }

    public function AuthenticatorIssuer(){
        return TwoFAConstants::TwoFA_AUTHENTICATOR_ISSUER;
    }

    public function getAuthenticatorSecret( $current_username ){
        $this->log_debug("Inside getAuthenticatorSecret. generating secret");
        $row = $this->getMoTfaUserDetails('miniorange_tfa_users',$current_username);

        if( is_array( $row ) && sizeof( $row ) > 0 ) {

            return isset( $row[0]['secret'] ) ? $row[0]['secret'] : false;
        } else {

            return false;
        }
    }

    function generateRandomString($length = 16) {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

public function getCustomerKeys($isMiniorange=false){
    $keys=array();
    if($isMiniorange){

        $keys['customer_key']= "16555";
        $keys['apiKey']      = "fFd2XcvTGDemZvbw1bcUesNJWEqKbbUq";
    }
    else{
        $details=self::getCustomerDetails();
        $keys['customer_key']= $this->getStoreConfig(TwoFAConstants::CUSTOMER_KEY);
        $keys['apiKey']  = $api_key = $this->getStoreConfig(TwoFAConstants::API_KEY);
    }
    return $keys;
}


  static function getTransactionName(){
		return 'Magento 2 Factor Authentication Plugin';
	 }

    static function getApiUrls(){
        $hostName = TwoFAConstants::HOSTNAME;
        return array(
            'challange'=>$hostName.'/moas/api/auth/challenge',
            'update'=>$hostName.'/moas/api/admin/users/update',
            'validate'=>$hostName.'/moas/api/auth/validate',
            'googleAuthService'=>$hostName.'/moas/api/auth/google-auth-secret',
            'googlevalidate'=>$hostName.'/moas/api/auth/validate-google-auth-secret',
            'createUser'=>$hostName.'/moas/api/admin/users/create',
            'kbaRegister'=>$hostName.'/moas/api/auth/register',
            'getUserInfo'=>$hostName.'/moas/api/admin/users/get',
             'feedback'   => $hostName.'/moas/api/notify/send'
        );
    }


    /**
     * get Admin Session data based of on the key
     *
     * @param $key
     * @param $remove
     * @return mixed
     */
    public function getAdminSessionData($key, $remove = false)
    {
        return $this->adminSession->getData($key, $remove);
    }



    /**
     * set customer Session Data
     *
     * @param $key
     * @param $value
     * @return
     */
    public function setSessionData($key, $value)
    {
        return $this->customerSession->setData($key, $value);
    }


    /**
     * Get customer Session data based off on the key
     *
     * @param $key
     * @param $remove
     */
    public function getSessionData($key, $remove = false)
    {
        return $this->customerSession->getData($key, $remove);
    }


    /**
     * Set Session data for logged in user based on if he/she
     * is in the backend of frontend. Call this function only if
     * you are not sure where the user is logged in at.
     *
     * @param $key
     * @param $value
     */
    public function setSessionValueForCurrentUser($key, $value)
    {
        if ($this->customerSession->isLoggedIn()) {
            $this->setSessionValue($key, $value);
        } elseif ($this->authSession->isLoggedIn()) {
            $this->setAdminSessionData($key, $value);
        }
    }


    /**
     * Check if the admin has configured the plugin with
     * the Identity Provier. Returns true or false
     */
    public function isTwoFAConfigured()
    {
        $loginUrl = $this->getStoreConfig(TwoFAConstants::AUTHORIZE_URL);
        return $this->isBlank($loginUrl) ? false : true;
    }


    /**
     * This function is used to check if customer has completed
     * the registration process. Returns TRUE or FALSE. Checks
     * for the email and customerkey in the database are set
     * or not.
     */
    public function micr()
    {
              $email = $this->getStoreConfig(TwoFAConstants::CUSTOMER_EMAIL);
        $key = $this->getStoreConfig(TwoFAConstants::CUSTOMER_KEY);
        return !$this->isBlank($email) && !$this->isBlank($key) ? true : false;
    }


    /**
     * Check if there's an active session of the user
     * for the frontend or the backend. Returns TRUE
     * or FALSE
     */
    public function isUserLoggedIn()
    {
        return $this->customerSession->isLoggedIn()
                || $this->authSession->isLoggedIn();
    }

    /**
     * Get the Current Admin User who is logged in
     */
    public function getCurrentAdminUser()
    {
        return $this->authSession->getUser();
    }


    /**
     * Get the Current Admin User who is logged in
     */
    public function getCurrentUser()
    {
        return $this->customerSession->getCustomer();
    }


    /**
     * Get the admin login url
     */
    public function getAdminLoginUrl()
    {
        return $this->getAdminUrl('adminhtml/auth/login');
    }

    /**
     * Get the admin page url
     */
    public function getAdminPageUrl()
    {
            return $this->getAdminBaseUrl();
    }

    /**
     * Get the customer login url
     */
    public function getCustomerLoginUrl()
    {
        return $this->getUrl('customer/account/login');
    }

    /**
     * Get is Test Configuration clicked
     */
    public function getIsTestConfigurationClicked()
    {
        return $this->getStoreConfig(TwoFAConstants::IS_TEST);
    }


    /**
     * Flush Magento Cache. This has been added to make
     * sure the admin/user has a smooth experience and
     * doesn't have to flush his cache over and over again
     * to see his changes.
     */
    public function flushCache($from = "")
    {

        $types = ['db_ddl']; // we just need to clear the database cache

        foreach ($types as $type) {
            $this->cacheTypeList->cleanType($type);
        }

        foreach ($this->cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }
    }


    /**
     * Get data in the file specified by the path
     */
    public function getFileContents($file)
    {
        return $this->fileSystem->fileGetContents($file);
    }


    /**
     * Put data in the file specified by the path
     */
    public function putFileContents($file, $data)
    {
        $this->fileSystem->filePutContents($file, $data);
    }


    /**
     * Get the Current User's logout url
     */
    public function getLogoutUrl()
    {
        if ($this->customerSession->isLoggedIn()) {
            return $this->getUrl('customer/account/logout');
        }
        if ($this->authSession->isLoggedIn()) {
            return $this->getAdminUrl('adminhtml/auth/logout');
        }
        return '/';
    }


    /**
     * Get/Create Callback URL of the site
     */
    public function getCallBackUrl()
    {
        return $this->getBaseUrl() . TwoFAConstants::CALLBACK_URL;
    }

    public function removeSignInSettFings()
    {
            $this->setStoreConfig(TwoFAConstants::SHOW_CUSTOMER_LINK, 0);
            $this->setStoreConfig(TwoFAConstants::SHOW_ADMIN_LINK, 0);
    }
    public function reinitConfig(){

            $this->reinitableConfig->reinit();
    }

        /**
     * This function is used to check if customer has completed
     * the registration process. Returns TRUE or FALSE. Checks
     * for the email and customerkey in the database are set
     * or not. Then checks if license key has been verified.
     */
	public function mclv()
	{
        return true;
		// $token = $this->getStoreConfig(TwoFAConstants::TOKEN);
		// $isVerified = AESEncryption::decrypt_data($this->getStoreConfig(TwoFAConstants::SAMLSP_CKL),$token);
		// $licenseKey = $this->getStoreConfig(TwoFAConstants::SAMLSP_LK);
		// return $isVerified == "true" ? TRUE : FALSE;
	}


    /**
     *Common Log Method .. Accessible in all classes through
     **/
    public function log_debug($msg="", $obj=null){

        if(!is_object($msg) && !is_array($msg)) {
            $this->logger->info('MO TwoFA Free Plan:' . $msg);
        }

        if(null !== $obj) {
            if(is_object($obj) || is_array($obj)) {
            }else{
                $this->logger->info($obj);
            }
        }
    }

    /**
   ****DATABASE Querying Methods
     * @param $table
    * @param $data
    */

    //Insert a row in any table
    public function insertRowInTable($table,$data){
    $this->log_debug("insert row ");
       $this->resource->getConnection()->insertMultiple($table, $data);
    }

    //Update a column in any table
    public function updateColumnInTable($table, $colName, $colValue, $idKey, $idValue){
       $this->log_debug("updateColumnInTable");
       $this->resource->getConnection()->update(
       $table,  [ $colName => $colValue],
        [$idKey." = ?" => $idValue]
    );
}

    //fetch user details
    public function getMoTfaUserDetails($table,$username=false){
       // $this->log_debug("getMOTfaUserDetails");
        $query = $this->resource->getConnection()->select()
            ->from($table,['username','active_method','configured_methods','email','phone','transactionId','secret','id','countrycode'])->where(
            "username='".$username."'"
            );
        $fetchData = $this->resource->getConnection()->fetchAll($query);
        return $fetchData;
    }



   //Update a set of values of a row in any table
   public function updateRowInTable($table, $valArray, $idKey, $idValue){
     $this->log_debug("updateRowInTable");
     $this->resource->getConnection()->update(
     $table, $valArray , [$idKey." = ?" => $idValue]
 );
}

public function deleteRowInTable($table, $idKey, $idValue){
    $this->log_debug("deleteRowIntable");
    $conn = $this->resource->getConnection();
   $sql = "DELETE FROM ".$table." WHERE ".$idKey."=".$idValue;

 //enter log here to know about deletion of row
    $conn->exec($sql);
//enter log here
}
    /**

     * Get value of any column from a table.

     * @param $table

     * @param $col

     * @param $idKey

     * @param $idValue

     * @return

     */

    public function getValueFromTableSQL($table, $col, $idKey, $idValue)

    {

        $connection = $this->resource->getConnection();

        //Select Data from table

        $sqlQuery = "SELECT ".$col. " FROM ".$table." WHERE ".$idKey. " = " .$idValue;

        $this->log_debug("SQL: ".$sqlQuery);

        $result = $connection->fetchOne($sqlQuery);

        $this->log_debug("result sql: ".$result);

        return $result;

    }

    public function verifyGauthCode( $code, $current_username, $discrepancy = 3, $currentTimeSlice = null ) {
        $this->log_debug("TwoFAUtlity: verifyGauthCode: execute");

        $secret = $this->getAuthenticatorSecret( $current_username );
        if($secret==false){
            $secret=$this->getSessionValue(TwoFAConstants::PRE_SECRET);
        }
        $customer_inline= $this->getSessionValue(TwoFAConstants::CUSTOMER_INLINE);
               if($customer_inline){
                $secret=$this->getSessionValue('customer_inline_secret');
                $this->setSessionValue(TwoFAConstants::CUSTOMER_SECRET,$secret);
               }
               $admin_inline= $this->getSessionValue(TwoFAConstants::ADMIN_IS_INLINE);
               if($admin_inline){
                $secret=$this->getSessionValue(TwoFAConstants::ADMIN_SECRET);
               }
		$response = array("status"=>'FALSE');
        if ($currentTimeSlice === null) {
            $currentTimeSlice = floor(time() / 30);
        }

        if (strlen($code) != 6) {
            return json_encode($response);
        }
        for ($i = -$discrepancy; $i <= $discrepancy; ++$i) {
            $calculatedCode = $this->getCode($secret, $currentTimeSlice + $i);
            if ($this->timingSafeEquals($calculatedCode, $code)) {
                $response['status']='SUCCESS';
                return json_encode($response);
            }
        }
        return json_encode($response);
    }

    function timingSafeEquals($safeString, $userString)
    {
        if (function_exists('hash_equals')) {
            return hash_equals($safeString, $userString);
        }
        $safeLen = strlen($safeString);
        $userLen = strlen($userString);

        if ($userLen != $safeLen) {
            return false;
        }

        $result = 0;

        for ($i = 0; $i < $userLen; ++$i) {
            $result |= (ord($safeString[$i]) ^ ord($userString[$i]));
        }

        // They are only identical strings if $result is exactly 0...
        return $result === 0;
    }

    function getCode($secret, $timeSlice = null)
    {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }

        $secretkey = $this->_base32Decode($secret);
        // Pack time into binary string
        $time = chr(0).chr(0).chr(0).chr(0).	pack('N*', $timeSlice);
        // Hash it with users secret key
        $hm = hash_hmac('SHA1', $time, $secretkey, true);

        // Use last nipple of result as index/offset
        $offset = ord(substr($hm, -1)) & 0x0F;

        // grab 4 bytes of the result
        $hashpart = substr($hm, $offset, 4);
        // Unpak binary value
        $value = unpack('N', $hashpart);
        $value = $value[1];
        // Only 32 bits
        $value = $value & 0x7FFFFFFF;
        $modulo = pow(10, 6);
        return str_pad($value % $modulo, 6, '0', STR_PAD_LEFT);
    }

    function _base32Decode($secret)
    {
        if (empty($secret)) {
            return '';
        }
        $base32chars = $this->_getBase32LookupTable();
        $base32charsFlipped = array_flip($base32chars);

        $paddingCharCount = substr_count($secret, $base32chars[32]);
        $allowedValues = array(6, 4, 3, 1, 0);
        if (!in_array($paddingCharCount, $allowedValues)) {
            return false;
        }


        for ($i = 0; $i < 4; ++$i) {
            if ($paddingCharCount == $allowedValues[$i] &&
                substr($secret, -($allowedValues[$i])) != str_repeat($base32chars[32], $allowedValues[$i])) {
                return false;
            }
        }
        $secret = str_replace('=', '', $secret);
        $secret = str_split($secret);
        $binaryString = '';
        for ($i = 0; $i < count($secret); $i = $i + 8) {
            $x = '';
            if (!in_array($secret[$i], $base32chars)) {
                return false;
            }
            for ($j = 0; $j < 8; ++$j) {

                 $x .= str_pad(base_convert($base32charsFlipped[$secret[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
            }
            $eightBits = str_split($x, 8);
            for ($z = 0; $z < count($eightBits); ++$z) {
                $binaryString .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) == 48) ? $y : '';

			}
        }

        return $binaryString;
    }

    function _getBase32LookupTable()
    {
        return array(
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', //  7
            'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', // 15
            'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', // 23
            'Y', 'Z', '2', '3', '4', '5', '6', '7', // 31
            '=',  // padding char
        );
    }

    public function getCustomerPhoneFromEmail($email=false){

        $getID_table='customer_entity';
         $query1 = $this->resource->getConnection()->select()
             ->from($getID_table,['entity_id'])->where(
             "email='".$email."'"
             );
         $fetchIDData = $this->resource->getConnection()->fetchAll($query1);
         if(($fetchIDData)==array()){
           return false;
         }
$entity_id=$fetchIDData[0]['entity_id'];

        $getPhone_table='customer_address_entity';
        $query2 = $this->resource->getConnection()->select()
        ->from($getPhone_table,['telephone'])->where(
        "entity_id='".$entity_id."'"
        );
    $fetchPhoneData = $this->resource->getConnection()->fetchAll($query2);
    if(($fetchPhoneData)==array()){
return false;

    }else{
        $phone_no=$fetchPhoneData[0]['telephone'];
        return $phone_no;
    }

     }

     public function getCustomerCountryFromEmail($email=false){

        $getID_table='customer_entity';
         $query1 = $this->resource->getConnection()->select()
             ->from($getID_table,['entity_id'])->where(
             "email='".$email."'"
             );
         $fetchIDData = $this->resource->getConnection()->fetchAll($query1);
         if(($fetchIDData)==array()){
           return false;
         }
$entity_id=$fetchIDData[0]['entity_id'];

        $getcountryID_table='customer_address_entity';
        $query2 = $this->resource->getConnection()->select()
        ->from( $getcountryID_table,['country_id'])->where(
        "entity_id='".$entity_id."'"
        );
    $fetchCountryIDData = $this->resource->getConnection()->fetchAll($query2);
    if(($fetchCountryIDData)==array()){
return false;

    }else{
        $country_id=$fetchCountryIDData[0]['country_id'];
        return $country_id;
    }

     }

    public function submit_message_to_magento_team($subject,$message){
       // CUSTOMER_EMAIL

        $userEmail = $this->getStoreConfig(TwoFAConstants::CUSTOMER_EMAIL);
        if($userEmail==NULL){
            $userEmail='NOAccount';
        }
        $magentoVersion = $this->getProductVersion();  

        Curl::submit_message_to_magento_team($userEmail,$subject,$message,$magentoVersion);
    }

    public function submit_email_for_registration($method,$method_name,$subject){
            $method_set = $this->getStoreConfig($method_name);
            if($method_set==NULL){
                $method=$method=='OOS' ? ' OTP Over SMS': ($method=='OOE' ? ' OTP Over Email': ($method=='OOSE'? 'OTP Over SMS and Email': ($method=='GoogleAuthenticator'? 'Google Authenticator':'')));
                $message='Selected method: '. $method;
                $this->setStoreConfig($method_name,1);
                $this->submit_message_to_magento_team($subject,$message);
            }
    }
    public function getProductVersion(){
        return  $this->productMetadata->getVersion(); 
    }
}
