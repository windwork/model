<?php
/**
 * Windwork
 * 
 * 一个用于快速开发高并发Web应用的轻量级PHP框架
 * 
 * @copyright Copyright (c) 2008-2017 Windwork Team. (http://www.windwork.org)
 * @license   http://opensource.org/licenses/MIT
 */
namespace wf\model;

/**
 * 领域模型类
 * 
 * 我们采用Active Record(活动记录)架构模式实现领域模型层，特点是一个模型类对应关系型数据库中的
 * 一个表，而模型类的一个实例对应表中的一行记录，封装了数据访问，并在这些记录上增加了领域逻辑。
 * 
 * @package     wf.model
 * @author      cm <cmpan@qq.com>
 * @link        http://docs.windwork.org/manual/wf.model.html
 * @since       0.1.0
 */
class Model extends Business {
    /**
     * 模型对应数据表名
     * 
     * @var string = ''
     */
    protected $table = '';
    
    /**
     * 请不要覆盖此属性，生成对象后自动给该变量赋值
     * 为减少出错的可能，将表结构，主键、主键值、表字段、表信息合并到该数组
     * @var array = []
     */
    protected $tableSchema = [
        'field'  => '', // 字段列表
        'pk'     => '', // 主键名，如果是多个字段构成的主键，则使用数组表示，如: ['pk1', 'pk2', ...]
        'ai'     => false, // 主键是否是自动增长
    ];
    
    /**
     * 表字段绑定属性
     * 
     * 设置表字段对应模型类的属性，以实现把类属性绑定到表字段，并且Model->toArray()方法可获取绑定属性的值。
     * 表字段名不分大小写，属性名大小写敏感。
     * <pre>格式为：
     * [
     *     '表字段1' => '属性1',
     *     '表字段2' => '属性2',
     *     ...
     * ]</pre>
     * @var array = [] 
     */
    protected $fieldMap = [];
        
    /**
     * 模型是否已从数据库加载（通过Model->load()或Model->loadBy()加载）
     * @var bool = null
     */
    protected $loadedPkv = null;
    
    /**
     * 锁定字段，不允许保存值
     * @var array = []
     */
    private $lockedFields = [];
    
    /**
     * toArray的时候，是否忽略值为null的元素
     * @var bool
     */
    public $ignoreNull = false;
    
    /**
     * 数据库访问对象实例
     * @var \wf\db\DBInterface
     */
    private $db = null;
    
    /**
     * 用来动态保存属性
     * @var array
     */
    protected $attrs = [];

    /**
     * 实例是否隔离，一旦隔离后，一个实例加载（映射）记录后，将不允许再加载另外一条数据
     * （数据加载后不允许修改主键值），从而避免主键值可随意修改导致加载的记录和更新的记录
     * 不是同一条记录
     * @var bool
     */
    protected $isInstanceApart = true;
            
    /**
     * 初始化表对象实例
     * 
     * 约定：如果集成模型基类后重写构造函数，必须在构造函数中调用父类的构造函数 parent::__construct();
     *
     * @throws \wf\model\Exception
     */
    public function __construct() {
        if (!$this->table) {
            throw new Exception('请设置模型类"' . get_class($this) . '"对应的表');
        }
        
        // 获取表结构并缓存
        if (!function_exists('wfCache') || !$this->tableSchema = (wfCache()->read("model/table_schema/{$this->table}"))) {
            // 自动加载表信息（字段名列表、主键、主键是否自增）
            $tableSchema = $this->getDb()->getTableSchema($this->table);
            is_array($tableSchema['pk']) && sort($tableSchema['pk']);
            
            // tableSchema
            $this->tableSchema['field']  = array_keys($tableSchema['field']); // 表字段名列表，为支持不区分大小写，已转小写
            $this->tableSchema['pk']     = $tableSchema['pk']; // 表主键名，已转为小写，如果是多个字段的主键，则为['主键1', '主键2']
            $this->tableSchema['ai']     = $tableSchema['ai'];
            
            if (function_exists('wfCache')) {
                wfCache()->write("model/table_schema/{$this->table}", $this->tableSchema);
            }
        }
        
        // 使字段绑定属性的字段名不区分大小写
        if($this->fieldMap) {
            $this->fieldMap = array_combine(array_map('strtolower', array_keys($this->fieldMap)), array_values($this->fieldMap));
        }

        // 新增记录自动增长主键不允许设置值
        if($this->tableSchema['ai']) {
            $this->addLockFields($this->tableSchema['pk']);
        }
    }

