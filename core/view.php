<?php
/**
 * CodeScape Framework - Hierarchical templating
 *
 * CSF View is a simple hierarchical templating system, inspired in part by
 * {@link http://www.djangoproject.com/ Django} templates.  It works on the 
 * principle that a template can be represented as a tree with 3 types of node:
 * <ul>
 *  <li>Block - a named subtree which can be overridden</li>
 *  <li>Text - literal text</li>
 *  <li>"Super" - a placeholder for inherited block content</li>
 * </ul>
 *
 * The string representation of the tree is simply the concatenation of all the
 * nodes of the tree, converting each node to a string.
 *
 * Template inheritance forms a "unary tree".  A block from lower in the tree 
 * replaces the content of the same block from higher in the tree, and "super"
 * nodes lower in the tree are replaced with text nodes containing the string
 * representation of the same block higher in the tree.
 *
 * The template parsing, generally speaking, involves 3 steps:
 * <ol>
 *  <li>Parsing the templates, following the template inheritance to the root</li>
 *  <li>Propagating block values downwards by replacing "super" nodes</li>
 *  <li>Propagating final block values upwards into the root template</li>
 * </ol>
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2008, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * Template node
 * 
 * Template node base class, providing the general operations available on nodes.
 * Effectively provides all functionality for Text and Super nodes.
 *
 * @see     view.php
 */
class Node
{
    /** @var    string  Node content - overrides the text of the Node */
    public $content = null;
    /** @var    string  Node children */
    protected $children = array();
    /** @var    array   Map of block names to block nodes */
    public $blocks = array();
    /** @var    array   "Super" (placeholder) nodes */
    protected $supers = array();

    /**
     * Constructor
     *
     * Optionally set the initial text value of the node (e.g. for text nodes)
     *
     * @param   string  $content        New block text
     */
    public function __construct($content = null)
    {
        $this->content = $content;
    }

    /**
     * String cast
     *
     * Return the text value of the Node - <var>$content</var> if it is set,
     * otherwise the concatenation of the text values of all the children.
     *
     * @return  string
     */
    public function __toString()
    {
        return is_null($this->content)
            ? implode('', $this->children)
            : $this->content;
    }

    /**
     * Set the text value of the node
     *
     * @param   string  $content        New block text
     */
    public function set_content($content)
    {
        $this->content = $content;
    }

    /**
     * Set the value of all the super nodes
     *
     * @param   string  $content        Super node content
     */
    public function set_super($content)
    {
        foreach ( $this->supers as &$s )
            $s->set_content($content);
    }

    /**
     * Add a text node, returning the new node
     *
     * @param   string  $content        Text for created node
     * @return  Node    The created node
     */
    public function add_text($content)
    {
        $n = new Node($content);
        $this->children[] = $n;
        return $n;
    }

    /**
     * Add a super (placeholder) node, returning the new node
     *
     * @return  Node    The created node
     */
    public function add_super()
    {
        $n = new Node();
        $this->children[] = $n;
        $this->supers[] = $n;
        return $n;
    }

    /**
     * Add a new block node, returning it
     *
     * Relies on the caller to eliminate duplicate blocks - this will just 
     * dumbly overwrite an existing block of the same name.
     *
     * @param   string  $blockname      Name of block
     * @return  BlockNode   The created node
     */
    public function add_block($blockname)
    {
        $n = new BlockNode($blockname);
        $this->children[] = $n;
        $this->blocks[$blockname] = $n;
        return $n;
    }
}

/**
 * Template block node
 *
 * This is a Node with a block name and a constructor which sets the block name.
 * The only real use for this is to tell the template writer which block they've 
 * forgotten to close.
 *
 * @see     view.php
 */
class BlockNode extends Node
{
    // Block name
    public $blockname = null;

    /*
     * Constructor
     * 
     * Store the block name
     */
    public function __construct($blockname)
    {
        $this->blockname = $blockname;
    }
}


/**
 * Template class
 *
 * Uses output buffering and the Node class to build a tree representing the
 * template.  This class is the root node for a template Node tree.
 *
 * Inherits from Node because in many ways it acts like a block node.  Provides
 * parsing of templates (using recursive instantiation to traverse a template 
 * hierarchy), and error messaging with the filename being parsed.  Provides the
 * following static methods for template structure:
 *
 * <ul>
 *  <li>{@link block Template::block}($blockname) - Start a block</li>
 *  <li>{@link endblock Template::endblock}() - End the most recently started 
 *      block</li>
 *  <li>{@link blocksuper Template::blocksuper}() - Insert the previous contents
 *      of the block</li>
 *  <li>{@link inherit Template::inherit}($file) - Inherit from another
 *      template</li>
 * </ul>
 *
 * Static properties are used for the variables needed by the static methods.
 *
 * @see     view.php
 * @todo    Add include_file()?
 */
