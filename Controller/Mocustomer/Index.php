<?php

namespace MiniOrange\TwoFA\Controller\Mocustomer;

use Magento\Framework\App\RequestInterface;
use MiniOrange\TwoFA\Helper\TwoFAUtility;
use MiniOrange\TwoFA\Helper\MiniOrangeInline;
use Magento\Customer\Model\Customer;
use MiniOrange\TwoFA\Helper\Exception\OtpValidateFailureException;
use Magento\Customer\Model\Session;
use MiniOrange\TwoFA\Helper\TwoFAConstants;

class Index extends \Magento\Framework\App\Action\Action
{
    protected $_pageFactory;

    protected $request;

    private $TwoFAUtility;

    private $miniOrangeInline;

    private $customerModel;

    private $customerSession;

    protected $resultFactory;

    protected $storeManager;

    private $url;
    private $responseFactory;
    protected $messageManager;
    public function __construct(
       \Magento\Framework\App\Action\Context $context,
       \Magento\Framework\View\Result\PageFactory $pageFactory,
       \Magento\Framework\Message\ManagerInterface $messageManager,
       \Magento\Framework\App\ResponseFactory $responseFactory,
       RequestInterface $request,
       TwoFAUtility $TwoFAUtility,
       MiniOrangeInline $miniOrangeInline,

       Customer $customerModel,
       Session $customerSession,
       \Magento\Framework\Controller\ResultFactory $resultFactory,
       \Magento\Framework\UrlInterface $url,
       \Magento\Store\Model\StoreManagerInterface $storeManager
       )
    {
       $this->_pageFactory = $pageFactory;
       $this->messageManager = $messageManager;
       $this->responseFactory = $responseFactory;
       $this->request = $request;

       $this->TwoFAUtility = $TwoFAUtility;
       $this->customerModel = $customerModel;
       $this->miniOrangeInline = $miniOrangeInline;
       $this->customerSession = $customerSession;

       $this->resultFactory = $resultFactory;
       $this->url = $url;
       $this->storeManager = $storeManager;

       return parent::__construct($context);
    }


   private function getCustomerFromAttributes($user_email)
   {
      $this->TwoFAUtility->log_debug("processUserAction: getCustomerFromAttributes");
      $this->customerModel->setWebsiteId($this->storeManager->getStore()->getWebsiteId());
      $customer = $this->customerModel->loadByEmail($user_email);
      return !is_null($customer->getId()) ? $customer : false;
   }

