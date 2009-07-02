<?php
/**
 * CodeScape Framework - Template library
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2009, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 * @link        http://codescape.net/csf/doc/template/
 */


/**
 * Template library class
 *
 * @link    http://codescape.net/csf/doc/template/#csf_template
 */
class CSF_Template
{
    /** @var    array   Template library options */
    protected $_options = array(
        'template_path' => '.',
        'template_ext' => '.php',
    );


    /**
     * Constructor
     *
     * Store options
     *
     * @param   array   $options    Template library options
     */
    public function __construct($options = array())
    {
        $this->_options = array_merge($this->_options, $options);
    }


    /**
     * Render a template
     *
     * Render the specified template, supplying the context object.  Template
     * file paths are created by concatenating the template path, template name
     * and template file extension.
     *
     * @param   string  $tpl    Template to render
     * @param   mixed   $C      Context object
     *
     * @return  string  The rendered template text
     */
    public function render($tpl, $C)
    {
        $t = new CSF_Template_Template($this, $tpl);
        return $t->render($C);
    }


    /**
     * Get path to template file
     */


    /*
     * Depth-counting output buffering wrapper
     *
     * Wraps the output buffering functions while keeping track of the depth.
     * This allows CSF_Template_Exception and derivitives to end all output
     * buffers created while parsing a template, removing the case where an
     * exception isn't seen because output buffering is still enabled.
     */

    /** @var    int     Buffer nesting level */
    protected static $_buffer_nesting = 0;

    /**
     * Start an output buffer
     */
    public static function buffer_start()
    {
        self::$_buffer_nesting++;
        ob_start();
    }

    /** 
     * End an output buffer
     *
     * @return  string  Contents of output buffer
     */
    public static function buffer_end()
    {
        self::$_buffer_nesting--;
        return ob_get_clean();
    }

    /**
     * End all output buffers
     */
    public static function buffer_endall()
    {
        for (; self::$_buffer_nesting > 0; self::$_buffer_nesting--)
            ob_end_clean();
    }
}





class CSF_Template_Template
{

}
