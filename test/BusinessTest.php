<?php
define('WF_IN', 1);

require_once '../../util/lib/Validator.php';
require_once '../lib/Error.php';
require_once '../lib/Business.php';

use wf\model\Business;

class ModelNoTable extends Business {
    public function test() {
        return $this->validate(['attr' => ''], ['attr' => ["required" => "请输入attr"]]);
    }
}

/**
 * Business test case.
 */
class BusinessTest extends PHPUnit_Framework_TestCase {
    
    /**
     *
     * @var ModelNoTable
     */
    private $model;
    
    /**
     * Prepares the environment before running a test.
     */
    protected function setUp() {
        parent::setUp ();
        
        $this->model = new ModelNoTable();
    }
    
    public function testGetErrs() {
        $this->assertEmpty($this->model->getError());
        $this->assertFalse($this->model->test());
        $errs = $this->model->getError();
        $this->assertNotEmpty($errs);

        $ret = $this->model->getError();
        
        $this->assertEquals("请输入attr", $ret->getMessage());
    }
    
    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown() {
        // TODO Auto-generated ModelNoTableTest::tearDown()
        $this->model = null;
        
        parent::tearDown ();
    }
}

