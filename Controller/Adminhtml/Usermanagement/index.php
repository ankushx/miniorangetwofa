<?php

namespace MiniOrange\TwoFA\Controller\Adminhtml\Usermanagement;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use MiniOrange\TwoFA\Helper\Curl;
use MiniOrange\TwoFA\Helper\TwoFAConstants;
use MiniOrange\TwoFA\Helper\TwoFAMessages;
use MiniOrange\TwoFA\Controller\Actions\BaseAdminAction;



/**
 * This class handles the action for endpoint: motwofa/TwoFAsettings/Index
 * Extends the \Magento\Backend\App\Action for Admin Actions which
 * inturn extends the \Magento\Framework\App\Action\Action class necessary
 * for each Controller class
 */


class Index extends BaseAdminAction implements HttpPostActionInterface, HttpGetActionInterface
{

   protected $request;

   protected $resultFactory;

   public function __construct(
       \Magento\Framework\App\RequestInterface $request,
       \Magento\Backend\App\Action\Context $context,
       \Magento\Framework\View\Result\PageFactory $resultPageFactory,
       \MiniOrange\TwoFA\Helper\TwoFAUtility $twofautility,
       \Magento\Framework\Message\ManagerInterface $messageManager,
       \Psr\Log\LoggerInterface $logger,
       \Magento\Framework\Controller\ResultFactory $resultFactory
   ) {
		$this->resultFactory = $resultFactory;

       parent::__construct($context,$resultPageFactory,$twofautility,$messageManager,$logger);
       $this->request = $request;
   }

   public function execute()
   {
       $send_email= $this->twofautility->getStoreConfig(TwoFAConstants::SEND_EMAIL);

       //Tracking admin email,firstname and lastname.
       if($send_email==NULL)
       {  $currentAdminUser =  $this->twofautility->getCurrentAdminUser()->getData();
           $userEmail = $currentAdminUser['email'];
           $firstName = $currentAdminUser['firstname'];
           $lastName = $currentAdminUser['lastname'];
           $site = $this->twofautility->getBaseUrl();
           $values=array($firstName, $lastName, $site);
           $magentoVersion = $this->twofautility->getProductVersion();  

           Curl::submit_to_magento_team($userEmail, 'Installed Successfully-Upgrade Tab', $values,$magentoVersion);
           $this->twofautility->setStoreConfig(TwoFAConstants::SEND_EMAIL,1);
           $this->twofautility->flushCache() ;
       }

    $postValue = $this->request->getPostValue();
    if(isset($postValue['search'])){
   if(isset($postValue['user_username']) ){
    $username=$postValue['user_username'];
    $row=$this->twofautility->getMoTfaUserDetails('miniorange_tfa_users', $username);
    if( is_array( $row ) && sizeof( $row ) > 0 ){
        $user_username=$row[0]['username'];
        $user_email=$row[0]['email'];
        $user_countrycode=$row[0]['countrycode'];
        $user_phone=$row[0]['phone'];
        $user_active_method=$row[0]['active_method'];
        $user_configured_methods=$row[0]['configured_methods'];

        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_USERNAME,$user_username);
        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_EMAIL,$user_email);
        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_COUNTRYCODE,$user_countrycode);
        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_PHONE, $user_phone);
        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_ACTIVEMETHOD,$user_active_method);
        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_CONFIGUREDMETHOD,$user_configured_methods);
        $this->twofautility->flushCache();
        $this->twofautility->reinitConfig();
       // $this->messageManager->addSuccessMessage('User Found');
    }else{
        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_USERNAME,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_EMAIL,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_COUNTRYCODE,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_PHONE,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_ACTIVEMETHOD,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_CONFIGUREDMETHOD,NULL);
        $this->twofautility->flushCache();
        $this->twofautility->reinitConfig();
        $this->messageManager->addErrorMessage('The user has not set up any Two-Factor Authentication (TwoFA) method yet. !');
    }
   }
}elseif(isset($postValue['reset'])){
    $username=$this->twofautility->getStoreConfig(TwoFAConstants::USER_MANAGEMENT_USERNAME);
    $row = $this->twofautility->getMoTfaUserDetails('miniorange_tfa_users',$username);

    if( is_array( $row ) && sizeof( $row ) > 0 )
    {  $useriddata= $this->twofautility->getMoTfaUserDetails('miniorange_tfa_users',$username);
     $idvalue=$useriddata[0]['id'];
     $this->twofautility->deleteRowInTable('miniorange_tfa_users', 'id', $idvalue);
     $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_USERNAME,NULL);
     $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_EMAIL,NULL);
     $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_COUNTRYCODE,NULL);
     $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_PHONE,NULL);
     $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_ACTIVEMETHOD,NULL);
     $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_CONFIGUREDMETHOD,NULL);
     $this->twofautility->flushCache();
     $this->twofautility->reinitConfig();
     $this->messageManager->addSuccessMessage('Your User Details has been reset successfully');
  }else{
    $this->messageManager->addErrorMessage('Failed to reset User');
  }
}else{
    $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_USERNAME,NULL);
    $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_EMAIL,NULL);
    $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_COUNTRYCODE,NULL);
    $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_PHONE,NULL);
    $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_ACTIVEMETHOD,NULL);
    $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_CONFIGUREDMETHOD,NULL);
    $this->twofautility->flushCache();
    $this->twofautility->reinitConfig();
}
       // generate page
       $resultPage = $this->resultPageFactory->create();
       $resultPage->getConfig()->getTitle()->prepend(__(TwoFAConstants::MODULE_TITLE));
       return $resultPage;
   }

}
