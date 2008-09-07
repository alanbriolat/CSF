<?php
/**
 * CodeScape Framework - URL dispatcher
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2008, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * Delegating URL dispatcher
 *
 * Dispatch uses an array of route definitions to dispatch a request to a 
 * particular controller.  A route definition maps a "match" rule to a
 * controller class name and a "rewrite" rule.
 * <ul>
 *  <li><b>Match ('blog/(\d+)'):</b> A regular expression to match the 
 *      beginning of a URL.</li>
 *  <li><b>Rewrite ('view/$1'):</b> An expression to replace the part of the URL
 *      matched by the "match" regular expression - trailing parts of the URL 
 *      are left unchanged.</li>
 *  <li><b>Controller ('BlogController')</b>: The controller class to dispatch
 *      the rewritten URL to - the dispatch_url() method is called on the 
 *      controller class with the rewritten URL as its argument.</li>
 * </ul>
 *
 * Dispatch is described as a delegating URL dispatcher because its method of 
 * routing is to delegate the translated URL to a controller, instead of doing 
 * all routing itself.  This means that each controller can implement its own 
 * routing behaviour for the rest of the URL.
 *
 * An example:
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
 * In the above example, a visit to '/blog/12' would be internally rewritten as
 * a call to dispatch_url('view/12') on the BlogController class.  It is 
 * important to note that a call to '/blog/12/addcomment' in this example would 
 * be rewritten to dispatch_url('view/12/addcomment') - this is explained below.
 *
 * Routes are tried first to last, so earlier route definitions take precedence
 * over later ones.
 *
 * URL matching and rewriting is done using {@link preg_match preg_match} and 
 * {@link preg_replace preg_replace}, so the "match" and "rewrite" rules follow 
 * the syntax expected by those functions.  The "anchored" modifier is added to 
 * the regular expression so patterns match only against the beginning of the 
 * string.  <b>Rules should not include a leading '/'!</b>  Anything trailing 
 * the matched string is preserved.
 *
 * Configuration:
 * <ul>
 *  <li>csf.dispatch.controller_dir - path to the directory containing the 
 *      controller classes (default: 'controllers')</li>
 *  <li>csf.dispatch.controller_prefix - prefix that converts a controller name
 *      into its class name (default: '')</li>
 *  <li>csf.dispatch.routes - dispatcher route definitions.</li>
 * </ul>
 *
 * Controller classes must be at <dir>/<classname>.php where <dir> is set by 
 * csf.dispatch.controller_dir and <classname> is the lowercase of the actual
 * class name (which itself is composed of the prefix and the controller name).
 */
class Dispatch extends CSF_Module
{
    // Dependencies
    protected $_depends = array(
        array('name' => 'view', 'interface' => 'CSF_IView'),
    );

    /*
     * Constructor
     *
     * Work out what the original path being dispatched is.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /*
     * Dispatch URL
     *
     * This (hopefully) can also be used for "soft redirects"
     */
    public function dispatch_url($url = null)
    {
        $url = is_null($url) ? self::get_request_uri() : $url;
        $url = ltrim($url, '/');

        $routes = CSF::config('csf.dispatch.routes');

        // Find a matching route
        foreach ( $routes as $pattern => $route )
        {
            // Sanitise pattern for use as regex
            $pattern = str_replace('#', '\#', $pattern);
            if ( preg_match("#$pattern#A", $url) )
            {
                // Find/load the controller class
                $class = self::load_controller($route['controller']);
                // Initialise the controller
                $c = new $class();
                // Dispatch the URL
                $c->dispatch_url(preg_replace(
                    "#$pattern#A", $route['rewrite'], $url));
                // Successfully dispatched
                return;
            }
        }

        // Didn't find a route - error
        Dispatch::error404('(none)', $url);
    }

