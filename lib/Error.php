<?php
/**
 * Windwork
 * 
 * 一个用于快速开发高并发Web应用的轻量级PHP框架
 * 
 * @copyright   Copyright (c) 2008-2016 Windwork Team. (http://www.windwork.org)
 * @license   http://opensource.org/licenses/MIT
 */
namespace wf\model;

/**
 * 模型错误信息类
 * 
 * @package     wf.model
 * @author      erzh <cmpan@qq.com>
 * @link        http://docs.windwork.org/manual/wf.model.error.html
 * @since       0.1.0
 */
class Error 
{
    /**
     * 消息状态码
     * @var code
     */
    private $code = 0;
    
    /**
     * 消息内容
     * @var string
     */
    private $message = '';

    /**
     * 默认错误状态码
     * @var int
     */
    const DEFAULT_MODEL_ERROR_CODE = 90000;
    
    /**
     * 错误状态码
     * @return int
     */
    public function getCode() 
    {
        return $this->code;
    }
    
    /**
     * 错误消息内容
     * @return string
     */
    public function getMessage() 
    {
        return $this->message;
    }
    
    /**
     * 
     * @param string|array $msg
     * @param int $code = 90000 错误码
     * @return \wf\model\Error
     */
    public function __construct($msg, $code = Error::DEFAULT_MODEL_ERROR_CODE)
    {
        $this->code = $code;
        $this->message = $msg;
    }
}
