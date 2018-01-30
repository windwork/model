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
 * ## 关于模型数据
 * - 未声明模型属性保存到Model::$attrs中
 * - Model::$attrs已定义元素才能访问，未定义抛出异常
 * - 表字段-模型属性映射：
 *   1）数据保存在模型对象属性；
 *   2）字段名方式访问则指向对象属性，不管属性是不是public类型都能访问；
 * - 重载属性与私有属性冲突，如果重载属性与私有属性同名，则重载访问属性时抛出异常。
 * - 不自动通过setter/getter访问重载属性元素，如访问$m->attr，重载获取数据时不去调用$m->getAttr()
 *
 * @package     wf.model
 * @author      cm <cmpan@qq.com>
 * @link        http://docs.windwork.org/manual/wf.model.html
 * @since       0.1.0
 */
class Model {
    /**
     * 模型对应数据表名
     *
     * @var string = ''
     */
    protected $table = '';

    /**
     * 是否是数据模型
     * - 只有在实现类中定义模型的$table属性的初始值，才是数据模型
     * - 数据模型绑定对应的数据库实例，可调用模型实例本身的CRUD操作
     * - 非数据模型不能调用模型实例本身的CRUD操作
     * @var bool
     */
    private $isDataModel = true;

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
     * 表字段绑定到显式声明的属性
     *
     * - 设置表字段对应模型类的属性，以实现把类属性绑定到表字段，并且Model->toArray()方法可获取绑定属性的值。
     * - 表字段名和属性名都是大小写敏感。
     * - 字段绑定属性后，属性值必能访问，可通过“$obj->字段名”或“$obj->属性名”访问。
     * <pre>格式为：
     * [
     *     '表字段1' => '属性1',
     *     '表字段2' => '属性2',
     *     ...
     * ]</pre>
     * @var array = []
     */
    protected $columnMap = [];

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
     * 实例是否隔离
     * 一旦隔离后，一个实例加载（映射）记录后，将不允许再加载另外一条数据
     * （数据加载后不允许修改主键值），从而避免主键值可随意修改导致加载的记录和更新的记录
     * 不是同一条记录
     * @var bool
     */
    protected $isInstanceApart = true;


    /**
     * 错误信息
     * @var \wf\model\Error
     */
    protected $error;

    /**
     * 获取错误信息
     *
     * @return \wf\model\Error
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * 是否有错误
     *
     * @return bool
     */
    public function hasError()
    {
        return isset($this->error);
    }

    /**
     * 重置错误信息（将错误信息属性设为null）
     * @return \wf\model\Model
     */
    public function resetError()
    {
        $this->error = null;
        return $this;
    }

    /**
     * 设置错误信息
     *
     * @param string|\wf\model\Error $error 错误消息内容
     * @param int $code = 90000 错误码，如果$error参数是\wf\model\Error实例，则忽略此参数
     * @return \wf\model\Model
     */
    public function setError($error, $code = \wf\model\Error::DEFAULT_MODEL_ERROR_CODE)
    {
        if ($error instanceof \wf\model\Error) {
            $this->error = $error;
        } elseif (is_scalar($error)) {
            $this->error = new \wf\model\Error($error, $code);
        } else {
            throw new \InvalidArgumentException('错误的消息类型');
        }

        return $this;
    }

    /**
     * 验证输入规则
     *
     * 如果验证不符合规则，则将错误信息赋值给模型错误信息属性
     *
     * @param array $data 待验证数据，[属性 => 值] 结构
     * @param array $validRules 支持的验证方法请参看\wf\util\Validator类
     * <pre>
     *   验证规则格式：[
     *   &nbsp;&nbsp;'待验证属性下标' => [
     *   &nbsp;&nbsp;&nbsp;&nbsp;'验证方法1' => '提示信息1',
     *   &nbsp;&nbsp;&nbsp;&nbsp;'验证方法2' => '提示信息2'
     *   &nbsp;&nbsp;&nbsp;&nbsp;...
     *   &nbsp;&nbsp;],
     *   &nbsp;&nbsp;...
     *   ]
     * </pre>
     * @return bool 验证是否通过
     */
    protected function validate(array $data, array $validRules)
    {
        if (!$validRules) {
            return true;
        }

        $validObj = new \wf\util\Validator();
        $validResult = $validObj->validate($data, $validRules, true);

        if (!$validResult) {
            $this->setError($validObj->getErrors()[0]);
            return false;
        }

        return true;
    }

