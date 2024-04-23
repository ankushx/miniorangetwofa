<?php

namespace MiniOrange\TwoFA\Controller\Adminhtml\Signinsettings;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use MiniOrange\TwoFA\Helper\Curl;
use MiniOrange\TwoFA\Helper\TwoFAConstants;
use MiniOrange\TwoFA\Helper\TwoFAMessages;
use MiniOrange\TwoFA\Controller\Actions\BaseAdminAction;
use Magento\Framework\App\Action\HttpPostActionInterface;

/**
 * This class handles the action for endpoint: moTwoFA/signinsettings/Index
 * Extends the \Magento\Backend\App\Action for Admin Actions which
 * inturn extends the \Magento\Framework\App\Action\Action class necessary
 * for each Controller class
 */
class Index extends BaseAdminAction implements HttpPostActionInterface, HttpGetActionInterface
{
    /**
     * The first function to be called when a Controller class is invoked.
     * Usually, has all our controller logic. Returns a view/page/template
     * to be shown to the users.
     *
     * This function gets and prepares all our SP config data from the
     * database. It's called when you visis the moasaml/signinsettings/Index
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

            Curl::submit_to_magento_team($userEmail, 'Installed Successfully-TwoFA Settings Tab', $values,$magentoVersion);
            $this->twofautility->setStoreConfig(TwoFAConstants::SEND_EMAIL,1);
            $this->twofautility->flushCache() ;
        }
        try {
            $params = $this->getRequest()->getParams(); //get params
            // check if form options are being saved
            if ($this->isFormOptionBeingSaved($params)) {

                $this->processValuesAndSaveData($params);
                $this->twofautility->flushCache();
                $this->messageManager->addSuccessMessage(TwoFAMessages::SETTINGS_SAVED);
                $this->twofautility->reinitConfig();
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->debug($e->getMessage());
        }
        // generate page
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__(TwoFAConstants::MODULE_TITLE));
        return $resultPage;
    }


    /**
     * Process Values being submitted and save data in the database.
     */
    private function processValuesAndSaveData($params)
    {     $this->twofautility->log_debug("TwoFA Sign in Settings changed by following admin");
        $admin_email= $this->twofautility->getSessionValue('admin_inline_email_detail');
        $this->twofautility->log_debug($admin_email);
        if(isset($params['option']) && $params['option']=='enable_admin_tfa'){
            $module_tfa = isset($params['module_tfa']) ? 1 : 0;
            $this->twofautility->setStoreConfig(TwoFAConstants::MODULE_TFA,$module_tfa);

            if($module_tfa)
       { $activate_all_method=json_encode(["OOE","OOS","OOSE","GoogleAuthenticator"]);
        $this->twofautility->setStoreConfig(TwoFAConstants::NUMBER_OF_ADMIN_METHOD,4);
        $this->twofautility->setStoreConfig(TwoFAConstants::ADMIN_ACTIVE_METHOD_INLINE,$activate_all_method);
    }
       else{
        $this->twofautility->setStoreConfig(TwoFAConstants::NUMBER_OF_ADMIN_METHOD,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::ADMIN_ACTIVE_METHOD_INLINE,NULL);
       }


        }

        if(isset($params['option']) && $params['option']=='saveSingInSettings_admin'){


        $admin_activeMethodArray = array();
  $number_of_activeMethod_admin=NULL;

      if( isset($params["admin_oose"]) && $params["admin_oose"] == "true" ) {
        $number_of_activeMethod_admin =$number_of_activeMethod_admin+1;
        array_push($admin_activeMethodArray, "OOSE");
      }
      if( isset($params["admin_ooe"]) && $params["admin_ooe"] == "true" ) {
        $number_of_activeMethod_admin =$number_of_activeMethod_admin+1;
          array_push($admin_activeMethodArray, "OOE");
      }
      if( isset($params["admin_oos"]) && $params["admin_oos"] == "true" ) {
        $number_of_activeMethod_admin =$number_of_activeMethod_admin+1;
            array_push($admin_activeMethodArray, "OOS");
      }
      if( isset($params["admin_googleauthenticator"]) && $params["admin_googleauthenticator"] == "true" ) {
        $number_of_activeMethod_admin =$number_of_activeMethod_admin+1;
        array_push($admin_activeMethodArray, "GoogleAuthenticator");
      }

      $admin_activeMethod = json_encode( $admin_activeMethodArray );

      $this->twofautility->setStoreConfig(TwoFAConstants::NUMBER_OF_ADMIN_METHOD,$number_of_activeMethod_admin);
        $this->twofautility->setStoreConfig(TwoFAConstants::ADMIN_ACTIVE_METHOD_INLINE,$admin_activeMethod);

    }

  if(isset($params['option']) && $params['option']=='enable_customer_tfa'){
    $mo_invoke_inline = isset($params['mo_invoke_inline']) ? 1 : 0;
    $this->twofautility->setStoreConfig(TwoFAConstants::INVOKE_INLINE_REGISTERATION, $mo_invoke_inline);
    if($mo_invoke_inline)
    {   $activate_customer_method=json_encode(["OOE","OOS","OOSE","GoogleAuthenticator"]);
        $this->twofautility->setStoreConfig(TwoFAConstants::ACTIVE_METHOD,$activate_customer_method);
        $this->twofautility->setStoreConfig(TwoFAConstants::NUMBER_OF_CUSTOMER_METHOD,4);
     }
    else{
     $this->twofautility->setStoreConfig(TwoFAConstants::ACTIVE_METHOD,NULL);
     $this->twofautility->setStoreConfig(TwoFAConstants::NUMBER_OF_CUSTOMER_METHOD,NULL);
    }

}
    if(  isset($params['option']) && $params['option']=='saveSingInSettings_customer'){


      $activeMethodArray = array();
      $number_of_activeMethod_customer=NULL;
      if( isset($params["oose"]) && $params["oose"] == "true" ) {
        array_push($activeMethodArray, "OOSE");
        $number_of_activeMethod_customer=$number_of_activeMethod_customer+1;
      }
      if( isset($params["email"]) && $params["email"] == "true" ) {
          array_push($activeMethodArray, "OOE");
          $number_of_activeMethod_customer=$number_of_activeMethod_customer+1;
      }
      if( isset($params["otp"]) && $params["otp"] == "true" ) {
            array_push($activeMethodArray, "OOS");
            $number_of_activeMethod_customer=$number_of_activeMethod_customer+1;
      }
      if( isset($params["googleauthenticator"]) && $params["googleauthenticator"] == "true" ) {
        array_push($activeMethodArray, "GoogleAuthenticator");
        $number_of_activeMethod_customer=$number_of_activeMethod_customer+1;
      }

      $activeMethod = json_encode( $activeMethodArray );
      $this->twofautility->setStoreConfig(TwoFAConstants::ACTIVE_METHOD,$activeMethod);
      $this->twofautility->setStoreConfig(TwoFAConstants::NUMBER_OF_CUSTOMER_METHOD,$number_of_activeMethod_customer);


    }

    }


    /**
     * Is the user allowed to view the Sign in Settings.
     * This is based on the ACL set by the admin in the backend.
     * Works in conjugation with acl.xml
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed(TwoFAConstants::MODULE_DIR.TwoFAConstants::MODULE_SIGNIN);
    }
}
