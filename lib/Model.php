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
 * @package     wf.model
 * @author      cm <cmpan@qq.com>
 * @link        http://docs.windwork.org/manual/wf.model.html
 * @since       0.1.0
 */
abstract class Model
{
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
     * @param bool $firstErrBreak = false 是否在验证出现第一次不符合规则时返回，为false则验证所有规则
     * @return bool 验证是否通过
     */
    protected function validate(array $data, array $validRules) {
        if (!$validRules) {
            return true;
        }
        
        $validObj = new \wf\util\Validator();
        $validResult = $validObj->validate($data, $validRules, true);
        
        if (!$validResult) {
            $this->setError($validObj->getErrs()[0]);
            return false;
        }
        
        return true;
    }
}