    /**
     * 获取属性
     *
     * @param string $name 获取的属性名或属性名列表
     * @return mixed
     * @throws \wf\model\Exception
     */
    public function &__get($name) {
        return $this->getAttrVal($name);
    }
    
    /**
     * 设置属性
     *
     * @param string $name
     * @param mixed $val
     * @return \wf\model\Model
     */
    public function __set($name, $val) {        
        $this->setAttrVal($name, $val);
        
        return $this;
    }
    
    /**
     * 该属性是否已经设置
     *
     * @param string $name
     * @return bool
     */
    public function __isset($name) 
    {
        $getter = 'get' . $name;
        if (method_exists($this, $getter)) {
            return $this->$getter() !== null;
        }
        
        $name = strtolower($name);
        
        // 存在字段映射
        if($this->fieldMap && key_exists($name, $this->fieldMap)) {
            $attr = $this->fieldMap[$name];
            $getter = 'get' . $attr;
            if(method_exists($this, $getter)) {
                return $this->$getter() !== null;
            }
        }
        
        return isset($this->attrs[$name]);
    }
    
    /**
     * 释放属性
     *
     * @param string $name 属性名
     * @return \wf\model\Model
     * @throws \wf\model\Exception
     */
    public function __unset($name) 
    {
        // 存在setter
        $setter = 'set' . ucfirst($name);
        if (method_exists($this, $setter)) {
            return $this->$setter(null);
        }
        
        $name = strtolower($name);

        // 字段-属性映射
        if($this->fieldMap && key_exists($name, $this->fieldMap)) {
            $attr = $this->fieldMap[$name];
            $setter = 'set' . $attr;
            if(method_exists($this, $setter)) {
                $this->$setter(null);
                return $this;
            }
            // 已声明非public属性外部只允许通过setter/getter访问
            throw new Exception("Property '{$name}' => '{$attr}' access denied.");
        }
        
        // 不存在映射字段则unset动态属性
        unset($this->attrs[$name]);
        
        return $this;
    }
    
    /**
     * 动态访问属性
     * @param string $name
     * @param mixed $args
     * @throws \BadMethodCallException
     */
    public function __call($name, $args = []) 
    {
        $message = 'Not exists method called: ' . get_called_class() . '::'.$name.'()';
        throw new \BadMethodCallException($message);        
    }
    
    /**
     * 获取一个属性的值
     * @param string $field
     * @return mixed
     * @throws \wf\model\Exception
     */
    protected function &getAttrVal($name) {
        if (property_exists($this, $name)) {
            // 已定义私有属性，外部只允许通过getter读取
            $getter = 'get' . ucfirst($name);
            if (method_exists($this, $getter)) {
                return $this->$getter();
            }
            // no getter
            throw new Exception("Property '{$name}' access denied.");
        }

        $name = strtolower($name);

        if($this->fieldMap && key_exists($name, $this->fieldMap)) {
            $attr = $this->fieldMap[$name];
            $getter = 'get' . $attr;
            // 已定义私有属性，外部只允许通过getter读取
            if(method_exists($this, $getter)) {
                return $this->$getter();
            }
            // 已声明非public属性外部只允许通过setter/getter访问
            throw new Exception("Property '{$name}' => '{$attr}' access denied.");
        } elseif(!isset($this->attrs[$name])) {
            $this->attrs[$name] = null;
        }

        // Indirect modification of overloaded property XXX has no effect 解决办法：
        // 1、直接返回属性$this->attrs[$name]
        //    不能返回重新定义的变量，否则 $m->aa['xx'] = 123 赋值出错
        //    为null时，先$this->attrs[$name] = null在返回，不能直接返回 null值，否则当 $m->aa 未定义时，$m->aa['xx'] = 123 赋值出错
        // 2、给方法加上引用
        return $this->attrs[$name];
    }
    