    /**
     * 初始化表对象实例
     *
     * 约定：如果集成模型基类后重写构造函数，必须在构造函数中调用父类的构造函数 parent::__construct();
     *
     * @throws \wf\model\Exception
     */
    public function __construct()
    {
        if (!$this->table) {
            $this->isDataModel = false;
            return;
        }

        // 获取表结构并缓存
        if (!function_exists('wfCache') || !$this->tableSchema = (wfCache()->read("model/table_schema/{$this->table}"))) {
            // 自动加载表信息（字段名列表、主键、主键是否自增）
            $tableSchema = $this->getDb()->getTableSchema($this->table);
            is_array($tableSchema['pk']) && sort($tableSchema['pk']);

            // tableSchema
            $this->tableSchema['field']  = array_keys($tableSchema['field']); // 表字段名列表
            $this->tableSchema['pk']     = $tableSchema['pk']; // 表主键名，已转为小写，如果是多个字段的主键，则为['主键1', '主键2']
            $this->tableSchema['ai']     = $tableSchema['ai'];

            if (function_exists('wfCache')) {
                wfCache()->write("model/table_schema/{$this->table}", $this->tableSchema);
            }
        }

        // 新增记录自动增长主键不允许设置值
        if($this->tableSchema['ai']) {
            $this->addLockFields($this->tableSchema['pk']);
        }
    }

    /**
     * 调用不可访问方法的处理
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
     * 获取属性
     *
     * @param string $name 获取的属性名或属性名列表
     * @return mixed
     * @throws \wf\model\Exception
     */
    public function &__get($name)
    {
        return $this->getAttrVal($name);
    }

    /**
     * 设置属性
     *
     * @param string $name
     * @param mixed $val
     * @return \wf\model\Model
     */
    public function __set($name, $val)
    {
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
        // 存在字段映射，则被映射的对象属性必能访问
        if($this->columnMap && key_exists($name, $this->columnMap)) {
            // 显式声明属性名
            $attr = $this->columnMap[$name];
            return !is_null($this->$attr);
        }

        return key_exists($name, $this->attrs);
    }

    /**
     * 释放属性
     *
     * 已声明属性设为null，重载属性unset
     *
     * @param string $name 属性名
     * @return \wf\model\Model
     * @throws \wf\model\Exception
     */
    public function __unset($name)
    {
        // 字段-属性映射
        if($this->columnMap && key_exists($name, $this->columnMap)) {
            $attr = $this->columnMap[$name];
            $setter = 'set' . $attr;

            // 有setter则通过setter赋null值，否则直接赋null值
            if(method_exists($this, $setter)) {
                $this->$setter(null);
            } else {
                $this->$attr = null;
            }

            return $this;
        } else {
            // 不存在映射字段则unset动态属性
            unset($this->attrs[$name]);
        }

        return $this;
    }

    /**
     * 获取一个属性的值
     *
     * （Indirect modification of overloaded property XXX has no effect 解决办法：给方法加上引用）
     *
     * @param string $field
     * @return mixed
     * @throws \wf\model\InvalidArgumentException
     */
    protected function &getAttrVal($name)
    {
        if($this->columnMap && key_exists($name, $this->columnMap)) {
            $attr = $this->columnMap[$name];
            return $this->$attr;
        } elseif(property_exists($this, $name)) {
            // 重载元素时存在同名已声明私有属性名无映射不允许重载
            throw new \InvalidArgumentException('access non public property on __get():' . get_class($this) . '::' . $name);
        } elseif(!key_exists($name, $this->attrs)) {
            // 必须初始化的重载属性才能访问，提升严谨性，同时避免引用方法抛出异常
            throw new \InvalidArgumentException('property not exists:' . get_class($this) . '::' . $name);
        }

        return $this->attrs[$name];
    }

