<?php
/**
 * CodeScape Framework - Session library
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2009, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 * @link        http://codescape.net/csf/doc/session/
 */

/**
 * Session class
 *
 * The CSF_Session class only sets PHP session options, rather than implementing
 * an alternative session storage.  It also provides a simple mechanism for 
 * using an alternative session storage.
 *
 * @link    http://codescape.net/csf/doc/session/#csf_session
 */
class CSF_Session
{
    /** @var    array   Options array */
    protected $_options = array(
        // Session name
        'name' => 'csf_session',
        // Session lifetime (in minutes)
        'lifetime' => 1,
    );


    /**
     * Constructor
     *
     * Set the necessary options and start the session.
     */
    public function __construct($options = array())
    {
        // Use supplied options
        $this->_options = array_merge($this->_options, $options);

        // Set session options
        session_cache_expire($this->_options['lifetime']);
        session_name($this->_options['name']);

        session_start();
        //$_SESSION['foo'] = 'bar';
    }
}
