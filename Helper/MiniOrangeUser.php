<?php
/**
 * @package     Joomla.miniorangetfa
 * @subpackage  Application
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
*/
namespace MiniOrange\TwoFA\Helper;

use MiniOrange\TwoFA\Helper\TwoFAConstants;
use MiniOrange\TwoFA\Helper\TwoFAMessages;
use MiniOrange\TwoFA\Helper\Curl;

class MiniOrangeUser{
    private $userInfoData;

    public function challenge($username,$TwoFAUtility, $authType=NULL, $isConfigure=false) {
       $user_name;
        $TwoFAUtility->log_debug("MiniOrangeUser.php : execute: Challenge");
          if($authType=="MICROSOFT AUTHENTICATOR" || $authType=="AUTHY AUTHENTICATOR" || $authType=="LASTPASS AUTHENTICATOR"|| $authType=="DUO AUTHENTICATOR")
               {
                   $authType="GOOGLE AUTHENTICATOR";
               }
               $row = $TwoFAUtility->getMoTfaUserDetails('miniorange_tfa_users', $username );

               $userdata= $this->userInfoData;
               $customerKeys = $TwoFAUtility->getCustomerKeys( false );
               $customerKey = $customerKeys['customer_key'];
               $apiKey      = $customerKeys['apiKey'];
               $authCodes   = array('OOE'=>'EMAIL','OOS'=>'SMS','OOSE'=>'SMS AND EMAIL','KBA'=>'KBA');
               $phone = isset( $row[0]['phone'] ) ? str_replace(" ","",$row[0]['phone']) : "";
               $email = isset( $row[0]['email'] ) ? str_replace(" ","",$row[0]['email']) : "";
               $countrycode = isset( $row[0]['countrycode'] ) ? str_replace(" ","",$row[0]['countrycode']) : "";
               $user_name =$username;
             
               if($userdata!=NULL)
               {
                $phone = isset($userdata['phone'] ) ? str_replace(" ","",$userdata['phone']) : "";
               $email = isset($userdata['email'] ) ? str_replace(" ","",$userdata['email']) : "";
               $countrycode = isset( $userdata['countrycode'] ) ? str_replace(" ","",$userdata['countrycode']) : "";
               $user_name= isset( $userdata['username']) ? $userdata['username']: '';
               }
               $customer_inline= $TwoFAUtility->getSessionValue(TwoFAConstants::CUSTOMER_INLINE);
               if($customer_inline){
                $phone=$TwoFAUtility->getSessionValue(TwoFAConstants::CUSTOMER__PHONE);
                $email=$TwoFAUtility->getSessionValue(TwoFAConstants::CUSTOMER__EMAIL);
                $countrycode=$TwoFAUtility->getSessionValue(TwoFAConstants::CUSTOMER_COUNTRY_CODE);
                $user_name=$TwoFAUtility->getSessionValue(TwoFAConstants::CUSTOMER_USERNAME);
               }
               $admin_inline= $TwoFAUtility->getSessionValue(TwoFAConstants::ADMIN_IS_INLINE);
               if($admin_inline){
                $phone=$TwoFAUtility->getSessionValue(TwoFAConstants::ADMIN__PHONE);
                $email=$TwoFAUtility->getSessionValue(TwoFAConstants::ADMIN__EMAIL);
                $countrycode=$TwoFAUtility->getSessionValue(TwoFAConstants::ADMIN_COUNTRY_CODE);
                $user_name=$TwoFAUtility->getSessionValue(TwoFAConstants::ADMIN_USERNAME);
               }
               $phone = $countrycode.$phone;

               if($authType=='OOS'){
                $phone_set= $phone;
               $email_set= '';
               $TwoFAUtility->log_debug("MiniOrangeUser.php : execute: Challenge:phone set");
               }if($authType=='OOE'){
                $phone_set= '';
                $email_set= $email;
                $TwoFAUtility->log_debug("MiniOrangeUser.php : execute: Challenge:email set");
               }if($authType=='OOSE'){
                $phone_set= $phone;
               $email_set= $email;
               $TwoFAUtility->log_debug("MiniOrangeUser.php : execute: Challenge:email and phone set");
               }

              if($isConfigure)
               {

                   $fields = array (
                       'customerKey' => $customerKey,
                       'username' => '',
                       'phone' => $phone_set,
                       'email' =>  $email_set,
                       'authType' => $authCodes[$authType],
                       'transactionName' => $TwoFAUtility->getTransactionName()
                   );
               }
               else{
                   $fields = array (
                     'customerKey' => $customerKey,
                     'username' =>  $user_name,
                     'transactionName' => $TwoFAUtility->getTransactionName(),
                     'authType'=>$authType
                   );
               }

               $urls = $TwoFAUtility->getApiUrls();
               $url=$urls['challange'];
               return Curl::challenge($customerKey,$apiKey,$url,$fields);
    }

