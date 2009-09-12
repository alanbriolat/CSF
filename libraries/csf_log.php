<?php
/**
 * CodeScape Framework - Log library
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2009, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 * @link        http://codescape.net/csf/doc/log/
 */

/**
 * Log class
 *
 * Logs events to a file.
 *
 * @todo    Document the "format" option well
 */
class CSF_Log
{
    /** @var    array   Options array */
    protected $_options = array(
        'format'    => '[%s] %s',
        'level'     => 'debug',
        'filename'  => 'log.log',
    );

    /** @var    array   Logging levels */
    protected $_levels = array(
        'none'      => 0,
        'critical'  => 10,
        'error'     => 20,
        'warning'   => 30,
        'info'      => 40,
        'debug'     => 50,
    );

    /** @var    resource    Log file pointer */
    protected $_file = null;


    /**
     * Constructor
     *
     * Merge options, handle extra logging levels
     *
     * @param   array   $options
     */
    public function __construct($options = array())
    {
        // If some extra levels have been supplied, add them
        if (array_key_exists('levels', $options))
        {
            foreach ($options['levels'] as $level)
                $this->add_level($level[0], $level[1]);

            unset($options['levels']);
        }

        // Merge remaining options
        $this->_options = array_merge($this->_options, $options);
    }


    /**
     * Add a log level
     *
     * Add a log level name with the specified level.  A lower level number
     * means the message is more critical.
     *
     * @param   string      $name
     * @param   int         $level
     */
    public function add_level($name, $level)
    {
        $this->_levels[$name] = $level;
    }

    
    /**
     * Write a string to the log file
     *
     * @param   string      $msg
     */
    public function write($msg)
    {
        // Open the log file if it's no already open (i.e. on first write)
        if (is_null($this->_file))
            $this->_file = fopen($this->_options['filename'], 'a');

        fwrite($this->_file, $msg."\n");
    }


    /**
     * Log a message
     *
     * Log a message under the specified log level.  If the current log level
     * is lower than the specified log level, the message is not logged.
     *
     * @param   string      $level
     * @param   string      $msg
     */
    public function log($level, $msg)
    {
        // Throw an error if the log level doesn't exist
        if (!array_key_exists($level, $this->_levels))
            throw new CSF_Log_Exception("Log level '$level' does not exist");

        // Write the log message if the current log level allows it
        if ($this->_levels[$level] <= $this->_levels[$this->_options['level']])
            $this->write(sprintf($this->_options['format'], $level, $msg));
    }


    /**
     * Catch log level method calls
     *
     * So that methods don't need to be defined for every log level, this method
     * catches all calls to undefined methods and passes them through to the
     * log() method with the method name as the log level.
     *
     * @param   string      $name       Method name
     * @param   string      $msg        Log message
     */
    public function __call($name, $args)
    {
        // Push the method name onto the front of the argument array
        array_unshift($args, $name);
        // Pass the call through to the log() method
        call_user_func_array(array($this, 'log'), $args);
    }
}


/**
 * Log library exception
 */
class CSF_Log_Exception extends Exception {}
