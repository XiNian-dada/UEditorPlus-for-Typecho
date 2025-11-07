/**
 * UEditorPlus 括号自动补全插件 - 修复版
 */
console.log('=== UEditorPlus自动补全插件开始加载 ===');

if (typeof UE === 'undefined') {
    console.error('UEditor未定义');
} else {
    console.log('UEditor已定义，注册插件');
    
    UE.plugins['autocomplete'] = function() {
        var me = this;
        console.log('插件初始化');
        
        me.ready(function() {
            console.log('编辑器准备就绪');
            
            // 使用UEditor的键盘事件监听
            me.addListener('keydown', function(type, evt) {
                var key = evt.key;
                
                // 检查是否在代码块中
                var range = me.selection.getRange();
                var container = range.startContainer;
                var isInCodeBlock = false;
                
                // 向上查找pre标签
                var node = container;
                while (node && node.nodeType !== 9) {
                    if (node.nodeType === 1 && node.tagName === 'PRE') {
                        isInCodeBlock = true;
                        break;
                    }
                    node = node.parentNode;
                }
                
                if (!isInCodeBlock) return;
                
                // 处理括号自动补全
                var bracketPairs = {
                    '{': '{}',
                    '[': '[]',
                    '(': '()',
                    '"': '""',
                    "'": "''",
                    '`': '``'
                };
                
                // 对应的闭括号
                var closingBrackets = {
                    '}': '{',
                    ']': '[',
                    ')': '(',
                    '"': '"',
                    "'": "'",
                    '`': '`'
                };
                
                // 处理开括号自动补全
                if (bracketPairs[key] && !evt.ctrlKey && !evt.metaKey && !evt.altKey) {
                    evt.preventDefault();
                    evt.stopPropagation();
                    
                    console.log('自动补全开括号:', key);
                    
                    var nativeSelection = me.selection.getNative();
                    var nativeRange = nativeSelection.getRangeAt(0);
                    
                    // 执行自动补全 - 移除了shouldSkip逻辑
                    var pair = bracketPairs[key];
                    var leftChar = pair[0];
                    var rightChar = pair[1];
                    
                    // 保存当前选区
                    var startOffset = nativeRange.startOffset;
                    
                    // 插入左括号
                    if (nativeRange.startContainer.nodeType === 3) {
                        // 文本节点
                        var text = nativeRange.startContainer.textContent;
                        var newText = text.slice(0, startOffset) + leftChar + text.slice(startOffset);
                        nativeRange.startContainer.textContent = newText;
                        
                        // 插入右括号
                        text = nativeRange.startContainer.textContent;
                        newText = text.slice(0, startOffset + 1) + rightChar + text.slice(startOffset + 1);
                        nativeRange.startContainer.textContent = newText;
                        
                        // 设置光标在中间
                        nativeRange.setStart(nativeRange.startContainer, startOffset + 1);
                        nativeRange.collapse(true);
                        nativeSelection.removeAllRanges();
                        nativeSelection.addRange(nativeRange);
                    } else {
                        // 元素节点，使用execCommand
                        me.execCommand('insertHtml', pair);
                        
                        // 尝试将光标移动到中间
                        setTimeout(function() {
                            var newRange = me.selection.getRange();
                            if (newRange.startContainer.nodeType === 3) {
                                newRange.setStart(newRange.startContainer, newRange.startOffset - 1);
                                newRange.collapse(true);
                                me.selection.getNative().removeAllRanges();
                                me.selection.getNative().addRange(newRange);
                            }
                        }, 10);
                    }
                    
                    return true;
                }
                
                // 处理闭括号智能跳过
                if (closingBrackets[key] && !evt.ctrlKey && !evt.metaKey && !evt.altKey) {
                    var nativeSelection = me.selection.getNative();
                    var nativeRange = nativeSelection.getRangeAt(0);
                    
                    if (nativeRange.startContainer.nodeType === 3) {
                        var text = nativeRange.startContainer.textContent;
                        var startOffset = nativeRange.startOffset;
                        
                        // 检查下一个字符是否就是我们要输入的闭括号
                        var nextChar = text.charAt(startOffset);
                        
                        if (nextChar === key) {
                            // 下一个字符就是对应的闭括号，跳过输入，直接移动光标
                            evt.preventDefault();
                            evt.stopPropagation();
                            
                            nativeRange.setStart(nativeRange.startContainer, startOffset + 1);
                            nativeRange.collapse(true);
                            nativeSelection.removeAllRanges();
                            nativeSelection.addRange(nativeRange);
                            
                            console.log('智能跳过闭括号:', key);
                            return true;
                        }
                    }
                }
                
                // 处理花括号内的回车键
                if (key === 'Enter' && !evt.ctrlKey && !evt.metaKey && !evt.altKey) {
                    var nativeSelection = me.selection.getNative();
                    var nativeRange = nativeSelection.getRangeAt(0);
                    
                    if (nativeRange.startContainer.nodeType === 3) {
                        var text = nativeRange.startContainer.textContent;
                        var startOffset = nativeRange.startOffset;
                        
                        // 检查光标是否在 {} 之间
                        if (startOffset > 0) {
                            var leftChar = text.charAt(startOffset - 1);
                            var rightChar = text.charAt(startOffset);
                            
                            if (leftChar === '{' && rightChar === '}') {
                                evt.preventDefault();
                                evt.stopPropagation();
                                
                                // 获取当前行的缩进
                                var lineStart = text.lastIndexOf('\n', startOffset - 1) + 1;
                                var lineIndent = '';
                                for (var i = lineStart; i < text.length; i++) {
                                    if (text[i] === ' ' || text[i] === '\t') {
                                        lineIndent += text[i];
                                    } else {
                                        break;
                                    }
                                }
                                
                                // 添加额外的缩进（4个空格或1个tab）
                                var extraIndent = '    '; // 可以改成 '\t' 如果你喜欢tab
                                
                                // 插入换行和缩进
                                var newText = text.slice(0, startOffset) + 
                                             '\n' + lineIndent + extraIndent + 
                                             '\n' + lineIndent + 
                                             text.slice(startOffset);
                                
                                nativeRange.startContainer.textContent = newText;
                                
                                // 设置光标在中间空行的缩进后
                                var newCursorPos = startOffset + 1 + lineIndent.length + extraIndent.length;
                                nativeRange.setStart(nativeRange.startContainer, newCursorPos);
                                nativeRange.collapse(true);
                                nativeSelection.removeAllRanges();
                                nativeSelection.addRange(nativeRange);
                                
                                console.log('花括号内智能换行');
                                return true;
                            }
                        }
                    }
                }
                
                // 处理退格键智能删除配对括号
                if (key === 'Backspace' && !evt.ctrlKey && !evt.metaKey && !evt.altKey) {
                    var nativeSelection = me.selection.getNative();
                    var nativeRange = nativeSelection.getRangeAt(0);
                    
                    if (nativeRange.startContainer.nodeType === 3) {
                        var text = nativeRange.startContainer.textContent;
                        var startOffset = nativeRange.startOffset;
                        
                        // 检查是否在配对的括号中间
                        if (startOffset > 0) {
                            var leftChar = text.charAt(startOffset - 1);
                            var rightChar = text.charAt(startOffset);
                            
                            // 检查是否是配对的括号
                            var isPair = (
                                (leftChar === '{' && rightChar === '}') ||
                                (leftChar === '[' && rightChar === ']') ||
                                (leftChar === '(' && rightChar === ')') ||
                                (leftChar === '"' && rightChar === '"') ||
                                (leftChar === "'" && rightChar === "'") ||
                                (leftChar === '`' && rightChar === '`')
                            );
                            
                            if (isPair) {
                                // 一次性删除两个括号
                                evt.preventDefault();
                                evt.stopPropagation();
                                
                                var newText = text.slice(0, startOffset - 1) + text.slice(startOffset + 1);
                                nativeRange.startContainer.textContent = newText;
                                
                                // 设置光标位置
                                nativeRange.setStart(nativeRange.startContainer, startOffset - 1);
                                nativeRange.collapse(true);
                                nativeSelection.removeAllRanges();
                                nativeSelection.addRange(nativeRange);
                                
                                console.log('智能删除配对括号');
                                return true;
                            }
                        }
                    }
                }
            });
            
            console.log('自动补全监听器已设置');
        });
    };
    
    console.log('插件注册完成');
}

console.log('=== UEditorPlus自动补全插件加载完成 ===');