    /**
     * 设置一个属性的值
     * @param string $name
     * @param mixed $value
     * @return \wf\model\Model
     * @throws \wf\model\Exception
     */
    protected function setAttrVal($name, $value) {
        // 数据保护
        if ($this->isInstanceApart && $this->isLoaded()) {
            $pk = $this->getPk();
            if((is_array($pk) && array_key_exists(strtolower($name), $pk) && $this->getPkv()[$name] != $value)
                || (is_scalar($pk) && strtolower($name) == strtolower($pk) && $this->getPkv() != $value)) {
                $msg = "The primarykey '{$name}' is readonly, if you want to change "
                    . "it In any case, you must new another instance of " . get_class($this)
                    . " or set the 'isInstanceApart' as false.";
                throw new Exception($msg);
            }

        }

        if (property_exists($this, $name)) {
            // 已定义私有属性，只允许通过setter方法来设置值
            $setter = 'set' . $name;
            if(method_exists($this, $setter)) {
                $this->$setter($value);
                return $this;
            }
            // no setter
            throw new Exception("Property '{$name}' access denied");
        }
        
        $name = strtolower($name);
        
        // 表字段有对应已定义模型类属性
        if($this->fieldMap && array_key_exists($name, $this->fieldMap)) {            
            $attr = $this->fieldMap[$name];
            $setter = 'set' . $attr;
            // 非公有属性只允许通过setter访问
            if(method_exists($this, $setter)) {
                $this->$setter($value);
                return $this;
            }
            // 已声明非public属性外部只允许通过setter/getter访问
            throw new Exception("Property '{$name}' => '{$attr}' access denied.");
        } else {
            $this->attrs[$name] = $value;
        }
        
        return $this;
    }
    
    /**
     * 设置模型主键值
     * 
     * @param string|array $pkv 主键值，如果是多个字段构成的主键，则使用关联数组结构，如: $pkv = ['pk1' => 123, 'pk2' => 'value', ...]
     * @throws \wf\model\Exception
     * @return \wf\model\Model
     */
    public function setPkv($pkv) {        
        if (is_scalar($pkv)) {
            $this->setAttrVal($this->getPk(), $pkv);
        } elseif (is_array($pkv)) {
            foreach ($this->getPk() as $pkItem) {
                $this->setAttrVal($pkItem, $pkv[$pkItem]);
            }
        } else {
            throw new Exception('object or resource is not allow for param $pkv of '.get_called_class().'::->setPkv($pkv)');
        }
        
        return $this;
    }
            
    /**
     * 从持久层加载模型数据,根据主键加载
     * @throws \wf\model\Exception
     * @return bool
     */
    public function load() {
        return $this->loadBy($this->pkvWhere());
    }
    
    /**
     * 根据条件加载实例
     * @param array $whereArr = []
     * @param string $order = '' 排序
     * @throws \wf\model\Exception
     * @return boolean
     */
    public function loadBy(array $whereArr = [], $order = '') {
        if (empty($whereArr)) {
            throw new Exception('The $whereArr param format error in '.get_called_class().'::loadBy($whereArr)!');
        }

        $array = $this->find(['where' => $whereArr, 'order' => $order])->fetchRow();
        
        if($array) {
            $this->fromArray($array);
            $this->setLoaded();
            return true;
        }
        
        return false;
    }
    
    /**
     * 模型加载数据后，必须设置当前实例加载的实例的主键值才被视为已加载
     * @return \wf\model\Model
     */
    protected function setLoaded() {
        $this->loadedPkv = $this->getPkv();
        return $this;
    }
    
