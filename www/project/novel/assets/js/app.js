const { createApp, ref, computed, watch, onMounted, onUnmounted } = Vue;

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
        let uiHideTimer = null;

        // --- 外觀設定 ---
        const themes = ['theme-dark', 'theme-light', 'theme-sepia'];
        const themeIndex = ref(0);
        const fontSize = ref(20);
        
        const themeClass = computed(() => themes[themeIndex.value]);
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
            
            if (savedTheme) themeIndex.value = parseInt(savedTheme, 10);
            if (savedSize) fontSize.value = parseInt(savedSize, 10);
        }

        function saveSettings() {
            localStorage.setItem('rm_category', category.value);
            // 獨立儲存各篇章的章節進度
            localStorage.setItem(`rm_chapter_${category.value}`, chapter.value);
            // 覆寫舊版的 rm_chapter 以備不時之需
            localStorage.setItem('rm_chapter', chapter.value);
            localStorage.setItem('rm_theme_idx', themeIndex.value);
            localStorage.setItem('rm_font_size', fontSize.value);
        }

        watch([category, chapter, themeIndex, fontSize], () => {
            saveSettings();
        });

        watch(themeClass, (newClass, oldClass) => {
            if (oldClass) document.body.classList.remove(oldClass);
            if (newClass) document.body.classList.add(newClass);
        }, { immediate: true });

        // --- 介面控制 ---
        function cycleTheme() {
            themeIndex.value = (themeIndex.value + 1) % themes.length;
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

            const url = `./assets/txt/${category.value}/${chapter.value}.txt`;
            
            try {
                // 強制加入些微延遲讓淡出動畫能順暢播放
                const [response] = await Promise.all([
                    fetch(url, { cache: 'no-store' }),
                    new Promise(resolve => setTimeout(resolve, 300))
                ]);
                
                if (!response.ok) {
                    throw new Error(`找不到第 ${chapter.value} 章`);
                }
                
                const text = await response.text();
                parseChapter(text);
                
                // 因為有 <transition mode="out-in"> 的 0.3 秒淡出動畫
                // 必須等待動畫結束且 Vue 將新 DOM 掛載後，元素才會有高度
                setTimeout(() => {
                    isRestoringScroll.value = true;
                    
                    if (isRestore) {
                        const savedScroll = localStorage.getItem(`rm_scroll_${category.value}`);
                        if (savedScroll) {
                            window.scrollTo({ top: parseInt(savedScroll, 10), behavior: 'auto' });
                        } else {
                            window.scrollTo({ top: 0, behavior: 'auto' });
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
                
            } catch (err) {
                error.value = err.message;
            } finally {
                loading.value = false;
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

        function handleEditClick() {
            if (isEditing.value) {
                isEditing.value = false;
                fetchChapter(); // 還原
            } else if (editPin.value) {
                isEditing.value = true;
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
                        pin: editPin.value
                    })
                });
                
                if (response.ok) {
                    saveStatus.value = '儲存成功！';
                    isEditing.value = false;
                    setTimeout(() => saveStatus.value = '', 2000);
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
            isEditing,
            saveStatus,
            showPinModal,
            pinInput,
            showUI,
            showSidebar,
            themeClass,
            fontSize,
            lineHeight,
            isFirstChapter,
            
            cycleTheme,
            changeFontSize,
            toggleUI,
            toggleSidebar,
            selectChapter,
            prevChapter,
            nextChapter,
            onCategoryChange,
            goToFirst,
            fetchChapter,
            handleEditClick,
            submitPin,
            cancelPin,
            saveChapter
        };
    }
});

app.mount('#app');