class Template extends Node
{
    /** @var    Template    "Current" template instance */
    protected static $instance = null;
    /** @var    array       Node stack for parsing nested blocks */
    protected static $nodestack = array();
    /** @var    Node        Current node (can be a Template - the root node) */
    protected static $currentnode = null;
    /** @var    string      File the current template inherits from */
    protected static $inherits_from = null;

    /** @var    string      Path to template directory */
    public $path = null;
    /** @var    string      Path to this template */
    protected $filepath = null;
    /** @var    array       Context data from View, for replacing variables */
    protected $context = array();

    /** @var    Template    Parent template */
    protected $parent = null;

    /**
     * Constructor
     *
     * Store file information and context
     *
     * @param   string  $path       Path to template directory
     * @param   string  $file       Path relative to template directory
     * @param   array   $context    Context data from View
     */
    public function __construct($path, $file, $context)
    {
        $this->path = $path;
        $this->filepath = $path.DS.$file;
        $this->context = $context;

        if ( !file_exists($this->filepath) )
            $this->error("Template not found");
    }

    /**
     * Reset
     *
     * Resets the static variables, using this Template as the current node and
     * static instance.  This should be called just before actually parsing a 
     * template file.
     */
    public function reset()
    {
        self::$instance = $this;
        self::$nodestack = array();
        self::$currentnode = $this;
        self::$inherits_from = null;
    }

    /**
     * Error
     *
     * Raise an error, giving the path to the file being used.
     *
     * @param   string  $error      The error message
     */
    public function error($error)
    {
        trigger_error("Template error: $error [{$this->filepath}]",
            E_USER_ERROR);
    }

    /**
     * Render the template, returning the resulting string
     * 
     * This is a three-stage process:
     *
     *  1.  Parse this template, and its parent if it has one (tail recursion 
     *      up the template hierarchy) .
     *  2.  Fill in super nodes with block text from higher in the hierarchy
     *      (head recursion up the template hierarchy, filtering the data down 
     *      into lower levels).
     *  3.  Propagate block contents up the hierarchy (tail recursion passing 
     *      the block contents as an argument), return the string value of the
     *      root Template.
     *
     * @return  string
     */
    public function render()
    {
        // Stage 1 - recursive parsing
        $this->parse();
        // Stage 2 - resolve super nodes
        $this->do_super();
        // Stage 3 - compile blocks and get compiled root template
        return $this->do_compile(array());
    }

    /**
     * Stage 1 - Recursive parsing
     *
     * Recursion achieved by parsing the parent template after the current one,
     * if one was specified.
     */
    public function parse()
    {
        $this->reset();
        // Start output buffering
        ob_start();
        // Expand the context data
        extract($this->context, EXTR_SKIP);
        // "Run" the template
        include($this->filepath);
        // Clean up - add the trailing text as a node
        self::$currentnode->add_text(ob_get_clean());

        // Error check - last block closed?
        if ( !self::$instance == self::$currentnode )
            $this->error('Block \'' . self::$currentnode->blockname 
                . '\' not closed');

        // Reverse block list - this means that in other operations the most
        // deeply nested blocks get processed first, making sure changes 
        // propagate correctly from bottom to top.
        $this->blocks = array_reverse($this->blocks);

        // Parse the parent template
        if ( self::$inherits_from )
        {
            $this->parent = new Template($this->path, self::$inherits_from,
                $this->context);
            $this->parent->parse();
        }
    }

    /**
     * Stage 2 - Resolving super nodes
     *
     * Recursively resolves nodes from top to bottom by head recursion, with the
     * method returning an array mapping block names to text values.  Lower 
     * block data takes precedence over higher block data.
     */
    public function do_super()
    {
        // Only bother resolving super nodes if there is a parent - doesn't make
        // sense without one (no block data to inherit)
        if ( $this->parent )
        {
            // Recurse
            $blockdata = $this->parent->do_super();

            // Resolve the super blocks in this template
            foreach ( $blockdata as $key => $data )
                if ( array_key_exists($key, $this->blocks) )
                    $this->blocks[$key]->set_super($data);
        }

        // Collate block data and return it
        $blockdata = array();
        foreach ( $this->blocks as $key => &$b )
            $blockdata[$key] = (string) $b;
        return $blockdata;
    }