    /**
     * 从数组加载实例数据 ,<br />
     * @param array $array
     * @param bool $setLoaded = false 是否设置实例为已加载
     * @return \wf\model\Model
     */
    public function fromArray($array, $setLoaded = false) {
        foreach ($array as $field => $value) {
            $field = strtolower($field);
            $this->setAttrVal($field, $value);
        }
        
        if ($setLoaded) {
            $this->setLoaded();
        }
        
        return $this;
    }
        
    /**
     * 是否存在该实例的持久信息
     * 
     * @throws \wf\model\Exception
     * @return bool
     */
    public function isExist() {
        if ($this->isLoaded()) {
            return  true;
        }
        
        return (bool)$this->find(['where' => $this->pkvWhere()])->fetchCount();
    }
    
    /**
     * 获取对象实例的主键值
     * @return mixed 如果是多个字段构成的主键，将返回数组结构的值，如: $pkv = ['pk1' => 123, 'pk2' => 'y', ...]
     */
    public function getPkv() {
        $pk = $this->getPk();
        if (is_array($pk)) {
            $pkv = [];
            foreach ($pk as $pkItem) {
                $val = $this->getAttrVal($pkItem);
                if ($val === null) {
                    return null;
                }
                $pkv[$pkItem] = $val;
            }
        } else {
            $pkv = $this->getAttrVal($pk);
        }
        
        return $pkv;
    }
    
    /**
     * 获取主键名
     * @return string|array
     */
    public function getPk() {
        return $this->tableSchema['pk'];
    }
    
    /**
     * 将实体对象转成数组型供调用属性数据
     * 建议直接用对象访问数据，尽可能少用转换成数组的方式获取数据。
     * @return array
     */
    public function toArray() {
        $arr = [];
        // 从保存未定义属性的变量中读取字段kv
        foreach ($this->attrs as $field => $value) {
            if ($this->ignoreNull && $value === null) {
                continue;
            }
            $arr[strtolower($field)] = $value;
        }
        
        // 从指定的属性中读取字段kv
        foreach ($this->fieldMap as $field => $attr) {
            if ($this->ignoreNull && $attr === null) {
                continue;
            }
            
            if (isset($this->$attr)) {
                $arr[strtolower($field)] = $this->$attr;
            } else {
                unset($arr[strtolower($field)]);
            }
        }
        
        return $arr;
    }
    
    /**
     * 删除一个持久化实体记录
     *
     * @return bool|int
     */
    public function delete() {
        return $this->deleteBy($this->pkvWhere());
    }
    
    /**
     * 根据条件删除实例
     * @param array $whArr
     * @throws Exception
     * @return boolean
     */
    public function deleteBy($whArr = []) {
        $where = \wf\db\QueryBuilder::whereArr($whArr ? $whArr : $this->pkvWhere());
        if(!trim($where)) {
            throw new Exception('请传入删除记录的条件'); 
        }
        
        $exe = $this->getDb()->exec("DELETE FROM %t WHERE %x", [$this->table, $where]);

        if (false === $exe) {
            throw new \wf\model\Exception($this->getDb()->getLastErr());
        }
        
        return $exe;
    }
    
    /**
     * @throws \wf\db\Exception
     */
    public function create() {    
        $data = $this->toArray();
        
        $arg = [$this->table, $this->fieldSet($data)];
        $sql = "INSERT INTO %t SET %x";
        $exe = $this->getDb()->exec($sql, $arg);
        
        if (false === $exe) {
            throw new \wf\model\Exception($this->getDb()->getLastErr());
        }
        
        // 插入数据库成功后设置主键值
        $pkv = null;
        
        if ($this->tableSchema['ai']) {
            // 自增主键
            $pkv = $this->getDb()->lastInsertId();
        } else if (is_array($this->tableSchema['pk'])) {
            // 多个字段主键
            $pkv = [];
            foreach ($this->tableSchema['pk'] as $pk) {
                if (isset($this->$pk)) {
                    $pkv[$pk] = $this->$pk;
                }
            }
        } else if (!empty($this->tableSchema['pk'])) {
            // 非自增单字段主键
            $pkv = $this->getAttrVal($this->tableSchema['pk']);
        }
        
        $this->setPkv($pkv)->setLoaded();
        
        return $pkv;        
    }


