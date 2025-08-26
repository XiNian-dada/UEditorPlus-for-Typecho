<?php
namespace TypechoPlugin\UEditorPlus;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Radio;
use Typecho\Widget\Helper\Form\Element\Select;
use Widget\Options;
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
/**
 * UEditor Plus是基于百度UEditor二开的所见即所得富文本编辑器。
 *
 * @package UEditorPlus for Typecho
 * @author jubaoshou
 * @version 4.1.0
 * @link https://github.com/jubaoshou/UEditorPlus-for-Typecho
 * Date: 2024-10-30
 */
class Plugin implements PluginInterface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        \Typecho\Plugin::factory('admin/write-post.php')->richEditor = __CLASS__ . '::render';
        \Typecho\Plugin::factory('admin/write-page.php')->richEditor = __CLASS__ . '::render';
        
    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate(){}
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Form $form) {
        // WebP转换设置
        $webpEnable = new Radio('webpEnable', array('1' => '启用', '0' => '禁用'), '0', 'WebP转换', '启用后会自动将上传的PNG、JPG图片转换为WebP格式');
        $form->addInput($webpEnable);
        
        $webpQuality = new Text('webpQuality', null, '80', 'WebP质量', '设置WebP图片质量，范围1-100，数值越高质量越好但文件越大');
        $form->addInput($webpQuality);
        
        $webpKeepOriginal = new Radio('webpKeepOriginal', array('1' => '保留', '0' => '删除'), '0', '保留原图', '转换为WebP后是否保留原始图片文件');
        $form->addInput($webpKeepOriginal);
        
        // 水印设置
        $watermarkEnable = new Radio('watermarkEnable', array('1' => '启用', '0' => '禁用'), '0', '图片水印', '启用后会为上传的图片添加水印');
        $form->addInput($watermarkEnable);
        
        $watermarkImage = new Text('watermarkImage', null, '', '水印图片路径', '水印图片的完整路径，支持PNG格式（推荐使用透明背景）');
        $form->addInput($watermarkImage);
        
        $watermarkText = new Text('watermarkText', null, '', '文字水印', '如果不设置水印图片，可以使用文字水印');
        $form->addInput($watermarkText);
        
        $watermarkFontSize = new Text('watermarkFontSize', null, '24', '水印字体大小', '设置文字水印的字体大小（像素），默认24');
        $form->addInput($watermarkFontSize);
        
        $watermarkPosition = new Select('watermarkPosition', array(
            'top-left' => '左上角',
            'top-right' => '右上角', 
            'bottom-left' => '左下角',
            'bottom-right' => '右下角',
            'center' => '居中'
        ), 'bottom-right', '水印位置', '选择水印在图片中的位置');
        $form->addInput($watermarkPosition);
        
        $watermarkOpacity = new Text('watermarkOpacity', null, '100', '水印不透明度', '设置水印不透明度，范围0-100，0为完全透明，100为完全不透明');
        $form->addInput($watermarkOpacity);
        
        $watermarkMargin = new Text('watermarkMargin', null, '10', '水印边距', '水印距离图片边缘的距离（像素）');
        $form->addInput($watermarkMargin);
        
        // 图片处理阈值设置
        $minWidth = new Text('minWidth', null, '200', '最小处理宽度', '只有宽度大于此值的图片才会被处理（像素）');
        $form->addInput($minWidth);
        
        $minHeight = new Text('minHeight', null, '200', '最小处理高度', '只有高度大于此值的图片才会被处理（像素）');
        $form->addInput($minHeight);
    }
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Form $form) {}
    
    /**
     * 插件实现方法
     * 
     * @access public
     * @return void
     */
    public static function render()
    {
        $js = \Typecho\Common::url('UEditorPlus/ueditor.config.js', Options::alloc()->pluginUrl);
        $js1 = \Typecho\Common::url('UEditorPlus/ueditor.all.js', Options::alloc()->pluginUrl);
        $js2 = \Typecho\Common::url('UEditorPlus/lang/zh-cn/zh-cn.js', Options::alloc()->pluginUrl);
        echo '<script type="text/javascript" src="'. $js. '"></script><script type="text/javascript" src="'. $js1. '"></script><script type="text/javascript" src="'. $js2. '"></script>';
        echo '<script type="text/javascript">
    //初始化编辑器
    $(document).ready(function (e) {
        var ue = UE.getEditor("text",{
            maximumWords:30000
        });
    });
    // 保存草稿时同步
    document.getElementById("btn-save").onclick = function() {
        ue.sync("text");
    }
    // 提交时同步
    document.getElementById("btn-submit").onclick = function() {
        ue.sync("text");
    }
    </script>';
    }
}