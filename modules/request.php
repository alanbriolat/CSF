<?php
/**
 * CodeScape Framework - Request module
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2009, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 * @link        http://codescape.net/csf/doc/request/
 */

CSF::load_library('csf_request');
CSF::register($MODULE_NAME, new CSF_Request($MODULE_CONF));
