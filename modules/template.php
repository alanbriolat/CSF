<?php
/**
 * CodeScape Framework - Template module
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2009, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 * @link        http://codescape.net/csf/doc/template/
 */

CSF::load_library('csf_template');
CSF::register($MODULE_NAME, new CSF_Template($MODULE_CONF));
