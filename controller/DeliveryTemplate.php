<?php
/**  
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 * 
 * Copyright (c) 2015 (original work) Open Assessment Technologies SA;
 *               
 * 
 */

namespace oat\taoDeliveryTemplate\controller;

use tao_actions_SaSModule;
use tao_helpers_Request;
use Renderer;
use tao_helpers_Uri;
use tao_models_classes_dataBinding_GenerisFormDataBinder;
use taoDelivery_actions_form_Delivery;
use oat\taoDeliveryTemplate\model\DeliveryTemplateService;
use core_kernel_classes_Class;

/**
 * Delivery template management controller
 *
 * @author Open Assessment Technologies SA
 * @package taoDeliveryTemplate
 * @license GPL-2.0
 *
 */
class DeliveryTemplate extends tao_actions_SaSModule {

    /**
     * constructor: initialize the service and the default data
     *
     * @access public
     * @author CRP Henri Tudor - TAO Team - {@link http://www.tao.lu}
     * @return Delivery
     */
    public function __construct()
    {
        parent::__construct();
        
        // the service is initialized by default
        $this->service = DeliveryTemplateService::singleton();
        $this->defaultData();
    }
    
    /**
     * (non-PHPdoc)
     * @see tao_actions_SaSModule::getClassService()
     */
    protected function getClassService()
    {
        return $this->service;
    }
    
    /**
     * Edit a delivery template instance
     *
     * @access public
     * @author CRP Henri Tudor - TAO Team - {@link http://www.tao.lu}
     * @return void
     */
    public function editTemplate()
    {
        $clazz = $this->getCurrentClass();
        $delivery = $this->getCurrentInstance();
    
        $formContainer = new taoDelivery_actions_form_Delivery($clazz, $delivery);
        $myForm = $formContainer->getForm();
    
        $myForm->evaluate();
    
        if ($myForm->isSubmited()) {
            if ($myForm->isValid()) {
                $propertyValues = $myForm->getValues();
    
                // then save the property values as usual
                $binder = new tao_models_classes_dataBinding_GenerisFormDataBinder($delivery);
                $delivery = $binder->bind($propertyValues);
    
                // edit process label:
                $this->service->onChangeLabel($delivery);
    
                $this->setData("selectNode", tao_helpers_Uri::encode($delivery->getUri()));
                $this->setData('message', __('Delivery saved'));
                $this->setData('reload', true);
            }
        }
    
        $this->setData('contentForm', $this->getContentForm());
    
        $this->setData('uri', tao_helpers_Uri::encode($delivery->getUri()));
        $this->setData('classUri', tao_helpers_Uri::encode($clazz->getUri()));
    
        $this->setData('hasContent', !is_null($this->service->getContent($delivery)));
    
        $this->setData('formTitle', __('Delivery properties'));
        $this->setData('myForm', $myForm->render());
    
        if (\common_ext_ExtensionsManager::singleton()->isEnabled('taoCampaign')) {
            $this->setData('campaign', \taoCampaign_helpers_Campaign::renderCampaignTree($delivery));
        }
        $this->setView('DeliveryTemplate/editDelivery.tpl');
    }
    
    /**
     *
     */
    protected function getContentForm()
    {
        $delivery = $this->getCurrentInstance();
        $content = $this->service->getContent($delivery);
        if (!is_null($content)) {
            // Author
            $modelImpl = $this->service->getImplementationByContent($content);
            return $modelImpl->getAuthoring($content);
        } else {
            // select Model
            $options = array();
            foreach ($this->service->getAllContentClasses() as $class) {
                $options[$class->getUri()] = $class->getLabel();
            }
            $renderer = new Renderer(DIR_VIEWS.'templates'.DIRECTORY_SEPARATOR.'DeliveryTemplate'.DIRECTORY_SEPARATOR.'ContentForm.tpl');
            $renderer->setData('models', $options);
            $renderer->setData('saveUrl', _url('setContentClass', null, null, array('uri' => $delivery->getUri())));
            return $renderer->render();
        }
    }
    
    /**
     * Set the model to use for the delivery
     */
    public function setContentClass()
    {
        $delivery = $this->getCurrentInstance();
        $contentClass = new core_kernel_classes_Class($this->getRequestParameter('model'));
    
        if (is_null($this->service->getContent($delivery))) {
            $content = $this->service->createContent($delivery, $contentClass);
            $success = true;
        } else {
            common_Logger::w('Content already defined, cannot be replaced');
            $success = false;
        }
        echo json_encode(array(
            'success' => $success
        ));
    }
    
    /**
     * Delete a delivery template
     *
     * @access public
     * @author CRP Henri Tudor - TAO Team - {@link http://www.tao.lu}
     * @return void
     * @throws Exception
     */
    public function delete()
    {
        if (! tao_helpers_Request::isAjax()) {
            throw new Exception("wrong request mode");
        }
    
        $deleted = $this->service->deleteInstance($this->getCurrentInstance());
    
        echo json_encode(array(
            'deleted' => $deleted
        ));
    }
    
    /**
     * get the compilation view
     *
     * @access public
     * @author CRP Henri Tudor - TAO Team - {@link http://www.tao.lu}
     * @return void
     */
    public function initCompilation(){
    
        $delivery = $this->getCurrentInstance();
    
        //init the value to be returned
        $deliveryData=array();
    
        $deliveryData["uri"] = $delivery->getUri();
    
        //check if a wsdl contract is set to upload the result:
        $resultServer = $this->service->getResultServer($delivery);
        $deliveryData['resultServer'] = $resultServer;
    
        $deliveryData['tests'] = array();
        if(!empty($resultServer)){
            	
            //get the tests list from the delivery id: likely, by parsing the deliveryContent property value
            //array of resource, test set
            $tests = array();
            $tests = $this->service->getRelatedTests($delivery);
            	
            foreach($tests as $test){
                $deliveryData['tests'][] = array(
                    "label" => $test->getLabel(),
                    "uri" => $test->getUri()
                );//url encode maybe?
            }
        }
    
        echo json_encode($deliveryData);
    }
}