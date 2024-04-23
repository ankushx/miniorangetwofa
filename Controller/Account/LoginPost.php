<?php

namespace MiniOrange\TwoFA\Controller\Account;

use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\Exception\EmailNotConfirmedException;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Data\Form\FormKey\Validator;


use MiniOrange\TwoFA\Helper\TwoFAConstants;
use MiniOrange\TwoFA\Helper\TwoFAUtility;
use MiniOrange\TwoFA\Helper\MiniOrangeUser;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class LoginPost extends \Magento\Customer\Controller\Account\LoginPost {

    private $cookieManager;
    protected $context;
    protected $customerSession;
    private $cookieMetadataFactory;
    protected $TwoFAUtility;
    protected $session;
    protected $customerAccountManagement;
    protected $accountRedirect;
    private $moduleManager;
    protected $customerUrl;
    protected $formKeyValidator;
    protected $storeManager;

    public function __construct(
        Context $context,
        Session $customerSession,
        AccountManagementInterface $customerAccountManagement,
        CustomerUrl $customerHelperData,
        Validator $formKeyValidator,
        AccountRedirect $accountRedirect,

        TwoFAUtility $TwoFAUtility,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->session = $customerSession;
        $this->customerAccountManagement = $customerAccountManagement;
        $this->customerUrl = $customerHelperData;
        $this->formKeyValidator = $formKeyValidator;
        $this->accountRedirect = $accountRedirect;

        $this->TwoFAUtility = $TwoFAUtility;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->moduleManager = $moduleManager;
        $this->storeManager = $storeManager;
        parent::__construct(
            $context,
            $customerSession,
            $customerAccountManagement,
            $customerHelperData,
            $formKeyValidator,
            $accountRedirect
        );
    }

    public function execute() {
        $this->TwoFAUtility->log_debug("Execute LoginPost");
        if ($this->session->isLoggedIn() || !$this->formKeyValidator->validate($this->getRequest())) {
            /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('home');
            return $resultRedirect;
        }

        if ($this->getRequest()->isPost()) {
            $login = $this->getRequest()->getPost('login');
            $resultRedirect = $this->resultRedirectFactory->create();

            if (!empty($login['username']) && !empty($login['password'])) {
                try {

                    // Redirection condition with add-on
                    if( $this->moduleManager->isEnabled('MiniOrange_RedirectionAddon') ) {
                        $domains = $this->TwoFAUtility->getStoreCustomConfig('miniorange/RedirectionAddon/domains' );
                        $domains_array = explode( ",", $domains );
                        $username_explode_array = explode( "@", $login['username'] );
                        if( in_array( $username_explode_array[1], $domains_array ) ) {
                            if( $this->moduleManager->isEnabled('MiniOrange_OAuth') ) {
                                $redirect_url = $this->TwoFAUtility->getBaseUrl() . "mooauth/actions/sendAuthorizationRequest/";
                            } else if( $this->moduleManager->isEnabled('MiniOrange_SP') ) {
                                $redirect_url = $this->TwoFAUtility->getBaseUrl() . "mospsaml/actions/sendAuthnRequest/";
                            }
                            $resultRedirect->setUrl( $redirect_url );
                            return $resultRedirect;
                        }
                    }


                    $customer = $this->customerAccountManagement->authenticate($login['username'], $login['password']);
                        $websiteCode = $this->storeManager->getStore()->getWebsite()->getCode();
                        $invokeInline = $this->TwoFAUtility->getStoreConfig(TwoFAConstants::INVOKE_INLINE_REGISTERATION);
                     $active_method =  $this->TwoFAUtility->getStoreConfig(TwoFAConstants::ACTIVE_METHOD);
                    $active_method_status= ($active_method=='[]' || $active_method==NULL) ? false : true ;
                    if($invokeInline && $active_method_status && $websiteCode == "base"){
                        $this->TwoFAUtility->log_debug("Execute LoginPost: Inline Invoked and found active method");
                        // Initiate MFA flow
                        $current_username = $login['username'];

                        $this->TwoFAUtility->setSessionValue( 'mousername', $login['username'] );

                        // Setting up in the cookie for printing
                        $publicCookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata();
                        $publicCookieMetadata->setDurationOneYear();
                        $publicCookieMetadata->setPath('/');
                        $publicCookieMetadata->setHttpOnly(false);
                        $this->cookieManager->setPublicCookie(
                            'mousername', $current_username, $publicCookieMetadata
                        );


                        $row = $this->TwoFAUtility->getMoTfaUserDetails('miniorange_tfa_users',$current_username);
                        $redirectionUrl = '';
                        if( is_array( $row ) && sizeof( $row ) > 0 )
                        {
                            $this->TwoFAUtility->log_debug("Execute LoginPost: Customer has already registered in TwoFA method");
                            $authType = $row[0]['active_method'];

                            if( "GoogleAuthenticator" !== $authType ) {
                                $mouser = new MiniOrangeUser();
                                $response = json_decode($mouser->challenge($current_username, $this->TwoFAUtility, $authType, true));

                            $r_status = $response->status;
                            $message = $response->message;
                                if($response->status == 'SUCCESS'){
                                    $this->TwoFAUtility->updateColumnInTable('miniorange_tfa_users', 'transactionId' , $response->txId, 'username', $current_username);


                            $params = array('mooption' => 'invokeTFA',
                            'message' => $message,
                            'r_status' => $r_status,
                            'active_method' => $authType
                                            );
            $resultRedirect->setPath('motwofa/mocustomer/index', $params);
                             }else{
                                $this->TwoFAUtility->log_debug("LoginPost.php : execute: your user limit has been exceeded ");
                                $this->messageManager->addError(__('Unable to send OTP.Please Contact your  Administrator'));
                                $resultRedirect->setPath('customer/account/login');
                             }
                            }else{
                                $params = array('mooption' => 'invokeTFA',
                                'active_method' => $authType
                             );

            $resultRedirect->setPath('motwofa/mocustomer/index', $params);
                            }


                        }
                        else
                        { $this->TwoFAUtility->log_debug("Execute LoginPost: Customer going through Inline");
                            $this->TwoFAUtility->flushCache();
                            $lk_verify=$this->TwoFAUtility->getStoreConfig(TwoFAConstants::LK_VERIFY);
                            if($lk_verify=='1')
                                {
                                    $lk_customer_avaliable= $this->TwoFAUtility->getStoreConfig(TwoFAConstants::LK_NO_OF_USERS);
                                    $customer_count= $this->TwoFAUtility->getStoreConfig(TwoFAConstants::CUSTOMER_COUNT);
                                    if($customer_count >= $lk_customer_avaliable){
                                        $this->TwoFAUtility->log_debug("LoginPost.php : execute: your user limit has been exceeded ");
                                        $this->messageManager->addError(__('Can not create new user.Please Contact your  Administrator'));
                                        $resultRedirect->setPath('customer/account/login');
                                        return $resultRedirect;
                                    }
                                }else{

                                    $count=$this->TwoFAUtility->getStoreConfig('free_customer_counter');
                                    if($count>=10 ){
                               
                                        $subject='TwoFA user limit has been exceeded';
                                        $message='Trying to create frontend user using '.$current_username.' email';
                                        $this->TwoFAUtility->submit_message_to_magento_team($subject,$message);
                                        $this->TwoFAUtility->log_debug("LoginPost.php : execute: your user limit has been exceeded ");
                                        $this->messageManager->addError(__('Your user limit has been exceeded.Please contact magentosupport@xecurify.com'));
                                        $resultRedirect->setPath('customer/account/login');
                                        return $resultRedirect;

                                    }
                                   }
                                   $number_of_activeMethod=$this->TwoFAUtility->getStoreConfig(TwoFAConstants::NUMBER_OF_CUSTOMER_METHOD);
                                   if($number_of_activeMethod==1){
                                       $customer_active_method=$this->TwoFAUtility->getStoreConfig(TwoFAConstants::ACTIVE_METHOD);
                                       $customer_active_method = trim($customer_active_method,'[""]');
                                       $params = array('mopostoption' => 'method', 'miniorangetfa_method' => $customer_active_method,'deleteSet'=>'deleteSet','inline_one_method'=>'1');
                                       $resultRedirect->setPath('motwofa/mocustomer', $params);
                                   }elseif($number_of_activeMethod>1){

                                    $params = array('mooption' => 'invokeInline', 'step' => 'ChooseMFAMethod');
                                    $resultRedirect->setPath('motwofa/mocustomer/index', $params);

                                   }

                        }
                        return $resultRedirect;
                    } else {
                        $this->TwoFAUtility->log_debug("Execute LoginPost: Invoke Inline off");
                        // Continue the flow
                        $this->session->setCustomerDataAsLoggedIn($customer);
                        $this->session->regenerateId();
                    }
                } catch (EmailNotConfirmedException $e) {
                    $value = $this->customerUrl->getEmailConfirmationUrl($login['username']);
                    $message = __(
                            'This account is not confirmed.' .
                            ' <a href="%1">Click here</a> to resend confirmation email.', $value
                    );
                    $this->messageManager->addError($message);
                    $this->session->setUsername($login['username']);

                    $resultRedirect->setPath('customer/account/login');
                    return $resultRedirect;

                } catch (AuthenticationException $e) {
                    $message = __('Invalid login or password.');
                    $this->messageManager->addError($message);
                    $this->session->setUsername($login['username']);

                    $resultRedirect->setPath('customer/account/login');
                    return $resultRedirect;

                } catch (\Exception $e) {
                    $this->messageManager->addError(__('Invalid login or password.'));
                    $resultRedirect->setPath('customer/account/login');
                    return $resultRedirect;
                }
            } else {
                $this->TwoFAUtility->log_debug("Execute LoginPost: Username or password null");
                $this->messageManager->addError(__('A login and a password are required.'));
                $resultRedirect->setPath('customer/account/login');
                return $resultRedirect;
            }
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('home');
        return $resultRedirect;
    }

}