    /**
     * @throws \wf\db\Exception
     * @return mixed
     */
    public function replace() {
        $data = $this->toArray();
        
        $arg = [$this->table, $this->fieldSet($data)];
        $sql = "REPLACE INTO %t SET %x";
        $exe = $this->getDb()->exec($sql, $arg);

        if (false === $exe) {
            throw new \wf\model\Exception($this->getDb()->getLastErr());
        }
        
        $pkv = null;
    
        if (is_array($this->tableSchema['pk'])) {
            // 多个字段主键
            $pkv = [];
            foreach ($this->tableSchema['pk'] as $pk) {
                if (isset($this->$pk)) {
                    $pkv[$pk] = $this->$pk;
                }
            }
        } else if (!empty($this->tableSchema['pk'])) {
            // 非自增单字段主键
            $pkv = $this->getAttrVal($this->tableSchema['pk']);
        }
    
        $this->setPkv($pkv);
    
        return $pkv;
    }
    
    /**
     * 更新记录
     */
    public function update() {
        return $this->updateBy($this->toArray(), $this->pkvWhere());
    }
    
    /**
     * 模型数据保存
     * 数据是从持久层加载则更新，否则插入新记录
     * @return bool
     */
    public function save() {
        if($this->isLoaded()) {
            // 更新记录
            return (bool)$this->update();
        } else {
            // 新增记录
            return (bool)$this->create();
        }
    }
    
    /**
     * 根据主键作为条件/传递给数据访问层（进行删改读操作）的默认条件
     * @throws \wf\model\Exception
     * @return array
     */
    protected function pkvWhere() {
        $this->checkPkv();
        if (is_array($this->getPk())) {
            if (is_scalar($this->getPkv())) {
                throw new Exception('Error type of '.get_called_class().'::$id, it mast be array');
            }
            
            $whereArr = [];
            foreach ((array)($this->getPkv()) as $pk => $pv) {
                $whereArr[] = [$pk, $pv, '='];
            }
        } else {
            $whereArr = [$this->getPk(), $this->getPkv(), '='];
        }
        
        return $whereArr;
    }
    
    /**
     * 查询获取模型表记录
     * @param array $opts = [] 查询选项(详看\wf\db\QueryBuilder::buildQueryOptions())
     * @return \wf\db\Finder
     */
    public function find($opts = []) {
        empty($opts['table']) && $opts['table'] = $this->table;
        
        $obj = new \wf\db\Finder($opts);
        $obj->setDb($this->getDb());
        
        return $obj;
    }
    
    /**
     * 根据条件更新表数据
     * @param array $data kv数组
     * @param array $whArr 条件数组
     * @return number
     * @throws \wf\model\Exception
     */
    public function updateBy($data, $whArr) {
        $where = \wf\db\QueryBuilder::whereArr($whArr);
        
        if (empty($where)) {
            throw new Exception('The $whereArr param format error!');
        }

        // 坚决不允许修改uuid值
        unset($data['uuid']); 
        
        // 不允许修改主键值
        foreach ((array)($this->getPk()) as $pk) {
            unset($data[$pk]);
        }
        
        $arg = [$this->table, $this->fieldSet($data), $where];
        $exe = $this->getDb()->exec("UPDATE %t SET %x WHERE %x", $arg);

        if (false === $exe) {
            throw new \wf\model\Exception($this->getDb()->getLastErr());
        }
        
        return $exe;
    }
        
