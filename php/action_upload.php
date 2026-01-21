<?php
/**
 * 上传附件和上传视频
 * User: Jinqn
 * Date: 14-04-09
 * Time: 上午10:17
 */
include "Uploader.class.php";
include "ImageProcessor.php";

/* 上传配置 */
$base64 = "upload";
switch (htmlspecialchars($_GET['action'])) {
    case 'uploadimage':
        $config = array(
            "pathFormat" => $CONFIG['imagePathFormat'],
            "maxSize" => $CONFIG['imageMaxSize'],
            "allowFiles" => $CONFIG['imageAllowFiles']
        );
        $fieldName = $CONFIG['imageFieldName'];
        break;
    case 'uploadscrawl':
        $config = array(
            "pathFormat" => $CONFIG['scrawlPathFormat'],
            "maxSize" => $CONFIG['scrawlMaxSize'],
            "allowFiles" => $CONFIG['scrawlAllowFiles'],
            "oriName" => "scrawl.png"
        );
        $fieldName = $CONFIG['scrawlFieldName'];
        $base64 = "base64";
        break;
    case 'uploadvideo':
        $config = array(
            "pathFormat" => $CONFIG['videoPathFormat'],
            "maxSize" => $CONFIG['videoMaxSize'],
            "allowFiles" => $CONFIG['videoAllowFiles']
        );
        $fieldName = $CONFIG['videoFieldName'];
        break;
    case 'uploadaudio':
        $config = array(
            "pathFormat" => $CONFIG['audioPathFormat'],
            "maxSize" => $CONFIG['audioMaxSize'],
            "allowFiles" => $CONFIG['audioAllowFiles']
        );
        $fieldName = $CONFIG['audioFieldName'];
        break;
    case 'uploadfile':
    default:
        $config = array(
            "pathFormat" => $CONFIG['filePathFormat'],
            "maxSize" => $CONFIG['fileMaxSize'],
            "allowFiles" => $CONFIG['fileAllowFiles']
        );
        $fieldName = $CONFIG['fileFieldName'];
        break;
}

/* 生成上传实例对象并完成上传 */
$up = new Uploader($fieldName, $config, $base64);

// 获取上传文件信息
$fileInfo = $up->getFileInfo();

// 如果上传成功且是图片，则进行图片处理
if ($fileInfo['state'] === 'SUCCESS' && in_array($_GET['action'], ['uploadimage', 'uploadscrawl'])) {
    // 获取插件配置
    $pluginConfig = \Typecho\Widget::widget('Widget_Options')->plugin('UEditorPlus');
    
    // 获取文件的完整路径
    $filePath = $_SERVER['DOCUMENT_ROOT'] . $fileInfo['url'];
    
    // 检查文件是否存在
    if (file_exists($filePath)) {
        $imageProcessor = new ImageProcessor();
        
        // 处理图片（水印 + WebP转换）
        $result = $imageProcessor->processImage($filePath, $pluginConfig, $fileInfo);
        
        // 如果处理成功，更新文件信息
        if ($result && isset($result['url'])) {
            $fileInfo['url'] = $result['url'];
            $fileInfo['title'] = $result['title'];
            $fileInfo['type'] = $result['type'];
            $fileInfo['size'] = $result['size'];
        }
    }
}

/* 返回数据 */
return json_encode($fileInfo);