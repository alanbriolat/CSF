<?php
/**
 * CodeScape Framework - Dispatch library
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2009, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 * @link        http://codescape.net/csf/doc/dispatch/
 */


/**
 * Dispatch class
 *
 * @link    http://codescape.net/csf/doc/dispatch/#csf_dispatch
 */
class CSF_Dispatch
{
    /** @var    array   Dispatcher options */
    protected $_options = array(
        'controller_path'   => '',
        'routes'            => array(),
        'class_prefix'      => '',
        'class_suffix'      => '',
        'case_sensitive'    => true,
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
     * Perform URI matching, rewriting and routing to the correct controller.
     * The end result is calling dispatch_uri on an instance of the correct
     * controller and returning its return value.  If no route matches the URI,
     * CSF_Dispatch_NotFound is thrown.
     *
     * @see     _load_controller
     *
     * @param   string  $uri    The URI to dispatch
     * @return  mixed
     * @throws  CSF_Dispatch_NotFound
     */
    public function dispatch($uri)
    {
        // Make sure there is no leading /
        $uri = ltrim($uri, '/');

        // Find a matching route
        foreach ($this->_options['routes'] as $pattern => $route)
        {
            // Sanitise pattern for use as regex
            $pattern = '#'.str_replace('#', '\#', $pattern).'#A';
            if (!$this->_options['case_sensitive'])
            {
                $pattern .= 'i';
            }

            // Does the pattern match?
            if (preg_match($pattern, $uri))
            {
                // Load the controller class
                $class = $this->_load_controller($route['controller'], $uri);
                // Create an instance of the controller
                $c = new $class();
                // Dispatch the URI to the controller
                return $c->dispatch(preg_replace($pattern,
                                                 $route['rewrite'],
                                                 $uri));
            }
        }

        // If we made it this far, no route was matched
        throw new CSF_Dispatch_NotFound("No route matching '$uri'");
    }


    /**
     * Load controller
     *
     * Attempt to lead a controller class, returning the full class name on
     * success, or throwing CSF_Dispatch_NotFound on failure.
     *
     * The full class name is constructed from the controller name (or the last
     * part if the name contains forward slashes), prefixed and suffixed with 
     * the class_prefix and class_suffix option values respectively.
     *
     * The filename to attempt to load the controller from is the controller
     * name suffixed with ".php" and prefixed with the controller_path option
     * value.
     *
     * @param   string  $controller     Controller name
     * @param   string  $uri            Request URI (for error information)
     * @return  string
     * @throws  CSF_Dispatch_NotFound
     */
    protected function _load_controller($controller, $uri)
    {
        // Class name
        $class = $this->_options['class_prefix']
            .$controller.$this->_options['class_suffix'];

        // If the class already exists, we're done
        if (class_exists($class)) return $class;

        // File path to load from
        $path = $this->_options['controller_path'].DIRECTORY_SEPARATOR
            .$controller.'.php';

        // Attempt to include the file
        if (file_exists($path)) include $path;

        // Check if the class was loaded
        if (class_exists($class)) return $class;

        // If we made it this far, we failed to load the controller =(
        throw new CSF_Dispatch_NotFound("Failed to load controller "
            ."$controller ($class) while attempting to dispatch '$uri'");
    }
}


/**
 * Method-args URI dispatcher
 *
 * This URI dispatcher helps implement the "/controller/method/arg1/arg2" style
 * of URI routing.  Given a controller and request URI, it will split the URI
 * with the / character, use the first part as the method to call (defaulting to 
 * "index") and any remaining parts as arguments to the method.  For example:
 *
 * <code>
 * CSF_dispatch_method_args($con, 'foo/bar/baz');
 * // is equivalent to
 * $con->foo('bar', 'baz');
 * </code>
 *
 * The return value is the return value of the method that is called.  If the
 * method could not be called, CSF_Dispatch_NotFound is thrown.
 *
 * @link    http://codescape.net/csf/doc/dispatch/#csf_dispatch_method_args
 *
 * @param   mixed   $controller     Controller to call method on
 * @param   mixed   $uri            URI to use for method and args
 * @return  mixed
 * @throws  CSF_Dispatch_NotFound
 */
function CSF_dispatch_method_args($controller, $uri)
{
    // Split the URI
    $parts = explode('/', trim($uri, '/'));

    // If the URI is empty, default to index, otherwise use first part
    $method = $parts[0] == '' ? 'index' : array_shift($parts);

    // Check that the method is valid
    // Blacklist the dispatch_uri method to prevent infinite recursion, and
    // only allow calls to public methods.  get_class_methods must be used 
    // because method_exists and is_callable both return true for private and
    // protected methods.
    if ($method != 'dispatch_uri' 
        && in_array($method, get_class_methods($controller)))
    {
        return call_user_func_array(array($controller, $method), $parts);
    }
    else
    {
        throw new CSF_Dispatch_NotFound("Method '$method' not found while "
            . "attempting to dispatch '$uri'");
    }
}


/**
 * "Not Found" exception
 */
class CSF_Dispatch_NotFound extends Exception
{
}
