<?php
/**
 * CodeScape Framework - Controller classes
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2009, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 * @link        http://codescape.net/csf/doc/dispatch/
 */


/**
 * Controller class
 *
 * This controller class is designed to accelerate MVC-style application
 * development on top of CSF.  It does this firstly by implementing the 
 * "method/args" part of "controller/method/args", and secondly by extending
 * CSF_Module to provide access to other modules via "$this->module".
 *
 * Unlike most other CSF libraries (which have no CSF dependencies), 
 * CSF_Controller depends directly on CSF itself and the CSF_Dispatch library,
 * and will not work without them.  For this reason, it is distributed as a 
 * separate library to allow CSF_Dispatch to be used on its own.
 */
class CSF_Controller extends CSF_Module
{
    /**
     * Dispatch URI
     *
     * Dispatch the URI using the "method/args" pattern.
     *
     * @param   string  $uri
     * @return  mixed
     */
    public function dispatch_uri($uri)
    {
        return CSF_dispatch_method_args($this, $uri);
    }
}
