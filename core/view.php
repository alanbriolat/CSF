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

class TextNode
{
    public function __construct($content)
    {
        $this->content = $content;
    }

    public function __toString()
    {
        return $this->content;
    }
}

class SuperNode
{
    public function __construct($block)
    {
        $this->name = $block;
    }

    public function __toString()
    {
        return '';
    }
}

class RootNode
{
    protected $children = array();
    protected $blocks = array();

    public function __construct()
    {
    }

    public function __toString()
    {
        return implode('', $this->children);
    }

    public function addBlockNode($block)
    {
        $n = new BlockNode($block);
        $this->children[] = $n;
        $this->blocks[$block] = $n;
        return $n;
    }

    public function addTextNode($text)
    {
        $n = new TextNode($text);
        $this->children[] = $n;
        return $n;
    }
}

class BlockNode extends RootNode
{
    public function __construct($block)
    {
        $this->name = $block;
    }
}


/*
 * View and templating module
 *
 * Implements PHP-based heirarchical templating.
 */
class View extends CSF_Module
{
    // Context data to make available to templates
    protected $context = array();

    // Parsing stuff
    protected $nodestack = array();
    protected $currentnode = null;
    protected $inherits = null;

    /*
     * Constructor
     *
     * Save the template directory
     */
    public function __construct($template_path = '.')
    {
        $this->template_path = rtrim($template_path, '\\/');
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

    public function parse_template($fn)
    {
        $this->parse_file($fn);
    }

    protected function parse_file($__filename)
    {
        $this->currentfile = $this->template_path.DS.$__filename;
        $csf =& CSF::get_instance();
        
        $root = new RootNode();
        $this->currentnode = $root;

        // Sandbox the file, and append the last block of text to the root node
        $root->addTextNode($this->sandbox($__filename));
        var_dump($root);
    }

    /*
     * Output parse error
     *
     * Stop all output buffering and output the error.
     */
    protected function parse_error($error)
    {
        trigger_error("Template parse error: $error [{$this->currentfile}]", 
            E_USER_ERROR);
    }

    /*
     * Reset template processor state
     */
    protected function reset()
    {
        $this->nodestack = array();
        $this->currentnode = null;
        $this->inherits = null;
    }

    /*
     * Sandbox template
     *
     * Run a template inside a minimal method containing no variables to easily
     * accidentally change, with the template context expanded.  If $__discard 
     * is false, return the output of the template.
     */
    protected function sandbox($__filename)
    {
        // Record current input file
        $this->currentfile = $this->template_path.DS.$__filename;
        // Expose the CSF object
        $csf =& CSF::get_instance();
        // Start output buffering
        ob_start();
        // Expand the template context
        extract($this->context, EXTR_SKIP);
        // Run the template
        include($this->currentfile);

        // Return the text of the file
        return ob_get_clean();
    }

    /*
     * Template inheritance
     */
    protected function inherits($tpl)
    {
        $this->inherits = $tpl;
    }

    /*
     * Start a template block
     */
    protected function begin($block)
    {
        // Check block nesting - cannot catch cross-file block self-nesting
        if ( $this->currentnode instanceof BlockNode 
                && $this->currentnode->name == $block )
            $this->parse_error("Block '$block' nested inside itself");


        // End text node
        $this->currentnode->addTextNode(ob_get_clean());
        // Start block node
        $n = $this->currentnode->addBlockNode($block);
        // Push the old node to the stack, use the new current node
        array_push($this->nodestack, $this->currentnode);
        $this->currentnode = $n;
        // Resume output buffering
        ob_start();
    }

    /*
     * End current template block
     */
    protected function end()
    {
        // Check that there is a block open
        if ( empty($this->nodestack) )
            trigger_error("Unexpected end of block: no open blocks", 
                E_USER_ERROR);

        // Add the remaining output as a text node
        $this->currentnode->addTextNode(ob_get_clean());
        // Restore the previous node from the stack
        $this->currentnode = array_pop($this->nodestack);
        // Resume output buffering
        ob_start();
    }

    /*
     * Previous block content
     *
     * Outputs the next-shallowest version of the current block.
     */
    protected function super()
    {
        // Get the current block
        $block = end($this->blockstack);

        // Search up the "tree" for the block
        $lvl = $this->level - 1;
        while ( $lvl >= 0 )
        {
            if ( array_key_exists($block, $this->blocks[$lvl]) )
            {
                echo $this->blocks[$lvl][$block];
                return;
            }
            else
            {
                $lvl--;
            }
        }
        echo '';
    }
}
?>
