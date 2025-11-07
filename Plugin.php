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
        
        // ===================== WebP转换设置 =====================
        // 使用Text元素作为说明，兼容更多Typecho版本
        $webpSection = new Text('webpSection', null, '', '<h3 style="margin-top: 20px; color: #007cba;">WebP转换设置</h3>', '以下是WebP图片格式转换的相关配置选项');
        $webpSection->input->setAttribute('style', 'display: none;');
        $form->addInput($webpSection);
        
        $webpEnable = new Radio('webpEnable', array('1' => '启用', '0' => '禁用'), '0', 'WebP转换', '启用后会自动将上传的PNG、JPG图片转换为WebP格式');
        $form->addInput($webpEnable);
        
        $webpQuality = new Text('webpQuality', null, '80', 'WebP质量', '设置WebP图片质量，范围1-100，数值越高质量越好但文件越大');
        $form->addInput($webpQuality);
        
        $webpKeepOriginal = new Radio('webpKeepOriginal', array('1' => '保留', '0' => '删除'), '0', '保留原图', '转换为WebP后是否保留原始图片文件');
        $form->addInput($webpKeepOriginal);
        
        
        // ===================== 水印基础设置 =====================
        $watermarkSection = new Text('watermarkSection', null, '', '<h3 style="margin-top: 30px; color: #007cba;">水印基础设置</h3>', '以下是水印功能的基础配置选项');
        $watermarkSection->input->setAttribute('style', 'display: none;');
        $form->addInput($watermarkSection);
        
        $watermarkEnable = new Radio('watermarkEnable', array('1' => '启用', '0' => '禁用'), '0', '图片水印', '启用后会为上传的图片添加水印');
        $form->addInput($watermarkEnable);
        
        $watermarkPosition = new Select('watermarkPosition', array(
            'top-left' => '左上角',
            'top-center' => '顶部居中',
            'top-right' => '右上角',
            'middle-left' => '左侧居中',
            'center' => '正中心',
            'middle-right' => '右侧居中',
            'bottom-left' => '左下角',
            'bottom-center' => '底部居中',
            'bottom-right' => '右下角'
        ), 'bottom-right', '水印位置', '选择水印在图片中的位置');
        $form->addInput($watermarkPosition);
        
        $watermarkOpacity = new Text('watermarkOpacity', null, '100', '水印不透明度', '设置水印不透明度，范围0-100，0为完全透明，100为完全不透明');
        $form->addInput($watermarkOpacity);
        
        $watermarkMargin = new Text('watermarkMargin', null, '10', '水印边距', '水印距离图片边缘的距离（像素）');
        $form->addInput($watermarkMargin);
        
        
        // ===================== 图片水印设置 =====================
        $imageWatermarkSection = new Text('imageWatermarkSection', null, '', '<h3 style="margin-top: 30px; color: #007cba;">图片水印设置</h3>', '以下是图片水印的相关配置选项');
        $imageWatermarkSection->input->setAttribute('style', 'display: none;');
        $form->addInput($imageWatermarkSection);
        
        $watermarkImage = new Text('watermarkImage', null, '', '水印图片路径', '水印图片的完整路径，支持PNG格式（推荐使用透明背景）。如果设置了图片水印，将优先使用图片水印。');
        $form->addInput($watermarkImage);
        
        $enableDynamicImageSize = new Radio('enableDynamicImageSize', array('1' => '启用', '0' => '禁用'), '1', '图片水印动态大小', '启用后图片水印会根据原图大小自动调整尺寸');
        $form->addInput($enableDynamicImageSize);
        
        $imageWatermarkMaxWidthRatio = new Text('imageWatermarkMaxWidthRatio', null, '25', '图片水印最大宽度占比', '图片水印相对于原图宽度的最大占比（百分比），例如25表示最大不超过原图宽度的25%');
        $form->addInput($imageWatermarkMaxWidthRatio);
        
        $imageWatermarkMaxHeightRatio = new Text('imageWatermarkMaxHeightRatio', null, '25', '图片水印最大高度占比', '图片水印相对于原图高度的最大占比（百分比），例如25表示最大不超过原图高度的25%');
        $form->addInput($imageWatermarkMaxHeightRatio);
        
        
        // ===================== 文字水印设置 =====================
        $textWatermarkSection = new Text('textWatermarkSection', null, '', '<h3 style="margin-top: 30px; color: #007cba;">文字水印设置</h3>', '以下是文字水印的相关配置选项');
        $textWatermarkSection->input->setAttribute('style', 'display: none;');
        $form->addInput($textWatermarkSection);
        
        $watermarkText = new Text('watermarkText', null, '', '文字水印', '如果不设置水印图片，可以使用文字水印。支持中文字符。');
        $form->addInput($watermarkText);
        
        $watermarkTextColor = new Text('watermarkTextColor', null, 'ffffff', '文字颜色', '文字水印的颜色，十六进制格式（不包含#号），例如：ffffff表示白色，000000表示黑色');
        $form->addInput($watermarkTextColor);
        
        $watermarkBorderColor = new Text('watermarkBorderColor', null, '000000', '文字边框颜色', '文字水印边框的颜色，十六进制格式（不包含#号），例如：000000表示黑色边框');
        $form->addInput($watermarkBorderColor);
        
        $watermarkBorderWidth = new Text('watermarkBorderWidth', null, '1', '文字边框宽度', '文字水印边框的宽度（像素），设置为0则无边框，数值越大边框越粗');
        $form->addInput($watermarkBorderWidth);
        
        
        // ===================== 动态字体大小设置 =====================
        $dynamicFontSection = new Text('dynamicFontSection', null, '', '<h3 style="margin-top: 30px; color: #007cba;">动态字体大小设置</h3>', '以下是动态字体大小的相关配置选项');
        $dynamicFontSection->input->setAttribute('style', 'display: none;');
        $form->addInput($dynamicFontSection);
        
        $enableDynamicFontSize = new Radio('enableDynamicFontSize', array('1' => '启用', '0' => '禁用'), '1', '动态字体大小', '启用后字体大小会根据图片尺寸自动调整，禁用后使用固定字体大小');
        $form->addInput($enableDynamicFontSize);
        
        $watermarkFontSize = new Text('watermarkFontSize', null, '24', '固定字体大小', '当禁用动态字体大小时使用的固定字体大小（像素）');
        $form->addInput($watermarkFontSize);
        
        $fontSizeWidthRatio = new Text('fontSizeWidthRatio', null, '30', '字体大小宽度占比', '字体大小相对于图片宽度的占比（百分比），用于动态计算字体大小');
        $form->addInput($fontSizeWidthRatio);
        
        $fontSizeHeightRatio = new Text('fontSizeHeightRatio', null, '5', '字体大小高度占比', '字体大小相对于图片高度的占比（百分比），用于动态计算字体大小');
        $form->addInput($fontSizeHeightRatio);
        
        $fontSizeAdjustmentFactor = new Text('fontSizeAdjustmentFactor', null, '0.8', '字体大小调整系数', '字体大小的微调系数（0.1-2.0），用于精细调整动态计算的字体大小，1.0为不调整');
        $form->addInput($fontSizeAdjustmentFactor);
        
        $minDynamicFontSize = new Text('minDynamicFontSize', null, '12', '最小字体大小', '动态计算字体大小的最小值（像素），防止字体过小');
        $form->addInput($minDynamicFontSize);
        
        $maxDynamicFontSize = new Text('maxDynamicFontSize', null, '72', '最大字体大小', '动态计算字体大小的最大值（像素），防止字体过大');
        $form->addInput($maxDynamicFontSize);
        
        
        // ===================== 图片处理阈值设置 =====================
        $thresholdSection = new Text('thresholdSection', null, '', '<h3 style="margin-top: 30px; color: #007cba;">图片处理阈值设置</h3>', '以下是图片处理阈值的相关配置选项');
        $thresholdSection->input->setAttribute('style', 'display: none;');
        $form->addInput($thresholdSection);
        
        $minWidth = new Text('minWidth', null, '200', '最小处理宽度', '只有宽度大于此值的图片才会被处理（像素）。设置阈值可以避免对过小的图片添加水印。');
        $form->addInput($minWidth);
        
        $minHeight = new Text('minHeight', null, '200', '最小处理高度', '只有高度大于此值的图片才会被处理（像素）。设置阈值可以避免对过小的图片添加水印。');
        $form->addInput($minHeight);
        
        
        // ===================== 使用说明 =====================
        $usageSection = new Text('usageSection', null, '', '<h3 style="margin-top: 30px; color: #007cba;">使用说明</h3>', '');
        $usageSection->input->setAttribute('style', 'display: none;');
        $form->addInput($usageSection);
        
        $usageInstructions = new Text('usageInstructions', null, '', '使用说明和配置建议', '
        <div style="margin: 15px 0; padding: 15px; background-color: #f9f9f9; border-left: 4px solid #007cba; font-size: 13px;">
            <h4 style="margin-top: 0; color: #333;">动态字体大小原理：</h4>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li><strong>宽度占比计算</strong>：根据图片宽度的设定百分比和文字长度计算字体大小</li>
                <li><strong>高度占比计算</strong>：根据图片高度的设定百分比计算字体大小</li>
                <li><strong>取最小值</strong>：从上述两种计算结果中取较小值，确保水印不会过大</li>
                <li><strong>范围限制</strong>：在最小和最大字体大小范围内调整最终结果</li>
            </ul>
            
            <h4 style="color: #333;">推荐设置：</h4>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li><strong>宽度占比</strong>：20-40%，适合大部分场景</li>
                <li><strong>高度占比</strong>：3-8%，确保水印不会过高</li>
                <li><strong>调整系数</strong>：0.6-1.2，用于微调效果</li>
                <li><strong>最小字体</strong>：10-15px，确保可读性</li>
                <li><strong>最大字体</strong>：50-100px，防止水印过大</li>
            </ul>
            
            <h4 style="color: #333;">颜色设置：</h4>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>颜色格式为十六进制，<strong>不要包含#号</strong></li>
                <li>常用颜色：ffffff(白)、000000(黑)、ff0000(红)、00ff00(绿)、0000ff(蓝)</li>
                <li>建议文字使用白色(ffffff)配黑色边框(000000)，或黑色(000000)配白色边框(ffffff)</li>
            </ul>
            
            <h4 style="color: #333;">TTF字体支持：</h4>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>将TTF字体文件命名为 <code>font.ttf</code> 并放置在插件目录下</li>
                <li>使用TTF字体可以获得更好的中文显示效果</li>
                <li>如果没有TTF字体，系统会自动使用内置字体</li>
            </ul>
        </div>
        ');
        $usageInstructions->input->setAttribute('style', 'display: none;');
        $form->addInput($usageInstructions);
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
    $autoCompleteJs = \Typecho\Common::url('UEditorPlus/auto-complete.js', Options::alloc()->pluginUrl);
    
    echo '<script type="text/javascript" src="'. $js. '"></script>';
    echo '<script type="text/javascript" src="'. $js1. '"></script>';
    echo '<script type="text/javascript" src="'. $js2. '"></script>';
    
    echo '<script type="text/javascript">
    // 等待UEditor完全加载后再加载自动补全插件
    function loadAutoComplete() {
        if (typeof UE !== "undefined" && UE.plugins) {
            var script = document.createElement("script");
            script.src = "'. $autoCompleteJs. '";
            script.onload = function() {
                console.log("UEditorPlus自动补全插件加载成功");
            };
            script.onerror = function() {
                console.error("UEditorPlus自动补全插件加载失败");
            };
            document.head.appendChild(script);
        } else {
            setTimeout(loadAutoComplete, 100);
        }
    }
    
    // 初始化编辑器
    $(document).ready(function (e) {
        // 先加载自动补全插件
        loadAutoComplete();
        
        // 延迟初始化编辑器，确保插件已加载
        setTimeout(function() {
            var ue = UE.getEditor("text",{
                maximumWords:30000
            });
            
            // 检查插件是否成功注册
            ue.addListener("ready", function() {
                console.log("编辑器准备就绪");
                if (typeof UE.plugins.autocomplete === "function") {
                    console.log("自动补全插件已注册");
                } else {
                    console.warn("自动补全插件未注册");
                }
            });
        }, 500);
    });
    
    // 保存草稿时同步
    document.getElementById("btn-save").onclick = function() {
        if (typeof ue !== "undefined") {
            ue.sync("text");
        }
    }
    
    // 提交时同步
    document.getElementById("btn-submit").onclick = function() {
        if (typeof ue !== "undefined") {
            ue.sync("text");
        }
    }
    </script>';
}
}