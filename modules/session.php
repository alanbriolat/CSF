<?php
/**
 * CodeScape Framework - Session module
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2009, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

CSF::load_library('csf_session');
CSF::register($MODULE_NAME, new CSF_Session($MODULE_CONF));
