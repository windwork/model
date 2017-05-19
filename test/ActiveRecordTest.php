<?php

define('IS_IN', 1);
define('IS_UNIT_TEST', 1);

require_once __DIR__ . '/../../db/lib/Exception.php';
require_once __DIR__ . '/../../db/lib/IDB.php';
require_once __DIR__ . '/../../db/lib/ADB.php';
require_once __DIR__ . '/../../db/lib/QueryBuilder.php';
require_once __DIR__ . '/../../db/lib/Find.php';
require_once __DIR__ . '/../../db/lib/strategy/PDOMySQL.php';
require_once __DIR__ . '/../../util/lib/Validator.php';
require_once __DIR__ . '/../lib/Model.php';
require_once __DIR__ . '/../lib/Exception.php';
require_once __DIR__ . '/../lib/ActiveRecord.php';

use wf\model\ActiveRecord;

function db() {
    return new \wf\db\strategy\PDOMySQL([
        'host'           => '127.0.0.1',   // 本机测试
        'port'           => '3306',        // 数据库服务器端口
        'name'           => 'test',       // 数据库名
        'user'           => 'root',        // 数据库连接用户名
        'pass'           => '123456',      // 数据库连接密码
        'tablePrefix'    => 'wk_',         // 表前缀
        'debug'          => 0,
    ]);
}

class ModelTestModel extends ActiveRecord 
{
    protected $table = 'phpunit_test_table';

    private $aa;

    public function setAa($aa){
        $this->aa = $aa;

        return $this;
    }

}

class ModelValidTestModel extends ModelTestModel 
{
    public function validRules() {
        return [
            'email' => [
                'email' => '邮箱格式有误！'
            ],
            'pw' => [
                'required' => '密码不能为空',
                'minLen' => ['msg' => '最短6位', 'minLen' => 6],
                'maxLen' => ['msg' => '最长20位', 'maxLen' => 20],
            ],
        ];
    }
    
}

class ModelMKTestModel extends ActiveRecord {
    protected $table = 'phpunit_test_table_mk';
        
}

class ModelTestModel2 extends ModelTestModel 
{
    protected $userName;
    protected $password;
    
    protected $noSeterGetter = 1;
    
    public function getUserName() 
    {
        return $this->userName;
    }
    
    public function getPassword() 
    {
        return $this->password;
    }
    
    public function setUserName($userName) 
    {
        $this->userName = $userName;
        return $this;
    }
    
    public function setPassword($password) 
    {
        $this->password = $password;
        return $this;
    }
    
    protected $fieldMap = [
        'uname' => 'userName',
        'pw'    => 'password',
    ];
}


/**
 * Model test case.
 */
class ActiveRecordTest extends PHPUnit_Framework_TestCase 
{
    
    /**
     *
     * @var Model
     */
    private $model;
    
