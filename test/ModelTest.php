<?php
define('WF_IN', 1);

require_once '../../util/lib/Validator.php';
require_once '../lib/Error.php';
require_once '../lib/Model.php';

use wf\model\Model;

class ModelNoTable extends Model {
    public function test() {
        return $this->validate(['attr' => ''], ['attr' => ["required" => "请输入attr"]]);
    }
}

/**
 * Model test case.
 */
class ModelTest extends PHPUnit_Framework_TestCase {
    
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
        
        $this->assertEquals("请输入attr", $this->model->getError());
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

