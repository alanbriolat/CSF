<?php
/**
 * CodeScape Framework - Dispatch module
 *
 * @package     CSF
 * @author      Alan Briolat
 * @copyright   (c) 2009, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

CSF::load_library('csf_dispatch');
CSF::register($MODULE_NAME, new csfDispatch($MODULE_CONF));
