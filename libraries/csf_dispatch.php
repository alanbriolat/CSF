<?php
/**
 * CodeScape Framework - Dispatch library
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2009, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */


/**
 * Dispatch class
 *
 * @todo    Documentation!
 */
class csfDispatch
{
    /** @var    array   Dispatcher options */
    protected $_options = array(
        'controller_path'   => '',
        'routes'            => array(),
    );


    /**
     * Constructor
     *
     * Store options, get ready to handle dispatch requests.
     *
     * @param   array   $options        Options array
     */
    public function __construct($options = array())
    {
        $this->_options = array_merge($this->_options, $options);
    }


    /**
     * Dispatch URI
     *
     * Perform URI rewriting and routing, dispatching the request to the correct
     * controller class.  It should be possible to use this method for "soft
     * redirects" too.
     *
     * @param   string  $uri        The URI to dispatch
     * @return  mixed   The return value of dispatch() on the controller
     */
    public function dispatch($uri == '')
    {
        // Make sure there is no leading /
        $uri = ltrim($uri, '/');

        // Find a matching route
        foreach ($this->_options['routes'] as $pattern => $route)
        {
            // Sanitise pattern for use as regex
            $pattern = str_replace('#', '\#', $pattern);
            // Does this pattern match?
            //if (preg_match('
        }
    }
}


/**
 * Controller base class
 *
 * @todo    Documentation!
 */
class csfController
{
}
