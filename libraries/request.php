<?php
/**
 * CodeScape Framework - Request library
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2009, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * Request class
 *
 * Gathers together everything useful that's request-related in one place.
 * (Optionally) fixes "magic quotes" mangling, obtains the request 
 * 
 * Provides a wrapper around request parameters such as the $_GET, $_POST, 
 * $_COOKIE and $_REQUEST superglobals, the request URI (before rewriting),
 * the request method, etc.  Performs useful operations on the data such as
 * undoing the damage of "magic quotes".
 */
class Request
{
    /** @var    array   Request options (initialised with defaults) */
    protected $_options = array(
        'preserve_original' => false,
        'fix_magic_quotes' => true,
    );
    /** @var    array   Cleaned $_GET variables */
    protected $_get = array();
    /** @var    array   Cleaned $_POST variables */
    protected $_post = array();
    /** @var    array   Cleaned $_COOKIE variables */
    protected $_cookie = array();
    /** @var    array   Cleaned $_REQUEST variables */
    protected $_request = array();
    /** @var    string  The request URI */
    protected $_uri;


    /**
     * Constructor
     *
     * Populate with request data. 
     *
     * @param   bool    $options            Request options
     */
    public function __construct($options = array())
    {
        // Use supplied options
        $this->_options = array_merge($this->_options, $options);

        // Modify the superglobals?
        if ($this->_options['preserve_original'])
        {
            // Deep copy the arrays (in PHP arrays are deep copied, but objects
            // are referenced - however these variables should have no objects 
            // in them!)
            $this->_get = $_GET;
            $this->_post = $_POST;
            $this->_cookie = $_COOKIE;
            $this->_request = $_REQUEST;
        }
        else
        {
            // Use references to the arrays
            $this->_get =& $_GET;
            $this->_post =& $_POST;
            $this->_cookie =& $_COOKIE;
            $this->_request =& $_REQUEST;
        }

        // Fix the damage done by magic_quotes_gpc=on ?
        if ($this->_options['fix_magic_quotes'] && get_magic_quotes_gpc() == 1)
        {
            array_walk_recursive($this->_get, 
                array('Request', '_stripslashes_array_walk'));
            array_walk_recursive($this->_post,
                array('Request', '_stripslashes_array_walk'));
            array_walk_recursive($this->_cookie,
                array('Request', '_stripslashes_array_walk'));
            array_walk_recursive($this->_request,
                array('Request', '_stripslashes_array_walk'));
        }

        // TODO: work out pre-rewrite request URI
    }


    /**
     * Get the pre-rewrite request URI
     *
     * @return  string
     */
    public function get_uri()
    {
        return $this->_uri;
    }


    /**
     * Get the user agent string
     *
     * @return  string
     */
    public function get_user_agent()
    {
        return $_SERVER['HTTP_USER_AGENT'];
    }


    /**
     * Get the user IP address
     *
     * @return  string
     */
    public function get_ip_address()
    {
        return $_SERVER['REMOTE_ADDR'];
    }


    /**
     * Get the HTTP request method as an uppercase string
     *
     * Sometimes the HTTP mode can end up supplied as a lowercase string, which
     * makes a "mode-checking" comparison an annoying combination of string
     * functions.  This always returns uppercase, so no need to worry.
     *
     * @return  string
     */
    public function get_method()
    {
        return strtoupper($_SERVER['REQUEST_METHOD']);
    }


    /**
     * Check if the HTTP request was using the specified method
     *
     * @param   string  $method     The method to check for
     *
     * @return  bool
     */
    public function is_method($method)
    {
        return strtoupper($method) == $this->get_method();
    }


    /**
     * Are we serving over HTTPS?
     *
     * @return  bool
     */
    public function is_secure()
    {
        return isset($_SERVER['HTTPS']);
    }


    /**
     * Get GET variable(s)
     *
     * @param   mixed   $keys       Variables to retrieve (or just return the
     *                              whole array if default/null)
     * @return  mixed   Single item or
     */
    public function GET($keys = null)
    {
        if (is_null($keys))
            return $this->_get;
        else
            return $this->_extract($this->_get, $keys);
    }


    /**
     * Get POST variable(s)
     *
     * @param   mixed   $keys       Variables to retrieve (or just return the
     *                              whole array if default/null)
     */
    public function POST($keys = null)
    {
        if (is_null($keys))
            return $this->_post;
        else
            return $this->_extract($this->_post, $keys);
    }


    /**
     * Get COOKIE variable(s)
     *
     * @param   mixed   $keys       Variables to retrieve (or just return the
     *                              whole array if default/null)
     */
    public function COOKIE($keys = null)
    {
        if (is_null($keys))
            return $this->_cookie;
        else
            return $this->_extract($this->_cookie, $keys);
    }


    /**
     * Get REQUEST variable(s)
     *
     * @param   mixed   $keys       Variables to retrieve (or just return the
     *                              whole array if default/null)
     */
    public function REQUEST($keys = null)
    {
        if (is_null($keys))
            return $this->_request;
        else
            return $this->_extract($this->_request, $keys);
    }


    /**
     * Generic function for extracting either a single or multiple values from
     * an associative array based on keys.
     *
     * If a single key is specified and the key doesn't exist, the usual
     * behaviour of accessing non-existant elements in PHP arrays will apply - 
     * a notice will be raised and null returned.
     *
     * If an array of keys is specified, and a particular key doesn't exist, it
     * will be omitted from the returned array.  If you need all keys to exist,
     * or want to supply default values, use:
     *
     *      $result = array_merge($defaults, $result_from_extract);
     *
     * @param   array   $array      The array to extract from
     * @param   mixed   $keys       The keys of the items to extract
     *
     * @return  mixed   Single item or associative array of items extracted
     */
    protected function _extract($array, $keys)
    {
        if (is_array($keys))
        {
            $ret = array();
            foreach ($keys as $k)
                if (isset($array[$k]))
                    $ret[$k] = $array[$k];
            return $ret;
        }
        else
        {
            return $array[$keys];
        }
    }


    /**
     * array_walk() compatible stripslashes() wrapper
     *
     * This function is a wrapper around stripslashes() suitable for use with
     * array_walk() and array_walk_recursive().  It modifies only string items,
     * and modifies them in-place.
     *
     * @param   mixed   $item       The item to modify
     * @param   mixed   $key        The key of the item
     */
    protected function _stripslashes_array_walk(&$item, $key)
    {
        if (is_string($item))
            $item = stripslashes($item);
    }
}
