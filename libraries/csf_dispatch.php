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
 * csfDispatch is a delegating URI dispatcher.  The use of the word "delegating"
 * here refers to the fact that it does not force the way controllers must work,
 * instead just routing the request to the appropriate controller's dispatch 
 * function.  The advantage of this approach is that special controllers can
 * implement their own logic without having to rewrite/replace csfDispatch.
 *
 * Which controller to dispatch to is determined by a set of routes.  The
 * routes are defined as an associative array mapping from a regular expression
 * (to match the request URI against) to a controller name and rewrite rule.
 *
 * An example route array:
 * <code>
 * $routes = array(
 *      // View blog post by ID
 *      'blog/(\d+)' => array('controller' => 'BlogController',
 *                            'rewrite'    => 'view/$1'),
 *      // Other blog operations
 *      'blog/(.*)'  => array('controller' => 'BlogController',
 *                            'rewrite'    => '$1'),
 *      // Index page
 *      '.*'         => array('controller' => 'BlogController',
 *                            'rewrite'    => 'index'),
 * );
 * </code>
 *
 * In the above example, a visit to 'blog/12' would be dispatched as a call to
 * dispatch('view/12') on the BlogController controller.
 *
 * In the above example, a visit to '/blog/12' would be internally rewritten as
 * a call to dispatch_url('view/12') on the BlogController class.  It is 
 * important to note that a call to '/blog/12/addcomment' in this example would 
 * be rewritten to dispatch_url('view/12/addcomment').
 *
 * Some things to note about route processing:
 * <ul>
 *  <li> Routes are attempted first to last, so the first rule that matches a
 *       given request URI will be used </li>
 *  <li> URI matching and rewriting is done using {@link preg_match preg_match}
 *       and {@link preg_replace preg_replace}, so the syntax expected matches 
 *       that of those functions </li>
 *  <li> The "anchored" modifier is applied to the pattern so that it only 
 *       matches against the beginning of the string </li>
 *  <li> Whether or not URIs have a leading '/' should be consistent, and the
 *       routing rules should also conform to this (csfRequest::get_uri() always
 *       omits the leading '/') </li>
 * </ul>
 *
 * The "class_prefix" and "class_suffix" options define a prefix and suffix to
 * the controller name to create the class name, but are not applied to the
 * filename.  The file to load a controller class from is that with the same
 * name as the controller in the directory specified by the "controller_path"
 * option.  For example, using the following configuration:
 *
 * <code>
 * $conf['controller_path'] = 'controllers';
 * $conf['class_prefix'] = '';
 * $conf['class_suffix'] = 'Controller';
 * </code>
 *
 * would cause a request that is mapped to the "Blog" controller to attempt to 
 * load the "BlogController" class from "controllers/Blog.php".
 *
 * @todo    Documentation!
 */
class csfDispatch
{
    /** @var    array   Dispatcher options */
    protected $_options = array(
        'controller_path'   => '',
        'routes'            => array(),
        'class_prefix'      => '',
        'class_suffix'      => '',
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
     *
     * @return  mixed   The return value of dispatch() on the controller
     *
     * @throws  csfControllerNotFoundException
     * @throws  csfDispatchError404
     */
    public function dispatch($uri = '')
    {
        // Make sure there is no leading /
        $uri = ltrim($uri, '/');

        // Find a matching route
        foreach ($this->_options['routes'] as $pattern => $route)
        {
            // Sanitise pattern for use as regex
            $pattern = str_replace('#', '\#', $pattern);
            // Does this pattern match?
            if (preg_match("#$pattern#A", $uri))
            {
                // Make sure the controller class is loaded
                $class = $this->_load_controller($route['controller'], $uri);
                // Create an instance of the controller
                $c = new $class();
                // Dispatch the URI to the controller (and return the result)
                return $c->dispatch(preg_replace("#$pattern#A",
                                                 $route['rewrite'],
                                                 $uri));
            }
        }

        // If we made it this far, no route was matched
        throw new csfDispatchError404("No route found matching '$uri'");
    }


    /**
     * Load controller
     *
     * Attempt to load a controller class, returning the full class name on
     * success, and throwing an exception on failure.
     *
     * @param   string  $controller     Controller name
     * @param   string  $uri            Request URI (for error information)
     *
     * @return  string  The actual class name
     *
     * @throws  csfControllerNotFoundException
     */
    protected function _load_controller($controller, $uri)
    {
        // Class name
        $class = $this->_options['class_prefix']
                    .$controller.$this->_options['class_suffix'];

        // If the class has already been loaded, we're done
        if (class_exists($class)) return $class;

        // (Otherwise, let's try and load it ...)

        // File path
        $path = $this->_options['controller_path'].DIRSEP.$controller.'.php';

        // Attempt to include the file
        if (file_exists($path)) include $path;

        // If the class has been loaded now, we're done
        if (class_exists($class)) return $class;
        // ... otherwise, throw the exception
        throw new csfControllerNotFoundException($controller, $uri);
    }
}


/**
 * Controller base class
 *
 * The only real requirement for a controller is that it has the "dispatch"
 * method, which can take a single argument (the request URI after rewriting
 * by csfDispatch::dispatch()).  This controller implements this method in a
 * a generically useful way: the URI is split up on '/' characters, the first
 * part being the method to call and the rest being arguments to that method.
 * If the URI is empty, the method defaults to "index".
 *
 * The controller will only dispatch to methods that exist and are public - this
 * gives more control over the client-callable interface of a controller.
 */
class csfController
{
    /**
     * Default dispatch action
     *
     * Treat the URI passed to the controller as method/arg1/arg2, resulting in
     * calling $this->method(arg1, arg2).  If the URI is empty, call 
     * $this->index().  This is probably what most controllers will want to do.
     *
     * Tighter control over method calls is given by specifically excluding 
     * "dispatch", and only allowing dispatch to public methods.
     *
     * @param   string  $uri        The URI to dispatch
     *
     * @return  mixed   The return value of the called method
     */
    public function dispatch($uri = '')
    {
        // Split the URI
        $parts = explode('/', trim($uri, '/'));

        // If the URI is empty, use index(), otherwise use first part
        if ($parts[0] == '')
            $method = 'index';
        else
            $method = array_shift($parts);

        // Check that the method is valid and call it
        if ($method != 'dispatch' && in_array($method, get_class_methods($this)))
        {
            return call_user_func_array(array($this, $method), $parts);
        }
        else
        {
            throw new csfDispatchError404("Method $method not found while ".
                "attempting to serve '$uri'");
        }
    }
}


/** Controller not found */
class csfControllerNotFoundException extends Exception
{
    public function __construct($name, $uri)
    {
        parent::__construct("Controller $name not found while attempting to ".
            "dispatch '$uri'");
    }
}

/** Page not found (HTTP error 404) */
class csfDispatchError404 extends Exception
{
}