    /**
     * 保存指定的属性（字段）值
     * @param string $fields 字段名列表，多个字段以逗号隔开
     * @return boolean
     * @throws \wf\model\Exception
     */
    public function saveFields($fields) {
        $fieldArr = explode(',', str_replace(' ', '', $fields));
        $update = [];

        foreach ($fieldArr as $field) {
            $update[$field] = $this->$field;
        }
        
        $arg = [$this->table, $this->fieldSet($update), \wf\db\QueryBuilder::whereArr($this->pkvWhere())];
        $exe = $this->getDb()->exec("UPDATE %t SET %x WHERE %x", $arg);

        if (false === $exe) {
            throw new \wf\model\Exception($this->getDb()->getLastErr());
        }
        
        return $exe;
    }
        
    /**
     * 检查主键及主键值是否已设置
     * @throws \wf\model\Exception
     */
    protected function checkPkv() {
        if (!$this->getPk() || null === $this->getPkv()) {
            throw new Exception('Please set the model\'s primary key and primary keys value');
        }
        
        return true;
    }
    
    /**
     * 从数组的下标对应的值中获取SQL的"字段1=值1,字段2=值2"的结构
     * @param array $data
     * @throws \wf\model\Exception
     * @return string 返回 "`f1` = 'xx', `f2` = 'xxx'"
     */
    protected function fieldSet(array $data) {
        if (!$this->tableSchema['field']) {
            throw new Exception('请在' . get_class($this) . '构造函数中调用父类的构造函数');
        }
        return \wf\db\QueryBuilder::buildSqlSet($data, $this->tableSchema['field'], $this->lockedFields);
    }

    /**
     * 添加锁定字段，锁定字段后，不保添加/更新字段的值到数据库。
     * @param string $fields 字段名，用半角逗号隔开
     * @return \wf\model\Model
     */
    public function addLockFields($fields) {
        $fields = explode(',', str_replace(' ', '', strtolower($fields)));
        $this->lockedFields = array_merge($this->lockedFields, $fields);
        return $this;
    }
    
    /**
     * 去掉锁定字段
     * @param string $fields
     * @return \wf\model\Model
     */
    public function removeLockFields($fields) {
        $fields = explode(',', str_replace(' ', '', strtolower($fields)));
        foreach ($fields as $field) {
            if (false !== ($fieldIndex = array_search($field, $this->lockedFields))) {
                unset($this->lockedFields[$fieldIndex]);
            }
        }
        
        return $this;
    }

    /**
     * 是否已加载实例
     * @return bool
     */
    public function isLoaded() {
        return $this->loadedPkv && $this->loadedPkv == $this->getPkv();
    }
    
    /**
     * 当前模型数据表
     */
    public function getTable() {
        return $this->table;
    }
    
    /**
     * 获取数据库访问对象实例
     */
    public function getDb() {
        if (!$this->db) {
            $this->db = \wfDb();
        }
        
        return $this->db;
    }
    
    /**
     * 设置数据库访问对象实例
     * @param \wf\db\DBInterface $db
     * @return \wf\model\Model
     */
    public function setDb(\wf\db\DBInterface $db) {
        $this->db = $db;
        
        return $this;
    }

    /**
     * 添加/修改时验证数据规则
     * @see \wf\util\Validator::validate()
     */
    public function validRules() {
        return [];
    }

    /**
     * 检验属性值是否正确匹配$this->validRules()中设置的规则
     * @return bool
     */
    public function doValidRules()
    {
        return parent::validate($this->toArray(), $this->validRules());
    }

    /**
     * 实例是否隔离，一旦隔离后，一个实例加载（映射）记录后，将不允许再加载另外一条数据
     * （数据加载后不允许修改主键值），从而避免主键值可随意修改导致加载的记录和更新的记录
     * 不是同一条记录
     * @param bool $isInstanceApart
     */
    public function setIsInstanceApart($isInstanceApart)
    {
        $this->isInstanceApart = $isInstanceApart;
    }
}
