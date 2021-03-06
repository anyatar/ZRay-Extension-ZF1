<?php

class ZF1 {
    
    private $isExceptionSaved = false;

	public function storeDispatcherExit($context, &$storage) {
	    $Zend_Controller_Dispatcher_Standard = $context["this"];
	    $request = $context["functionArgs"][0];
	    
	    $action = $Zend_Controller_Dispatcher_Standard->getActionMethod($request);
	    $className = $this->getControllerName($Zend_Controller_Dispatcher_Standard, $request);
	    $storage['request'][] = array (  'action' => $action,
	                                   'controller' => $className,
	                                   'moduleClaaName' => $this->getModuleClassName($Zend_Controller_Dispatcher_Standard, $className));
	}
	
	public function storeFrontDispatchExit($context, &$storage) {
		$Zend_Controller_Front = $context["this"];
		$plugins = $Zend_Controller_Front->getPlugins();
		 
		foreach ($plugins as $plugin) {
		  $storage['plugin'][get_class($plugin)] = $plugin;
		}
	}
	
    public function storeViewExit($context, &$storage) {
    	$storage['view'][] = $context["functionArgs"];
    }
    
    public function storeViewHelperExit($context, &$storage) {
    	
    	$name = $context["functionArgs"][0];
    	$args = $context["functionArgs"][1];
    	
    	$Zend_View_Abstract = $context["this"];
    	$helper = $Zend_View_Abstract->getHelper($name);
    	$storage['activated_view_helper'][] = array('name' => $name,
    												'args' => $args,
    												'helperClass' => get_class($helper));
    	                                             //'helperObject' => $helper);
    }
    
    public function storeHandleErrorExit($context, &$storage) {
        
        $Zend_Controller_Plugin_ErrorHandler = $context["this"];
        //$storage['ErrorHandler'] = array();
        $this->getException($Zend_Controller_Plugin_ErrorHandler, $storage['ErrorHandler']);
    }

    
    public function storeRouterRewriteRequestExit($context, &$storage) {
       $storage['requestObject'][] = $context['returnValue'];
    }
    
	////////////// PRIVATES ///////////////////
	
    private function getException($Zend_Controller_Plugin_ErrorHandler, &$storage) {
        $response = $Zend_Controller_Plugin_ErrorHandler->getResponse();

        $reflection = new \ReflectionProperty('Zend_Controller_Plugin_ErrorHandler', '_isInsideErrorHandlerLoop');
        $reflection->setAccessible(true);
        $_isInsideErrorHandlerLoop = $reflection->getValue($Zend_Controller_Plugin_ErrorHandler);
        
        // check for an exception AND allow the error handler controller the option to forward
        if ($response->isException() && !$this->isExceptionSaved) {
            // Get exception information
            $error            = new ArrayObject(array(), ArrayObject::ARRAY_AS_PROPS);
            $exceptions       = $response->getException();
            $exception        = $exceptions[0];
            $exceptionType    = get_class($exception);
            $error->exception = $exception;
            switch ($exceptionType) {
                case 'Zend_Controller_Router_Exception':
                    if (404 == $exception->getCode()) {
                        $error->type = $Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ROUTE;
                    } else {
                        $error->type = $Zend_Controller_Plugin_ErrorHandler::EXCEPTION_OTHER;
                    }
                    break;
                case 'Zend_Controller_Dispatcher_Exception':
                    $error->type = $Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER;
                    break;
                case 'Zend_Controller_Action_Exception':
                    if (404 == $exception->getCode()) {
                        $error->type = $Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION;
                    } else {
                        $error->type = $Zend_Controller_Plugin_ErrorHandler::EXCEPTION_OTHER;
                    }
                    break;
                default:
                    $error->type = $Zend_Controller_Plugin_ErrorHandler::EXCEPTION_OTHER;
                    break;
               
            }
            
            $this->isExceptionSaved = true;
            $storage[] = array (  'exceptionType' => $exceptionType);
                                //'error' => $error,);
                                //'exceptions' => $exceptions);
                                //'exception' => $exception);
        }
    }
    
    private function getControllerName($Zend_Controller_Dispatcher_Standard, $request) {
        /**
         * Get controller class
         */
        if (!$Zend_Controller_Dispatcher_Standard->isDispatchable($request)) {
        	$controller = $request->getControllerName();
        	if (!$Zend_Controller_Dispatcher_Standard->getParam('useDefaultControllerAlways') && !empty($controller)) {
        		throw new Exception('Invalid controller specified (' . $request->getControllerName() . ')');
        	}
        
        	$className = $Zend_Controller_Dispatcher_Standard->getDefaultControllerClass($request);
        } else {
        	$className = $Zend_Controller_Dispatcher_Standard->getControllerClass($request);
        	if (!$className) {
        		$className = $Zend_Controller_Dispatcher_Standard->getDefaultControllerClass($request);
        	}
        }
        return $className;
    }
    
    private function getModuleClassName($Zend_Controller_Dispatcher_Standard, $className) {
        $moduleClassName = $className;
       
        
        $reflection = new \ReflectionProperty('Zend_Controller_Dispatcher_Standard', '_curModule');
        $reflection->setAccessible(true);
        $_curModule = $reflection->getValue($Zend_Controller_Dispatcher_Standard);
     
        
        $reflection = new \ReflectionProperty('Zend_Controller_Dispatcher_Standard', '_defaultModule');
        $reflection->setAccessible(true);
        $_defaultModule = $reflection->getValue($Zend_Controller_Dispatcher_Standard);
        
        if (($_defaultModule != $_curModule)
        		|| $Zend_Controller_Dispatcher_Standard->getParam('prefixDefaultModule'))
        {
        	$moduleClassName = $Zend_Controller_Dispatcher_Standard->formatClassName($_curModule, $className);
        }
        return $moduleClassName;
    }
}



$zre = new ZRayExtension("ZF1");
$zf1Storage = new ZF1();

// Allocate ZRayExtension for namespace "zf1"
$zre = new \ZRayExtension("zf1");

$zre->traceFunction("Zend_Controller_Dispatcher_Standard::dispatch",  function(){}, array($zf1Storage, 'storeDispatcherExit'));
$zre->traceFunction("Zend_Controller_Front::dispatch", function(){}, array($zf1Storage, 'storeFrontDispatchExit'));
$zre->traceFunction("Zend_View::_run",  function(){}, array($zf1Storage, 'storeViewExit'));
$zre->traceFunction("Zend_View_Abstract::__call", function(){}, array($zf1Storage, 'storeViewHelperExit'));
$zre->traceFunction("Zend_Controller_Plugin_ErrorHandler::_handleError", function(){}, array($zf1Storage, 'storeHandleErrorExit'));
$zre->traceFunction("Zend_Controller_Router_Rewrite::route", function(){} , array($zf1Storage, 'storeRouterRewriteRequestExit'));