    /*
     * Get actual URI (i.e. what was redirected)
     *
     * Parts of this are inspired by CodeIgniter's "Router" class.  Tries both
     * $_SERVER variables and environment variables.  Which variable is used can
     * be overriden with csf.dispatch.uri_protocol being set to something other 
     * than 'auto'.
     */
    public static function get_request_uri()
    {
        $protocol = strtoupper(CSF::config('csf.dispatch.uri_protocol', 'AUTO'));
        if ($protocol == 'AUTO')
        {
            // Is there a PATH_INFO variable?
            $path = isset($_SERVER['PATH_INFO'])
                ? ltrim($_SERVER['PATH_INFO'], '/')
                : ltrim(@getenv('PATH_INFO'), '/');
            if ($path != '')
                return $path;

            // No PATH_INFO - try ORIG_PATH_INFO
            $path = isset($_SERVER['ORIG_PATH_INFO'])
                ? ltrim($_SERVER['ORIG_PATH_INFO'], '/')
                : ltrim(@getenv('ORIG_PATH_INFO'), '/');
            if ($path != '')
                return $path;
            
            // Neither exist - return an empty URI
            return '';
        }
        else
        {
            return isset($_SERVER[$protocol])
                ? ltrim($_SERVER[$protocol])
                : ltrim(@getenv($protocol));
        }
    }

    /*
     * Attempt to load a controller class, returning the full class name 
     * (including prefix) on success, and causing an error on failure.
     */
    protected static function load_controller($controller)
    {
        // Class name
        $prefix = CSF::config('csf.dispatch.controller_prefix', '');
        $class = $prefix.$controller;

        // Class path
        $path = CSF::config('csf.dispatch.controller_dir');
        $filepath = $path.DS.strtolower($controller).'.php';

        // Try to load the class if it doesn't exist
        if (!class_exists($class))
            if (file_exists($filepath))
                include($filepath);

        // See if the class is still missing
        if (class_exists($class))
        {
            // Check that the class implements the Controller interface
            $r = new ReflectionClass($class);
            if ($r->implementsInterface('CSF_IController'))
                return $class;
            else
                trigger_error("Dispatch: Controller class $class does not "
                    . "implement CSF_IController interface", E_USER_ERROR);
        }
        else
        {
            // Class still missing - give up and error
            trigger_error("Dispatch: unable to load controller $controller "
                . "($class) from $filepath", E_USER_ERROR);
        }
    }

    /*
     * Error 404
     *
     * Parses the template set in csf.dispatch.template_404 (defaults to 
     * error404.php)
     */
    public static function error404($controller, $url)
    {
        echo CSF::CSF()->view->render(
            CSF::config('csf.dispatch.template_404', 'error404.php'), 
            array('controller' => $controller, 'url' => $url));
    }
}

/*
 * Controller superclass
 *
 * It is not essential to extend Controllers from this class, however all 
 * controllers must implement the CSF_IController interface.
 */
class Controller implements CSF_IController
{
    /*
     * Default dispatch action
     *
     * Treat the URL passed to the controller as method/arg1/arg2, resulting in
     * calling $this->method(arg1, arg2).  If the URL is "empty", call 
     * $this->index().  This is probably what most controllers will want to do.
     *
     * Tighter control over method calls is given by specifically excluding 
     * dispatch_url, and only allowing dispatch to public methods.
     */
    public function dispatch_url($url)
    {
        // Split the URL
        $urlparts = explode('/', trim($url, '/'));

        // If the URL is empty, use index(), otherwise use the first part
        if ($urlparts[0] == '')
            $method = 'index';
        else
            $method = array_shift($urlparts);

        // Exclude 'dispatch_url'
        if (strtolower($method) == 'dispatch_url')
            $this->error404($url);

        // Check that the method both exists and is public
        $r = new ReflectionClass($this);
        if ($r->hasMethod($method) && $r->getMethod($method)->isPublic())
        {
            // Method exists - call it
            call_user_func_array(array($this, $method), $urlparts);
        }
        else
        {
            // Not found
            $this->error404($url);
        }
    }

    /*
     * Default 404 error - use Dispatch::error404()
     */
    protected function error404($url)
    {
        Dispatch::error404(get_class($this), $url);
    }
}

?>
