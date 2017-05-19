<?php
/**
 * Windwork
 * 
 * 一个开源的PHP轻量级高效Web开发框架
 * 
 * @copyright Copyright (c) 2008-2017 Windwork Team. (http://www.windwork.org)
 * @license   http://opensource.org/licenses/MIT
 */
namespace wf\model;

/**
 * 模型基类，不包含访问数据库的逻辑
 *
 * @package     wf.core
 * @author      cm <cmpan@qq.com>
 * @link        http://docs.windwork.org/manual/wf.model.html
 * @since       0.1.0
 */
abstract class Model
{
    /**
     * 错误信息
     * @var array
     */
    protected $errs = [];
    
    /**
     * 获取错误信息
     *
     * @return array
     */
    public function getErrs()
    {
        return $this->errs;
    }
    
    /**
     * 获取最后一个错误的内容
     *
     * @return string
     */
    public function getLastErr()
    {
        $err = end($this->errs);
        reset($this->errs);
        
        return $err;
    }
    
    /**
     * 是否有错误
     *
     * @return bool
     */
    public function hasErr()
    {
        return empty($this->errs) ? false : true;
    }
    
    /**
     * 设置错误信息
     *
     * @param string|array $msg
     * @return \wf\core\Object
     */
    public function setErr($msg)
    {
        $this->errs = array_merge($this->errs, (array)$msg);
    
        return $this;
    }
    
    /**
     * 重置错误信息
     * @return \wf\model\ModelBase
     */
    public function resetErr() {
        $this->errs = [];
        
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
     * @param bool $firstErrBreak = false 是否在验证出现第一次不符合规则时返回，为false则验证所有规则
     * @return bool 验证是否通过
     */
    protected function validate(array $data, array $validRules, $firstErrBreak = false) {
        if (!$validRules) {
            return true;
        }
        
        $validObj = new \wf\util\Validator();
        $validResult = $validObj->validate($data, $validRules, $firstErrBreak);
        
        if (!$validResult) {
            $this->setErr($validObj->getErrs());
            return false;
        }
        
        return true;
    }
}
