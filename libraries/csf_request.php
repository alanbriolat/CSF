<?php
/**
 * CodeScape Framework - Request library
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2009, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 * @link        http://codescape.net/csf/doc/request/
 */

/**
 * Request class
 *
 * Gathers together everything useful that's request-related in one place.
 * (Optionally) fixes "magic quotes" mangling, obtains the request 
 * 
 * Provides a wrapper around request parameters such as the $_GET, $_POST, 
 * $_COOKIE and $_REQUEST superglobals, the request URI (before rewriting),
 * the request method, client information, etc.  Performs useful operations
 * on the data such as undoing the damage of "magic quotes".
 *
 * @todo    Test get_uri() against mod_php, PHP CGI, lighttpd, IIS
 * @todo    Do any HTTPDs require the REQUEST_URI method of obtaining the
 *          originally requested URI?
 *
 * @link    http://codescape.net/csf/doc/request/#csf_request
 */
class CSF_Request
{
    /** @var    array   Request options (initialised with defaults) */
    protected $_options = array(
        // Copy superglobals rather than modifying them
        'preserve_original' => true,
        // Attempt to fix the damage caused my magic quotes, if enabled
        'fix_magic_quotes' => true,
        // Method for getting the URI (AUTO, GET, REQUEST_URI, or the name of
        // a $_SERVER variable)
        'uri_protocol' => 'AUTO',
        // The $_GET variable to use if uri_protocol=GET
        'uri_get_variable' => '_uri',
    );
    
    /** @var    string  The request URI */
    protected $_uri = null;

    /** @var    array   Cleaned $_GET variables */
    public $GET = array();
    
    /** @var    array   Cleaned $_POST variables */
    public $POST = array();
    
    /** @var    array   Cleaned $_COOKIE variables */
    public $COOKIE = array();
    
    /** @var    array   Cleaned $_REQUEST variables */
    public $REQUEST = array();


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
            $this->GET = $_GET;
            $this->POST = $_POST;
            $this->COOKIE = $_COOKIE;
            $this->REQUEST = $_REQUEST;
        }
        else
        {
            // Use references to the arrays
            $this->GET =& $_GET;
            $this->POST =& $_POST;
            $this->COOKIE =& $_COOKIE;
            $this->REQUEST =& $_REQUEST;
        }

        // Fix the damage done by magic_quotes_gpc=on ?
        if ($this->_options['fix_magic_quotes'] && get_magic_quotes_gpc() == 1)
        {
            array_walk_recursive($this->GET,
                array('CSF_Request', '_stripslashes_array_walk'));
            array_walk_recursive($this->POST,
                array('CSF_Request', '_stripslashes_array_walk'));
            array_walk_recursive($this->COOKIE,
                array('CSF_Request', '_stripslashes_array_walk'));
            array_walk_recursive($this->REQUEST,
                array('CSF_Request', '_stripslashes_array_walk'));
        }
    }


    /**
     * Get the specified GET variables
     *
     * Extract and return the specified GET variables, ignoring those that do
     * not exist.  If <var>$defaults</var> is supplied, merge the extracted 
     * variables with it to override the default values and create the final
     * output. 
     *
     * @see     _extract
     * @param   array   $keys       Keys of variables to extract
     * @param   array   $defaults   Default variable values
     * @return  array
     */
    public function extract_GET($keys, $defaults = array())
    {
        return array_merge($defaults, $this->_extract($this->GET, $keys));
    }


    /**
     * Get the specified POST variables
     *
     * Extract and return the specified POST variables, ignoring those that do
     * not exist.  If <var>$defaults</var> is supplied, merge the extracted 
     * variables with it to override the default values and create the final
     * output. 
     *
     * @see     _extract
     * @param   array   $keys       Keys of variables to extract
     * @param   array   $defaults   Default variable values
     * @return  array
     */
    public function extract_POST($keys, $defaults = array())
    {
        return array_merge($defaults, $this->_extract($this->POST, $keys));
    }


    /**
     * Get the specified COOKIE variables
     *
     * Extract and return the specified COOKIE variables, ignoring those that do
     * not exist.  If <var>$defaults</var> is supplied, merge the extracted 
     * variables with it to override the default values and create the final
     * output. 
     *
     * @see     _extract
     * @param   array   $keys       Keys of variables to extract
     * @param   array   $defaults   Default variable values
     * @return  array
     */
    public function extract_COOKIE($keys, $defaults = array())
    {
        return array_merge($defaults, $this->_extract($this->COOKIE, $keys));
    }


    /**
     * Get the specified REQUEST variables
     *
     * Extract and return the specified REQUEST variables, ignoring those that do
     * not exist.  If <var>$defaults</var> is supplied, merge the extracted 
     * variables with it to override the default values and create the final
     * output. 
     *
     * @see     _extract
     * @param   array   $keys       Keys of variables to extract
     * @param   array   $defaults   Default variable values
     * @return  array
     */
    public function extract_REQUEST($keys, $defaults = array())
    {
        return array_merge($defaults, $this->_extract($this->REQUEST, $keys));
    }


    /**
     * Get the pre-rewrite request URI
     *
     * @return  string
     */
    public function get_uri()
    {
        // Bail out if we've already worked this out
        if (!is_null($this->_uri)) return $this->_uri;

        // A lot of this method is copied from CodeIgniter, but I like to
        // think I do considerably less mangling and back-and-forth (not
        // to mention less abuse of regular expressions)
        switch (strtoupper($this->_options['uri_protocol']))
        {
        // Attempt to automatically find the originally requested path
        case 'AUTO':
            $path = trim((isset($_SERVER['PATH_INFO']) 
                            ? $_SERVER['PATH_INFO'] 
                            : @getenv('PATH_INFO')), '/ ');

            if (!empty($path))
            {
                $this->_uri = $path;
                break;
            }

            $path = trim((isset($_SERVER['ORIG_PATH_INFO']) 
                            ? $_SERVER['ORIG_PATH_INFO'] 
                            : @getenv('ORIG_PATH_INFO')), '/ ');
            
            if (!empty($path))
            {
                $this->_uri = $path;
                break;
            }

            // Didn't get anything
            $this->_uri = '';
            break;

        // Use a $_GET variable
        case 'GET':
            if (isset($this->GET[$this->_options['uri_get_variable']]))
            {
                $this->_uri = trim(
                    $this->GET[$this->_options['uri_get_variable']], '/ ');
            }
            else
            {
                $this->_uri = '';
            }
            break;

        // Parse $_SERVER['REQUEST_URI'] (not implemented)
        case 'REQUEST_URI':
            break;

        // Default: use the value of a specified variable (e.g. QUERY_STRING)
        default:
            $this->_uri = trim((isset($_SERVER[$this->_options['uri_protocol']])
                                    ? $_SERVER[$this->_options['uri_protocol']]
                                    : @getenv($this->_options['uri_protocol'])),
                               '/ ');
            break;
        }

        // Return whatever the result was
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
     * Generic function for extracting multiple values from an associative array
     *
     * @param   array   $array      The array to extract from
     * @param   array   $keys       The keys of the items to extract
     * @return  array
     */
    protected function _extract($array, $keys)
    {
        $ret = array();
        foreach ($keys as $k)
            if (array_key_exists($k, $array))
                $ret[$k] = $array[$k];
        return $ret;
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
