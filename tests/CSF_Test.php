<?php
/**
 * CodeScape Framework - Unit Tests - CSF class
 *
 * @package     CSF_Test
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2009, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

require 'PHPUnit/Framework.php';

require 'csf.php';


/**
 * A CSF subclass which adds the ability to be reset, for the purposes of
 * testing (since a load of static methods are a pain to test!)
 */
abstract class Testable_CSF extends CSF
{
    /**
     * Reset CSF state
     */
    public function reset()
    {
        // Reset state variables
        self::$_config = array();
        self::$_library_paths = array();
        self::$_module_paths = array();
        self::$_libraries = array();
        self::$_modules = array();
    }
}


/**
 * A simple class for distinct test objects
 */
class TestClass
{
    public $val;

    public function __construct($val)
    {
        $this->val = $val;
    }
}


/**
 * Unit tests for main CSF class
 */
class CSF_Test extends PHPUnit_Framework_TestCase
{
    /**
     * Reset CSF state before each test
     */
    public function setUp()
    {
        Testable_CSF::reset();
    }

    /**
     * Get existing configuration item
     */
    public function testConfigExisting()
    {
        // Some test data
        Testable_CSF::init(array(
            'a' => 'b', 
            'c' => array(
                'd' => 'e',
                'f' => array('g', 'h')
            )
        ));

        // Simple item
        $this->assertEquals('b', Testable_CSF::config('a'));
        $this->assertEquals('e', Testable_CSF::config('c.d'));

        // Composite item
        $this->assertEquals(array('g', 'h'), Testable_CSF::config('c.f'));
        $this->assertEquals(array('d' => 'e', 'f' => array('g', 'h')),
                            Testable_CSF::config('c'));
    }

    /**
     * Get non-existing configuration item, return default instead
     */
    public function testConfigNotExistingWithDefault()
    {
        // Some test data
        Testable_CSF::init(array(
            'a' => 'b', 
            'c' => array(
                'd' => 'e',
                'f' => array('g', 'h')
            )
        ));

        // Single incorrect part
        $this->assertEquals('failed', Testable_CSF::config('x', 'failed'));
        // First incorrect part
        $this->assertEquals('failed', Testable_CSF::config('y.x', 'failed'));
        // Non-first incorrect part (array)
        $this->assertEquals('failed', Testable_CSF::config('c.z', 'failed'));
        // Non-first incorrect part (non-array)
        $this->assertEquals('failed', Testable_CSF::config('a.z', 'failed'));
    }

    /**
     * Get non-existing configuration item, throw exception
     * @expectedException   CSF_ConfigNotFound
     */
    public function testConfigNotExistingNoDefault()
    {
        // No test data - any query will fail
        
        $x = Testable_CSF::config('nothinghere');
    }

    /**
     * Get non-existing module
     * @expectedException   CSF_ModuleNotRegistered
     */
    public function testGetModuleNotExisting()
    {
        $x = Testable_CSF::get('foo');
    }

    /**
     * Register and get modules
     */
    public function testRegisterAndGetModule()
    {
        // An arbitrary object
        $m = new TestClass('foo');
        // Register as a module
        Testable_CSF::register('foo', $m);

        // Module should be registered now
        $x = Testable_CSF::get('foo');
        $this->assertEquals($x, $m);

        // Make sure these are the same object
        $x->val = 'bar';
        $this->assertEquals($x, $m);
    }

    /**
     * Create duplicate module
     * @expectedException   CSF_ModuleConflict
     */
    public function testRegisterDuplicateModule()
    {
        // Two arbitrary objects
        $m1 = new TestClass('foo');
        $m2 = new TestClass('bar');

        // Attempt to register both under the same name
        Testable_CSF::register('foo', $m1);
        Testable_CSF::register('foo', $m2);
    }

    /**
     * Alias a module
     */
    public function testAliasModule()
    {
        $m1 = new TestClass('foo');

        Testable_CSF::register('foo', $m1);
        Testable_CSF::alias('foo', 'bar');

        $this->assertEquals(Testable_CSF::get('foo'), Testable_CSF::get('bar'));
    }

    /**
     * Alias a duplicate module
     * @expectedException   CSF_ModuleConflict
     */
    public function testAliasDuplicateModule()
    {
        $m1 = new TestClass('foo');
        $m2 = new TestClass('bar');

        Testable_CSF::register('foo', $m1);
        Testable_CSF::register('bar', $m2);
        Testable_CSF::alias('bar', 'foo');
    }
}
