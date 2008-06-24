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
 * Patterns are matched with preg_match/preg_replace, with an implicit start 
 * (^) character and end ($) character.  preg_replace is run to transform
 * the matched string before passing it to the controller's URL dispatcher.
 *
 * Controllers classes are assumed to be <prefix><controller>, where prefix is 
 * config item 'csf.dispatch.controller_prefix', and the containing file will be
 * looked for at <dir>/<controller>.php, where dir is the config item
 * 'csf.dispatch.controller_dir' and controller is the lowercase of the 
 * controller name.
 */
class Dispatch
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

        foreach ( $routes as $pattern => $route )
        {
            $controller = $route[0];
            $rewrite = $route[1];


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

        // Method doesn't exist? 404!
        if ( !method_exists($this, $method) )
        {
            // Controller-specific 404?
            if ( !method_exists($this, 'error404') )
            {
                $this->error404($url);
            }
            else
            {
                // Fallback to Dispatch::error404()
                Dispatch::error404(get_class($this), $url);
            }
        }
        else
        {
            // Method exists - call it!
            call_user_func_array(array($this, $method), $urlparts);
        }
    }
}

?>
