<?php

namespace MiniOrange\TwoFA\Model;

use DateTimeZone;
use Exception;
use Magento\Backend\Helper\Data;
use Magento\Backend\Model\Auth\Credential\StorageInterface as CredentialStorageInterface;
use Magento\Backend\Model\Auth\StorageInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Data\Collection\ModelFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\Plugin\AuthenticationException as PluginAuthenticationException;
use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Session\SessionManager;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use MiniOrange\TwoFA\Helper\TwoFAUtility;
use MiniOrange\TwoFA\Helper\TwoFAConstants;
use MiniOrange\TwoFA\Helper\MiniOrangeUser;
use Magento\Framework\App\RequestInterface;

/**
 * Backend Auth model
 */
class Auth extends \Magento\Backend\Model\Auth
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var DateTime
     */
    protected $_dateTime;

    /**
     * @var UrlInterface
     */
    protected $_url;

    /**
     * @var ResponseInterface
     */
    protected $_response;

    /**
     * @var SessionManager
     */
    protected $_storageSession;

    /**
     * Action flag
     *
     * @var ActionFlag
     */
    protected $actionFlag;

    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * @var TrustedFactory
     */
    protected $_trustedFactory;

    /**
     * @var bool
     */
    protected $_isTrusted = false;

    /**
     * @var TwoFAUtility
     */
    protected $twofautility;
    protected $_request;
    /**
     * Auth constructor.
     *
     * @param ManagerInterface $eventManager
     * @param Data $backendData
     * @param StorageInterface $authStorage
     * @param CredentialStorageInterface $credentialStorage
     * @param ScopeConfigInterface $coreConfig
     * @param ModelFactory $modelFactory
     * @param Request $request
     * @param DateTime $dateTime
     * @param UrlInterface $url
     * @param ResponseInterface $response
     * @param SessionManager $storageSession
     * @param ActionFlag $actionFlag
     * @param HelperData $helperData
     * @param TrustedFactory $trustedFactory
     */
    public function __construct(
        ManagerInterface $eventManager,
        Data $backendData,
        StorageInterface $authStorage,
        CredentialStorageInterface $credentialStorage,
        ScopeConfigInterface $coreConfig,
        ModelFactory $modelFactory,
        Request $request,
        DateTime $dateTime,
        UrlInterface $url,
        ResponseInterface $response,
        SessionManager $storageSession,
        ActionFlag $actionFlag,
        TrustedFactory $trustedFactory,
        TwoFAUtility $twofaUtility,
        RequestInterface $_request
    ) {
        $this->request         = $request;
        $this->_dateTime       = $dateTime;
        $this->_url            = $url;
        $this->_response       = $response;
        $this->_storageSession = $storageSession;
        $this->actionFlag      = $actionFlag;
        $this->_trustedFactory = $trustedFactory;
        $this->twofautility    = $twofaUtility;
        $this->_request = $_request;
        parent::__construct($eventManager, $backendData, $authStorage, $credentialStorage, $coreConfig, $modelFactory);
    }

    /**
     * Perform login process
     *
     * @param string $username
     * @param string $password
     *
     * @throws PluginAuthenticationException
     * @throws Exception
     * @throws AuthenticationException
     */
    public function login($username, $password)
    {   $params = $this->_request->getParams();

        if (empty($username) || empty($password)) {
            self::throwException(__('You did not sign in correctly or your account is temporarily disabled.'));
        }

        try {
            $this->twofautility->log_debug("Auth.php : execute :admin");
            $this->_initCredentialStorage();
            $this->getCredentialStorage()->login($username, $password);

            if ($this->getCredentialStorage()->getId()) {
                // Get the details and set in the session
                $user = $this->getCredentialStorage();
                $this->twofautility->setSessionValue('admin_user_id',$user->getID());
                $this->actionFlag->set('', Action::FLAG_NO_DISPATCH, true);

                $this->_storageSession->setData('user', $user);

                $current_username = $user->getUsername();
                //set admin email for inline registration.
                $admin_details=$user->getData();
                $admin_email=$admin_details['email'];
                $this->twofautility->setSessionValue( 'admin_inline_email_detail', $admin_email);

                $row = $this->twofautility->getMoTfaUserDetails('miniorange_tfa_users', $current_username);

                $this->twofautility->flushCache();
                 $module_tfa = $this->twofautility->getStoreConfig(TwoFAConstants::MODULE_TFA);
                $backdoor_login=NULL;
                 if(isset($params['backdoor'])){
                    $this->twofautility->log_debug("Auth.php : execute: backdoor login of admin set");
                    $backdoor_login=1;
                }
                 if($module_tfa && $backdoor_login==NULL) {
                    $this->twofautility->log_debug("Auth.php : execute: module enabled");
                    if( is_array( $row ) && sizeof( $row ) > 0 ){
                        // MFA only for those who have configured
                        $this->twofautility->log_debug("Auth.php : execute: admin has already set TwoFA settings");
                        $authType = $row[0]['active_method'];
                        if($authType==NULL)
                        { $this->twofautility->log_debug("Auth.php : execute: No active method");
                            $this->NormalLoginFlow();
                        }else{
                        if( "GoogleAuthenticator" != $authType ) {
                            $this->twofautility->log_debug("Auth.php : execute: Active method found:".$authType);
                            $mouser = new MiniOrangeUser();
                            $response = json_decode($mouser->challenge($username,$this->twofautility, $authType, true));

                            if($response->status === 'SUCCESS'){
                                $this->twofautility->log_debug("Auth.php : Otp send succesfully");
                            $this->twofautility->updateColumnInTable('miniorange_tfa_users', 'transactionId' , $response->txId, 'username', $current_username);
                            }
                        }
                        $url = $this->_url->getUrl('motwofa/otp/authindex')."?&steps=InvokeAdminTfa&selected_method=".$authType;

                        if(( "GoogleAuthenticator" != $authType ) && ($response->status == 'SUCCESS')){
                            $this->twofautility->log_debug("Auth.php : execute: response sucess");
                            $message=$response->message;
                            $url=$url."&status=SUCCESS&steps=InvokeAdminTfa&selected_method=".$authType."&message=".$message;
                            }

                        if(( "GoogleAuthenticator" != $authType ) && ($response->status == 'FAILED')){
                            $this->twofautility->log_debug("Auth.php : Otp send failed");
                            $this->twofautility->log_debug("Auth.php : execute: response failed");
                            $message=$response->message;
                            $url=$url."&status=FAILED&steps=InvokeAdminTfa&message=".$message;
                            }
                        $this->_response->setRedirect($url);
                        }
                    } else {
                        $this->twofautility->log_debug("Auth.php : execute: admin going through admin inline process");
                        $lk_verify=$this->twofautility->getStoreConfig(TwoFAConstants::LK_VERIFY);
                        if($lk_verify=='1')
                       {    $this->twofautility->flushCache();
                        $lk_customer_avaliable= $this->twofautility->getStoreConfig(TwoFAConstants::LK_NO_OF_USERS);
                       $customer_count= $this->twofautility->getStoreConfig(TwoFAConstants::CUSTOMER_COUNT);
                        if($customer_count>=$lk_customer_avaliable){
                            $this->twofautility->log_debug("Auth.php : execute: your user limit has been exceeded ");
                            $url = $this->_url->getUrl('motwofa/otp/authindex');
                            $url=$url."?&steps=UserLimit";
                            $this->_response->setRedirect($url);
                        }
                       }else{
                        $count= $this->twofautility->getStoreConfig('free_customer_counter');
                        if($count>=10 ){
                            $subject='TwoFA user limit has been exceeded';
                            $message='Trying to create frontend user using '.$current_username.' email';
                            $this->TwoFAUtility->submit_message_to_magento_team($subject,$message);

                            $url = $this->_url->getUrl('motwofa/otp/authindex');
                            $url=$url."?&steps=UserLimit";
                            $this->_response->setRedirect($url);
                        }
                       }
                        //first clear values store in session
                        if(!isset($url)){
                         $this->twofautility->setSessionValue( 'admin_email', NULL);
                         $this->twofautility->setSessionValue('admin_phone', NULL);
                         $this->twofautility->setSessionValue('admin_countrycode', NULL);
                         $this->twofautility->setSessionValue('admin_isinline', 1);
                         $this->twofautility->setSessionValue( 'admin_secret', $this->twofautility->generateRandomString());
                         $this->twofautility->setSessionValue( 'admin_active_method', NULL);
                         $this->twofautility->setSessionValue( 'admin_config_method', NULL);
                          $this->twofautility->setSessionValue( 'admin_transactionid',NULL);
                         $this->twofautility->setSessionValue('admin_username', $username);

                         $admin_role = $this->twofautility->get_admin_role_name();
                         $number_of_activeMethod=$this->twofautility->getStoreConfig(TwoFAConstants::NUMBER_OF_ADMIN_METHOD);
                            if($admin_role=='Administrators'){
                                if($number_of_activeMethod==1){
                                    $admin_active_method=$this->twofautility->getStoreConfig(TwoFAConstants::ADMIN_ACTIVE_METHOD_INLINE);

                                    $admin_active_method = trim($admin_active_method,'[""]');
                                    $url = $this->_url->getUrl('motwofa/otp/authpost');
                                    $url=$url."?&choose_method=1&Save=Save&steps=".$admin_active_method;
                                    $this->_response->setRedirect($url);

                                }elseif($number_of_activeMethod>1){

                                    $url = $this->_url->getUrl('motwofa/otp/authindex');
                                    $url=$url."?&steps=choosemethod";
                                    $this->_response->setRedirect($url);

                                }elseif($number_of_activeMethod==NULL){
                                    $this->NormalLoginFlow();
                                }

                            }else{
                                $this->NormalLoginFlow();
                            }

                        }
                    }
                }elseif($backdoor_login){
                    $this->twofautility->log_debug("Auth.php : execute: backdoor login executed by following admin");
                    $this->twofautility->log_debug($username);
                   $api_key=$this->twofautility->getStoreConfig(TwoFAConstants::API_KEY);

                   if($params['backdoor'] == $api_key){
                    $this->NormalLoginFlow();
                   }else{
                    $url = $this->_url->getUrl('motwofa/otp/authindex');
                            $url=$url."?&steps=Backdoor";
                            $this->_response->setRedirect($url);
                   }

                } else {
                    $this->twofautility->log_debug("Auth.php : execute: module disabled");
                    $this->NormalLoginFlow();

                }

            }

            if (!$this->getAuthStorage()->getUser()) {
                self::throwException(__('You did not sign in correctly or your account is temporarily disabled.'));
            }
        } catch (PluginAuthenticationException $e) {
            $this->_eventManager->dispatch(
                'backend_auth_user_login_failed',
                ['user_name' => $username, 'exception' => $e]
            );
            throw $e;
        } catch (LocalizedException $e) {
            $this->_eventManager->dispatch(
                'backend_auth_user_login_failed',
                ['user_name' => $username, 'exception' => $e]
            );
            self::throwException(__('You did not sign in correctly or your account is temporarily disabled.'));
        }


    }
    public function NormalLoginFlow(){
        $this->twofautility->log_debug("Auth.php : execute: NormalLoginFlow");
        // Login process for those who have not configured the MFA
        $this->getAuthStorage()->setUser($this->getCredentialStorage());
        $this->getAuthStorage()->processLogin();

        $this->_eventManager->dispatch(
            'backend_auth_user_login_success',
            ['user' => $this->getCredentialStorage()]
        );
              }
}
