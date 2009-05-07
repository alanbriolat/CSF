<?php
/**
 * Tpl - A PHP template engine
 *
 * Tpl is a simple hierarchical template engine inspired by the 
 * {@link http://www.djangoproject.com/ Django} template engine.  Since PHP is
 * already a template language (of sorts), Tpl does not attempt to replace it,
 * but extends it with a useful inheritance framework to simplify the writing
 * of templates for both small and complex web applications.
 *
 * In Tpl, a template can be represented as a tree with 3 types of node:
 * <ul>
 *  <li> Block - a named subtree which can be overridden </li>
 *  <li> Text - literal text </li>
 *  <li> Super - a placeholder for inherited block content </li>
 * </ul>
 * 
 * Template inheritance forms a unary tree - multiple inheritance is not
 * allowed, since the semantics for this are very hard to define and there is
 * unlikely to be need for this capability.
 *
 * In the template inheritance tree, Block nodes override their counterparts
 * higher in the tree, and Super nodes are replaced with the content of the
 * block they are contained within from further up the tree.
 *
 * A single render of a template is parsed in 4 steps:
 * <ol>
 *  <li> Parse the templates from the desired template up to the root
 *       of the inheritance tree </li>
 *  <li> Propagate block values down the tree, replacing Super nodes </li>
 *  <li> Propagate final block values up to the root template </li>
 *  <li> Use the string representation of the root template </li>
 * </ol>
 *
 * @package     Tpl
 * @version     0.8
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2009, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */


/**
 * Template exception
 */
class TplException extends Exception
{
    /**
     * Constructor
     *
     * Clean up output buffering before throwing an exception from Tpl.
     *
     * @param   string      $msg        Error message
     */
    public function __construct($msg)
    {
        Tpl::buffer_endall();
        parent::__construct($msg);
    }
}



/**
 * Template node
 *
 * Implements all the generic features of a template node, which mostly
 * consists of the aggregation of child nodes and overriding a node by turning
 * it into a text node.
 */
class TplNode
{
    /**
     * Node text - overrides children
     * @var     string
     */
    protected $text = '';

    /**
     * Node children, in order
     * @var     array
     */
    protected $children = array();


    /**
     * Render the node
     *
     * Return the string representation of the node.  If a text string has been
     * set, that is returned, otherwise the concatenation of rendering the
     * child nodes is returned.
     *
     * @return  string  The string representation of the node
     */
    public function render()
    {
        if (!empty($this->text))
        {
            // Text set, return it
            return $this->text;
        }
        elseif (!empty($this->children))
        {
            // Return concatenation of children
            $c = array();
            foreach ($this->children as $child)
                $c[] = $child->render();
            return implode('', $c);
        }
        else
        {
            // Nothing, return empty string
            return '';
        }
    }


    /**
     * Set the text of a node
     *
     * @param   string      $text
     */
    public function set($text)
    {
        $this->text = $text;
    }


    /**
     * Append a child node
     *
     * @param   TplNode     $node
     */
    public function append($node)
    {
        $this->children[] = $node;
    }
}



/**
 * Template Text node
 */
class TplTextNode extends TplNode
{
    /**
     * Constructor
     *
     * Save the content of the text node
     */
    public function __construct($text)
    {
        $this->set($text);
    }
}



/**
 * Template Super node
 */
class TplSuperNode extends TplNode
{
}



/**
 * Template Block node
 */
class TplBlockNode extends TplNode
{
    /**
     * Block name
     * @var     string
     */
    protected $name;


    /**
     * Constructor
     *
     * Set the name of the block
     */
    public function __construct($name)
    {
        $this->name = $name;
    }


    /**
     * Get the name of the block
     *
     * @return  string
     */
    public function name()
    {
        return $this->name;
    }


    /**
     * Set value of Super nodes
     *
     * @param   string      $text
     */
    public function set_super($text)
    {
        foreach ($this->children as $child)
            if ($child instanceof TplSuperNode)
                $child->set($text);
    }
}



/**
 * Template
 *
 * Represents a template file, and inherits from TplNode since it is the root
 * node of a template.
 */
class TplTemplate extends TplNode
{
    /**
     * Template path (including base directory)
     * @var     string
     */
    protected $path;

    /**
     * Map of block names to nodes
     * @var     array
     */
    protected $blocks = array();

    /**
     * Stack of nodes, for processing nested blocks
     * @var     array
     */
    protected $stack = array();

    /**
     * Parent template path
     * @var     string
     */
    protected $parent_path = '';

    /**
     * Parent template
     * @var     TplTemplate
     */
    protected $parent = null;