    /**
     * Stage 3 - Collect block contents
     *
     * Tail-recursively collects the final versions of every block, passing the
     * block data up the hierarchy as an argument, returning the string 
     * representation of the root template.
     */
    public function do_compile($blockdata)
    {
        // Override blocks defined lower down
        foreach ( $blockdata as $key => $data )
            if ( array_key_exists($key, $this->blocks) )
                $this->blocks[$key]->set_content($data);

        // Merge the new block data with existing block data
        foreach ( $this->blocks as $key => &$b )
            $blockdata[$key] = (string) $b;

        // If this is the root, return the compiled template, otherwise return
        // the value of compiling the parent
        if ( $this->parent )
            return $this->parent->do_compile($blockdata);
        else
            return (string) $this;
    }

    /**
     * Start a template block
     *
     * @param   string  $blockname      Name of the block to start
     */
    public static function block($blockname)
    {
        // Error check - duplicate block?
        if ( array_key_exists($blockname, self::$instance->blocks) )
            self::$instance->error("Duplicate block '$blockname' detected");

        // Add text so far to current block
        self::$currentnode->add_text(ob_get_clean());
        // Create the new block
        $b = self::$currentnode->add_block($blockname);
        // Also store a reference to the block in the Template object
        self::$instance->blocks[$blockname] = $b;
        // Push the current block, set new block as current
        array_push(self::$nodestack, self::$currentnode);
        self::$currentnode = $b;
        // Restart output buffering
        ob_start();
    }

    /**
     * End the current template block
     */
    public static function endblock()
    {
        // Error check - no open block?
        if ( empty(self::$nodestack) )
            self::$instance->error('Unexepected end of block: no blocks open');

        // Add text so far to the block
        self::$currentnode->add_text(ob_get_clean());
        // Restore previous node as current
        self::$currentnode = array_pop(self::$nodestack);
        // Restart output buffering
        ob_start();
    }

    /**
     * Insert the content of the current block from higher in the hierarchy
     */
    public static function blocksuper()
    {
        // Error check - is this actually a block?
        if ( !(self::$currentnode instanceof BlockNode) )
            self::$instance->error('Cannot use blocksuper() outside of a block');

        // Add text so far to the block
        self::$currentnode->add_text(ob_get_clean());
        // Add the super node
        self::$currentnode->add_super();
        // Restart output buffering
        ob_start();
    }

    /**
     * Inherit from another template
     *
     * @param   string  $file       Template path relative to template directory
     */
    public static function inherit($file)
    {
        // Error check - inheritance already specified?
        if ( self::$inherits_from )
            self::$instance->error('Can only inherit from a single template');

        // Store the file to inherit from
        self::$inherits_from = $file;
    }
}

/**
 * The CSF View module
 *
 * View is a wrapper around the templating system, providing the ability to 
 * edit context variables to make available to the templates, and the ability to
 * get the result of parsing a template.
 *
 * @see     view.php
 * @see     Template
 */
class View extends CSF_Module implements CSF_IView
{
    /** @var    array   Context data to make available to the template(s) */
    protected $context = array();
    /** @var    string  Path to template directory */
    protected $template_path = '';

    /**
     * Constructor
     *
     * Save the template directory
     */
    public function __construct()
    {
        $this->template_path = rtrim(
            CSF::config('csf.view.template_dir'), '\\/');
    }

    /**
     * Get context variable using $viewobj->variable
     *
     * @param   string  $name       The variable to retrieve
     * @return  mixed
     */
    public function __get($name)
    {
        return $this->context[$name];
    }
    /**
     * Set context variable using $viewobj->variable = value
     *
     * @param   string  $name       The variable to set
     * @param   mixed   $value
     */
    public function __set($name, $value)
    {
        $this->context[$name] = $value;
    }
    /**
     * Check if a context variable is defined
     *
     * @param   string  $name       The variable to check
     * @return  boolean
     */
    public function __isset($name)
    {
        return isset($this->context[$name]);
    }
    /**
     * Delete a context variable
     *
     * @param   string  $name       The variable to delete
     */
    public function __unset($name)
    {
        unset($this->context[$name]);
    }

    /**
     * Same as $view->name property access
     * @see     __get
     */
    public function set($name, $value)
    {
        $this->context[$name] = $value;
    }
    /**
     * Same as $view->name property access
     * @see     __set
     */
    public function get($name)
    {
        return $this->context[$name];
    }

    /**
     * Render a template with the current context
     *
     * @param   string  $template       Template path
     * @param   array   $context        Override template context
     * @return  string
     */
    public function render($template, $context = null)
    {
        $context = is_null($context) ? $this->context : $context;

        $t = new Template($this->template_path, $template, $context);
        return $t->render();
    }
}
?>
