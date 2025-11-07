<?php
/**
 * 图片处理类
 * 功能：WebP转换、添加水印（支持动态字体大小）
 */
class ImageProcessor 
{
    /**
     * 处理上传的图片
     * @param string $filePath 图片文件路径
     * @param object $config 插件配置
     * @param array $fileInfo 文件信息
     * @return array|false 处理结果
     */
    public function processImage($filePath, $config, $fileInfo) 
    {
        // 检查是否为图片文件
        if (!$this->isImage($filePath)) {
            return false;
        }
        
        // 获取图片信息
        $imageInfo = getimagesize($filePath);
        if (!$imageInfo) {
            return false;
        }
        
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $type = $imageInfo[2];
        
        // 检查图片尺寸是否达到处理阈值
        $minWidth = isset($config->minWidth) ? intval($config->minWidth) : 200;
        $minHeight = isset($config->minHeight) ? intval($config->minHeight) : 200;
        
        if ($width < $minWidth || $height < $minHeight) {
            return false;
        }
        
        // 创建图片资源
        $image = $this->createImageResource($filePath, $type);
        if (!$image) {
            return false;
        }
        
        // 添加水印
        if (isset($config->watermarkEnable) && $config->watermarkEnable == '1') {
            $image = $this->addWatermark($image, $width, $height, $config);
        }
        
        $result = $fileInfo;
        
        // WebP转换
        if (isset($config->webpEnable) && $config->webpEnable == '1' && function_exists('imagewebp')) {
            $webpPath = $this->convertToWebP($image, $filePath, $config);
            if ($webpPath) {
                $result['url'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $webpPath);
                $result['title'] = basename($webpPath);
                $result['type'] = '.webp';
                $result['size'] = filesize($webpPath);
                
                // 是否删除原图
                if (!isset($config->webpKeepOriginal) || $config->webpKeepOriginal != '1') {
                    unlink($filePath);
                }
            }
        } else {
            // 不转WebP，但需要保存添加水印后的图片
            if (isset($config->watermarkEnable) && $config->watermarkEnable == '1') {
                $this->saveImage($image, $filePath, $type);
                $result['size'] = filesize($filePath);
            }
        }
        
        // 清理内存
        imagedestroy($image);
        
        return $result;
    }
    
