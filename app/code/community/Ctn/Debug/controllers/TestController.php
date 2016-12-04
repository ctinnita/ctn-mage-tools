<?php

class Ctn_Debug_TestController extends Mage_Core_Controller_Front_Action
{    
    // http://mground.dev/ctndebug/test/logbacktrace
    public function logBacktraceAction()
    {
        Mage::helper('ctn_debug')->logBacktrace();
    }
}