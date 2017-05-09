<?php
define('IS_IN', 1);

require_once '../../util/lib/Validator.php';
require_once '../lib/ModelBase.php';
require_once '../lib/Model.php';

use wf\model\Model;

class ModelNoTable extends Model {
    
}

/**
 * Model test case.
 */
class ModelNoTableTest extends PHPUnit_Framework_TestCase {
    
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
    
    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown() {
        // TODO Auto-generated ModelNoTableTest::tearDown()
        $this->model = null;
        
        parent::tearDown ();
    }
    
    /**
     * Tests Model->fromArray()
     */
    public function testFromArray() {
        $arr = ['aa' => 'dssdds', 'bb' => 12233];
        $this->model->fromArray($arr);

        $this->assertEquals($arr['aa'], $this->model->aa);
        $this->assertEquals($arr['bb'], $this->model->bb);        
    }
    
    
    
    /**
     * Tests Model->toArray()
     */
    public function testToArray() {
        $arr = ['aa' => 'dssdds', 'bb' => 12233];
        $this->model->fromArray($arr);
        
        $retArr = $this->model->toArray();

        $this->assertEquals($arr['aa'], $retArr['aa']);
        $this->assertEquals($arr['bb'], $retArr['bb']);
    }
    
}