   public function execute() {
      $this->TwoFAUtility->log_debug("processUserAction: execute");
      $postValue = $this->request->getPostValue();
      $request =  $this->request->getParams();

      if(isset($request['inline_one_method'])){
         $postValue=$request;
      }

   if(isset($postValue['mopostoption']) && $postValue['mopostoption']=='method' && isset($postValue['miniorangetfa_method']) && $postValue['miniorangetfa_method']=='OOE' ){
      $postValue['mopostoption']='challenge';
      $postValue['deleteSet']='deleteSet';
       $method = $postValue['miniorangetfa_method'];
       $method_name = $method == 'OOS' ?TwoFAConstants::CUSTOMER_SMS_METHOD : ($method == 'OOE' ? TwoFAConstants::CUSTOMER_EMAIL_METHOD: ($method == 'OOSE' ? TwoFAConstants::CUSTOMER_SMSANDEMAIL_METHOD: ($method== 'GoogleAuthenticator' ? TwoFAConstants::CUSTOMER_GOOGLEAUTH_METHOD : '')));
       $subject='| Customer Inline Configuration';
       $this->TwoFAUtility->submit_email_for_registration($method,$method_name,$subject);

      $postValue['redirect_to']=$this->storeManager->getStore()->getBaseUrl().'motwofa/mocustomer/?mooption=invokeInline&step=OOEMethodValidation&savestep=OOE&deleteSet=deleteSet';
     $this->miniOrangeInline->thirdStepSubmit($this->TwoFAUtility);
   }
if(isset($postValue['delConfirm']))
{  $this->TwoFAUtility->log_debug("MoCustomer : execute: delete row");
   $current_username = $this->TwoFAUtility->getSessionValue( 'mousername');
   $row = $this->TwoFAUtility->getMoTfaUserDetails('miniorange_tfa_users',$current_username);


		if( is_array( $row ) && sizeof( $row ) > 0 )
		{  $useriddata= $this->TwoFAUtility->getMoTfaUserDetails('miniorange_tfa_users',$current_username);

         $idvalue=$useriddata[0]['id'];

         $this->TwoFAUtility->deleteRowInTable('miniorange_tfa_users', 'id', $idvalue);
      }

      $redirect_url = $this->url->getUrl('customer/account/login');
      $redirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
      $redirect->setUrl($redirect_url);
      return $redirect;
}
      $redirect_url = '';

      if(isset($postValue['mopostoption'])){

         if( "uservalotp" == $postValue['mopostoption'] ) {
            $this->TwoFAUtility->log_debug("MoCustomer : execute: customer 2fa validation ");
            $current_username = $this->TwoFAUtility->getSessionValue( 'mousername');
            $row = $this->TwoFAUtility->getMoTfaUserDetails('miniorange_tfa_users',$current_username);
            $username = $current_username;
            $response = $this->miniOrangeInline->TFAValidate($this->TwoFAUtility);

            if($response){
               $user = $this->getCustomerFromAttributes($username);
               $redirect_url = $this->url->getUrl('customer/account');
               $this->customerSession->setCustomerAsLoggedIn($user);
            } else {
               $redirect_url = $this->url->getUrl('motwofa/mocustomer') . "?mooption=invokeTFA&error=error";
            }
         } else if( "invokeInline" == $postValue['mopostoption'] ) {
            $this->TwoFAUtility->log_debug("MoCustomer : execute: Invokeinline registration ");
            $this->miniOrangeInline->testing($this->TwoFAUtility);

               $redirect_url = $this->url->getUrl('motwofa/mocustomer') . "?mooption=invokeInline&step=ValidatePasscodeForEmailAddress";


         } else if( "validate" == $postValue['mopostoption'] ){
            $this->TwoFAUtility->log_debug("MoCustomer : execute: validate inline regstration ");
            $this->miniOrangeInline->pageTwoSubmit($this->TwoFAUtility);
            $redirect_url = $this->url->getUrl('motwofa/mocustomer') . "?mooption=invokeInline&step=ChooseMFAMethod";
         } else if("method"== $postValue['mopostoption'] ){
           //do changes for google authenticaor 10 user limit here

           $this->TwoFAUtility->log_debug("MoCustomer : execute: Inline registration :select method ");
            if(isset($postValue['miniorangetfa_method'])) {
                $method = $postValue['miniorangetfa_method'];
                $method_name = $method == 'OOS' ? TwoFAConstants::CUSTOMER_SMS_METHOD : ($method == 'OOE' ? TwoFAConstants::CUSTOMER_EMAIL_METHOD : ($method == 'OOSE' ? TwoFAConstants::CUSTOMER_SMSANDEMAIL_METHOD : ($method == 'GoogleAuthenticator' ? TwoFAConstants::CUSTOMER_GOOGLEAUTH_METHOD : '')));
                $subject = '| Customer Inline Configuration';
                $this->TwoFAUtility->submit_email_for_registration($method, $method_name, $subject);
            }
           //do change for picking phone number from customer db

            $redirect_url = $this->miniOrangeInline->thirdStepSubmit($this->TwoFAUtility);
            $redirect_url=$redirect_url."&savestep=".$postValue['miniorangetfa_method'];
            if(isset($postValue['deleteSet']))
            {
               $redirect_url=$redirect_url."&deleteSet=deleteSet";
            }
if($postValue['miniorangetfa_method']=='OOS' || $postValue['miniorangetfa_method']=='OOSE'){
   $this->TwoFAUtility->log_debug("MoCustomer : Customer Inline registration : fetch phone countrycode and email from db ->already set by customer");
   $current_username = $this->TwoFAUtility->getSessionValue( 'mousername');
   $phone=  $this->TwoFAUtility->getCustomerPhoneFromEmail($current_username);
   $country_name_id= $this->TwoFAUtility->getCustomerCountryFromEmail($current_username);

   if($phone!=false && $country_name_id!=false){
      $this->TwoFAUtility->log_debug("MoCustomer : execute: Inline registration :phone and country code found ");
      $countrycode=$this->getPhoneID_from_ContryCode($country_name_id);
      $countrycode= strval($countrycode);
      $this->TwoFAUtility->setSessionValue('customer_phone', $phone);
      $this->TwoFAUtility->setSessionValue('customer_countrycode', $countrycode);

if($postValue['miniorangetfa_method']=='OOSE'){
   $this->TwoFAUtility->log_debug("MoCustomer : execute: Inline registration :OOSE method email found");
   $email=$current_username;
   $this->TwoFAUtility->setSessionValue('customer_email', $email);
}
$this->TwoFAUtility->log_debug("MoCustomer : execute: Inline registration :send otp ");
   $send_otp_response = $this->miniOrangeInline->pageFourChallenge($this->TwoFAUtility);
   $r_status=$send_otp_response['status'];
$message=$send_otp_response['message'];
         
            if($r_status=='FAILED')
            {   $this->TwoFAUtility->log_debug("MoCustomer : execute: Inline registration :responce falied to send otp ");
               $this->TwoFAUtility->log_debug("MoCustomer : execute: Inline registration: response failed");
               $this->responseFactory->create()->setRedirect($this->storeManager->getStore()->getBaseUrl().'customer/account/login')->sendResponse();
               $this->messageManager->addErrorMessage(__($message." Contact Your Administrator"));return;
            }else{
            $this->TwoFAUtility->log_debug("MoCustomer : execute: Inline registration :otp send succesfully ");
            $redirect_url=$redirect_url."&message=".$message."&showdiv=showdiv";
         }
      }

}

         } else if("challenge"== $postValue['mopostoption']){
            $this->TwoFAUtility->log_debug("MoCustomer : execute: Inline registration: challenge ->send otp");
            $send_otp_response = $this->miniOrangeInline->pageFourChallenge($this->TwoFAUtility);
            $current_username = $this->TwoFAUtility->getSessionValue( 'mousername');
$r_status=$send_otp_response['status'];
$message=$send_otp_response['message'];

            if($r_status=='FAILED')
            {   $this->TwoFAUtility->log_debug("MoCustomer : execute: Inline registration :responce falied to send otp ");
               $this->TwoFAUtility->log_debug("MoCustomer : execute: Inline registration: response failed");
               $this->responseFactory->create()->setRedirect($this->storeManager->getStore()->getBaseUrl().'customer/account/login')->sendResponse();
               $this->messageManager->addErrorMessage(__($message." Contact Your Administrator"));return;
            }else{$this->TwoFAUtility->log_debug("MoCustomer : execute: Inline registration :otp send succesfully ");
            $redirect_url = isset( $postValue['redirect_to'] ) ? $postValue['redirect_to'] : $this->url->getCurrentUrl();
            if(isset($postValue['deleteSet']))
            {
               $redirect_url=$redirect_url."&deleteSet=deleteSet";
            }
            $redirect_url=$redirect_url."&message=".$message."&showdiv=showdiv";

         }
         } else if("movalotp"== $postValue['mopostoption']){
            $this->TwoFAUtility->log_debug("MoCustomer : execute:movalopt: validate otp ");
            $response = $this->miniOrangeInline->pageFourValidate($this->TwoFAUtility);

            if($response){
               $this->TwoFAUtility->log_debug("MoCustomer : execute: Otp validation succesful");
               $user = $this->getCustomerFromAttributes($this->TwoFAUtility->getSessionValue( 'mousername'));
               $this->customerSession->setCustomerAsLoggedIn($user);
               $redirect_url = $this->url->getUrl('customer/account');
            }
            else {
               $this->TwoFAUtility->log_debug("MoCustomer : execute: Otp validation failure");
               $current_username = $this->TwoFAUtility->getSessionValue( 'mousername');
               //throw new OtpValidateFailureException;
                  if(!isset($postValue['savestep']))
                  {
                     $redirect_url=$this->url->getCurrentUrl()."?mooption=invokeTFA&error=error";
                  }else{
                  if($postValue['savestep']=='OOS'){
                     $redirect_url=$this->url->getCurrentUrl()."/?mooption=invokeInline&step=OOSMethodValidation&error=error&deleteSet=deleteSet&showdiv=showdiv";
                  }
                  if($postValue['savestep']=='OOE'){

                     $redirect_url=$this->url->getCurrentUrl()."/?mooption=invokeInline&step=OOEMethodValidation&error=error&deleteSet=deleteSet";
                  }
                  if($postValue['savestep']=='OOSE')
                  {
                     $redirect_url=$this->url->getCurrentUrl()."/?mooption=invokeInline&step=OOSEMethodValidation&error=error&showdiv=showdiv&deleteSet=deleteSet&useremail=".$current_username;
                  }
                  if($postValue['savestep']=='GoogleAuthenticator')
                  {
                     $redirect_url=$this->url->getCurrentUrl()."/?mooption=invokeInline&step=GAMethodValidation&error=error&deleteSet=deleteSet";

                  }
               }



            }
         }
         $redirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
         $redirect->setUrl($redirect_url);
         return $redirect;
      } else {
         return $this->_pageFactory->create();
      }
   }


