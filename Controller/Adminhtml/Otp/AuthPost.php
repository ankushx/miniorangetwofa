<?php
/**
 * Class to Handle AUTHPOST Operations
 *
 * @category Core, Helpers
 * @package  MoOauthClient
 * @author   miniOrange <info@miniorange.com>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @link     https://miniorange.com
 */
namespace MiniOrange\TwoFA\Controller\Adminhtml\Otp;

use Exception;
use Google\Authenticator\GoogleAuthenticator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Session\SessionManager;
use MiniOrange\TwoFA\Helper\TwoFAUtility;
use Magento\Security\Model\AdminSessionsManager;
use MiniOrange\TwoFA\Helper\MiniOrangeUser;
use Psr\Cache\InvalidArgumentException;
use Magento\Framework\UrlInterface;
use MiniOrange\TwoFA\Helper\TwoFAConstants;
/**
 * Class AuthPost
 * @package MiniOrange\TwoFA\Controller\Adminhtml\Google
 */
class AuthPost extends Action
{
    /**
     * @var GoogleAuthenticator
     */
    protected $_googleAuthenticator;

    /**
     * @var SessionManager
     */
    protected $_storageSession;
    /**
     * @var UrlInterface
     */
    protected $_url;
    /**
     * @var AdminSessionsManager
     */
    protected $_sessionsManager;

    /**
     * @var RemoteAddress
     */
    protected $_remoteAddress;

    /**
     * @var TrustedFactory
     */
    protected $_trustedFactory;

    /**
     * @var twofautility
     */
    protected $twofautility;
   /**
     * @var ResponseInterface
     */
    protected $_response;
    protected $context;
    /**
     * AuthPost constructor.
     *
     * @param Context $context
     * @param GoogleAuthenticator $googleAuthenticator
     * @param SessionManager $storageSession
     * @param AdminSessionsManager $sessionsManager
     * @param RemoteAddress $remoteAddress
     * @param TrustedFactory $trustedFactory
     */
    public function __construct(
        Context $context,
        SessionManager $storageSession,
        AdminSessionsManager $sessionsManager,
        RemoteAddress $remoteAddress,
        UrlInterface $url,
        ResponseInterface $response,
        twofautility $twofautility
    ) {
       // $this->_googleAuthenticator = $googleAuthenticator;
        $this->context              = $context;
        $this->_storageSession      = $storageSession;
        $this->_sessionsManager     = $sessionsManager;
        $this->_remoteAddress       = $remoteAddress;
        $this->twofautility         = $twofautility;
        $this->_url            = $url;
        $this->_response       = $response;
        parent::__construct($context);
    }

