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
     *
     * @param   string  $tpl    Template name
     *
     * @return  string  Path to template file
     */
    public function get_path($tpl)
    {
        return $this->_options['template_path'] . DIRECTORY_SEPARATOR
            . $tpl . $this->_options['template_ext'];
    }


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



/**
 * Template-related exception
 *
 * This exception class is pretty much the same as the standard Exception,
 * except for the fact it closes all output buffers opened by the template
 * library.
 */
class CSF_Template_Exception extends Exception
{
    /**
     * Constructor
     * 
     * Clean up output buffering when an exception is generated
     */
    public function __construct($msg)
    {
        CSF_Template::buffer_endall();
        parent::__construct($msg);
    }
}


/**
 * Generic template node
 *
 * Implements the generic features of a template node - aggregation of child 
 * nodes and replacement with a text string.
 */
class CSF_Template_Node
{
    /** @var    string      Node text */
    protected $_text = '';

    /** @var    array       Child nodes */
    protected $_children = array();


    /**
     * Render the node
     *
     * If the text of the node has been set, return it, otherwise return the
     * concatenation of rendering all child nodes in order.
     *
     * @return  string
     */
    public function render()
    {
        if (!empty($this->_text))
        {
            // Text set, return it
            return $this->_text;
        }
        elseif (!empty($this->_children))
        {
            // Has children, render them all
            $c = array();
            foreach ($this->_children as $child)
                $c[] = $child->render();
            return implode('', $c);
        }
        else
        {
            // No content
            return '';
        }
    }


    /**
     * Set the node text
     *
     * @param   string  $text
     */
    public function set($text)
    {
        $this->_text = $text;
    }


    /**
     * Append a child node
     *
     * @param   CSF_Template_Node   $node
     */
    public function append($node)
    {
        $this->_children[] = $node;
    }
}



/**
 * Template text node
 *
 * A node which only contains text.  Has a constructor which sets the node text.
 */
class CSF_Template_TextNode extends CSF_Template_Node
{
    /**
     * Constructor
     *
     * Set the node text
     *
     * @param   string  $text
     */
    public function __construct($text)
    {
        $this->set($text);
    }
}



/**
 * Template super node
 *
 * A super node is just a placeholder.
 */
class CSF_Template_SuperNode extends CSF_Template_Node
{
}



/**
 * Template block node
 *
 * A named template block.  Provides the ability to set the value of all
 * contained super blocks.
 */
class CSF_Template_BlockNode extends CSF_Template_Node
{
    /** @var    string  Name of the template block */
    protected $_name;


    /**
     * Constructor
     *
     * Set the block name.
     *
     * @param   string  $name
     */
    public function __construct($name)
    {
        $this->_name = $name;
    }


    /**
     * Get the block name
     *
     * @return  string
     */
    public function name()
    {
        return $this->_name;
    }


    /**
     * Set the text of super nodes
     *
     * @param   string  $text
     */
    public function set_super($text)
    {
        foreach ($this->_children as $child)
            if ($child instanceof CSF_Template_SuperNode)
                $child->set($text);
    }
}



/**
 * Template
 *
 * Represents a template file, and inherits from CSF_Template_Node since it's
 * the root node of a template.
 */
class CSF_Template_Template extends CSF_Template_Node
{
    /**
     * Parent template engine instance, to allow the correct formation of
     * template paths using get_path.
     * @var     CSF_Template
     */
    protected $_engine;

    /** @var    string  Template path */
    protected $_path;

    /** @var    array   Template blocks */
    protected $_blocks = array();

    /** @var    array   Node stack, for processing nested blocks */
    protected $_stack = array();

    /** @var    string  (Unprocessed) parent template path (for inheritance) */
    protected $_parent_path;

    /** @var    CSF_Template_Template   Parent template */
    protected $_parent;


    /**
     * Constructor
     *
     * Store reference to template engine, get the template path.
     *
     * @param   CSF_Template    $engine
     * @param   string          $tpl        Template name
     */
    public function __construct($engine, $tpl)
    {
        $this->_engine = $engine;
        $this->_path = $engine->get_path($tpl);

        if (!file_exists($this->_path))
            throw new CSF_Template_Exception("Template not found: $tpl");
    }


    /**
     * Render the template and return the resulting string
     *
     * Rendering a template is a 4-stage process:
     * <ol>
     *  <li> Parse the template.  If the template inherits from another, parse
     *       that too, until the root of the template hierarchy </li>
     *  <li> Fill in Super nodes with block text from higher up in the
     *       hierarchy </li>
     *  <li> Propagate block contents up the hierarchy to the root 
     *       template </li>
     *  <li> Return the string value of the root template </li>
     * </ol>
     *
     * @param   mixed       $C      Context object
     */
    public function render($C = array())
    {
        $this->_parse($C);
        $this->_super();
        return $this->_compile();
    }


