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
    protected $_request_uri;


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
            array_walk_recursive($this->_get, 'csf_stripslashes_array_walk');
            array_walk_recursive($this->_post, 'csf_stripslashes_array_walk');
            array_walk_recursive($this->_cookie, 'csf_stripslashes_array_walk');
            array_walk_recursive($this->_request, 'csf_stripslashes_array_walk');
        }
    }
}


/**
 * stripslashes() array_walk function
 *
 * This function is a wrapper around stripslashes() suitable for use with
 * array_walk() and array_walk_recursive().  It modifies only string items,
 * and modifies them in-place.
 *
 * @param   mixed   $item       The item to modify
 * @param   mixed   $key        The key of the item
 */
function csf_stripslashes_array_walk(&$item, $key)
{
    if (is_string($item))
        $item = stripslashes($item);
}