    /**
     * 检查是否为图片文件
     */
    private function isImage($filePath) 
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
    }
    
    /**
     * 创建图片资源
     */
    private function createImageResource($filePath, $type) 
    {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($filePath);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($filePath);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($filePath);
            default:
                return false;
        }
    }
    
    /**
     * 添加水印
     */
    private function addWatermark($image, $width, $height, $config) 
    {
        // 检查是否有水印图片
        if (!empty($config->watermarkImage) && file_exists($config->watermarkImage)) {
            return $this->addImageWatermark($image, $width, $height, $config);
        } 
        // 使用文字水印
        elseif (!empty($config->watermarkText)) {
            return $this->addTextWatermark($image, $width, $height, $config);
        }
        
        return $image;
    }
    
    /**
     * 添加图片水印
     */
    private function addImageWatermark($image, $width, $height, $config) 
    {
        $watermarkPath = $config->watermarkImage;
        $watermarkInfo = getimagesize($watermarkPath);
        
        if (!$watermarkInfo) {
            return $image;
        }
        
        // 创建水印图片资源
        $watermark = $this->createImageResource($watermarkPath, $watermarkInfo[2]);
        if (!$watermark) {
            return $image;
        }
        
        $wmWidth = $watermarkInfo[0];
        $wmHeight = $watermarkInfo[1];
        
        // 动态调整图片水印大小
        if (isset($config->enableDynamicImageSize) && $config->enableDynamicImageSize == '1') {
            $newSize = $this->calculateDynamicImageSize($width, $height, $wmWidth, $wmHeight, $config);
            if ($newSize['width'] != $wmWidth || $newSize['height'] != $wmHeight) {
                $resizedWatermark = imagecreatetruecolor($newSize['width'], $newSize['height']);
                
                // 保持透明度
                imagealphablending($resizedWatermark, false);
                imagesavealpha($resizedWatermark, true);
                $transparent = imagecolorallocatealpha($resizedWatermark, 255, 255, 255, 127);
                imagefill($resizedWatermark, 0, 0, $transparent);
                imagealphablending($resizedWatermark, true);
                
                imagecopyresampled($resizedWatermark, $watermark, 0, 0, 0, 0, 
                    $newSize['width'], $newSize['height'], $wmWidth, $wmHeight);
                
                imagedestroy($watermark);
                $watermark = $resizedWatermark;
                $wmWidth = $newSize['width'];
                $wmHeight = $newSize['height'];
            }
        }
        
        // 计算水印位置
        $position = $this->calculatePosition($width, $height, $wmWidth, $wmHeight, $config);
        
        // 获取透明度
        $opacity = isset($config->watermarkOpacity) ? intval($config->watermarkOpacity) : 50;
        
        // 添加水印
        $this->imagecopymerge_alpha($image, $watermark, $position['x'], $position['y'], 0, 0, $wmWidth, $wmHeight, $opacity);
        
        imagedestroy($watermark);
        return $image;
    }
    
    /**
     * 添加文字水印（带黑边效果，支持动态字体大小）
     */
    private function addTextWatermark($image, $width, $height, $config) 
    {
        $text = $config->watermarkText;
        
        // 动态计算字体大小
        $fontSize = $this->calculateDynamicFontSize($width, $height, $config);
        
        // 获取不透明度设置
        $opacity = isset($config->watermarkOpacity) ? intval($config->watermarkOpacity) : 100;
        $alpha = 127 - intval($opacity * 127 / 100); // 转换为alpha值，0=不透明，127=透明
        
        // 获取文字颜色配置
        $textColorConfig = isset($config->watermarkTextColor) ? $config->watermarkTextColor : 'ffffff';
        $borderColorConfig = isset($config->watermarkBorderColor) ? $config->watermarkBorderColor : '000000';
        
        // 解析颜色
        $textRGB = $this->hexToRgb($textColorConfig);
        $borderRGB = $this->hexToRgb($borderColorConfig);
        
        // 定义颜色
        $textColor = imagecolorallocatealpha($image, $textRGB['r'], $textRGB['g'], $textRGB['b'], $alpha);
        $borderColor = imagecolorallocatealpha($image, $borderRGB['r'], $borderRGB['g'], $borderRGB['b'], $alpha);
        
        // 获取字体文件路径
        $fontPath = __DIR__ . '/font.ttf';
        $useTTF = file_exists($fontPath);
        
        if ($useTTF) {
            // 使用TTF字体计算文字尺寸
            $textBox = imagettfbbox($fontSize, 0, $fontPath, $text);
            $textWidth = abs($textBox[4] - $textBox[0]);
            $textHeight = abs($textBox[5] - $textBox[1]);
        } else {
            // 使用内置字体时，需要根据动态字体大小调整内置字体等级
            $builtinFontSize = $this->convertToBuiltinFontSize($fontSize);
            $textWidth = strlen($text) * ($builtinFontSize * 2); // 估算宽度
            $textHeight = $builtinFontSize * 3; // 估算高度
            $fontSize = $builtinFontSize; // 重新赋值为内置字体大小
        }
        
        // 计算文字位置
        $position = $this->calculatePosition($width, $height, $textWidth, $textHeight, $config);
        $x = $position['x'];
        $y = $position['y'];
        
        // 获取边框宽度配置
        $borderWidth = isset($config->watermarkBorderWidth) ? intval($config->watermarkBorderWidth) : 1;
        
        // 绘制文字水印
        if ($useTTF) {
            // 使用TTF字体
            $baseY = $y + $textHeight;
            
            // 1. 绘制边框（根据边框宽度绘制多层）
            for ($bw = 1; $bw <= $borderWidth; $bw++) {
                $borderOffsets = [
                    [-$bw, -$bw], [0, -$bw], [$bw, -$bw],
                    [-$bw,  0],              [$bw,  0],
                    [-$bw,  $bw], [0,  $bw], [$bw,  $bw]
                ];
                
                foreach ($borderOffsets as $offset) {
                    imagettftext($image, $fontSize, 0, $x + $offset[0], $baseY + $offset[1], $borderColor, $fontPath, $text);
                }
            }
            
            // 2. 绘制主文字
            imagettftext($image, $fontSize, 0, $x, $baseY, $textColor, $fontPath, $text);
            
        } else {
            // 使用内置字体
            
            // 1. 绘制边框
            for ($bw = 1; $bw <= $borderWidth; $bw++) {
                $borderOffsets = [
                    [-$bw, -$bw], [0, -$bw], [$bw, -$bw],
                    [-$bw,  0],              [$bw,  0],
                    [-$bw,  $bw], [0,  $bw], [$bw,  $bw]
                ];
                
                foreach ($borderOffsets as $offset) {
                    imagestring($image, $fontSize, $x + $offset[0], $y + $offset[1], $text, $borderColor);
                }
            }
            
            // 2. 绘制主文字
            imagestring($image, $fontSize, $x, $y, $text, $textColor);
        }
        
        return $image;
    }
    
    /**
     * 动态计算字体大小
     */
    private function calculateDynamicFontSize($width, $height, $config) 
    {
        // 获取配置的基础字体大小
        $baseFontSize = isset($config->watermarkFontSize) ? intval($config->watermarkFontSize) : 24;
        
        // 检查是否启用动态字体大小
        $enableDynamicSize = isset($config->enableDynamicFontSize) ? $config->enableDynamicFontSize == '1' : true;
        
        if (!$enableDynamicSize) {
            return $baseFontSize;
        }
        
        // 获取动态计算参数
        $widthRatio = isset($config->fontSizeWidthRatio) ? floatval($config->fontSizeWidthRatio) : 30.0; // 默认30%
        $heightRatio = isset($config->fontSizeHeightRatio) ? floatval($config->fontSizeHeightRatio) : 5.0; // 默认5%
        $adjustmentFactor = isset($config->fontSizeAdjustmentFactor) ? floatval($config->fontSizeAdjustmentFactor) : 0.8; // 调整系数
        
        // 按宽度比例计算字体大小
        $text = $config->watermarkText;
        $textLength = mb_strlen($text, 'UTF-8'); // 支持中文字符
        $widthBasedSize = ($width * ($widthRatio / 100)) / $textLength * $adjustmentFactor;
        
        // 按高度比例计算字体大小
        $heightBasedSize = $height * ($heightRatio / 100);
        
        // 取两者中的较小值
        $dynamicSize = min($widthBasedSize, $heightBasedSize);
        
        // 获取字体大小范围限制
        $minFontSize = isset($config->minDynamicFontSize) ? intval($config->minDynamicFontSize) : 12;
        $maxFontSize = isset($config->maxDynamicFontSize) ? intval($config->maxDynamicFontSize) : 72;
        
        // 应用限制
        $dynamicSize = max($minFontSize, min($maxFontSize, $dynamicSize));
        
        return intval($dynamicSize);
    }
    
    /**
     * 动态计算图片水印大小
     */
    private function calculateDynamicImageSize($imgWidth, $imgHeight, $wmWidth, $wmHeight, $config) 
    {
        // 获取图片水印最大占比配置
        $maxWidthRatio = isset($config->imageWatermarkMaxWidthRatio) ? floatval($config->imageWatermarkMaxWidthRatio) : 25.0; // 默认25%
        $maxHeightRatio = isset($config->imageWatermarkMaxHeightRatio) ? floatval($config->imageWatermarkMaxHeightRatio) : 25.0; // 默认25%
        
        // 计算最大允许尺寸
        $maxWidth = $imgWidth * ($maxWidthRatio / 100);
        $maxHeight = $imgHeight * ($maxHeightRatio / 100);
        
        // 如果水印本身就比最大尺寸小，则不需要缩放
        if ($wmWidth <= $maxWidth && $wmHeight <= $maxHeight) {
            return ['width' => $wmWidth, 'height' => $wmHeight];
        }
        
        // 按比例缩放
        $scaleX = $maxWidth / $wmWidth;
        $scaleY = $maxHeight / $wmHeight;
        $scale = min($scaleX, $scaleY);
        
        return [
            'width' => intval($wmWidth * $scale),
            'height' => intval($wmHeight * $scale)
        ];
    }
    
    /**
     * 将动态计算的字体大小转换为内置字体大小
     */
    private function convertToBuiltinFontSize($fontSize) 
    {
        if ($fontSize <= 10) return 1;
        if ($fontSize <= 15) return 2;
        if ($fontSize <= 20) return 3;
        if ($fontSize <= 30) return 4;
        return 5;
    }
    
    /**
     * 十六进制颜色转RGB
     */
    private function hexToRgb($hex) 
    {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }
    
    /**
     * 计算水印位置
     */
    private function calculatePosition($imgWidth, $imgHeight, $wmWidth, $wmHeight, $config) 
    {
        $margin = isset($config->watermarkMargin) ? intval($config->watermarkMargin) : 10;
        $position = isset($config->watermarkPosition) ? $config->watermarkPosition : 'bottom-right';
        
        switch ($position) {
            case 'top-left':
                return ['x' => $margin, 'y' => $margin];
            case 'top-center':
                return ['x' => ($imgWidth - $wmWidth) / 2, 'y' => $margin];
            case 'top-right':
                return ['x' => $imgWidth - $wmWidth - $margin, 'y' => $margin];
            case 'middle-left':
                return ['x' => $margin, 'y' => ($imgHeight - $wmHeight) / 2];
            case 'center':
                return ['x' => ($imgWidth - $wmWidth) / 2, 'y' => ($imgHeight - $wmHeight) / 2];
            case 'middle-right':
                return ['x' => $imgWidth - $wmWidth - $margin, 'y' => ($imgHeight - $wmHeight) / 2];
            case 'bottom-left':
                return ['x' => $margin, 'y' => $imgHeight - $wmHeight - $margin];
            case 'bottom-center':
                return ['x' => ($imgWidth - $wmWidth) / 2, 'y' => $imgHeight - $wmHeight - $margin];
            case 'bottom-right':
                return ['x' => $imgWidth - $wmWidth - $margin, 'y' => $imgHeight - $wmHeight - $margin];
            default:
                return ['x' => $imgWidth - $wmWidth - $margin, 'y' => $imgHeight - $wmHeight - $margin];
        }
    }
    
    /**
     * 支持透明度的图片合并函数
     */
    private function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct) 
    {
        if (!isset($pct)) {
            return false;
        }
        
        $pct /= 100;
        
        // 获取图片的宽度和高度
        $w = imagesx($src_im);
        $h = imagesy($src_im);
        
        // 创建一个临时图片
        $cut = imagecreatetruecolor($src_w, $src_h);
        
        // 拷贝源图片的相应部分到临时图片
        imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
        
        // 拷贝要合并的图片到临时图片
        imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);
        
        // 设置临时图片为混合模式
        imagealphablending($dst_im, true);
        
        // 将临时图片合并到目标图片
        imagecopymerge($dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct * 100);
        
        imagedestroy($cut);
    }
    
    /**
     * 转换为WebP格式
     */
    private function convertToWebP($image, $originalPath, $config) 
    {
        $quality = isset($config->webpQuality) ? intval($config->webpQuality) : 80;
        $webpPath = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $originalPath);
        
        // 设置WebP支持透明度
        imagesavealpha($image, true);
        
        if (imagewebp($image, $webpPath, $quality)) {
            return $webpPath;
        }
        
        return false;
    }
    
    /**
     * 保存图片
     */
    private function saveImage($image, $filePath, $type) 
    {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return imagejpeg($image, $filePath, 90);
            case IMAGETYPE_PNG:
                imagesavealpha($image, true);
                return imagepng($image, $filePath);
            case IMAGETYPE_GIF:
                return imagegif($image, $filePath);
            default:
                return false;
        }
    }
}