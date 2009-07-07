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
 * Persistent session data module
 *
 * Provide access to persistent session data.  Expects the following database
 * table layout:
 *
 *  session_id      | VARCHAR(40)       |       (1)
 *  ip_address      | VARCHAR(15)       |
 *  user_agent      | VARCHAR(96)       |
 *  last_activity   | INTEGER           |       (2)
 *  data            | TEXT              |
 *
 *  (1) SHA1 hash of a unique identifier.
 *
 *  (2) Yes, I realise this isn't Y2K38-safe, but datetime formats vary too much
 *      between DBMSs to do it any other way.
 */
class Session extends CSF_Module
{
    // Dependencies
    protected $_depends = array(
        array('name' => 'request', 'interface' => 'CSF_IRequest'),
    );

    // Cookie data
    protected $cookie = array();
    // Session data
    protected $data = array();
    // Cookie encryption
    protected $cookie_crypt = null;
    // Data encryption
    protected $data_crypt = null;
    // Configuration
    protected $config = array();
    // Session database
    protected $db = null;

    /*
     * Constructor
     *
     * Load session data, or create a new session.
     */
    public function __construct()
    {
        parent::__construct();

        // Local reference to configuration
        $config = $this->config = CSF::config('csf.session');
        // Local reference to session database
        $this->db =& $this->CSF->__get($config['db_module']);
        // Cookie encryption
        $this->cookie_crypt =& CSF::make_module('encrypt', 
            array($this->config['secret']));

        if ( isset($_COOKIE[$config['name']]) )
        {
            // Try to decode an existing cookie
            $this->cookie = $this->cookie_crypt->decode_var(
                $_COOKIE[$config['name']]);

            if ( empty($this->cookie) )
            {
                // If decoding failed, create a new session
                $this->create_session();
            }
            else
            {
                // Check for an existing session
                $stmt = $this->db->query(
                    "SELECT * FROM {$this->config['db_table']}
                        WHERE session_id = ?
                            AND ip_address = ?
                            AND user_agent = ?
                            AND last_activity > ?
                        LIMIT 1",
                    $this->cookie['sessionid'],
                    $this->CSF->request->ip_address(),
                    $this->CSF->request->user_agent(96),
                    time() - $config['lifetime']);
                $sess = $stmt->fetch(PDO::FETCH_ASSOC);

                if ( !$sess )
                {
                    // Session lookup failed - this session either doesn't exist
                    // or is invalid, so delete it
                    $this->db->query(
                        "DELETE FROM {$this->config['db_table']}
                            WHERE session_id = ?",
                        $this->cookie['sessionid']);
                    // ... and create a new one!
                    $this->create_session();
                }
                else
                {
                    // Session valid - decrypt session data
                    $this->data_crypt =& CSF::make_module('encrypt',
                        array($this->cookie['secret']));
                    $this->data = $this->data_crypt->decode_var($sess['data']);
                }
            }
        }
        else
        {
            // No session cookie - create a session
            $this->create_session();
        }

        // Bind the session superglobal to the session data array
        $_SESSION =& $this->data;

        // Clean up timed out sessions
        $this->gc();
    }

    /*
     * Destructor
     *
     * Save session data
     */
    public function __destruct()
    {
        $this->save();
    }

    /*
     * Save session data
     *
     * Encrypt and save session data to the database
     */
    public function save()
    {
        $this->db->query(
            "UPDATE {$this->config['db_table']}
                SET last_activity = ?,
                    data = ?
                WHERE session_id = ?",
            time(),
            $this->data_crypt->encode_var($this->data),
            $this->cookie['sessionid']);
    }

    /*
     * Create a new session
     *
     * Create a new session in the database, and send the necessary information
     * to the client in an encrypted cookie for accessing the data.
     */
    protected function create_session()
    {
        // Create new cookie/session info
        $this->cookie = array(
            'sessionid' => $this->new_sessionid(),
            'secret'    => $this->new_secret(),
        );
        // Clear session data
        $this->data = array();

        // Initialise data encryption with new key
        $this->data_crypt =& CSF::make_module('encrypt',
            array($this->cookie['secret']));

        // Insert session into the database
        $this->db->query(
            "INSERT INTO {$this->config['db_table']}
                (session_id, ip_address, user_agent, last_activity, data)
                VALUES (?, ?, ?, ?, ?)",
            $this->cookie['sessionid'],
            $this->CSF->request->ip_address(),
            $this->CSF->request->user_agent(96),
            time(),
            $this->data_crypt->encode_var($this->data));

        // Send the cookie
        setcookie(
            $this->config['name'],
            $this->cookie_crypt->encode_var($this->cookie),
            time() + 63072000,      // 2 years
            CSF::config('csf.session.path', '/'),
            CSF::config('csf.session.domain', ''));
    }

    /*
     * Garbage collection - remove expired sessions
     *
     * Probability of garbage collection is set in csf.session.gc_prob, default
     * is 0.01 (1% of pageviews).
     */
    protected function gc()
    {
        if ((rand()/getrandmax()) < CSF::config('csf.session.gc_prob', 0.01))
        {
            $this->db->query(
                "DELETE FROM {$this->config['db_table']}
                    WHERE last_activity < ?",
                time() - $this->config['lifetime']);
        }
    }

    /*
     * Create a unique session ID, SHA1 hashed (40 chars)
     */
    protected function new_sessionid()
    {
        return sha1(uniqid('', true));
    }

    /*
     * Create a new 32-byte encryption key
     */
    protected function new_secret()
    {
        $out = '';
        for ( $i = 32 ; $i > 0 ; $i-- )
            $out .= chr(mt_rand(0, 255));
        return $out;
    }

    /*
     * Get/set session data
     */
    public function get($name)
    {
        return $this->data[$name];
    }
    public function set($name, $value)
    {
        $this->data[$name] = $value;
    }

    /*
     * Overload property access to use session data
     */
    public function __get($name)
    {
        return $this->data[$name];
    }
    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }
    public function __unset($name)
    {
        unset($this->data[$name]);
    }
}
?>