    /**
     * Prepares the environment before running a test.
     */
    protected function setUp() 
    {
        parent::setUp ();
        
        // 新建测试表
        $sql = "
        DROP TABLE IF EXISTS phpunit_test_table;
        CREATE TABLE `phpunit_test_table` (
            `id`  int(10) NOT NULL AUTO_INCREMENT ,
            `uname`  varchar(255) NOT NULL DEFAULT '' ,
            `pw` varchar(255) NOT NULL DEFAULT '' ,
            `email`  varchar(255) NOT NULL DEFAULT '' ,
            `desc`  text NULL ,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        db()->exec($sql);
        
        db()->exec("REPLACE INTO phpunit_test_table VALUE (1, 'cmpan', '123456', 'emall@xx.com', 'desc')");
        
        $this->model = new ModelTestModel();
    }
    
    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown() 
    {
        $this->model = null;
        
        // 删除测试表
        $sql = "DROP TABLE IF EXISTS phpunit_test_table;
                DROP TABLE IF EXISTS phpunit_test_table_mk;";
        db()->exec($sql);    
        
        parent::tearDown ();
            
    }
    
    /**
     * Tests Model->setPkv()
     */
    public function testSetPkv() 
    {
        $this->model->setPkv(1);

        // 通过主键名或getPkv()都可以获取到主键值
        $this->assertEquals(1, $this->model->id);
        $this->assertEquals(1, $this->model->getPkv());
        
        // 主键为数组
        $sql = "
        DROP TABLE IF EXISTS phpunit_test_table_mk;
        CREATE TABLE `phpunit_test_table_mk` (
            `pk1` varchar(64) NOT NULL DEFAULT '' ,
            `pk2` varchar(64) NOT NULL DEFAULT '' ,
            `pw` varchar(255) NOT NULL DEFAULT '' ,
            `email`  varchar(255) NOT NULL DEFAULT '' ,
            `desc`  text NULL ,
            PRIMARY KEY (`pk1`,pk2)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        db()->exec($sql);
        
        $objMK = new ModelMKTestModel();
        $objMK->fromArray([
            'pk1' => 123,
            'pk2' => 456,
            'pw' => '123456',
            'email' => 'xx@x.cc',
            'desc' => 'sdfsf',
        ])->create();
        $rPkv = $objMK->getPkv();
        
        $objMK2 = new ModelMKTestModel();
        $objMK2->setPkv($rPkv)->load();
        $rData = $objMK2->toArray();
        $this->assertEquals('123456', $rData['pw']);
        $this->assertEquals('xx@x.cc', $rData['email']);
        $this->assertEquals('sdfsf', $rData['desc']);
        
        // replace
        // 只有主键为非自增的时候，才能在创建时指定主键
        $mpk = [
            'pk1' => 999,
            'pk2' => 888,            
        ];
        $data = $mpk + [
            'pw' => '123456',
            'email' => 'xx@x.cc',
            'desc' => 'xxxdes',
        ];
        $objMK2 = new ModelMKTestModel();
        $objMK2->fromArray($data)->replace();
        
        $isExist = (new ModelMKTestModel())->setPkv($mpk)->isExist();
        $this->assertTrue($isExist);
        
        $objMK3 = new ModelMKTestModel();
        $objMK3->setPkv($mpk)->load();
        $this->assertEquals('xxxdes', $objMK3->desc);
        $this->assertEquals('xx@x.cc', $objMK3->email);
    }
    
    /**
     * Tests Model->load()
     */
    public function testLoad() 
    {
        // 必须设置主键值才能load
        try {
            $err = '';
            $this->model->load();
        } catch (\Exception $e) {
            $err = $e->getMessage();
        }        
        $this->assertNotEmpty($err);
        
        $this->model->setPkv(1);
        $this->model->load();

        $this->assertEquals('cmpan', $this->model->uname);
    }
    
    /**
     * Tests Model->loadBy()
     */
    public function testLoadBy() 
    {        
        $this->assertTrue($this->model->loadBy(['uname', 'cmpan']));
    }
    
    /**
     * Tests Model->fromArray()
     */
    public function testFromArray() 
    {
        $data = [
            'a' => 123,
            'b' => 'hijok',
        ];
        
        $this->model->fromArray($data);
        
        foreach ($data as $key => $val) {
            $this->assertEquals($val, $this->model->$key);
        }
    }
    
    /**
     * Tests Model->isExist()
     */
    public function testIsExist() 
    {
        $this->assertFalse($this->model->setPkv(9999)->isExist());
        $this->assertTrue($this->model->setPkv(1)->isExist());
    }
    
    /**
     * Tests Model->getPkv()
     */
    public function testGetPkv() 
    {
        $this->model->loadBy(['uname', 'cmpan']);
        $this->assertEquals('1', $this->model->getPkv());
    }
    
    /**
     * Tests Model->getPk()
     */
    public function testGetPk() 
    {
        $this->assertEquals('id', $this->model->getPk());
    }
    
    /**
     * Tests Model->toArray()
     */
    public function testToArray() 
    {
        $data = [
            'a' => 123,
            'b' => 'hijok',
        ];
        
        $this->model->fromArray($data);
        
        $arr = $this->model->toArray();
        
        foreach ($data as $key => $val) {
            $this->assertEquals($val, $arr[$key]);
        }

        $obj2 = new ModelTestModel2();
        
        // 动态属性set/get/unset/isset
        $obj2->dVar = 110;
        $this->assertEquals(110, $obj2->dVar);
        unset($obj2->dVar);//unset映射属性，动态属性受影响
        $this->assertFalse(isset($obj2->dVar));

        // 私有属性没有setter/getter
        $allowGet = true;
        try {
            $obj2->noSeterGetter = 123; // 私有属性没有setter/getter，访问将抛出异常
        } catch (\Exception $e) {
            $allowGet = false;
        }
        $this->assertFalse($allowGet);
        
        $allowGet = true;
        try {
            $x = $obj2->noSeterGetter; // 私有属性没有setter/getter，访问将抛出异常
        } catch (\Exception $e) {
            $allowGet = false;
        }
        $this->assertFalse($allowGet);
        
        // userName属性映射到uname字段
        // password属性映射到pw字段
        $obj2->setUserName('cm');
        $obj2->setPassword('123456');
        
        // toArray
        $obj2->xyz = 112358;
        $data = $obj2->toArray();
        $this->assertEquals(['xyz' => 112358, 'uname' => 'cm', 'pw' => '123456'], $data);

        // 属性映射到字段后，可用字段名读取到属性值
        $this->assertEquals('cm', $obj2->uname);
        // 动态属性名不区分大小写
        $this->assertEquals('cm', $obj2->uName); 
        $this->assertEquals('123456', $obj2->pw);
        
        // 属性映射到字段后，修改字段值，属性值也跟着变
        $obj2->pw = '654321'; // pw字段映射到 private $password; 通过 public setPassword($password) 访问
        $this->assertEquals('654321', $obj2->pw);
        $this->assertEquals('654321', $obj2->getPassword());
        $this->assertEquals('654321', $obj2->password);

        
        // 私有属性定义有对应的setter/getter方法，可以自动通过setter/getter访问
        $obj2->password = '456123'; // private $password; 通过 public setPassword($password) 访问
        $this->assertEquals('456123', $obj2->pw);
        $this->assertEquals('456123', $obj2->getPassword());
        $this->assertEquals('456123', $obj2->password);
                
        // isset
        $this->assertFalse(isset($obj2->qqqq)); // 不存在并且未动态赋值的属性
        $this->assertFalse(isset($obj2->noSeterGetter)); // private $noSeterGetter属性没有getter方法不能访问
        $this->assertTrue(isset($obj2->pw)); // $obj2->password
        $this->assertTrue(isset($obj2->password)); // private $password属性有getter方法可访问
        
        // unset
        unset($obj2->uname);//unset动态属性，映射属性受影响
        $this->assertEquals(null, $obj2->userName);
        
        unset($obj2->password);//unset映射属性，动态属性受影响
        $this->assertEquals(null, $obj2->pw);
    }
    
    /**
     * Tests Model->delete()
     */
    public function testDelete() 
    {
        $id = $this->testCreate();
        
        $m2 = new ModelTestModel();        
        $m2->setPkv($id);
        $this->assertTrue($m2->load());
        
        $this->assertTrue((bool)$m2->delete());

        $m3 = new ModelTestModel();
        $m3->setPkv($id);
        $this->assertFalse($m3->load());
    }
    
    /**
     * Tests Model->deleteBy()
     */
    public function testDeleteBy() 
    {
        $id = $this->testCreate();
        
        $this->assertTrue($this->model->loadBy(['id', $id]));
        $this->model->deleteBy(['id', $id]);
        
        $this->assertFalse($this->model->loadBy(['id', $id]));        
    }
    
    /**
     * Tests Model->create()
     */
    public function testCreate() 
    {
        $data = [
            'uname' => 'erzh',
            'pw'    => '123456',
            'email' => 'erzh@wf.com',
            'desc'  => 'test',
        ];
        $isSaved = $this->model->fromArray($data)->create();
        
        $this->assertNotEmpty($isSaved);
        
        return $this->model->getPkv();
    }
    
    /**
     * Tests Model->update()
     */
    public function testUpdate() 
    {
        $this->testCreate();

        $name = uniqid();
        $desc = uniqid();
        $this->model->uname = $name;
        $this->model->desc = $desc;
        $this->model->update();
        
        // 修改uname、desc为unique值后，根据修改后的属性能查出数据
        $loaded = $this->model->loadBy([
            ['uname', $name],
            ['desc', $desc],
        ]);
        $this->assertTrue($loaded);        
    }
    
    /**
     * Tests Model->save()
     */
    public function testSave() 
    {
        $obj1 = clone $this->model;
        $obj2 = clone $this->model;
        $obj3 = clone $this->model;
        
        // create
        $this->model->fromArray([
            'uname' => 'xxx',
            'pw' => '123456',
        ]);
        $this->model->save();
        
        // update        
        $this->model->save();
        
        $obj1->setPkv($this->model->getPkv())->load();
        $this->assertEquals('xxx', $obj1->uname);
        $this->assertEquals('123456', $obj1->pw);
    }
    
    /**
     * Tests Model->find()
     */
    public function testFind() 
    {        
        $obj = $this->model->find();
        $this->assertTrue($obj instanceof \wf\db\Find);
    }
    
    /**
     * Tests Model->updateBy()
     */
    public function testUpdateBy() 
    {
        $data = [
            'uname' => uniqid(),
            'pw'    => uniqid(),
            'email' => 'erzh@wf.com',
            'desc'  => 'test',
        ];
        $isCreated = $this->model->fromArray($data)->create();
        
        $this->assertNotEmpty($isCreated);
        
        $desc = '255dsds第';
        $this->model->updateBy(['desc' => $desc], [
            ['uname', $data['uname']],
            ['pw', $data['pw']],
        ]);

        $m = new ModelTestModel();
        $m->setPkv($this->model->getPkv())->load();
        $this->assertEquals($desc, $m->desc);
    }
    
    /**
     * Tests Model->saveFields()
     */
    public function testSaveFields() 
    {
        $this->testCreate();
        $name = uniqid();
        $pw   = uniqid();
        $desc = uniqid();

        $this->model->uname = $name;
        $this->model->pw = $pw; 
        $this->model->desc = $desc;
        
        // 保存uname,pw
        $this->model->saveFields('uname,pw');
        
        $whereArr = [
            ['uname', $name],
            ['pw', $pw],
        ];
        $m = new ModelTestModel();
        $isLoaded = $m->loadBy($whereArr);
        $this->assertNotEmpty($isLoaded);
        
        // uname,pw更新了，但desc没更新
        $this->assertNotEquals($desc, $m->desc);
        $this->assertEquals('test', $m->desc);
    }
    
    /**
     * Tests Model->addLockFields()
     */
    public function testAddLockFields() 
    {
        // 锁定字段后不能添加或更新
        $this->model->addLockFields('email,pw'); // 锁定email,pw字段
        $id = $this->testCreate();
        
        $this->model->setPkv($id)->load();
        $this->assertEmpty($this->model->email);
        $this->assertEmpty($this->model->pw);
        
        // 可更新email字段，不可更新pw字段
        $this->model->removeLockFields('email'); // 解锁email字段
        $this->model->email = '123888@xx.com';
        $this->model->pw = '123888';
        $this->model->update();

        $m3 = new ModelTestModel();
        $m3->setPkv($id)->load();
        $this->assertEmpty($m3->pw); // pw不可更新
        $this->assertNotEmpty($m3->email); // email更新
        
        
        // 可设置email字段值，不可设置pw字段值
        $id = $this->testCreate();        
        $this->model->setPkv($id)->load();
        $this->assertNotEmpty($this->model->email);
        $this->assertEmpty($this->model->pw);

        $this->model->removeLockFields('pw');  // 解锁pw字段
        
        // 字段解锁后可新增
        $id = $this->testCreate();
        $this->model->setPkv($id)->load();
        $this->assertNotEmpty($this->model->email);
        $this->assertNotEmpty($this->model->pw);
        
        // 字段解锁后可修改
        $this->model->email = 'xxx@qq.com';
        $this->model->pw = '654321';
        $this->model->save();
        
        $m3 = new ModelTestModel();
        $m3->setPkv($id)->load();
        $this->assertEquals($this->model->email, $m3->email);
        $this->assertEquals($this->model->pw, $m3->pw);
    }
    
    /**
     * Tests Model->isLoaded()
     */
    public function testIsLoaded() 
    {
        // 1、新增后为loaded
        $this->testCreate();
        $this->assertTrue($this->model->isLoaded());
        
        // 2、加载后为loaded
        $m2 = new ModelTestModel();
        $m2->setPkv($this->model->getPkv())->load();
        $this->assertTrue($m2->isLoaded());
        
        // 3、Model::fromArray()强制为loaded
        $m3 = new ModelTestModel();
        $m3->fromArray([1, 2, 3], 1);
        $this->assertFalse($m3->isLoaded()); // 没设置主键值，强制设置loaded无效
        
        $m3->fromArray(['id' => 12580], 1);
        $this->assertTrue($m3->isLoaded()); // 设置了主键值，强制设置loaded才有效
    }
    
    public function testValidate() {
        $obj = new ModelValidTestModel();
        $data = [
            'desc' => '111'
        ];
        // 密码为空
        $this->assertFalse($obj->fromArray($data)->create());

        // 密码小于6位
        $data['pw'] = '123';
        $obj->resetErr();
        $this->assertFalse($obj->fromArray($data)->create());
        
        // 密码超过20位
        $data['pw'] = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
        $obj->resetErr();
        $this->assertFalse($obj->fromArray($data)->create());
        
        // 密码合法
        $obj->resetErr();
        $data['pw'] = '123456';
        $this->assertTrue((bool)$obj->fromArray($data)->create());
        
        // 邮箱格式不合法
        $obj->resetErr();
        $data['email'] = '123456';
        $this->assertFalse((bool)$obj->fromArray($data)->create());
        
        $obj->resetErr();
        $data['email'] = 'dsd@xx.cc';
        $this->assertTrue((bool)$obj->fromArray($data)->create());
    }
    
    /**
     * Tests Model->getTable()
     */
    public function testGetTable() 
    {
        $this->assertEquals('phpunit_test_table', $this->model->getTable());
    }
}

