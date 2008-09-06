<?php
/**
 * CodeScape Framework - Request module
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2008, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * Request module
 *
 * Provide more intuitive access to various request variables and properties.
 */
class Request extends CSF_Module implements CSF_IRequest
{
    /*
     * Constructor
     *
     * Populate the object with data.  Fix escaped data in the GET, POST and 
     * COOKIE arrays (and therefore REQUEST too).
     */
    public function __construct($fix_magic_quotes = false)
    {
        parent::__construct();

        // Magic with references - fix the damage that magic_quotes_gpc=On does.
        if ( $fix_magic_quotes && get_magic_quotes_gpc() == 1 )
        {
            $a = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);
            foreach ( $a as &$array )
                foreach ( $array as &$value )
                    $value = stripslashes($value);
        }
    }

    /**
     * Get HTTP GET variable(s)
     *
     * @param   mixed   $vars
     * @return  mixed
     * @see     CSF_IRequest::get
     */
    public function get($vars)
    {
        if ( is_array($vars) )
        {
            $ret = array();
            foreach ( $vars as $key )
                $ret[$key] = $_GET[$key];
            return $ret;
        }
        else
        {
            return $_GET[$vars];
        }
    }

    /**
     * Get HTTP POST variable(s)
     *
     * @param   mixed   $vars
     * @return  mixed
     * @see     CSF_IRequest::get
     */
    public function post($vars)
    {
        if ( is_array($vars) )
        {
            $ret = array();
            foreach ( $vars as $key )
                $ret[$key] = $_POST[$key];
            return $ret;
        }
        else
        {
            return $_POST[$vars];
        }
    }

    /**
     * Get HTTP REQUEST variable(s)
     *
     * @param   mixed   $vars
     * @return  mixed
     * @see     CSF_IRequest::get
     */
    public function request($vars)
    {
        if ( is_array($vars) )
        {
            $ret = array();
            foreach ( $vars as $key )
                $ret[$key] = $_REQUEST[$key];
            return $ret;
        }
        else
        {
            return $_REQUEST[$vars];
        }
    }

    /**
     * Get HTTP COOKIE variable(s)
     *
     * @param   mixed   $vars
     * @return  mixed
     * @see     CSF_IRequest::get
     */
    public function cookie($vars)
    {
        if ( is_array($vars) )
        {
            $ret = array();
            foreach ( $vars as $key )
                $ret[$key] = $_COOKIE[$key];
            return $ret;
        }
        else
        {
            return $_COOKIE[$vars];
        }
    }

    /**
     * Get IP address of client
     *
     * @return  string
     */
    public function ip_address()
    {
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Get user agent string
     *
     * Get the user agent string, optionally trimmed to <var>$length</var>
     * characters.
     *
     * @param   int     $length     User agent string length
     * @return  string
     * @todo    Move this to CSF_IRequest?
     */
    public function user_agent($length = 0)
    {
        return $length 
            ? substr($_SERVER['HTTP_USER_AGENT'], 0, $length)
            : $_SERVER['HTTP_USER_AGENT'];
    }

    /**
     * Check the HTTP request method
     *
     * @param   string  $method     The request method to check for
     * @return  boolean <var>$method</var> == request method
     */
    public function is_method($method)
    {
        return strtolower($method) == $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Check if the connection is secure HTTP
     *
     * @return  boolean
     * @todo    Move this to CSF_IRequest?
     */
    public function is_secure()
    {
        return isset($_SERVER['HTTPS']);
    }
}

?>
