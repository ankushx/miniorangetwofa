<?php

namespace MiniOrange\TwoFA\Controller\Adminhtml\Account;

use Magento\Backend\App\Action\Context;
use MiniOrange\TwoFA\Helper\TwoFAConstants;
use MiniOrange\TwoFA\Helper\TwoFAMessages;
use MiniOrange\TwoFA\Controller\Actions\BaseAdminAction;
use MiniOrange\TwoFA\Helper\Curl;

/**
 * This class handles the action for endpoint: mospsaml/account/Index
 * Extends the \Magento\Backend\App\Action for Admin Actions which
 * inturn extends the \Magento\Framework\App\Action\Action class necessary
 * for each Controller class
 */
class Index extends BaseAdminAction
{
    private $options = array (
        'registerNewUser',
        'loginExistingUser',
        'removeAccount',
        'refresh',
    );

    private $registerNewUserAction;
    private $loginExistingUserAction;
    public function __construct(\Magento\Backend\App\Action\Context $context,
                                \Magento\Framework\View\Result\PageFactory $resultPageFactory,
                                \MiniOrange\TwoFA\Helper\TwoFAUtility $twofautility,
                                \Magento\Framework\Message\ManagerInterface $messageManager,
                                \Psr\Log\LoggerInterface $logger,
                                \MiniOrange\TwoFA\Controller\Actions\RegisterNewUserAction $registerNewUserAction,
                                \MiniOrange\TwoFA\Controller\Actions\LoginExistingUserAction $loginExistingUserAction)
    {
        //You can use dependency injection to get any class this observer may need.
        parent::__construct($context,$resultPageFactory,$twofautility,$messageManager,$logger);
        $this->registerNewUserAction = $registerNewUserAction;
        $this->loginExistingUserAction = $loginExistingUserAction;
    }


    /**
     * The first function to be called when a Controller class is invoked.
     * Usually, has all our controller logic. Returns a view/page/template
     * to be shown to the users.
     *
     * This function gets and prepares all our SP config data from the
     * database. It's called when you visis the moasaml/account/Index
     * URL. It prepares all the values required on the SP setting
     * page in the backend and returns the block to be displayed.
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */
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

            Curl::submit_to_magento_team($userEmail, 'Installed Successfully-Account Tab', $values,$magentoVersion);
            $this->twofautility->setStoreConfig(TwoFAConstants::SEND_EMAIL,1);
            $this->twofautility->flushCache() ;
        }

        try{
            $params = $this->getRequest()->getParams();  //get params
            if($this->isFormOptionBeingSaved($params)) // check if form options are being saved
            {
                $keys 			= array_values($params);
                $operation 		= array_intersect($keys,$this->options);
                if(count($operation) > 0) {  // route data and proccess
                    $this->_route_data(array_values($operation)[0],$params);
                    $this->twofautility->flushCache();
                }
                $this->twofautility->reinitConfig();
            }

        }catch(\Exception $e){
            $this->messageManager->addErrorMessage($e->getMessage());
			$this->logger->debug($e->getMessage());
        }
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu(TwoFAConstants::MODULE_DIR.TwoFAConstants::MODULE_BASE);
        $resultPage->addBreadcrumb(__('Account Settings'), __('Account Settings'));
        $resultPage->getConfig()->getTitle()->prepend(__(TwoFAConstants::MODULE_TITLE));
        return $resultPage;
    }


    /**
	 * Route the request data to appropriate functions for processing.
	 * Check for any kind of Exception that may occur during processing
	 * of form post data. Call the appropriate action.
	 *
	 * @param $op refers to operation to perform
	 * @param $params
	 */
	private function _route_data($op,$params)
	{
		switch ($op)
		{
			case $this->options[0]:
				$this->registerNewUserAction->setRequestParam($params)
                    ->execute();						                    break;
            case $this->options[1]:
				$this->loginExistingUserAction->setRequestParam($params)
                     ->execute();                                           break;
            case $this->options[2]:
				$this->goBackToRegistrationPage(); 						    break;
            case $this->options[3]:
				$this->goRefresh(); 						                break;

		}
	}

    /**
     * Is the user allowed to view the Account settings.
     * This is based on the ACL set by the admin in the backend.
     * Works in conjugation with acl.xml
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed(TwoFAConstants::MODULE_DIR.TwoFAConstants::MODULE_ACCOUNT);
    }

    private function goRefresh()
    {
        $customer_key= $this->twofautility->getStoreConfig(TwoFAConstants::CUSTOMER_KEY);
        $api_key= $this->twofautility->getStoreConfig(TwoFAConstants::API_KEY);
        if($customer_key==NULL || $api_key==NULL){
            return false;
        }
         Curl::get_email_sms_transactions($customer_key,$api_key);
    }

    private function goBackToRegistrationPage()
    {
        $this->twofautility->setStoreConfig(TwoFAConstants::CUSTOMER_EMAIL,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::CUSTOMER_KEY,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::API_KEY,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::INVOKE_INLINE_REGISTERATION,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::TOKEN ,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::CUSTOMER_PHONE,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::REG_STATUS,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::TXT_ID,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::MODULE_TFA ,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::ACTIVE_METHOD ,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::INVOKE_INLINE_REGISTERATION,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::LK_NO_OF_USERS,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::LK_VERIFY,NULL);
    }


}
