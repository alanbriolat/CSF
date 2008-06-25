<?php
/*
 * CodeScape Framework - A simple, flexible PHP framework
 * Copyright (C) 2008, Alan Briolat <alan@codescape.net>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/*
 * Delegating URL dispatcher
 *
 * Use "route" definitions to dispatch a request to a particular controller. 
 * The controller then has responsibility for handling the rest of the URL.
 * 
 * Routes are of the form:
 *      match => array(controller, rewrite)
 * For example:
 *      'mysection/(.*)' => array('MyController', 'view/$1')
 *
 * A request for /mysection/foobar/baz would result in a call to 
 * MyController::dispatch_url('view/foobar/baz').
 *
 * Routes are tried from the first to the last, so earlier routes take
 * precedence over later routes.
 *
 * Patterns are matched with preg_match/preg_replace.  The "anchored" modifier
 * is used, so patterns match only from the beginning of the URL (excluding the 
 * leading /).  Note that anything that follows what the pattern matches will 
 * remain appended to the resulting URL that gets passed to the controller.
 *
 * Controllers classes are assumed to be <prefix><controller>, where prefix is 
 * config item 'csf.dispatch.controller_prefix', and the containing file will be
 * looked for at <dir>/<controller>.php, where dir is the config item
 * 'csf.dispatch.controller_dir' and controller is the lowercase of the 
 * controller name.
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
        $url = is_null($url) ? $this->get_request_uri() : $url;
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
                $class = $this->load_controller($route[0]);
                // Initialise the controller
                $c = new $class();
                // Dispatch the URL
                $c->dispatch_url(preg_replace("#$pattern#A", $route[1], $url));
                // Don't bother with any more routes!
                break;
            }
        }
    }

    /*
     * Get actual URI (i.e. what was redirected)
     *
     * At the moment just tries ORIG_PATH_INFO, which should be suitable for
     * all usage with Apache (tested on 2.2).  This needs to be expanded to be
     * more robust, using PATH_INFO, QUERY_STRING and even possibly REQUEST_URI.
     */
    protected function get_request_uri()
    {
        return ltrim($_SERVER['ORIG_PATH_INFO'], '/');
    }

    /*
     * Attempt to load a controller class, returning the full class name 
     * (including prefix) on success, and causing an error on failure.
     */
    protected function load_controller($controller)
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
        if (empty($urlparts))
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