    /**
     * Stage 1 - Parse the template(s)
     *
     * Parse this template, and then if a parent was specified, parse that.
     * This will cause the parsing to travel up the inheritance hierarchy
     * recursively.
     *
     * @param   mixed       $C      Context object
     */
    protected function _parse($C)
    {
        // Set the current template to be the base of the block stack
        $this->_stack = array($this);

        // Expose the template variable
        $TPL = $this;
        // Start buffering
        CSF_Template::buffer_start();
        // "Run" the template
        include($this->_path);

        // Check that all blocks were closed
        if (count($this->_stack) > 1)
        {
            $name = end($this->_stack)->name();
            throw new CSF_Template_Exception("Block '$name' not closed");
        }

        // Clean up - add remaining text as a Text node
        $this->append(new CSF_Template_TextNode(CSF_Template::buffer_end()));

        // Reverse block list so that most-deeply-nested blocks are processed
        // first, correctly propagating from bottom to top
        $this->_blocks = array_reverse($this->_blocks);

        // Parse the parent template if one was set
        if ($this->_parent_path)
        {
            $this->_parent = new CSF_Template_Template($this->_engine, 
                                                       $this->_parent_path);
            $this->_parent->_parse($C);
        }
    }


    /**
     * Stage 2 - Replace Super nodes
     *
     * Propagate block values down from the root template to populate Super
     * nodes with the correct values.  Lower block data takes precedence over
     * higher block date, so the value for a Super node is that of the closest
     * ancestor block of the same name.
     *
     * @return  array   The text values of blocks in this template and above
     */
    protected function _super()
    {
        // Text values of blocks
        $blocktext = array();

        // Only bother resolving Super nodes if there is a parent template,
        // otherwise there won't be any values to use anyway
        if ($this->_parent)
        {
            // Head recursion - process the root template first
            $blocktext = $this->_parent->_super();

            // Resolve the Super blocks in this template
            foreach ($blocktext as $key => $text)
                if (array_key_exists($key, $this->_blocks))
                    $this->_blocks[$key]->set_super($text);
        }

        // Collate block data, overwriting the blocks from higher up with those
        // in this template
        foreach ($this->_blocks as $key => $b)
            $blocktext[$key] = $b->render();

        return $blocktext;
    }


    /**
     * Stage 3 - Collect output
     *
     * Propagate final block values up the tree to the root template, and
     * return the string representation of that root template.  The process
     * is tail recursive, carrying the "current" block data with it.
     *
     * @param   array   $blocktext      The current block data
     * @return  string
     */
    protected function _compile($blocktext = array())
    {
        // Override blocks with content in this template
        foreach ($blocktext as $key => $text)
            if (array_key_exists($key, $this->_blocks))
                $this->_blocks[$key]->set($text);

        // Merge lower block data with this template's block data
        foreach ($this->_blocks as $key => $b)
            $blocktext[$key] = $b->render();

        // If this is the root, return the compiled template, otherwise
        // continue up the tree
        if ($this->_parent)
            return $this->_parent->_compile($blocktext);
        else
            return parent::render();
    }


    /**
     * Start a template block
     *
     * @param   string      $name       Block name
     */
    public function block($name)
    {
        // Error if this template already has this block
        if (array_key_exists($name, $this->_blocks))
            throw new CSF_Template_Exception("Duplicate block '$name' detected");

        // Get the current block
        $top = end($this->_stack);

        // Add text so far to the current block
        $top->append(new CSF_Template_TextNode(CSF_Template::buffer_end()));
        // Create the new block
        $b = new CSF_Template_BlockNode($name);
        // Add it to the block list
        $this->_blocks[$name] = $b;
        // Add to the current block
        $top->append($b);
        // Set new block as current
        array_push($this->_stack, $b);
        // Restart buffering
        CSF_Template::buffer_start();
    }


    /**
     * End the current template block
     */
    public function endblock()
    {
        // Error if there are no open blocks
        if (count($this->_stack) < 2)
            throw new CSF_Template_Exception('endblock() outside of block');

        // Get the current block
        $top = end($this->_stack);

        // Add the text so far to the block
        $top->append(new CSF_Template_TextNode(CSF_Template::buffer_end()));
        // Close the block by popping it from the stack
        array_pop($this->_stack);
        // Restart buffering
        CSF_Template::buffer_start();
    }


    /**
     * Insert the inherited content of the current block
     */
    public function super()
    {
        // Get the current block
        $top = end($this->_stack);

        // Error if we're not actually in a block
        if (!($top instanceof CSF_Template_BlockNode))
            throw new CSF_Template_Exception(
                'Cannot use super() outside of block');

        // Add text so far to the block
        $top->append(new CSF_Template_TextNode(CSF_Template::buffer_end()));
        // Add super node
        $top->append(new CSF_Template_SuperNode());
        // Start buffering again
        CSF_Template::buffer_start();
    }


    /**
     * Inherit from another template
     *
     * @param   string      $template
     */
    public function inherit($template)
    {
        // Check that we're not attempting multiple inheritance
        if ($this->_parent_path)
            throw new CSF_Template_Exception(
                'Multiple inheritance not allowed');

        $this->_parent_path = $template;
    }
}
