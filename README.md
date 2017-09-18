Windwork 模型组件
=========================
Windwork模型基类\wf\model\Business不包含数据库库的访问。
Windwork领域模型\wf\model\Model可访问数据库。

Windwork Model领域模型有如下特性：
- 是一种领域模型(Domain Model)，业务逻辑代码封装在模型类中，并通过模型访问数据，即模型层的职责是负责业务逻辑和数据访问。；
- 每一个数据表对应一个模型类，类的每一个对象实例对应于数据库中表的一行记录；通常表的每个字段在类中都有相应的属性；
- 将记录字段映射到模型的属性；
- 在模型基类中封装了对数据库的访问，即CURD，负责把自己持久化；


## 安装
该组件已包含在Windwork框架中，如果你已安装Windwork框架则可以直接使用。

- 安装方式一：通过composer安装（推荐）  
```
composer require windwork/wf
```

- 安装方式二：传统方式安装  
[下载源码](https://github.com/windwork/wf/releases)后，解压源码到项目文件夹中，然后require_once $PATH_TO_WF/core/lib/Loader.php文件，即可自动加载组件中的类。

## 模型约定

```
// 启用应用模块
文件夹为：app/{$mod}/model/
命名空间：app\{$mod}\model
// 不启用模块
文件夹为：app/model/
命名空间：app\model

类命名为：XxxModel,类名以大写开头，Model作为后缀，使用驼峰命名规则。
文件名为：XxxModel.php,类名后面加.php，大小写敏感；
需要继承：需要数据库读写的模型继承\wf\model\Model类，实现业务逻辑但不需要数据存取的继承\wf\model\Business类；
对应表类：protected $table = '数据表名称';
```

## 字段映射
加载数据到模型后，将自动映射到模型属性。

user表
```
CREATE TABLE `user` (
  `uid` int(11) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `uname` varchar(32) NOT NULL DEFAULT '',
  `upass` varchar(32) NOT NULL DEFAULT '',
  `email` varchar(32) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

User模型
```
namespace app\user\model;

class UserModel extends \wf\model\Model {    
    protected $table = 'user';
}

```

user 表映射到UserModel的属性
```
$user = new \app\user\model\UserModel();

// 加载uid=1的用户信息
if($user->setPkv(1)->load()) {
    // 模型加载一条记录后，自动映射到属性，我们可以通过属性来获取表中对应字段的值
    echo $user->uname; // 输出uname属性（字段）值
    echo $user->email; // 输出email属性（字段）值
} else {
    print '该用户不存在';
}

```

### 显式字段映射
前面的案例是隐式映射表字段，即没有声明模型属性，自动把表字段映射成模型的属性。
如果已设置模型属性，我们需要设置属性对应的字段进行显式映射。
显式映射后，在模型数据从数据库加载时自动映射到类的属性；保存模型数据到数据库时，自动从模型属性获取数据存入数据库。

例如：
```
namespace app\user\model;

class UserModel extends \wf\model\Model {    
    protected $table = 'user';    
    
    /**
     * 属性和表字段的绑定
     * 
     * 设置表字段对应模型类的属性，以实现把类属性绑定到表字段，并且Model->toArray()方法可获取绑定属性的值。
     * 表字段名不分大小写，属性名大小写敏感。
     * 
     * @var array = [
     *     '表字段1' => '属性1',
     *     '表字段2' => '属性2',
     *     ...
     * ]
     */
    protected $fieldMap = [
        'uname' => 'userName', // 把字段uname映射到name属性，属性名和字段名可以不一样
    ];

    private $userName;
}
```



## 数据访问

### 模型封装数据访问方法：
Model::load(): 从数据库加载模型一条记录，并将记录映射到模型属性，执行前需设置主键值；
Model::create(): 添加/新增模型数据到数据库
Model::update()：更新模型数据，执行前需先加载模型实体数据
Model::delete()：更新模型数据，执行前需先设置主键
Model::save()：保存模型数据，如果已加载数据则修改，否则创建
Model::replace()：替换方式保存模型数据
Model::updateBy($cdt)：根据条件更新记录字段值；
Model::deleteBy($cdt)：根据条件删除记录；
Model::find()：数据库查询器，查询数据库记录集
### 原生SQL读写数据库
在模型中可执行原生SQL，通过 $this->getDb()获取数据库操作对象，更详细见 [数据库操作组件](http://docs.windwork.org/manaul/wf.db.html)，数据库操作对象可执行如下方法进行数据库读写：
```    
    /**
     * 获取所有记录
     * 
     * @param string $sql
     * @param array $args = [] sql格式化参数值列表
     * @param bool $allowCache
     */
    public function getAll($sql, array $args = [], $allowCache = false);
    
    /**
     * 获取第一列
     * 
     * @param string $sql
     * @param array $args = []  sql格式化参数值列表
     * @param bool $allowCache
     */
    public function getRow($sql, array $args = [], $allowCache = false);
            
    /**
     * 获取第一列第一个字段
     * 
     * @param string $sql
     * @param array $args =[]  sql格式化参数值列表
     * @param bool $allowCache
     */
    public function getColumn($sql, array $args = [], $allowCache = false);

    /**
     * 执行写入SQL
     * 针对没有结果集合返回的写入操作，
     * 比如INSERT、UPDATE、DELETE等操作，它返回的结果是当前操作影响的列数。
     * 
     * @param string $sql
     * @param array $args = []  sql格式化参数值列表
     * @throws \wf\db\Exception
     * @return int
     */
    public function exec($sql, array $args = []);
    
    /**
     * 取得上一步 INSERT 操作产生的 ID
     *
     * @return string 
     */
    public function lastInsertId();
    
    /**
     * 插入多行数据
     * 过滤掉没有的字段
     *
     * @param array $rows
     * @param string $table  插入表
     * @param array $fields  允许插入的字段名
     * @param string $isReplace = false 是否使用 REPLACE INTO插入数据，false为使用 INSERT INTO
     */
    public function insertRows(array $rows, $table, $fields = [], $isReplace = false);
    
```
例如：
- 获取多条记录 $this->getDb()->getAll($sql);
- 获取一条记录 $this->getDb()->getRow($sql);
- 获取一条记录的第一列的值 self::getDb()->getColumn($sql);

#### SQL防注入
直接使用SQL容易产生注入的安全问题，我们封装了SQL格式化的方式防注入。

以%作为标识，%后面的字符为格式化参数的数据类型。支持的类型有：
- %t:表名； 
- %a：字段名；  
- %n:数字值；
- %i：整形；
- %f：浮点型； 
- %s：字符串值; 
- %x:保留不处理

数据库操作类执行SQL的方法都支持SQL格式化。
```
// 从user表获取10个 is_valid = 1 的用户
$sql = "SELECCT * from %t WHERE is_valid = %i LIMIT 0,10";
$args = [
  'user', // %t 参数的值
  1,// %i 参数的值
];
$this->getDb()->getAll($sql, $args);

```


### 使用事务
使用事务的前提是：你使用的引擎必须支持事务。MyISAM、MEMORY引擎不支持事务，InnoDB引擎支持事务。MySQL经过多年的发展，InnoDB引擎已经是MySQL引擎中最有优势的引擎，所以推荐你优先使用InnoDB引擎。

可以嵌套启用事务，最终只在最上一级事务提交后才会真正执行事务。
数据库操作失败时将抛出异常，我们可以捕捉到异常后回滚事务，如果调用的模型是输入验证而非数据库操作错误，在事务链中建议使用抛出异常的方式，如果模型方法中不抛出异常，我们可以在事务操作逻辑中获取模型错误信息再抛出异常。

```
namespace app\user\model;

class UserModel extends \wf\model\Model 
{    
    protected $table = 'user';

    public function create() 
    {
        $trans = $this->getDb()->beginTransaction();
        try {
            parent::create();
            // 其他数据库写入业务
            // ……

            // 没异常则提交事务
            $trans->commit();
        } catch(\Exception $e) {
            // 出现异常则回滚事务
            $trans->rollback();
        }
    }
}

```



## 模型表关联
为了保持代码简单直观，对于数据表关联的关系，我们没有实现到模型上进行自动处理，每个模型的表有关联数据表，需程序员自己实现业务处理。

## 服务层
一般情况下我们的业务逻辑是写在模型中，但如果逻辑很复杂的时候，模型代码越来越多，就会变得不可控。为解决这个问题，我们可以增加服务层。服务层不直接操作数据库，而是负责业务逻辑的处理，把数据存取的职责交给模型，服务从多个模型调用数据来完成业务逻辑。

### 服务层约定

- 启用应用模块时
  - 文件夹为：app/{$mod}/service/
  - 命名空间：app\{$mod}\service
- 不启用应用模块时
  - 文件夹为：app/service/
  - 命名空间：app\service
- 类命名为 XxxService，类名以大写开头，Service作为后缀，使用驼峰命名规则。
- 文件名为 XxxService.php,类名后面加.php，大小写敏感；
- 需要继承 wf\model\Model 类



### 服务层实例
```
// app/trade/service/OrderService.php
namespace app\trade\service;

class OrderService extends wf\model\Model
{
    // 确认下单
    public function createOrder($attr)
    {
        // 
    }
}
```


<br />  
<br />  

### 要了解更多？  
> - [官方完整文档首页](http://docs.windwork.org/manual/)  
> - [官方源码首页](https://github.com/windwork)  
