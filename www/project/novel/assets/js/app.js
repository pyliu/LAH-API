const { createApp, ref, computed, watch, onMounted, onUnmounted, nextTick } = Vue;

const app = createApp({
    setup() {
        // --- 狀態定義 ---
        const category = ref('人界篇');
        const chapter = ref(1);

        const loading = ref(false);
        const error = ref('');
        const chapterTitle = ref('');
        const chapterParagraphs = ref([]);
        const isEditing = ref(false);
        const saveStatus = ref('');
        const editPin = ref('');

        const showPinModal = ref(false);
        const pinInput = ref('');
        const catalog = ref({});
        const searchQuery = ref('');

        const showUI = ref(true);
        const showSidebar = ref(false);
        const isRestoringScroll = ref(false);
        const highlightedParagraphIndex = ref(-1);
        const activeParagraphIndex = ref(-1);
        const targetParagraphIndexToScroll = ref(-1);
        const charBookmark = ref(null); // { chapter, pIndex, charOffset, textStyle } — 字元級書籤
        let uiHideTimer = null;
        let prefetchTimer = null;  // 預載快取防抖計時器

        // --- 預載快取狀態 ---
        // 'idle' | 'prefetching' | 'done' | 'offline'
        const cacheStatus = ref('idle');


        // --- 外觀設定 ---
        const themes = ['theme-dark', 'theme-light', 'theme-sepia'];
        const themeIndex = ref(0);
        const fontSize = ref(20);

        // --- 文字風格：'cn' = 大陸版(assets/txt)，'tw' = 台灣版(assets/txt_tw)，'alt' = 改寫版(assets/txt_tw/*-alt.txt) ---
        const textStyle = ref('alt');
        const hasAltVersion = ref(false);
        const displayTextStyle = computed(() => {
            if (textStyle.value === 'alt' && !hasAltVersion.value) {
                return 'tw';
            }
            return textStyle.value;
        });

        const themeClass = computed(() => themes[themeIndex.value]);
        const styleClass = computed(() => 'style-' + displayTextStyle.value);
        const lineHeight = computed(() => Math.round(fontSize.value * 1.8) + 'px');

        const isFirstChapter = computed(() => {
            if (category.value === '非正式篇章') return chapter.value <= 1;
            return category.value === '人界篇' && chapter.value <= 1;
        });

        const filteredCatalog = computed(() => {
            const currentCat = catalog.value[category.value];
            if (!currentCat) return [];

            const query = searchQuery.value.trim().toLowerCase();
            if (!query) return currentCat;

            return currentCat.filter(item =>
                item.title.toLowerCase().includes(query) ||
                item.id.toString().includes(query)
            );
        });

        const chapterWordCount = computed(() => {
            let count = chapterTitle.value.replace(/\s/g, '').length;
            chapterParagraphs.value.forEach(p => count += p.replace(/\s/g, '').length);
            return count;
        });

        // --- 生命週期與儲存 ---
        onMounted(async () => {
            loadSettings();
            editPin.value = sessionStorage.getItem('rm_edit_pin') || '';

            // 先載入目錄
            await fetchCatalog();

            // 再載入文章
            fetchChapter(true);
            scrollToCurrentChapter();

            // 監聽滾動，自動隱藏 UI
            window.addEventListener('scroll', handleScroll);

            // 監聽觸控手勢
            window.addEventListener('touchstart', handleTouchStart, { passive: true });
            window.addEventListener('touchend', handleTouchEnd, { passive: true });
        });

        onUnmounted(() => {
            window.removeEventListener('scroll', handleScroll);
            window.removeEventListener('touchstart', handleTouchStart);
            window.removeEventListener('touchend', handleTouchEnd);
            if (uiHideTimer) clearTimeout(uiHideTimer);
            if (prefetchTimer) clearTimeout(prefetchTimer);
        });

        function loadSettings() {
            const savedCategory = localStorage.getItem('rm_category');
            if (savedCategory) category.value = savedCategory;

            // 讀取該篇章的獨立記憶進度
            const savedChapter = localStorage.getItem(`rm_chapter_${category.value}`);
            if (savedChapter) {
                let parsed = parseInt(savedChapter, 10);
                if (category.value === '人界篇' && parsed > 1260) parsed = 1;
                if (category.value === '靈界篇' && (parsed <= 1260 || parsed > 2443)) parsed = 1261;
                chapter.value = parsed;
            } else {
                // 為了向下相容，檢查舊版的全局章節設定
                const oldChapter = localStorage.getItem('rm_chapter');
                if (oldChapter) {
                    let parsed = parseInt(oldChapter, 10);
                    if (category.value === '人界篇' && parsed > 1260) parsed = 1;
                    if (category.value === '靈界篇' && (parsed <= 1260 || parsed > 2443)) parsed = 1261;
                    chapter.value = parsed;
                }
            }

            const savedTheme = localStorage.getItem('rm_theme_idx');
            const savedSize = localStorage.getItem('rm_font_size');
            const savedStyle = localStorage.getItem('rm_text_style');

            if (savedTheme) themeIndex.value = parseInt(savedTheme, 10);
            if (savedSize) fontSize.value = parseInt(savedSize, 10);
            if (savedStyle) {
                textStyle.value = savedStyle;
            } else {
                textStyle.value = 'alt';
            }

            // 讀取字元書籤
            const savedBookmark = localStorage.getItem(`rm_bookmark_${category.value}`);
            if (savedBookmark) {
                try {
                    charBookmark.value = JSON.parse(savedBookmark);
                } catch (e) {
                    charBookmark.value = null;
                }
            }
        }

        function saveSettings() {
            localStorage.setItem('rm_category', category.value);
            // 獨立儲存各篇章的章節進度
            localStorage.setItem(`rm_chapter_${category.value}`, chapter.value);
            // 覆寫舊版的 rm_chapter 以備不時之需
            localStorage.setItem('rm_chapter', chapter.value);
            localStorage.setItem('rm_theme_idx', themeIndex.value);
            localStorage.setItem('rm_font_size', fontSize.value);
            localStorage.setItem('rm_text_style', textStyle.value);

            // 寫入字元書籤
            if (charBookmark.value) {
                localStorage.setItem(`rm_bookmark_${category.value}`, JSON.stringify(charBookmark.value));
            }
        }

        watch([category, chapter, themeIndex, fontSize, textStyle], () => {
            saveSettings();
        });

        watch([themeClass, styleClass], ([newTheme, newStyle], [oldTheme, oldStyle] = []) => {
            if (oldTheme) document.body.classList.remove(oldTheme);
            if (oldStyle) document.body.classList.remove(oldStyle);
            if (newTheme) document.body.classList.add(newTheme);
            if (newStyle) document.body.classList.add(newStyle);
        }, { immediate: true });

        // --- 介面控制 ---
        function cycleTheme() {
            themeIndex.value = (themeIndex.value + 1) % themes.length;
        }

        function toggleTextStyle() {
            var oldStyle = displayTextStyle.value;
            if (displayTextStyle.value === 'cn') {
                textStyle.value = 'tw';
            } else if (displayTextStyle.value === 'tw') {
                textStyle.value = hasAltVersion.value ? 'alt' : 'cn';
            } else {
                textStyle.value = 'cn';
            }
            // 切換風格後清除舊風格快取並重新載入當前章節
            clearChapterCache(category.value, oldStyle);
            fetchChapter(false);
        }

        function changeFontSize(delta) {
            let newSize = fontSize.value + delta;
            if (newSize >= 14 && newSize <= 40) {
                fontSize.value = newSize;
            }
        }

        function toggleUI() {
            if (showSidebar.value) {
                showSidebar.value = false; // 點擊旁邊關閉側邊欄
            } else if (!showPinModal.value) {
                showUI.value = !showUI.value;
            }
        }

        function scrollToCurrentChapter() {
            setTimeout(() => {
                const el = document.getElementById('sidebar-item-' + chapter.value);
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 150); // 稍微延遲等待 DOM 渲染
        }

        function toggleSidebar() {
            showSidebar.value = !showSidebar.value;
            if (showSidebar.value) {
                showUI.value = true;
                scrollToCurrentChapter();
            }
        }

        let scrollSaveTimer = null;
        function handleScroll() {
            // 如果側邊欄開著就不自動隱藏
            if (showSidebar.value) return;

            // 防止載入中或還原捲動位置時，因為 DOM 變動導致 scrollY 為 0 覆寫掉原有紀錄
            if (loading.value || error.value || isRestoringScroll.value) return;

            // 記錄捲動位置 (使用 debounce 避免頻繁寫入)
            if (scrollSaveTimer) clearTimeout(scrollSaveTimer);
            scrollSaveTimer = setTimeout(() => {
                localStorage.setItem(`rm_scroll_${category.value}`, Math.round(window.scrollY));
            }, 300);

            // 滾動時自動隱藏 UI，提升沈浸感
            if (showUI.value) {
                if (uiHideTimer) clearTimeout(uiHideTimer);
                uiHideTimer = setTimeout(() => {
                    showUI.value = false;
                }, 2000); // 滾動後 2 秒自動隱藏
            }
        }

        // --- 手勢控制 ---
        let touchStartX = 0;
        let touchStartY = 0;

        function handleTouchStart(e) {
            touchStartX = e.touches[0].screenX;
            touchStartY = e.touches[0].screenY;
        }

        function handleTouchEnd(e) {
            if (showSidebar.value) return; // 側邊欄開啟時不處理滑動

            const touchEndX = e.changedTouches[0].screenX;
            const touchEndY = e.changedTouches[0].screenY;

            const deltaX = touchEndX - touchStartX;
            const deltaY = touchEndY - touchStartY;

            // 確保水平滑動距離大於 60px，且水平位移明顯大於垂直位移（避免與上下捲動衝突）
            if (Math.abs(deltaX) > 60 && Math.abs(deltaX) > Math.abs(deltaY) * 1.5) {
                if (deltaX > 0) {
                    prevChapter();
                } else {
                    nextChapter();
                }
            }
        }

        // --- 預載快取工具 ---

        /** 依 category、chapterId、style 回傳對應 URL */
        function getChapterUrl(cat, chId, style) {
            if (style === 'tw') {
                return `./assets/txt_tw/${cat}/${chId}.txt`;
            } else if (style === 'alt') {
                return `./assets/txt_tw/${cat}/${chId}-alt.txt`;
            }
            return `./assets/txt/${cat}/${chId}.txt`;
        }

        /** 取得目前章節之後的最多 3 個有效章節 ID */
        function getNextChapterIds(cat, currentId) {
            var list = catalog.value[cat];
            if (!list || list.length === 0) return [];
            var ids = [];
            var found = false;
            for (var i = 0; i < list.length; i++) {
                if (found) {
                    ids.push(list[i].id);
                    if (ids.length >= 3) break;
                } else if (list[i].id === currentId) {
                    found = true;
                }
            }
            return ids;
        }

        /** 快取 key 規則：rm_cache_{category}_{chapterId}_{style} */
        function cacheKey(cat, chId, style) {
            return 'rm_cache_' + cat + '_' + chId + '_' + style;
        }

        /** 背景預載下三章，存入 localStorage；若 localStorage 已滿則靜默忽略 */
        async function prefetchChapters() {
            var style = displayTextStyle.value;
            var cat = category.value;
            var ids = getNextChapterIds(cat, chapter.value);
            if (ids.length === 0) return;

            cacheStatus.value = 'prefetching';
            var successCount = 0;

            for (var i = 0; i < ids.length; i++) {
                var chId = ids[i];
                var key = cacheKey(cat, chId, style);

                // 如果 localStorage 已有且未過期（7 天），跳過
                var existing = localStorage.getItem(key);
                if (existing) {
                    try {
                        var cached = JSON.parse(existing);
                        var age = Date.now() - (cached.ts || 0);
                        if (age < 7 * 24 * 3600 * 1000) {
                            successCount++;
                            continue;
                        }
                    } catch(e) { /* 損壞的快取，繼續重新抓取 */ }
                }

                var url = getChapterUrl(cat, chId, style);
                try {
                    var resp = await fetch(url);
                    if (resp.ok) {
                        var text = await resp.text();
                        var payload = JSON.stringify({ ts: Date.now(), text: text });
                        try {
                            localStorage.setItem(key, payload);
                            successCount++;
                        } catch(storageErr) {
                            // localStorage 已滿，靜默放棄
                            console.warn('[Cache] localStorage 空間不足，無法快取第', chId, '章');
                        }
                    }
                } catch(fetchErr) {
                    // 離線或網路錯誤，靜默放棄
                    console.warn('[Cache] 無法預載第', chId, '章：', fetchErr.message);
                }
            }

            cacheStatus.value = successCount > 0 ? 'done' : 'idle';
            // 3 秒後圖示恢復 idle（避免長時間停留在 done 狀態）
            setTimeout(function() {
                if (cacheStatus.value === 'done') cacheStatus.value = 'idle';
            }, 3000);
        }

        /** 清除指定 category + style 下的所有快取 key */
        function clearChapterCache(cat, style) {
            var keysToRemove = [];
            for (var i = 0; i < localStorage.length; i++) {
                var k = localStorage.key(i);
                if (k && k.indexOf('rm_cache_' + cat + '_') === 0 && k.indexOf('_' + style) !== -1) {
                    keysToRemove.push(k);
                }
            }
            keysToRemove.forEach(function(k) { localStorage.removeItem(k); });
        }

        // --- 閱讀邏輯 ---
        async function fetchCatalog() {
            try {
                const response = await fetch('./assets/catalog.json');
                if (response.ok) {
                    catalog.value = await response.json();
                } else {
                    console.error('無法載入目錄');
                }
            } catch (err) {
                console.error('載入目錄時發生錯誤', err);
            }
        }

        async function fetchChapter(isRestore = false) {
            loading.value = true;
            error.value = '';

            // 處理跨篇章自動切換 (人界篇 <-> 靈界篇 <-> 仙界篇)
            if (category.value === '人界篇' && chapter.value > 1260) {
                category.value = '靈界篇';
            } else if (category.value === '靈界篇' && chapter.value <= 1260) {
                category.value = '人界篇';
            } else if (category.value === '靈界篇' && chapter.value > 2443) {
                category.value = '仙界篇';
                chapter.value = 1;
            } else if (category.value === '仙界篇' && chapter.value < 1) {
                category.value = '靈界篇';
                chapter.value = 2443;
            }

            // 防呆檢測：如果目錄存在，確保章節號有效
            if (catalog.value[category.value]) {
                const isValid = catalog.value[category.value].some(item => item.id === chapter.value);
                if (!isValid) {
                    error.value = `找不到第 ${chapter.value} 章`;
                    loading.value = false;
                    return;
                }
            }

            // 檢查當前章節是否存在改寫版 (-alt.txt)
            try {
                const altUrl = `./assets/txt_tw/${category.value}/${chapter.value}-alt.txt`;
                const altCheck = await fetch(altUrl, { method: 'HEAD', cache: 'no-store' });
                hasAltVersion.value = altCheck.ok;
            } catch (e) {
                hasAltVersion.value = false;
            }

            // 依據目前實際應顯示之風格決定請求路徑
            let url = `./assets/txt/${category.value}/${chapter.value}.txt`;
            if (displayTextStyle.value === 'tw') {
                url = `./assets/txt_tw/${category.value}/${chapter.value}.txt`;
            } else if (displayTextStyle.value === 'alt') {
                url = `./assets/txt_tw/${category.value}/${chapter.value}-alt.txt`;
            }

            // 優先讀取 localStorage 快取（離線可用）
            var cachedText = null;
            var ckKey = cacheKey(category.value, chapter.value, displayTextStyle.value);
            var ckRaw = localStorage.getItem(ckKey);
            if (ckRaw) {
                try {
                    var ckData = JSON.parse(ckRaw);
                    var ckAge = Date.now() - (ckData.ts || 0);
                    if (ckAge < 7 * 24 * 3600 * 1000) {
                        cachedText = ckData.text;
                    }
                } catch(e) { /* 快取損壞，繼續網路請求 */ }
            }

            try {
                var text;
                if (cachedText !== null) {
                    // 命中快取：直接使用，並補一個短暫延遲讓淡出動畫能順暢播放
                    await new Promise(function(resolve) { setTimeout(resolve, 300); });
                    text = cachedText;
                } else {
                    // 未命中快取：從網路讀取
                    const [response] = await Promise.all([
                        fetch(url, { cache: 'no-store' }),
                        new Promise(resolve => setTimeout(resolve, 300))
                    ]);

                    if (!response.ok) {
                        throw new Error(`找不到第 ${chapter.value} 章`);
                    }
                    text = await response.text();

                    // 同步寫入快取供下次離線使用
                    try {
                        localStorage.setItem(ckKey, JSON.stringify({ ts: Date.now(), text: text }));
                    } catch(e) { /* localStorage 已滿，靜默忽略 */ }
                }

                parseChapter(text);

                // 因為有 <transition mode="out-in"> 的 0.3 秒淡出動畫
                // 必須等待動畫結束且 Vue 將新 DOM 掛載後，元素才會有高度
                setTimeout(() => {
                    isRestoringScroll.value = true;

                    if (isRestore) {
                        // 優先：字元書籤精準還原
                        const bm = charBookmark.value;
                        if (bm && bm.chapter === chapter.value) {
                            const pIdx = bm.pIndex;
                            const charOff = bm.charOffset;
                            const paragraphs = document.querySelectorAll('.chapter-content p');
                            const target = paragraphs[pIdx];
                            if (target) {
                                const y = target.getBoundingClientRect().top + window.scrollY - 120;
                                window.scrollTo({ top: Math.max(0, y), behavior: 'auto' });
                                localStorage.setItem(`rm_scroll_${category.value}`, Math.round(window.scrollY));
                                // 還原後以 highlight 提示使用者所在位置
                                highlightCharBookmark(pIdx, charOff);
                            }
                        } else if (targetParagraphIndexToScroll.value !== -1) {
                            // 次優先：段落書籤（編輯後還原用）
                            const paragraphs = document.querySelectorAll('.chapter-content p');
                            const target = paragraphs[targetParagraphIndexToScroll.value];
                            if (target) {
                                const y = target.getBoundingClientRect().top + window.scrollY - 120;
                                window.scrollTo({ top: y, behavior: 'auto' });

                                highlightedParagraphIndex.value = targetParagraphIndexToScroll.value;
                                setTimeout(() => {
                                    if (highlightedParagraphIndex.value === targetParagraphIndexToScroll.value) {
                                        highlightedParagraphIndex.value = -1;
                                    }
                                }, 3000);

                                localStorage.setItem(`rm_scroll_${category.value}`, Math.round(window.scrollY));
                            }
                            targetParagraphIndexToScroll.value = -1;
                            activeParagraphIndex.value = -1;
                        } else {
                            // 最後：fallback 到 px 捲動位置
                            const savedScroll = localStorage.getItem(`rm_scroll_${category.value}`);
                            if (savedScroll) {
                                window.scrollTo({ top: parseInt(savedScroll, 10), behavior: 'auto' });
                                highlightCurrentView();
                            } else {
                                window.scrollTo({ top: 0, behavior: 'auto' });
                            }
                        }
                    } else {
                        // 切換新章節後回到頂部，並重置該篇章的捲動記憶
                        window.scrollTo({ top: 0, behavior: 'auto' });
                        localStorage.setItem(`rm_scroll_${category.value}`, 0);
                    }

                    // 等待瀏覽器完成滾動，避免觸發錯誤的 handleScroll
                    setTimeout(() => {
                        isRestoringScroll.value = false;
                    }, 300);
                }, 600);

                // 切換章節後短暫顯示 UI 讓使用者知道成功跳轉
                showUI.value = true;
                if (uiHideTimer) clearTimeout(uiHideTimer);
                uiHideTimer = setTimeout(() => { showUI.value = false; }, 3000);

                // 成功載入後，背景預載下三章（防抖 500ms，避免快速翻頁重複觸發）
                if (prefetchTimer) clearTimeout(prefetchTimer);
                prefetchTimer = setTimeout(function() {
                    prefetchChapters();
                }, 500);

            } catch (err) {
                error.value = err.message;
            } finally {
                loading.value = false;
                activeParagraphIndex.value = -1;
            }
        }

        function parseChapter(text) {
            // 移除前後空白並將 \r\n 轉為 \n
            let lines = text.replace(/\r\n/g, '\n').split('\n').map(l => l.trim()).filter(l => l.length > 0);

            if (lines.length === 0) {
                error.value = '檔案內容為空';
                return;
            }

            // 第一行通常是標題
            chapterTitle.value = lines[0];

            // 有些標題會在前幾行重複，簡單過濾掉
            let contentLines = lines.slice(1);
            if (contentLines.length > 0 && contentLines[0].includes(chapterTitle.value)) {
                contentLines = contentLines.slice(1);
            }

            // 組合 HTML 段落改為陣列
            chapterParagraphs.value = contentLines;
        }

        function highlightCurrentView() {
            setTimeout(() => {
                const paragraphs = document.querySelectorAll('.chapter-content p');
                let closestIndex = -1;
                let minDistance = Infinity;
                for (let i = 0; i < paragraphs.length; i++) {
                    const rect = paragraphs[i].getBoundingClientRect();
                    const distance = Math.abs(rect.top - 120);
                    if (distance < minDistance) {
                        minDistance = distance;
                        closestIndex = i;
                    }
                }
                if (closestIndex !== -1) {
                    highlightedParagraphIndex.value = closestIndex;
                    setTimeout(() => {
                        if (highlightedParagraphIndex.value === closestIndex) {
                            highlightedParagraphIndex.value = -1;
                        }
                    }, 3000);
                }
            }, 100);
        }

        // 從 click event 取得在 TextNode 中的字元 offset 與所在 <p> 段落 index
        // 回傳 { pIndex, charOffset } 或 null
        function getCharOffsetFromClick(e) {
            let range;
            if (document.caretRangeFromPoint) {
                // Chrome / Safari / Edge
                range = document.caretRangeFromPoint(e.clientX, e.clientY);
            } else if (document.caretPositionFromPoint) {
                // Firefox
                var pos = document.caretPositionFromPoint(e.clientX, e.clientY);
                if (!pos) return null;
                range = document.createRange();
                range.setStart(pos.offsetNode, pos.offset);
            }
            if (!range) return null;

            var charOffset = range.startOffset;
            var node = range.startContainer;

            // 往上找到最近的 <p> 元素
            var el = (node.nodeType === 3) ? node.parentElement : node;
            while (el && el.tagName !== 'P') {
                el = el.parentElement;
            }
            if (!el) return null;

            // 確認 <p> 屬於 .chapter-content
            var paragraphs = document.querySelectorAll('.chapter-content p');
            var pIndex = -1;
            for (var i = 0; i < paragraphs.length; i++) {
                if (paragraphs[i] === el) { pIndex = i; break; }
            }
            if (pIndex === -1) return null;

            return { pIndex: pIndex, charOffset: charOffset };
        }

        // 在目標段落的指定字元位置注入 <mark class="char-bookmark"> 並 scrollIntoView
        // doScroll=true（預設）：還原書籤時捲動到 mark；doScroll=false：點擊當下已有平滑捲動，不重複捲動
        // 3 秒後自動移除 <mark>，還原 TextNode
        function highlightCharBookmark(pIndex, charOffset, doScroll) {
            if (doScroll === undefined) doScroll = true;
            setTimeout(function() {
                var paragraphs = document.querySelectorAll('.chapter-content p');
                var target = paragraphs[pIndex];
                if (!target) return;

                // 找到 TextNode（<p> 的第一個 text child）
                var textNode = null;
                for (var i = 0; i < target.childNodes.length; i++) {
                    if (target.childNodes[i].nodeType === 3) {
                        textNode = target.childNodes[i];
                        break;
                    }
                }
                if (!textNode) return;

                var text = textNode.textContent;
                var len = text.length;
                if (charOffset >= len) charOffset = Math.max(0, len - 1);

                // 書籤詞框：以 charOffset 為中心，前後各取 3 字（共約 7 字）
                var start = Math.max(0, charOffset - 3);
                var end   = Math.min(len, charOffset + 4);

                // 用 Range API 框住目標文字範圍
                var range = document.createRange();
                range.setStart(textNode, start);
                range.setEnd(textNode, end);

                var mark = document.createElement('mark');
                mark.className = 'char-bookmark';

                try {
                    range.surroundContents(mark);
                } catch (err) {
                    // surroundContents 若遇到跨 element 邊界會 throw，fallback 到整段高亮
                    highlightedParagraphIndex.value = pIndex;
                    setTimeout(function() {
                        if (highlightedParagraphIndex.value === pIndex) {
                            highlightedParagraphIndex.value = -1;
                        }
                    }, 3000);
                    return;
                }

                // 捲動讓 mark 進入視野
                if (doScroll) {
                    mark.scrollIntoView({ behavior: 'auto', block: 'center' });
                }

                // 3 秒後移除 <mark>，還原文字節點
                setTimeout(function() {
                    if (mark.parentNode) {
                        var parent = mark.parentNode;
                        var frag = document.createDocumentFragment();
                        while (mark.firstChild) frag.appendChild(mark.firstChild);
                        parent.replaceChild(frag, mark);
                        parent.normalize(); // 合併相鄰 TextNode
                    }
                }, 3100);
            }, 100);
        }

        async function setMemoryPoint(index, e) {
            if (isEditing.value) return;

            if (activeParagraphIndex.value === index) {
                // 再次點選同一段落時取消保留的高亮
                activeParagraphIndex.value = -1;
            } else {
                // 字元書籤：從 click event 取得精確字元位置
                var loc = (e) ? getCharOffsetFromClick(e) : null;
                var newBookmark = {
                    chapter: chapter.value,
                    pIndex: index,
                    charOffset: (loc && loc.pIndex === index) ? loc.charOffset : 0,
                    textStyle: displayTextStyle.value
                };
                charBookmark.value = newBookmark;
                // 立即寫入 localStorage
                localStorage.setItem('rm_bookmark_' + category.value, JSON.stringify(newBookmark));

                // 點選段落時保留高亮
                activeParagraphIndex.value = index;

                // 等待 Vue 更新 DOM（套用 .active-paragraph 的邊框與內距樣式）
                await nextTick();

                // 確保瀏覽器完成 layout 繪製後動態實時測量該句子的精準位置並進行滾動
                requestAnimationFrame(() => {
                    const paragraphs = document.querySelectorAll('.chapter-content p');
                    const target = paragraphs[index];
                    if (target) {
                        // 實時獲取句子相對於目前 viewport 頂部的距離
                        const rect = target.getBoundingClientRect();
                        const currentScrollY = window.scrollY || window.pageYOffset || 0;

                        // 計算讓句子頂部恰好定位在 viewport 上方 74px 所需的 ScrollY 位置
                        const targetScrollY = currentScrollY + rect.top - 74;

                        window.scrollTo({
                            top: Math.max(0, targetScrollY),
                            behavior: 'smooth'
                        });
                    }
                });

                // 點擊當下立即注入字元 highlight，doScroll=false 避免與上方平滑捲動衝突
                highlightCharBookmark(index, newBookmark.charOffset, false);
            }

            // 點擊段落（無論高亮還是取消）強制顯示 UI 導航列，以便可以按編輯
            showUI.value = true;

            // 清除自動隱藏計時器，避免 UI 剛跑出來又馬上因為先前的計時而被隱藏
            if (uiHideTimer) clearTimeout(uiHideTimer);
        }

        function reloadAndHighlight() {
            if (activeParagraphIndex.value !== -1) {
                // 記錄目標段落索引，讓 fetchChapter(true) 能夠基於新 DOM 無視 padding 差異精準定位
                targetParagraphIndexToScroll.value = activeParagraphIndex.value;
            } else {
                localStorage.setItem(`rm_scroll_${category.value}`, Math.round(window.scrollY));
            }
            fetchChapter(true);
        }

        function cancelEdit() {
            isEditing.value = false;
            reloadAndHighlight();
        }

        function handleEditClick() {
            if (isEditing.value) {
                cancelEdit();
            } else if (editPin.value) {
                isEditing.value = true;
                if (activeParagraphIndex.value !== -1) {
                    const paragraphs = document.querySelectorAll('.chapter-content p');
                    if (paragraphs[activeParagraphIndex.value]) {
                        const y = paragraphs[activeParagraphIndex.value].getBoundingClientRect().top + window.scrollY - 120;
                        window.scrollTo({ top: y, behavior: 'smooth' });
                        highlightedParagraphIndex.value = activeParagraphIndex.value;
                        setTimeout(() => {
                            if (highlightedParagraphIndex.value === activeParagraphIndex.value) {
                                highlightedParagraphIndex.value = -1;
                            }
                        }, 3000);
                    }
                } else {
                    highlightCurrentView();
                }
            } else {
                showPinModal.value = true;
                pinInput.value = '';
            }
        }

        async function submitPin() {
            if (!pinInput.value) return;

            try {
                const response = await fetch('./save_chapter.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'verify',
                        pin: pinInput.value
                    })
                });

                if (response.ok) {
                    editPin.value = pinInput.value;
                    sessionStorage.setItem('rm_edit_pin', editPin.value);
                    showPinModal.value = false;
                    isEditing.value = true;
                    highlightCurrentView();
                } else {
                    alert('密碼錯誤，請重新輸入！');
                    pinInput.value = '';
                }
            } catch (err) {
                alert('連線錯誤，無法驗證密碼');
            }
        }

        function cancelPin() {
            showPinModal.value = false;
        }

        async function saveChapter() {
            // 組合新內容
            const newContent = [chapterTitle.value, ...chapterParagraphs.value].join('\n\n');

            saveStatus.value = '儲存中...';
            try {
                const response = await fetch('./save_chapter.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        category: category.value,
                        chapter: chapter.value,
                        content: newContent,
                        pin: editPin.value,
                        textStyle: displayTextStyle.value   // 'cn' 或 'tw' 或 'alt'
                    })
                });

                if (response.ok) {
                    saveStatus.value = '儲存成功！';
                    isEditing.value = false;
                    setTimeout(() => saveStatus.value = '', 2000);
                    reloadAndHighlight();
                } else if (response.status === 403) {
                    saveStatus.value = '密碼錯誤！請重新輸入';
                    isEditing.value = false;
                    editPin.value = '';
                    sessionStorage.removeItem('rm_edit_pin');
                    setTimeout(() => saveStatus.value = '', 3000);
                } else {
                    const data = await response.json().catch(() => ({}));
                    saveStatus.value = '錯誤: ' + (data.error || '儲存失敗');
                    setTimeout(() => saveStatus.value = '', 3000);
                }
            } catch (err) {
                saveStatus.value = '連線錯誤: ' + err.message;
                setTimeout(() => saveStatus.value = '', 3000);
            }
        }

        function selectChapter(id) {
            chapter.value = id;
            showSidebar.value = false; // 手機版選擇後關閉側邊欄
            fetchChapter();
            scrollToCurrentChapter();
        }

        function prevChapter() {
            if (isFirstChapter.value) return;

            chapter.value--;
            fetchChapter();
            scrollToCurrentChapter();
        }

        function nextChapter() {
            chapter.value++;
            fetchChapter();
            scrollToCurrentChapter();
        }

        function onCategoryChange(newCategory) {
            // 切換篇章時，嘗試載入該篇章最後閱讀的章節進度
            let savedChapter = localStorage.getItem(`rm_chapter_${newCategory}`);
            let targetChapter = null;

            if (savedChapter) {
                targetChapter = parseInt(savedChapter, 10);

                // 防呆：如果讀取到的章節不屬於該篇章的範圍，則捨棄（修復 localStorage 污染的問題）
                if (newCategory === '人界篇' && targetChapter > 1260) targetChapter = null;
                if (newCategory === '靈界篇' && (targetChapter <= 1260 || targetChapter > 2443)) targetChapter = null;
            }

            if (targetChapter) {
                chapter.value = targetChapter;
            } else {
                // 如果沒有紀錄或紀錄無效，預設跳轉到該篇章的第一個邏輯章節
                if (newCategory === '人界篇') chapter.value = 1;
                else if (newCategory === '靈界篇') chapter.value = 1261;
                else if (newCategory === '仙界篇' || newCategory === '非正式篇章') chapter.value = 1;
            }

            category.value = newCategory;

            fetchChapter(true);
            scrollToCurrentChapter();
        }

        function goToFirst() {
            onCategoryChange(category.value);
        }

        return {
            category,
            chapter,
            catalog,
            searchQuery,
            filteredCatalog,
            loading,
            error,
            chapterTitle,
            chapterParagraphs,
            chapterWordCount,
            activeParagraphIndex,
            highlightedParagraphIndex,
            charBookmark,
            isEditing,
            saveStatus,
            showPinModal,
            pinInput,
            showUI,
            showSidebar,
            themeClass,
            styleClass,
            themeIndex,
            fontSize,
            lineHeight,
            isFirstChapter,
            textStyle,
            displayTextStyle,
            hasAltVersion,
            cacheStatus,

            cycleTheme,
            changeFontSize,
            toggleTextStyle,
            toggleUI,
            toggleSidebar,
            selectChapter,
            prevChapter,
            nextChapter,
            onCategoryChange,
            goToFirst,
            fetchChapter,
            handleEditClick,
            cancelEdit,
            setMemoryPoint,
            submitPin,
            cancelPin,
            saveChapter
        };
    }
});

app.mount('#app');