   public function getPhoneID_from_ContryCode($code){
      $countrycode = array(
         'AD'=>'376',
         'AE'=>'971',
         'AF'=>'93',
         'AG'=>'1268',
         'AI'=>'1264',
         'AL'=>'355',
         'AM'=>'374',
         'AN'=>'599',
         'AO'=>'244',
         'AQ'=>'672',
         'AR'=>'54',
         'AS'=>'1684',
         'AT'=>'43',
         'AU'=>'61',
         'AW'=>'297',
         'AZ'=>'994',
         'BA'=>'387',
         'BB'=>'1246',
         'BD'=>'880',
         'BE'=>'32',
         'BF'=>'226',
         'BG'=>'359',
         'BH'=>'973',
         'BI'=>'257',
         'BJ'=>'229',
         'BL'=>'590',
         'BM'=>'1441',
         'BN'=>'673',
         'BO'=>'591',
         'BR'=>'55',
         'BS'=>'1242',
         'BT'=>'975',
         'BW'=>'267',
         'BY'=>'375',
         'BZ'=>'501',
         'CA'=>'1',
         'CC'=>'61',
         'CD'=>'243',
         'CF'=>'236',
         'CG'=>'242',
         'CH'=>'41',
         'CI'=>'225',
         'CK'=>'682',
         'CL'=>'56',
         'CM'=>'237',
         'CN'=>'86',
         'CO'=>'57',
         'CR'=>'506',
         'CU'=>'53',
         'CV'=>'238',
         'CX'=>'61',
         'CY'=>'357',
         'CZ'=>'420',
         'DE'=>'49',
         'DJ'=>'253',
         'DK'=>'45',
         'DM'=>'1767',
         'DO'=>'1809',
         'DZ'=>'213',
         'EC'=>'593',
         'EE'=>'372',
         'EG'=>'20',
         'ER'=>'291',
         'ES'=>'34',
         'ET'=>'251',
         'FI'=>'358',
         'FJ'=>'679',
         'FK'=>'500',
         'FM'=>'691',
         'FO'=>'298',
         'FR'=>'33',
         'GA'=>'241',
         'GB'=>'44',
         'GD'=>'1473',
         'GE'=>'995',
         'GH'=>'233',
         'GI'=>'350',
         'GL'=>'299',
         'GM'=>'220',
         'GN'=>'224',
         'GQ'=>'240',
         'GR'=>'30',
         'GT'=>'502',
         'GU'=>'1671',
         'GW'=>'245',
         'GY'=>'592',
         'HK'=>'852',
         'HN'=>'504',
         'HR'=>'385',
         'HT'=>'509',
         'HU'=>'36',
         'ID'=>'62',
         'IE'=>'353',
         'IL'=>'972',
         'IM'=>'44',
         'IN'=>'91',
         'IQ'=>'964',
         'IR'=>'98',
         'IS'=>'354',
         'IT'=>'39',
         'JM'=>'1876',
         'JO'=>'962',
         'JP'=>'81',
         'KE'=>'254',
         'KG'=>'996',
         'KH'=>'855',
         'KI'=>'686',
         'KM'=>'269',
         'KN'=>'1869',
         'KP'=>'850',
         'KR'=>'82',
         'KW'=>'965',
         'KY'=>'1345',
         'KZ'=>'7',
         'LA'=>'856',
         'LB'=>'961',
         'LC'=>'1758',
         'LI'=>'423',
         'LK'=>'94',
         'LR'=>'231',
         'LS'=>'266',
         'LT'=>'370',
         'LU'=>'352',
         'LV'=>'371',
         'LY'=>'218',
         'MA'=>'212',
         'MC'=>'377',
         'MD'=>'373',
         'ME'=>'382',
         'MF'=>'1599',
         'MG'=>'261',
         'MH'=>'692',
         'MK'=>'389',
         'ML'=>'223',
         'MM'=>'95',
         'MN'=>'976',
         'MO'=>'853',
         'MP'=>'1670',
         'MR'=>'222',
         'MS'=>'1664',
         'MT'=>'356',
         'MU'=>'230',
         'MV'=>'960',
         'MW'=>'265',
         'MX'=>'52',
         'MY'=>'60',
         'MZ'=>'258',
         'NA'=>'264',
         'NC'=>'687',
         'NE'=>'227',
         'NG'=>'234',
         'NI'=>'505',
         'NL'=>'31',
         'NO'=>'47',
         'NP'=>'977',
         'NR'=>'674',
         'NU'=>'683',
         'NZ'=>'64',
         'OM'=>'968',
         'PA'=>'507',
         'PE'=>'51',
         'PF'=>'689',
         'PG'=>'675',
         'PH'=>'63',
         'PK'=>'92',
         'PL'=>'48',
         'PM'=>'508',
         'PN'=>'870',
         'PR'=>'1',
         'PT'=>'351',
         'PW'=>'680',
         'PY'=>'595',
         'QA'=>'974',
         'RO'=>'40',
         'RS'=>'381',
         'RU'=>'7',
         'RW'=>'250',
         'SA'=>'966',
         'SB'=>'677',
         'SC'=>'248',
         'SD'=>'249',
         'SE'=>'46',
         'SG'=>'65',
         'SH'=>'290',
         'SI'=>'386',
         'SK'=>'421',
         'SL'=>'232',
         'SM'=>'378',
         'SN'=>'221',
         'SO'=>'252',
         'SR'=>'597',
         'ST'=>'239',
         'SV'=>'503',
         'SY'=>'963',
         'SZ'=>'268',
         'TC'=>'1649',
         'TD'=>'235',
         'TG'=>'228',
         'TH'=>'66',
         'TJ'=>'992',
         'TK'=>'690',
         'TL'=>'670',
         'TM'=>'993',
         'TN'=>'216',
         'TO'=>'676',
         'TR'=>'90',
         'TT'=>'1868',
         'TV'=>'688',
         'TW'=>'886',
         'TZ'=>'255',
         'UA'=>'380',
         'UG'=>'256',
         'US'=>'1',
         'UY'=>'598',
         'UZ'=>'998',
         'VA'=>'39',
         'VC'=>'1784',
         'VE'=>'58',
         'VG'=>'1284',
         'VI'=>'1340',
         'VN'=>'84',
         'VU'=>'678',
         'WF'=>'681',
         'WS'=>'685',
         'XK'=>'381',
         'YE'=>'967',
         'YT'=>'262',
         'ZA'=>'27',
         'ZM'=>'260',
         'ZW'=>'263'
     );
     $flipped_conutryCode=array_flip($countrycode);
     $key = array_search($code,$flipped_conutryCode);
     return $key ;
   }

}
