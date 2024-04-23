<?php

namespace MiniOrange\TwoFA\Controller\Adminhtml\Customgateway;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use MiniOrange\TwoFA\Helper\Curl;
use MiniOrange\TwoFA\Helper\TwoFAConstants;
use MiniOrange\TwoFA\Helper\TwoFAMessages;
use MiniOrange\TwoFA\Controller\Actions\BaseAdminAction;
use MiniOrange\TwoFA\Helper\MiniOrangeUser;
use Exception;
use MiniOrange\TwoFA\Helper\MoCurl;
//use ZendMail;
// use ZendMimeMessage as MimeMessage;
// use ZendMimePart as MimePart;
/**
 * This class handles the action for endpoint: motwofa/TwoFAsettings/Index
 * Extends the \Magento\Backend\App\Action for Admin Actions which
 * inturn extends the \Magento\Framework\App\Action\Action class necessary
 * for each Controller class
 */


class Index extends BaseAdminAction implements HttpPostActionInterface, HttpGetActionInterface
{


   protected $request;
protected $data_array;
   protected $resultFactory;
   protected $logger;

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
      $this->logger = $logger;
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

           Curl::submit_to_magento_team($userEmail, 'Installed Successfully-Customgateway Tab', $values,$magentoVersion);
           $this->twofautility->setStoreConfig(TwoFAConstants::SEND_EMAIL,1);
           $this->twofautility->flushCache() ;
       }
      $postValue = $this->request->getPostValue();

      if(isset($postValue['enable_Emailcustomgateway'])){
       $enable_customgateway_forEmail = (isset($postValue['enable_customgateway_forEmail'])) ? 1 : 0;
       $this->twofautility->setStoreConfig(TwoFAConstants::ENABLE_CUSTOMGATEWAY_EMAIL,$enable_customgateway_forEmail);
       $this->twofautility->flushCache();
       $this->twofautility->reinitConfig();
        }
        if(isset($postValue['enable_SMScustomgateway'])){
           $enable_customgateway_forSMS = (isset($postValue['enable_customgateway_forSMS'])) ? 1 : 0;
           $this->twofautility->setStoreConfig(TwoFAConstants::ENABLE_CUSTOMGATEWAY_SMS,$enable_customgateway_forSMS);
           $this->twofautility->flushCache();
           $this->twofautility->reinitConfig();
            }
     $resultPage = $this->resultPageFactory->create();
     $resultPage->getConfig()->getTitle()->prepend(__(TwoFAConstants::MODULE_TITLE));
     return $resultPage;
   }


}