    function mo2f_update_userinfo($TwoFAUtility,$email,$authType, $phone='') {
        $TwoFAUtility->log_debug("MiniOrangeUser.php : execute:mo2f_update_userinfo");
        $customerKeys= $TwoFAUtility->getCustomerKeys();
        $customerKey = $customerKeys['customer_key'];
        $apiKey      = $customerKeys['apiKey'];
        $authCodes   = array('OOE'=>'EMAIL','OOS'=>'SMS','OOSE'=>'SMS AND EMAIL','KBA'=>'KBA','google'=>'GOOGLE AUTHENTICATOR','MA'=>'MICROSOFT AUTHENTICATOR','AA'=>'AUTHY AUTHENTICATOR','LPA'=>'LASTPASS AUTHENTICATOR','DUO'=>'Duo AUTHENTICATOR');
        $fields            = array(
            'customerKey'            => $customerKey,
            'username'               => $email,
            'transactionName'        => $TwoFAUtility->getTransactionName(),
        );
        if($authType!=''){
            if( $authType === "GoogleAuthenticator" ) {
                $fields['authType'] = "Google Authenticator";
            } else {
                $fields['authType']=$authCodes[$authType];
            }
        }
        if($phone!=''){
            $fields['phone']=$phone;
        }

        $urls = $TwoFAUtility->getApiUrls();
        $url=$urls['update'];
       return Curl::update($customerKey,$apiKey,$url,$fields);
    }

    function mo_create_user($TwoFAUtility, $email, $authType, $phone='') {
        $TwoFAUtility->log_debug("MiniOrangeUser.php : execute:mo_create_user");
        $customerKeys= $TwoFAUtility->getCustomerKeys();
        $customerKey = $customerKeys['customer_key'];
        $apiKey      = $customerKeys['apiKey'];
        $authCodes   = array('OOE'=>'EMAIL','OOS'=>'SMS','OOSE'=>'SMS AND EMAIL','KBA'=>'KBA','google'=>'GOOGLE AUTHENTICATOR','MA'=>'MICROSOFT AUTHENTICATOR','AA'=>'AUTHY AUTHENTICATOR','LPA'=>'LASTPASS AUTHENTICATOR','DUO'=>'Duo AUTHENTICATOR');
        $fields            = array(
            'customerKey'            => $customerKey,
            'username'               => $email,
            'transactionName'        => $TwoFAUtility->getTransactionName(),
        );
        if($authType!=''){
            if( $authType === "GoogleAuthenticator" ) {
                $fields['authType'] = "Google Authenticator";
            } else {
                $fields['authType']=$authCodes[$authType];
            }
        }
        if($phone!=''){
            $fields['phone']=$phone;
        }

        $urls = $TwoFAUtility->getApiUrls();
        $url = $urls['createUser'];
        return Curl::update($customerKey,$apiKey,$url,$fields);
    }

    public function validate($username,$token,$authType,$TwoFAUtility, $answers = NULL,$isConfiguring=false) {
        $TwoFAUtility->log_debug("MiniOrangeUser.php : execute: validate");
        $customerKeys= $TwoFAUtility->getCustomerKeys( true );
        $customerKey = $customerKeys['customer_key'];
        $apiKey      = $customerKeys['apiKey'];
        $row=$TwoFAUtility->getMoTfaUserDetails('miniorange_tfa_users',$username);
        $userdata= $this->userInfoData;

        if($userdata!=NULL){
            $transactionID=$userdata['transactionId'];
            $user_name=$userdata['username'];
         
        }elseif(is_array( $row ) && sizeof( $row ) > 0 ){
            $transactionID=$row[0]['transactionId'];
        }
        $customer_inline= $TwoFAUtility->getSessionValue(TwoFAConstants::CUSTOMER_INLINE);
               if($customer_inline){
                $transactionID=$TwoFAUtility->getSessionValue(TwoFAConstants::CUSTOMER_TRANSACTIONID);
                
               }
             
               $admin_inline= $TwoFAUtility->getSessionValue(TwoFAConstants::ADMIN_IS_INLINE);
               if($admin_inline){
                $transactionID=$TwoFAUtility->getSessionValue(TwoFAConstants::ADMIN_TRANSACTIONID);
               }
        $authCodes   = array('OOE'=>'EMAIL','OOS'=>'SMS','OOSE'=>'SMS AND EMAIL');
      
        if($isConfiguring){
            $fields = array (
                'customerKey' => $customerKey,
                'txId' => $transactionID,
                'token' => str_replace(" ","",$token),
            );
        }
        else{
            $fields = array (
                'customerKey' => $customerKey,
                'username' => $username,
                'txId' => $transactionID,
                'token' => str_replace(" ","",$token),
                'authType' =>array_key_exists($authType, $authCodes)?$authCodes[$authType]:$authType ,
                'answers' => $answers
            );
        }

        $urls = $TwoFAUtility->getApiUrls();
        $url=$urls['validate'];
   
     return Curl::validate($customerKey,$apiKey,$url,$fields);

    }

    public function setUserInfoData($userInfoData)
    {
        $this->userInfoData = $userInfoData;
        return $this;
    }
}