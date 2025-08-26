<?php
/**
 * 图片处理类
 * 功能：WebP转换、添加水印
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
     * 添加文字水印（带黑边效果，不加粗）
     */
    private function addTextWatermark($image, $width, $height, $config) 
    {
        $text = $config->watermarkText;
        $fontSize = isset($config->watermarkFontSize) ? intval($config->watermarkFontSize) : 24;  // 支持配置字体大小
        
        // 获取不透明度设置
        $opacity = isset($config->watermarkOpacity) ? intval($config->watermarkOpacity) : 100;
        $alpha = 127 - intval($opacity * 127 / 100); // 转换为alpha值，0=不透明，127=透明
        
        // 定义颜色：白色文字，黑色边框
        $textColor = imagecolorallocatealpha($image, 255, 255, 255, $alpha); // 白色
        $borderColor = imagecolorallocatealpha($image, 0, 0, 0, $alpha);     // 黑色边框
        
        // 获取字体文件路径
        $fontPath = __DIR__ . '/font.ttf';
        $useTTF = file_exists($fontPath);
        
        if ($useTTF) {
            // 使用TTF字体计算文字尺寸
            $textBox = imagettfbbox($fontSize, 0, $fontPath, $text);
            $textWidth = abs($textBox[4] - $textBox[0]);
            $textHeight = abs($textBox[5] - $textBox[1]);
        } else {
            // 使用内置字体
            $fontSize = 5; // 内置字体大小
            $textWidth = strlen($text) * 10;
            $textHeight = 15;
        }
        
        // 计算文字位置
        $position = $this->calculatePosition($width, $height, $textWidth, $textHeight, $config);
        $x = $position['x'];
        $y = $position['y'];
        
        // 绘制文字水印
        if ($useTTF) {
            // 使用TTF字体
            $baseY = $y + $textHeight;
            
            // 1. 绘制黑边（8方向描边）
            $borderOffsets = [
                [-1, -1], [0, -1], [1, -1],
                [-1,  0],           [1,  0],
                [-1,  1], [0,  1], [1,  1]
            ];
            
            foreach ($borderOffsets as $offset) {
                imagettftext($image, $fontSize, 0, $x + $offset[0], $baseY + $offset[1], $borderColor, $fontPath, $text);
            }
            
            // 2. 绘制主文字（单次绘制，不加粗）
            imagettftext($image, $fontSize, 0, $x, $baseY, $textColor, $fontPath, $text);
            
        } else {
            // 使用内置字体
            
            // 1. 绘制黑边
            $borderOffsets = [
                [-1, -1], [0, -1], [1, -1],
                [-1,  0],           [1,  0],
                [-1,  1], [0,  1], [1,  1]
            ];
            
            foreach ($borderOffsets as $offset) {
                imagestring($image, $fontSize, $x + $offset[0], $y + $offset[1], $text, $borderColor);
            }
            
            // 2. 绘制主文字
            imagestring($image, $fontSize, $x, $y, $text, $textColor);
        }
        
        return $image;
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
            case 'top-right':
                return ['x' => $imgWidth - $wmWidth - $margin, 'y' => $margin];
            case 'bottom-left':
                return ['x' => $margin, 'y' => $imgHeight - $wmHeight - $margin];
            case 'bottom-right':
                return ['x' => $imgWidth - $wmWidth - $margin, 'y' => $imgHeight - $wmHeight - $margin];
            case 'center':
                return ['x' => ($imgWidth - $wmWidth) / 2, 'y' => ($imgHeight - $wmHeight) / 2];
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