<?php
/**
 * CodeScape Framework - Module interfaces
 *
 * The method used for dependency checking is to see if particular modules 
 * implement particular interfaces, since this allows core modules to be 
 * overridden without breaking other core modules which depend upon them.  This
 * file contains the interfaces for use when overriding core modules.  The 
 * interfaces are automatically loaded by CSF.
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2008, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * Encryption module interface
 */
interface CSF_IEncrypt
{
    /**
     * Constructor
     * @param   string  $key        Encryption key
     */
    public function __construct($key);

    /**
     * Encrypt value
     * @param   mixed   $data       Simple value to encrypt
     * @return  string  Non-printable binary!
     */
    public function encrypt($data);
    /**
     * Decrypt value
     * @param   string  $data       Binary data to decrypt
     * @return  mixed
     */
    public function decrypt($data);

    /**
     * Encrypt and base64 encode
     * @param   mixed   $data       Simple value to encrypt
     * @return  string  Base64-encoded encrypted value
     */
    public function encode($data);
    /**
     * Base64 decode and decrypt
     * @param   string  $data       Base64-encoded encrypted value
     * @return  mixed   Decrypted decoded value
     */
    public function decode($data);

    /**
     * Serialise, encrypt, encode
     * @param   mixed   $data       Serialisable data to encrypt
     * @return  string  Base64-encoded encrypted data
     */
    public function encode_var($data);
    /**
     * Decode, decrypt, unserialise
     * @param   string  $data       Base64-encoded encrypted data
     * @return  mixed   Decrypted decoded data
     */
    public function decode_var($data);
}

/**
 * Request module interface
 */
interface CSF_IRequest
{
    /**
     * Get HTTP GET variable(s)
     * @param   mixed   $vars       Identifier(s) of variable(s) to return
     * @return  mixed   Associative array of identifiers to values if 
     *                  <var>$vars</var> is an array, otherwise the value at
     *                  the specified identifier.
     */
    public function get($vars);
    /**
     * Get HTTP POST variable(s)
     * @param   array   $vars
     * @return  array
     * @see     get
     */
    public function post($vars);
    /**
     * Get HTTP REQUEST (GET and POST combined) variable(s)
     * @param   array   $vars
     * @return  array
     * @see     get
     */
    public function request($vars);
    /**
     * Get HTTP COOKIE variable(s)
     * @param   array   $vars
     * @return  array
     * @see     get
     */
    public function cookie($vars);

    /**
     * Get IP address of client
     * @return  string
     */
    public function ip_address();
    /**
     * Check the HTTP request method
     * @param   string  $method     The request method to check for
     * @return  boolean <var>$method</var> == request method
     */
    public function is_method($method);
}

/**
 * Session module interface
 */
interface CSF_ISession
{
    /**
     * Get session variable
     * @param   string  $name
     * @return  mixed
     */
    public function get($name);
    /**
     * Set session variable
     * @param   string  $name
     * @param   mixed   $value
     */
    public function set($name, $value);
    
    /**
     * Get session variable
     * @param   string  $name
     * @return  mixed
     */
    public function __get($name);
    /**
     * Set session variable
     * @param   string  $name
     * @param   mixed   $value
     */
    public function __set($name, $value);
    /**
     * Check if session variable is set
     * @param   string  $name
     * @return  boolean
     */
    public function __isset($name);
    /**
     * Remove session variable
     * @param   string  $name
     */
    public function __unset($name);

    /**
     * Save session data
     */
    public function save();
}

/**
 * Controller interface
 */
interface CSF_IController
{
    /**
     * URL dispatch
     * @param   string  $url    The URL to process
     */
    public function dispatch_url($url);
}

/**
 * View/templating interface
 * @todo    Add get_context()?
 */
interface CSF_IView
{
    /**
     * Get context variable
     * @param   string  $name
     * @return  mixed
     */
    public function get($name);
    /**
     * Set context variable
     * @param   string  $name
     * @param   mixed   $value
     */
    public function set($name, $value);
    /**
     * Get context variable
     * @param   string  $name
     * @return  mixed
     */
    public function __get($name);
    /**
     * Set context variable
     * @param   string  $name
     * @param   mixed   $value
     */
    public function __set($name, $value);
    /**
     * Check if context variable exists
     * @param   string  $name
     * @return  boolean
     */
    public function __isset($name);
    /**
     * Remove context variable
     * @param   string  $name
     */
    public function __unset($name);

    /**
     * Render a template
     * @param   string  $template   Template path relative to template directory
     * @param   array   $context    Override context data
     * @return  string  The processed template output
     */
    public function render($template, $context = null);
}
?>