    /**
     * 设置一个属性的值
     * @param string $name
     * @param mixed $value
     * @return \wf\model\Model
     * @throws \wf\model\Exception
     */
    protected function setAttrVal($name, $value)
    {
        // 数据保护，启用数据独立的模型，不允许重新加载数据
        if ($this->isInstanceApart && $this->isLoaded()) {
            $pk = $this->getPk();
            if((is_array($pk) && array_key_exists($name, $pk) && $this->getPkv()[$name] != $value)
                || (is_scalar($pk) && strtolower($name) == strtolower($pk) && $this->getPkv() != $value)) {
                $msg = "The primarykey '{$name}' is readonly, if you are going to change it in any case, "
                    . "you must new another instance of " . get_class($this) . " or set the 'isInstanceApart' property as false.";
                throw new Exception($msg);
            }
        }

        // 表字段有对应已定义模型类属性
        if($this->columnMap && array_key_exists($name, $this->columnMap)) {
            $attr = $this->columnMap[$name];
            $setter = 'set' . $attr;

            if(method_exists($this, $setter)) {
                $this->$setter($value);
            } else {
                $this->$attr = $value;
            }

            return $this;
        } else if (property_exists($this, $name)) {
            // 重载元素时存在同名已声明私有属性名无映射不允许重载赋值
            throw new \InvalidArgumentException('access non public property on __get():' . get_class($this) . '::' . $name);
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
    public function setPkv($pkv)
    {
        if (is_scalar($pkv)) {
            $this->setAttrVal($this->getPk(), $pkv);
        } elseif (is_array($pkv)) {
            foreach ($this->getPk() as $pkItem) {
                $this->setAttrVal($pkItem, $pkv[$pkItem]);
            }
        } else {
            throw new \InvalidArgumentException('object or resource is not allow for param $pkv of '.get_called_class().'::->setPkv($pkv)');
        }

        return $this;
    }

    /**
     * 从持久层加载模型数据,根据主键加载
     * @throws \wf\model\Exception
     * @return bool
     */
    public function load()
    {
        return $this->loadBy($this->pkvAsWhere());
    }

    /**
     * 根据条件加载实例
     * @param array $whereArr = []
     * @param string $order = '' 排序
     * @throws \wf\model\Exception
     * @return boolean
     */
    public function loadBy(array $whereArr = [], $order = '')
    {
        $this->mustBeDataModel();

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
    protected function setLoaded()
    {
        $this->loadedPkv = $this->getPkv();
        if($this->loadedPkv === null) {
            throw new \UnexpectedValueException('The data is not loaded.');
        }
        return $this;
    }

    /**
     * 从数组加载实例数据 ,<br />
     * @param array $array
     * @param bool $setLoaded = false 是否设置实例为已加载
     * @return \wf\model\Model
     */
    public function fromArray($array, $setLoaded = false)
    {
        foreach ($array as $field => $value) {
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
    public function isExist()
    {
        $this->mustBeDataModel();

        if ($this->isLoaded()) {
            return  true;
        }

        return (bool)$this->find([
            'where' => $this->pkvAsWhere()
        ])->fetchCount();
    }

    /**
     * 获取对象实例的主键值
     * @return mixed 如果是多个字段构成的主键，将返回数组结构的值，如: $pkv = ['pk1' => 123, 'pk2' => 'y', ...]
     */
    public function getPkv()
    {
        $pk = $this->getPk();

        try {
            if (is_array($pk)) {
                $pkv = [];
                foreach ($pk as $pkItem) {
                    $val = $this->getAttrVal($pkItem);
                    $pkv[$pkItem] = $val;
                }
            } else {
                $pkv = $this->getAttrVal($pk);
            }
        } catch (\Exception $e) {
            return null;
        }

        return $pkv;
    }

    /**
     * 获取主键名
     * @return string|array
     */
    public function getPk()
    {
        return $this->tableSchema['pk'];
    }

    /**
     * 将实体对象转成数组型供调用属性数据
     * 建议直接用对象访问数据，尽可能少用转换成数组的方式获取数据。
     * @return array
     */
    public function toArray()
    {
        $arr = [];
        // 从保存未定义属性的变量中读取字段kv
        foreach ($this->attrs as $field => $value) {
            $arr[$field] = $value;
        }

        // 从指定的属性中读取字段kv
        foreach ($this->columnMap as $field => $attr) {
            if (property_exists($this, $attr)) {
                $arr[$field] = $this->$attr;
            } else {
                unset($arr[$field]);
            }
        }

        return $arr;
    }

    /**
     * 删除一个持久化实体记录
     *
     * @return bool|int
     */
    public function delete()
    {
        return $this->deleteBy($this->pkvAsWhere());
    }

    /**
     * 根据条件删除实例
     * @param array $whArr
     * @throws \wf\model\Exception
     * @return boolean
     */
    public function deleteBy($whArr = [])
    {
        $this->mustBeDataModel();

        $where = \wf\db\QueryBuilder::whereArr($whArr ? $whArr : $this->pkvAsWhere());
        if(!trim($where)) {
            throw new \InvalidArgumentException('请传入删除记录的条件');
        }

        $exe = $this->getDb()->exec("DELETE FROM %t WHERE %x", [$this->table, $where]);

        if (false === $exe) {
            throw new Exception($this->getDb()->getLastErr());
        }

        return $exe;
    }

    /**
     * @throws \wf\db\Exception
     */
    public function create()
    {
        $this->mustBeDataModel();

        $data = $this->toArray();

        $arg = [$this->table, $this->fieldSet($data)];
        $sql = "INSERT INTO %t SET %x";
        $exe = $this->getDb()->exec($sql, $arg);

        if (false === $exe) {
            throw new Exception($this->getDb()->getLastErr());
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
    public function replace()
    {
        $this->mustBeDataModel();

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
    public function update()
    {
        return $this->updateBy($this->toArray(), $this->pkvAsWhere());
    }

    /**
     * 模型数据保存
     * 数据是从持久层加载则更新，否则插入新记录
     * @return bool
     */
    public function save()
    {
        if($this->isLoaded()) {
            // 更新记录
            return (bool)$this->update();
        } else {
            // 新增记录
            return (bool)$this->create();
        }
    }

    /**
     * 检验必须是数据模型
     * @throws Exception
     */
    public function mustBeDataModel()
    {
        if (!$this->isDataModel) {
            throw new Exception('请设置模型类"' . get_class($this) . '"对应的数据表');
        }
    }

    /**
     * 根据主键作为条件/传递给数据访问层（进行删改读操作）的默认条件
     * @throws \wf\model\Exception
     * @return array
     */
    protected function pkvAsWhere()
    {
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
     * @param array $opts = [] 查询选项
     * @see \wf\db\QueryBuilder::buildQueryOptions()
     * @return \wf\db\Finder
     */
    public function find($opts = [])
    {
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
    public function updateBy($data, $whArr)
    {
        $this->mustBeDataModel();

        $where = \wf\db\QueryBuilder::whereArr($whArr);

        if (empty($where)) {
            throw new Exception('The $whereArr param format error!');
        }

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
    public function saveFields($fields)
    {
        $this->mustBeDataModel();

        $fieldArr = explode(',', str_replace(' ', '', $fields));
        $update = [];

        foreach ($fieldArr as $field) {
            $update[$field] = $this->$field;
        }

        $arg = [
            $this->table,
            $this->fieldSet($update),
            \wf\db\QueryBuilder::whereArr($this->pkvAsWhere())
        ];
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
    protected function checkPkv()
    {
        if (!$this->getPk() || !$this->getPkv()) {
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
    protected function fieldSet(array $data)
    {
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
    public function addLockFields($fields)
    {
        $fields = explode(',', str_replace(' ', '', $fields));
        $this->lockedFields = array_merge($this->lockedFields, $fields);
        return $this;
    }

    /**
     * 去掉锁定字段
     * @param string $fields
     * @return \wf\model\Model
     */
    public function removeLockFields($fields)
    {
        $fields = explode(',', str_replace(' ', '', $fields));
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
    public function isLoaded()
    {
        return $this->loadedPkv && $this->loadedPkv == $this->getPkv();
    }

    /**
     * 当前模型数据表
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * 获取数据库访问对象实例
     */
    public function getDb()
    {
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
    public function setDb(\wf\db\DBInterface $db)
    {
        $this->db = $db;

        return $this;
    }

    /**
     * 添加/修改时验证数据规则
     * @see \wf\util\Validator::validate()
     */
    public function validRules()
    {
        return [];
    }

    /**
     * 检验属性值是否正确匹配$this->validRules()中设置的规则
     * @return bool
     */
    public function runValidRules()
    {
        return $this->validate($this->toArray(), $this->validRules());
    }

    /**
     * 实例是否隔离，一旦隔离后，一个实例加载（映射）记录后，将不允许再加载另外一条数据
     * （数据加载后不允许修改主键值），从而避免主键值可随意修改导致加载的记录和更新的记录
     * 不是同一条记录
     * @param bool $isInstanceApart
     * @return \wf\model\Model
     */
    public function setInstanceApart($isInstanceApart)
    {
        $this->isInstanceApart = $isInstanceApart;
        return $this;
    }
}