    /**
     * Constructor
     *
     * Get template path, throw an error if the template doesn't exist
     */
    public function __construct($template)
    {
        $this->path = Tpl::get_path($template);

        if (!file_exists($this->path))
            throw new TplException("Template not found: $template");
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
        // Make sure Tpl::* calls get routed to the right place
        Tpl::$current = $this;

        // Set the current template to be the base of the block stack
        $this->stack = array($this);

        // Start buffering
        Tpl::buffer_start();
        // "Run" the template
        include($this->path);

        // Check that all blocks were closed
        if (count($this->stack) > 1)
        {
            $name = end($this->stack)->name();
            throw new TplException("Block '$name' not closed");
        }

        // Clean up - add remaining text as a Text node
        $this->append(new TplTextNode(Tpl::buffer_end()));

        // Reverse block list so that most-deeply-nested blocks are processed
        // first, correctly propagating from bottom to top
        $this->blocks = array_reverse($this->blocks);

        // Parse the parent template if one was set
        if ($this->parent_path)
        {
            $this->parent = new TplTemplate($this->parent_path);
            $this->parent->_parse($C);
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
        if ($this->parent)
        {
            // Head recursion - process the root template first
            $blocktext = $this->parent->_super();

            // Resolve the Super blocks in this template
            foreach ($blocktext as $key => $text)
                if (array_key_exists($key, $this->blocks))
                    $this->blocks[$key]->set_super($text);
        }

        // Collate block data, overwriting the blocks from higher up with those
        // in this template
        foreach ($this->blocks as $key => $b)
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
            if (array_key_exists($key, $this->blocks))
                $this->blocks[$key]->set($text);

        // Merge lower block data with this template's block data
        foreach ($this->blocks as $key => $b)
            $blocktext[$key] = $b->render();

        // If this is the root, return the compiled template, otherwise
        // continue up the tree
        if ($this->parent)
            return $this->parent->_compile($blocktext);
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
        if (array_key_exists($name, $this->blocks))
            throw new TplException("Duplicate block '$name' detected");

        // Get the current block
        $top = end($this->stack);

        // Add text so far to the current block
        $top->append(new TplTextNode(Tpl::buffer_end()));
        // Create the new block
        $b = new TplBlockNode($name);
        // Add it to the block list
        $this->blocks[$name] = $b;
        // Add to the current block
        $top->append($b);
        // Set new block as current
        array_push($this->stack, $b);
        // Restart buffering
        Tpl::buffer_start();
    }


    /**
     * End the current template block
     */
    public function endblock()
    {
        // Error if there are no open blocks
        if (count($this->stack) < 2)
            throw new TplException('endblock() outside of block');

        // Get the current block
        $top = end($this->stack);

        // Add the text so far to the block
        $top->append(new TplTextNode(Tpl::buffer_end()));
        // Close the block by popping it from the stack
        array_pop($this->stack);
        // Restart buffering
        Tpl::buffer_start();
    }


    /**
     * Insert the inherited content of the current block
     */
    public function super()
    {
        // Get the current block
        $top = end($this->stack);

        // Error if we're not actually in a block
        if (!($top instanceof TplBlockNode))
            throw new TplException('Cannot use super() outside of block');

        // Add text so far to the block
        $top->append(new TplTextNode(Tpl::buffer_end()));
        // Add super node
        $top->append(new TplSuperNode());
        // Start buffering again
        Tpl::buffer_start();
    }


    /**
     * Inherit from another template
     *
     * @param   string      $template
     */
    public function inherit($template)
    {
        // Check that we're not attempting multiple inheritance
        if ($this->parent_path)
            throw new TplException("Multiple inheritance not allowed");

        $this->parent_path = $template;
    }
}



/**
 * Main Tpl class
 *
 * All methods on the Tpl class are static to provide the illusion of
 * namespacing, because such a thing doesn't exist in PHP5, and I personally
 * think static properties and methods are better than global variables and
 * functions.
 */
class Tpl
{
    /**
     * Base directory that template paths are relative to
     * @var     string
     * @static
     */
    protected static $path = '';

    /**
     * Nesting of output buffering
     * @var     int
     * @static
     */
    protected static $buffer_nesting = 0;

    /**
     * Current template for routing calls such as Tpl::super() to
     * @var     TplTemplate
     */
    public static $current;


    /**
     * Render a template
     *
     * @param   string  $template       Path to the template to render
     * @param   mixed   $C              Context object
     * @param   boolean $echo           Echo the result instead of returning?
     *                                  [default: true]
     *
     * @return  string  The rendered template text if $echo == false
     */
    public static function render($template, $C, $echo = true)
    {
        // TODO: Update path before render so that "include()" can still be used?

        $t = new TplTemplate($template);
        
        if ($echo)
        {
            echo $t->render($C);
            return '';
        }
        else
        {
            return $t->render($C);
        }
    }


    /**
     * Set the template base directory
     *
     * @param   string      $path
     */
    public static function set_path($path)
    {
        $path = rtrim($path, '/');
        if (!empty($path))
            self::$path = $path . '/';
        else
            self::$path = '';
    }


    /**
     * Get a real template path by applying the base directory
     *
     * @param   string      $template
     * @return  string
     */
    public static function get_path($template = '')
    {
        return self::$path.$template;
    }


    /**
     * Start an output buffer
     *
     * A wrapper around ob_start() to keep track of the level of nesting.  This
     * allows TplException to kill all of Tpl's output buffering when it is
     * thrown (by calling Tpl::buffer_endall()).
     */
    public static function buffer_start()
    {
        Tpl::$buffer_nesting++;
        ob_start();
    }


    /**
     * End an output buffer and get its contents
     *
     * A wrapper around ob_get_clean() to keep track of the level of nesting.
     *
     * @return  string      The contents of the (ended) output buffer
     */
    public static function buffer_end()
    {
        Tpl::$buffer_nesting--;
        return ob_get_clean();
    }


    /**
     * End all output buffers, discarding the contents
     *
     * Uses the current nesting level to end all output buffers.
     */
    public static function buffer_endall()
    {
        while (Tpl::$buffer_nesting)
        {
            ob_end_clean();
            Tpl::$buffer_nesting--;
        }
    }



    /****************************************************************
     * Functions that should be called from within templates
     ***************************************************************/

    /**
     * Start a template block
     *
     * @param   string      $name       Block name
     */
    public static function block($name)
    {
        self::$current->block($name);
    }


    /**
     * End the current template block
     */
    public static function endblock()
    {
        self::$current->endblock();
    }


    /**
     * Insert the inherited content of the current block
     */
    public static function super()
    {
        self::$current->super();
    }


    /**
     * Inherit from another template
     *
     * @param   string      $template
     */
    public static function inherit($template)
    {
        self::$current->inherit($template);
    }
}

