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
 * Simple hierarchical templating system
 *
 * This works on the principle that a template can be divided into three types
 * of "node":
 * 
 *  - Text:     Literal text, always a leaf node
 *  - Block:    A sub-tree, has any nodes as children
 *  - Super:    A placeholder for the content of a Block further up the 
 *              hierarchy
 *
 * The text of a tree is the concatenation of the text of all it's children.
 *
 * @todo    Possibly add Template::includefile() ?
 */

/*
 * Template node
 * 
 * This encapsulates the operations of text and super nodes.
 */
class Node
{
    // Node content - if not null, will override the text of the Node
    public $content = null;
    // Node children - concatenation gives the text of the Node
    protected $children = array();
    // Blocks within the current Node - only applies to blocks
    // (mapping of block name to Node)
    public $blocks = array();
    // Super nodes - placeholders for the parent value of the current block
    protected $supers = array();

    /*
     * Constructor
     *
     * Optionally set the initial text value of the node (e.g. for text nodes)
     */
    public function __construct($content = null)
    {
        $this->content = $content;
    }

    /*
     * String cast (magic method)
     *
     * Return the text value of the Node - $content if it is set, otherwise the
     * concatenation of the text values of all the children.
     */
    public function __toString()
    {
        return is_null($this->content)
            ? implode('', $this->children)
            : $this->content;
    }

    /*
     * Set the text value of the node
     */
    public function set_content($content)
    {
        $this->content = $content;
    }

    /*
     * Set the value of all the super nodes
     */
    public function set_super($content)
    {
        foreach ( $this->supers as &$s )
            $s->set_content($content);
    }

    /*
     * Add a text node, returning the new node
     */
    public function add_text($content)
    {
        $n = new Node($content);
        $this->children[] = $n;
        return $n;
    }

    /*
     * Add a super (placeholder) node, returning the new node
     */
    public function add_super()
    {
        $n = new Node();
        $this->children[] = $n;
        $this->supers[] = $n;
        return $n;
    }

    /*
     * Add a new block node, returning it
     *
     * Relies on the caller to eliminate duplicate blocks - this will just 
     * dumbly overwrite an existing block of the same name.
     */
    public function add_block($blockname)
    {
        $n = new BlockNode($blockname);
        $this->children[] = $n;
        $this->blocks[$blockname] = $n;
        return $n;
    }
}

/*
 * Template block node
 *
 * This is a Node with a block name and a constructor which sets the block name.
 * The only real use for this is to tell the programmer which block they've 
 * forgotten to close.
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


/*
 * Template class
 *
 * Uses output buffering and the Node class to build a tree representing the
 * template.
 *
 * Inherits from Node because in many ways it acts like a block node.  Provides
 * parsing of templates (using recursive instantiation to traverse a template 
 * hierarchy), and error messaging with the filename being parsed.  Provides the
 * following static methods for template structure:
 *
 *  - Template::block($blockname)   Start a block
 *  - Template::endblock()          End the most recently started block
 *  - Template::blocksuper()        Insert the previous contents of the block
 *  - Template::inherit($file)      Inherit from another template
 *
 * Static properties are used for the variables needed by the static methods.
 */
class Template extends Node
{
    // Template instance currently being parsed
    protected static $instance = null;
    // Node stack for parsing of nested blocks
    protected static $nodestack = array();
    // Current Node (either the Template or the current block)
    protected static $currentnode = null;
    // Parent template as set by Template::inherit()
    protected static $inherits_from = null;

    // Path to templates
    public $path = null;
    // Path to the this template
    protected $filepath = null;
    // Context data from View
    protected $context = array();

    // Template parent
    protected $parent = null;

    /*
     * Constructor
     *
     * Store file information and context
     */
    public function __construct($path, $file, $context)
    {
        $this->path = $path;
        $this->filepath = $path.DS.$file;
        $this->context = $context;
    }

    /*
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

    /*
     * Error
     *
     * Raise an error, giving the path to the file being used.
     */
    public function error($error)
    {
        trigger_error("Template error: $error [{$this->filepath}]",
            E_USER_ERROR);
    }

    /*
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

    /*
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

    /*
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

    /*
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

    /*
     * Start a template block
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

    /*
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

    /*
     * Insert the content of the block from higher in the hierarchy
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

    /*
     * Inherit from another template
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

/*
 * The CSF View module, a wrapper for the templating system
 */
class View extends CSF_Module implements CSF_IView
{
    // Context data to make available to templates
    protected $context = array();

    /*
     * Constructor
     *
     * Save the template directory
     */
    public function __construct()
    {
        $this->template_path = rtrim(
            CSF::config('csf.view.template_dir'), '\\/');
    }

    /*
     * Override property access to use context
     */
    public function __get($name)
    {
        return $this->context[$name];
    }
    public function __set($name, $value)
    {
        $this->context[$name] = $value;
    }
    public function __isset($name)
    {
        return isset($this->context[$name]);
    }
    public function __unset($name)
    {
        unset($this->context[$name]);
    }

    /*
     * Same as $view->name property access
     */
    public function set($name, $value)
    {
        $this->context[$name] = $value;
    }
    public function get($name)
    {
        return $this->context[$name];
    }

    /*
     * Render a template with the current context
     */
    public function render($template, $context = null)
    {
        $context = is_null($context) ? $this->context : $context;

        $t = new Template($this->template_path, $template, $context);
        return $t->render();
    }
}
?>
