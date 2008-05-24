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
 * View and templating module
 *
 * Implements PHP-based heirarchical templating.
 */
class View extends CSF_Module
{
    // Context data to make available to templates
    protected $context = array();

    // Template the most recent template inherits from
    protected $inherits = null;
    // Block data, indexed by level then block name
    protected $blocks = array();
    // Level of the template currently being parsed
    protected $level = 0;
    // Stack for block nesting
    protected $blockstack = array();
    // Current filename, for giving useful errors
    protected $currentfile = null;

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

    /*
     * Template processor
     *
     * 3-stage template processor:
     *  1) Follow the inheritance to the "root" template
     *  2) Traverse the templates from top to bottom, collecting block contents
     *  3) Traverse templates bottom to top, merging blocks
     */
    public function parse_template($fn)
    {
        // Get the template heirarchy
        $templates = array();
        do {
            // Parse the template so $this->inherits gets set
            $this->sandbox($fn);
            // Store the template path
            array_unshift($templates, $fn);
            // Use the next template, reset the inheritance variable
            $fn = $this->inherits;
            $this->inherits(null);
            // Keep going until we reach the "root"
        } while ( $fn );

        // Reset the processor state
        $this->reset();

        // Link $lvl to $this->level - neater code!
        $lvl =& $this->level;

        // Top-down parsing stage - collect block contents
        for ( $lvl = 0 ; $lvl < count($templates) ; $lvl++ )
        {
            $this->sandbox($templates[$lvl]);
        }

        // Bottom-up parsing stage (all except the root)
        for ( $lvl = count($templates) - 1 ; $lvl > 0 ; $lvl-- )
        {
            $this->sandbox($templates[$lvl]);
        }

        // Parse the root template
        return $this->sandbox($templates[0], false);
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
        $this->blocks = array();
        $this->inherits = null;
        $this->level = 0;
    }

    /*
     * Sandbox template
     *
     * Run a template inside a minimal method containing no variables to easily
     * accidentally change, with the template context expanded.  If $__discard 
     * is false, return the output of the template.
     */
    protected function sandbox($__filename, $__discard = true)
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

        // Check that

        // Decide what to do with the output
        if ( $__discard )
        {
            ob_end_clean();
            return '';
        }
        else
        {
            return ob_get_clean();
        }
    }

    /*
     * Template inheritance
     *
     * Used from within templates to tell the processor that the template 
     * inherits from the specified template.
     */
    protected function inherits($tpl)
    {
        $this->inherits = $tpl;
    }

    /*
     * Start a template block
     *
     * Used from within templates to mark the start of a block.
     */
    protected function begin($block)
    {
        // Check block nesting - cannot catch cross-file block self-nesting
        if ( in_array($block, $this->blockstack) )
            $this->parse_error("Block '$block' nested inside itself");

        array_push($this->blockstack, $block);
        ob_start();
    }

    /*
     * End a template block
     *
     * Used from within templates to mark the end of a template block.  Stores
     * the block content for the current level.  Ouputs either the next-deepest
     * version of the block, or the block itself.
     */
    protected function end($block)
    {
        // Check the block being closed is correct
        $top = array_pop($this->blockstack);
        if ( $block != $top )
            $this->parse_error(
                "Unexpected end of block '$block', expected '$top'");

        // Get the output of the block
        $output = ob_get_clean();

        // Store the output
        $this->blocks[$this->level][$block] = $output;

        // Search down the "tree" for an overridden version of the block
        $lvl = $this->level + 1;
        while ( $lvl < count($this->blocks) )
        {
            if ( array_key_exists($block, $this->blocks[$lvl]) )
            {
                echo $this->blocks[$lvl][$block];
                return;
            }
            else
            {
                $lvl++;
            }
        }
        echo $output;
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