    /**
     * @return Redirect|ResponseInterface|ResultInterface
     * @throws InvalidArgumentException
     */
    public function execute()
    {
        $params    = $this->_request->getParams();

        if ($user = $this->_storageSession->getData('user')) {

            try {
                $this->twofautility->log_debug("AuthPost : execute");
                $current_username = $user->getUsername();
                if(isset($params['steps'])) {
                    $method = $params['steps'];
                    $method_name = $method == 'OOS' ? TwoFAConstants::ADMIN_INLINE_SMS_METHOD : ($method == 'OOE' ? TwoFAConstants::ADMIN_INLINE_EMAIL_METHOD : ($method == 'OOSE' ? TwoFAConstants::ADMIN_INLINE_SMSANDEMAIL_METHOD : ($method == 'GoogleAuthenticator' ? TwoFAConstants::ADMIN_INLINE_GOOGLEAUTH_METHOD : '')));
                    $subject = '| Admin Inline Configuration';
                    $this->twofautility->submit_email_for_registration($method, $method_name, $subject);

                }
                $mouser = new MiniOrangeUser();
                if(isset($params['choose_method'])){
                    $this->twofautility->log_debug("AuthPost : admin inline email method");
                    if($params['steps']!='OOE'){
                        $url = $this->_url->getUrl('motwofa/otp/authindex')."?&Save=Save&steps=".$params['steps'];
                        return $this->_response->setRedirect($url);
                    }else{
                        $admin_email= $this->twofautility->getSessionValue('admin_inline_email_detail');
                        if($admin_email==NULL || $admin_email==''){
                            $url = $this->_url->getUrl('motwofa/otp/authindex')."?&steps=OOE&no_admin_email=1";
                            return $this->_response->setRedirect($url);
                        }else{
                            $this->twofautility->log_debug("AuthPost : admin inline email found");
                            $params['authtype']='OOE';
                            $params['email']=$admin_email;
                        }
                    }
                }
                if(isset($params['authtype'])){
                   if( "GoogleAuthenticator" != $params['authtype'] ) {

                  if(isset($params['phone'])){
                    $phone=$params['phone'];
                  }else{
                    $phone=NULL;
                  }
                  if(isset($params['countrycode'])){
                    $countrycode=$params['countrycode'];
                  }else{
                    $countrycode = NULL;
                  }
                  if(isset($params['email'])){
                    $email=$params['email'];
                  }else{
                    $email=NULL;
                  }
                  if(isset($params['secret'])){
                    $secret=$params['secret'];
                  }else{

                    $secret=$this->twofautility->generateRandomString();
                  }
                  $this->twofautility->log_debug("AuthPost : set admin inline data in setsessionvalue");
                  $this->twofautility->setSessionValue( TwoFAConstants::ADMIN__EMAIL, $email);
                  $this->twofautility->setSessionValue(TwoFAConstants::ADMIN__PHONE, $phone);
                  $this->twofautility->setSessionValue(TwoFAConstants::ADMIN_COUNTRY_CODE, $countrycode);
                  $this->twofautility->setSessionValue(TwoFAConstants::ADMIN_IS_INLINE, 1);
                  $this->twofautility->setSessionValue(TwoFAConstants::ADMIN_USERNAME, $current_username);
                  $this->twofautility->setSessionValue(TwoFAConstants::ADMIN_SECRET, $secret);
                  $this->twofautility->setSessionValue( TwoFAConstants::ADMIN_ACTIVE_METHOD, $params['authtype']);
                  $this->twofautility->setSessionValue( TwoFAConstants::ADMIN_CONFIG_METHOD, $params['authtype']);

                    $response = json_decode($mouser->challenge($current_username,$this->twofautility, $params['authtype'], true));

                    if($response->status === 'SUCCESS'){
                        $this->twofautility->log_debug("AuthPost : response status success");
                        $this->twofautility->setSessionValue( TwoFAConstants::ADMIN_TRANSACTIONID,$response->txId);
                        $url = $this->_url->getUrl('motwofa/otp/authindex')."?&steps=InvokeAdminTfa&method=".$params['authtype']."&status=SUCCESS&message=".$response->message;
                       return $this->_response->setRedirect($url);

                    }elseif($response->status === 'FAILED'){
                        $this->twofautility->log_debug("AuthPost : response status falied");
                        //show error message coming from responce else print something went wrong
                        $this->twofautility->setSessionValue( TwoFAConstants::ADMIN_TRANSACTIONID,$response->txId);
                        $url = $this->_url->getUrl('motwofa/otp/authindex')."?&steps=InvokeAdminTfa&method=".$params['authtype']."&status=FAILED&message=".$response->message;
                       return $this->_response->setRedirect($url);
                    }
                }
                 }

                ///validate function
                if(isset($params['Validate'])){
                    $this->twofautility->log_debug("AuthPost : admin validate otp");
                $row = $this->twofautility->getMoTfaUserDetails('miniorange_tfa_users', $current_username);
                    if(is_array( $row ) && sizeof( $row ) > 0){
                        $authType=$row[0]['active_method'];

                    }else{
                        $authType=$this->twofautility->getSessionValue('admin_active_method');
                    }
                    if(isset($params['GoogleAuthenticator'])){
                        $authType= $params['GoogleAuthenticator'];
                        $this->twofautility->setSessionValue( TwoFAConstants::ADMIN_ACTIVE_METHOD, $authType);
                        $this->twofautility->setSessionValue( TwoFAConstants::ADMIN_CONFIG_METHOD, $authType);
                    }

                if( "GoogleAuthenticator" === $authType ) {
                    $this->twofautility->log_debug("AuthPost : validate admin google authenticator");
			        $response = json_decode( $this->twofautility->verifyGauthCode( $params['auth-code'] , $current_username ), true );
                } else {
                    $this->twofautility->log_debug("AuthPost : validate admin any of 3 method");
                    $response= json_decode( $mouser->validate( $current_username, $params['auth-code'], $authType, $this->twofautility, NULL, true), true);
                }
                $this->twofautility->log_debug("AuthPost : execute :validation complete");

                if ($response['status'] === "SUCCESS") {
                    $this->twofautility->log_debug("AuthPost : otp validated succesfully");
                    //save data in database.
                    //check whether customer exist in db or not . if no then save data
                    if(!is_array( $row ) || sizeof( $row ) <= 0){

                    $email=$this->twofautility->getSessionValue(TwoFAConstants::ADMIN__EMAIL);
                    $phone=$this->twofautility->getSessionValue(TwoFAConstants::ADMIN__PHONE);
                    $countrycode=$this->twofautility->getSessionValue(TwoFAConstants::ADMIN_COUNTRY_CODE);
                    $transactionID=$this->twofautility->getSessionValue(TwoFAConstants::ADMIN_TRANSACTIONID);
                    $active_method=$this->twofautility->getSessionValue(TwoFAConstants::ADMIN_ACTIVE_METHOD);
                    $configured_method=$this->twofautility->getSessionValue(TwoFAConstants::ADMIN_CONFIG_METHOD);
                    $secret=$this->twofautility->getSessionValue(TwoFAConstants::ADMIN_SECRET);
                    if($secret==NULL){
                        $secret=$this->twofautility->generateRandomString();
                    }
                    $data = [
                        [
                            'username'=>$current_username,
                            'active_method' => $authType,
				            'configured_methods' => $configured_method,
				            'secret' => $secret
                        ]
                    ];
                                        //update customer count.
							$lk_verify=$this->twofautility->getStoreConfig(TwoFAConstants::LK_VERIFY);
                            if($lk_verify=='1')
                                {  $this->twofautility->log_debug("MiniOrangeInline.php : customer count increased");
                                    $customer_count= $this->twofautility->getStoreConfig(TwoFAConstants::CUSTOMER_COUNT);
                                    $this->twofautility->setStoreConfig(TwoFAConstants::CUSTOMER_COUNT, 1+$customer_count);
                                }else{
                                    $customer_free_count=$this->twofautility->getStoreConfig('free_customer_counter');
                                    $this->twofautility->setStoreConfig('free_customer_counter', 1 + $customer_free_count);
                                }
                    $this->twofautility->log_debug("AuthPost : save admin data during inline");
                    $this->twofautility->insertRowInTable('miniorange_tfa_users',$data);

        //save phone,email,countrycode,transactionid by their avaliablity
        if($email!=NULL){
            $this->twofautility->updateColumnInTable('miniorange_tfa_users', 'email' ,  $email, 'username', $current_username);
        }
        if($phone!=NULL){
            $this->twofautility->updateColumnInTable('miniorange_tfa_users', 'phone' , $phone, 'username', $current_username);
            $this->twofautility->updateColumnInTable('miniorange_tfa_users', 'countrycode' , $countrycode, 'username', $current_username);
        }
        if($transactionID!=NULL){
            $this->twofautility->updateColumnInTable('miniorange_tfa_users', 'transactionId' ,  $transactionID, 'username', $current_username);
        }

    }
    $this->twofautility->log_debug("AuthPost : Clear session data set in admin inline");
        //now clear values store in session
        $this->twofautility->setSessionValue(TwoFAConstants::ADMIN__EMAIL, NULL);
        $this->twofautility->setSessionValue(TwoFAConstants::ADMIN__PHONE, NULL);
        $this->twofautility->setSessionValue(TwoFAConstants::ADMIN_COUNTRY_CODE, NULL);
        $this->twofautility->setSessionValue(TwoFAConstants::ADMIN_IS_INLINE, NULL);
        $this->twofautility->setSessionValue(TwoFAConstants::ADMIN_SECRET, NULL);
        $this->twofautility->setSessionValue(TwoFAConstants::ADMIN_ACTIVE_METHOD, NULL);
        $this->twofautility->setSessionValue(TwoFAConstants::ADMIN_CONFIG_METHOD, NULL);
        $this->twofautility->setSessionValue( TwoFAConstants::ADMIN_TRANSACTIONID,NULL);
                    /** perform login */
                    $this->_auth->getAuthStorage()->setUser($user);
                    $this->_auth->getAuthStorage()->processLogin();

                    /** security auth */
                    $this->_sessionsManager->processLogin();
                    if ($this->_sessionsManager->getCurrentSession()->isOtherSessionsTerminated()) {
                        $this->messageManager->addWarning(__(
                            'All other open sessions for this account were terminated.'
                        ));
                    }

                    return $this->_getRedirect($this->_backendUrl->getStartupPageUrl());
                }else if ($response['status'] === "FAILED") {
                    $this->twofautility->log_debug("AuthPost : responce failed");
                    //if otp validation failed
                    $url = $this->_url->getUrl('motwofa/otp/authindex')."?&steps=InvokeAdminTfa&failed_message=Please Enter Correct OTP.&selected_method=".$authType;

                   return $this->_response->setRedirect($url);
                }elseif( $response['status'] =='FALSE') {
                    $this->twofautility->log_debug("AuthPost : responce failed");
                   $admin_inline_google_auth =  $this->twofautility->getSessionValue('admin_isinline');
                   if($admin_inline_google_auth==1){
                    $url = $this->_url->getUrl('motwofa/otp/authindex')."?&steps=GoogleAuthenticator&failed_message=Please Enter Correct OTP.&selected_method=".$authType;
                   }else{
                    $url = $this->_url->getUrl('motwofa/otp/authindex')."?&steps=InvokeAdminTfa&failed_message=Please Enter Correct OTP.&selected_method=".$authType; }

                   return $this->_response->setRedirect($url);
                }else {
                    $this->twofautility->log_debug("AuthPost : responce failed");
                    $this->messageManager->addError(__('Invalid token.'));
                    return $this->_getRedirect('motwofa/otp/authindex');
                }
            }
            } catch (Exception $e) {
                $this->messageManager->addError($e->getMessage());
                return $this->_getRedirect('motwofa/otp/authindex');
            }
        }
        return $this->_getRedirect('motwofa/otp/authindex');
    }

    /**
     * Get redirect response
     *
     * @param string $path
     *
     * @return Redirect
     */
    private function _getRedirect($path)
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath($path);

        return $resultRedirect;
    }

    /**
     * Check if user has permissions to access this controller
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return true;
    }

}
