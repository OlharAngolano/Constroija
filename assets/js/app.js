/* Constrói Já — Core JavaScript Application Module (ES2022) */

const App = {
    // Configurações injetadas pelo PHP no layout global
    url: window.APP?.url || window.location.origin,
    csrfToken: window.APP?.csrfToken || '',
    userId: window.APP?.userId || null,
    isLoggedIn: window.APP?.isLoggedIn || false,
    isAdmin: window.APP?.isAdmin || false,

    // --- HTTP & API HELPERS ---

    /**
     * Efetua pedidos HTTP para os endpoints da API com injeção automática de CSRF e CORS
     */
    async api(endpoint, options = {}) {
        // Se for um endpoint relativo, usar caminhos relativos ao domínio atual para evitar bloqueios de CORS e CSP (connect-src 'self')
        const url = endpoint.startsWith('http') ? endpoint : `/${endpoint.replace(/^\//, '')}`;
        
        // Configurar cabeçalhos padrão
        options.headers = {
            'X-Requested-With': 'XMLHttpRequest',
            ...options.headers
        };

        // Injetar token CSRF para pedidos que modificam dados
        if (options.method && ['POST', 'PUT', 'DELETE'].includes(options.method.toUpperCase())) {
            if (this.csrfToken) {
                options.headers['X-CSRF-Token'] = this.csrfToken;
            }
        }

        try {
            const response = await fetch(url, options);
            
            // Ler resposta como texto primeiro para tratar respostas que não são JSON válido
            const responseText = await response.text();
            let data = {};
            
            try {
                if (responseText.trim()) {
                    data = JSON.parse(responseText);
                }
            } catch (jsonError) {
                console.error("Erro ao analisar JSON da resposta:", responseText);
                throw new Error(`Resposta inválida do servidor (Formato inesperado).`);
            }
            
            if (!response.ok) {
                throw new Error(data.error || `Erro de rede: ${response.status}`);
            }
            return data;
        } catch (error) {
            console.error(`Erro API [${endpoint}]:`, error);
            throw error;
        }
    },

    async get(endpoint) {
        return this.api(endpoint, { method: 'GET' });
    },

    async post(endpoint, data = {}) {
        return this.api(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
    },

    async upload(endpoint, formData) {
        // O fetch define o Content-Type de forma automática com a boundary correspondente
        return this.api(endpoint, {
            method: 'POST',
            body: formData
        });
    },

    async delete(endpoint, data = {}) {
        return this.api(endpoint, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
    },

    // --- UI HELPERS ---

    /**
     * Mostra um toast elegante no canto superior direito
     */
    showToast(message, type = 'success', duration = 4000) {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `flash-message flash-message-${type}`;
        
        // Ícones baseados no tipo
        let icon = 'info';
        if (type === 'success') icon = 'check-circle';
        if (type === 'danger') icon = 'alert-triangle';
        if (type === 'warning') icon = 'alert-circle';

        toast.innerHTML = `
            <i data-lucide="${icon}"></i>
            <span>${message}</span>
        `;
        
        container.appendChild(toast);
        if (window.lucide) window.lucide.createIcons();

        // Remover toast após animação
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-10px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },

    showModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('active');
        }
    },

    hideModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('active');
        }
    },

    openConfirm(message, onConfirm) {
        // Modal de confirmação simples, mas premium e nativa
        if (confirm(message)) {
            onConfirm();
        }
    },

    setLoading(btn, loading = true) {
        if (!btn) return;
        if (loading) {
            btn.disabled = true;
            btn.dataset.originalText = btn.innerHTML;
            btn.innerHTML = `<div class="spinner" style="width:16px; height:16px; border-width:2px;"></div> A processar...`;
        } else {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.originalText || btn.innerHTML;
        }
    },

    formatCurrency(amount, currency = 'AOA') {
        const num = parseFloat(amount);
        if (isNaN(num)) return '0,00 Kz';
        
        switch (currency.toUpperCase()) {
            case 'USD':
                return '$ ' + num.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
            case 'EUR':
                return '€ ' + num.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&.').replace('.', ',');
            case 'AOA':
            default:
                return num.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ".") + ' Kz';
        }
    },

    timeAgo(dateStr) {
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return 'agora';
        const seconds = Math.floor((new Date() - date) / 1000);
        
        let interval = seconds / 31536000;
        if (interval > 1) return `há ${Math.floor(interval)} ano(s)`;
        interval = seconds / 2592000;
        if (interval > 1) return `há ${Math.floor(interval)} mê(s)es`;
        interval = seconds / 86400;
        if (interval > 1) return `há ${Math.floor(interval)} dia(s)`;
        interval = seconds / 3600;
        if (interval > 1) return `há ${Math.floor(interval)} hora(s)`;
        interval = seconds / 60;
        if (interval > 1) return `há ${Math.floor(interval)} minuto(s)`;
        return "agora mesmo";
    },

    // --- AUTENTICAÇÃO ---

    async logout() {
        try {
            await this.post('/api/auth/logout');
            this.showToast('Sessão encerrada com sucesso.', 'success');
            setTimeout(() => window.location.href = '/', 1000);
        } catch (e) {
            window.location.href = '/';
        }
    },

    // --- FEED SOCIAL & STORIES ---
    Feed: {
        page: 1,
        hasMore: true,
        loading: false,
        searchQuery: '',

        linkify(text) {
            if (!text) return '';
            let escaped = text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
            const urlRegex = /(https?:\/\/|ftp:\/\/|file:\/\/|www\.)[^\s<]+/gi;
            return escaped.replace(urlRegex, (url) => {
                let href = url;
                if (url.toLowerCase().startsWith('www.')) {
                    href = 'http://' + url;
                }
                return `<a href="${href}" target="_blank" rel="noopener noreferrer" style="color:var(--accent-primary); text-decoration:underline;">${url}</a>`;
            });
        },

        formatPostBody(postId, content) {
            const maxChars = 280;
            if (!content) return '';
            if (content.length <= maxChars) {
                const div = document.createElement('div');
                div.innerText = content;
                return this.renderHashtags(div.innerHTML);
            }
            
            const divShort = document.createElement('div');
            divShort.innerText = content.substring(0, maxChars);
            const shortEscaped = divShort.innerHTML;
            
            const divFull = document.createElement('div');
            divFull.innerText = content;
            const fullEscaped = divFull.innerHTML;

            return `
                <span class="post-content-short">${this.renderHashtags(shortEscaped)}</span>
                <span class="post-content-full" style="display:none;">${this.renderHashtags(fullEscaped)}</span>
                <button onclick="App.Feed.toggleReadMore(${postId})" class="read-more-btn" style="background:none; border:none; color:var(--accent-primary); font-weight:600; padding:0; margin-left:4px; cursor:pointer; font-size:14px; display:inline-block; vertical-align:baseline;">... Ver mais</button>
            `;
        },

        toggleReadMore(postId) {
            const container = document.getElementById(`post-content-${postId}`);
            if (!container) return;
            const shortEl = container.querySelector('.post-content-short');
            const fullEl = container.querySelector('.post-content-full');
            const btnEl = container.querySelector('.read-more-btn');
            
            if (shortEl && fullEl && btnEl) {
                if (fullEl.style.display === 'none') {
                    fullEl.style.display = 'inline';
                    shortEl.style.display = 'none';
                    btnEl.textContent = 'Ver menos';
                } else {
                    fullEl.style.display = 'none';
                    shortEl.style.display = 'inline';
                    btnEl.textContent = '... Ver mais';
                }
            }
        },

        renderHashtags(html) {
            if (!html) return '';
            const hashtagRegex = /(^|\s)#([a-zA-Z0-9_À-ÿ]+)/g;
            return html.replace(hashtagRegex, (match, space, tag) => {
                return `${space}<span onclick="event.stopPropagation(); App.Feed.setSearchQuery('#${tag}')" style="color:var(--accent-primary); cursor:pointer; font-weight:600; text-decoration:none;" class="hashtag-link">#${tag}</span>`;
            });
        },

        setSearchQuery(query) {
            const listEl = document.getElementById('feed-posts-list');
            if (!listEl) {
                window.location.href = `/feed?search=${encodeURIComponent(query)}`;
                return;
            }
            
            this.searchQuery = query;
            this.page = 1;
            listEl.innerHTML = '';
            this.hasMore = true;
            
            const indicator = document.getElementById('feed-search-indicator');
            const tagEl = document.getElementById('feed-search-tag');
            if (indicator && tagEl) {
                if (query) {
                    tagEl.textContent = query;
                    indicator.style.display = 'flex';
                } else {
                    indicator.style.display = 'none';
                }
            }
            
            this.loadMore();
        },

        clearSearch() {
            this.setSearchQuery('');
        },

        getDefaultVideoUrl(q, fallbackUrl) {
            if (!q) return fallbackUrl;
            const speed = navigator.connection ? navigator.connection.downlink : 5;
            if (speed < 1.5 && q['360p']) {
                return q['360p'];
            }
            return q['720p'] || q['original'] || fallbackUrl;
        },

        changeVideoQuality(postId, quality) {
            const video = document.getElementById(`video-el-${postId}`);
            if (!video) return;
            
            const qualities = JSON.parse(video.getAttribute('data-qualities') || '{}');
            let targetUrl = '';
            
            if (quality === 'auto') {
                const speed = navigator.connection ? navigator.connection.downlink : 5;
                if (speed < 1.5) {
                    targetUrl = qualities['360p'] || qualities['original'] || qualities['720p'] || '';
                } else {
                    targetUrl = qualities['720p'] || qualities['original'] || qualities['360p'] || '';
                }
            } else {
                targetUrl = qualities[quality] || '';
            }
            
            if (targetUrl) {
                const currentTime = video.currentTime;
                const isPaused = video.paused;
                
                video.src = `${App.url}/${targetUrl}`;
                video.load();
                video.currentTime = currentTime;
                
                if (!isPaused) {
                    video.play().catch(() => {});
                }
                App.showToast(`Qualidade alterada para: ${quality === 'auto' ? 'Auto' : quality}`, 'info');
            }
        },

        switchTab(tab) {
            document.querySelectorAll('.feed-tab-btn').forEach(btn => {
                btn.classList.remove('active');
                btn.style.color = 'var(--text-muted)';
                btn.style.borderBottom = 'none';
            });
            
            const activeBtn = document.getElementById(`tab-btn-${tab}`);
            if (activeBtn) {
                activeBtn.classList.add('active');
                activeBtn.style.color = 'var(--text-primary)';
                activeBtn.style.borderBottom = '2px solid var(--accent-primary)';
            }
            
            const filterSelect = document.getElementById('feed-filter');
            if (filterSelect) {
                filterSelect.value = tab;
            }
            
            this.page = 1;
            const listEl = document.getElementById('feed-posts-list');
            if (listEl) listEl.innerHTML = '';
            this.hasMore = true;
            this.loadMore();
        },

        renderReelCard(post) {
            const card = document.createElement('div');
            card.className = 'reel-card slideUp';
            card.id = `post-card-${post.id}`;
            card.style.cssText = `
                position: relative;
                width: 100%;
                max-width: 360px;
                height: 600px;
                background: #000;
                border-radius: var(--radius-md);
                overflow: hidden;
                margin: 0 auto 24px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.5);
                border: 1px solid rgba(255,255,255,0.05);
            `;
            
            const q = post.video_qualities;
            let videoHTML = '';
            
            if (q && q.status === 'processing') {
                videoHTML = `
                    <div style="position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:20px; text-align:center; background:#0f1422; color:#fff;">
                        <div class="spinner" style="display:inline-block; width:30px; height:30px; border:3px solid rgba(249,115,22,0.3); border-top-color:var(--accent-primary); border-radius:50%; animation: spin 1s linear infinite; margin-bottom:12px;"></div>
                        <p style="font-size:13px; color:var(--text-secondary);">Processando vídeo...</p>
                    </div>
                `;
            } else {
                const defaultUrl = this.getDefaultVideoUrl(q, post.file_url);
                let options = `<option value="auto">Auto</option>`;
                if (q) {
                    if (q['720p']) options += `<option value="720p">720p</option>`;
                    if (q['360p']) options += `<option value="360p">360p</option>`;
                    if (q['original']) options += `<option value="original">Original</option>`;
                }
                
                videoHTML = `
                    <video id="video-el-${post.id}" class="reel-video-element" style="width:100%; height:100%; object-fit:cover;" loop playsinline data-qualities='${JSON.stringify(q || {})}'>
                        <source src="${App.url}/${defaultUrl}" type="${post.file_type}">
                    </video>
                    <div onclick="App.Feed.togglePlayReel(${post.id})" style="position:absolute; inset:0; cursor:pointer; z-index:2;"></div>
                    
                    <div style="position:absolute; top:15px; right:15px; z-index:10; background:rgba(0,0,0,0.6); padding:4px 8px; border-radius:4px; font-size:12px; color:#fff; display:flex; align-items:center; gap:4px;">
                        <i data-lucide="settings" style="width:12px; height:12px;"></i>
                        <select onchange="App.Feed.changeVideoQuality(${post.id}, this.value)" style="background:none; border:none; color:#fff; font-size:12px; font-weight:700; cursor:pointer; outline:none;" id="video-quality-select-${post.id}">
                            ${options}
                        </select>
                    </div>
                `;
            }
            
            const isLiked = parseInt(post.is_liked || 0) === 1;
            const likeColor = isLiked ? 'var(--accent-primary)' : '#fff';
            const likeFill = isLiked ? 'var(--accent-primary)' : 'none';
            
            card.innerHTML = `
                ${videoHTML}
                
                <div style="position:absolute; bottom:0; left:0; right:0; background:linear-gradient(transparent, rgba(0,0,0,0.85)); padding:20px 15px; z-index:5; color:#fff; display:flex; flex-direction:column; gap:8px; pointer-events:none;">
                    <div style="display:flex; align-items:center; gap:10px; pointer-events:auto;">
                        <a href="${App.url}/profile/${post.username}" style="display:flex; align-items:center; gap:8px; color:#fff; text-decoration:none;">
                            <img src="${post.avatar_url}" style="width:36px; height:36px; border-radius:50%; border:2px solid var(--accent-primary); object-fit:cover;">
                            <div>
                                <span style="font-weight:700; font-size:14px; text-shadow:0 1px 3px rgba(0,0,0,0.8);">${post.name}</span>
                                <span style="font-size:10px; color:rgba(255,255,255,0.7); display:block;">@${post.username}</span>
                            </div>
                        </a>
                    </div>
                    <p style="font-size:13px; margin:0; text-shadow:0 1px 3px rgba(0,0,0,0.8); max-height:80px; overflow-y:auto; pointer-events:auto;">
                        ${this.formatPostBody(post.id, post.content || '')}
                    </p>
                </div>
                
                <div style="position:absolute; right:15px; bottom:120px; z-index:6; display:flex; flex-direction:column; gap:16px; align-items:center;">
                    <button onclick="App.Feed.toggleLikeReel(${post.id})" id="like-btn-reel-${post.id}" style="background:rgba(0,0,0,0.5); border:none; width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:${likeColor}; cursor:pointer; transition:scale 0.2s;">
                        <i data-lucide="heart" style="width:20px; height:20px; fill:${likeFill};"></i>
                    </button>
                    <span id="like-count-reel-${post.id}" style="font-size:11px; color:#fff; font-weight:700; text-shadow:0 1px 2px rgba(0,0,0,0.8); margin-top:-10px;">${post.likes_count || 0}</span>
                    
                    <button onclick="App.Feed.openReelComments(${post.id})" style="background:rgba(0,0,0,0.5); border:none; width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; cursor:pointer;">
                        <i data-lucide="message-circle" style="width:20px; height:20px;"></i>
                    </button>
                    <span style="font-size:11px; color:#fff; font-weight:700; text-shadow:0 1px 2px rgba(0,0,0,0.8); margin-top:-10px;">${post.comments_count || 0}</span>
                </div>
            `;
            
            setTimeout(() => {
                const videoEl = card.querySelector('.reel-video-element');
                if (videoEl) {
                    const obs = new IntersectionObserver((entries) => {
                        if (entries[0].isIntersecting) {
                            videoEl.play().catch(() => {});
                        } else {
                            videoEl.pause();
                        }
                    }, { threshold: 0.6 });
                    obs.observe(videoEl);
                }
            }, 100);

            return card;
        },

        togglePlayReel(postId) {
            const video = document.getElementById(`video-el-${postId}`);
            if (!video) return;
            if (video.paused) {
                video.play().catch(() => {});
            } else {
                video.pause();
            }
        },
        
        async toggleLikeReel(postId) {
            try {
                const response = await App.post('/api/posts/like', { post_id: postId });
                const btn = document.getElementById(`like-btn-reel-${postId}`);
                const count = document.getElementById(`like-count-reel-${postId}`);
                
                if (btn && count) {
                    if (response.data.liked) {
                        btn.style.color = 'var(--accent-primary)';
                        btn.querySelector('i').style.fill = 'var(--accent-primary)';
                    } else {
                        btn.style.color = '#fff';
                        btn.querySelector('i').style.fill = 'none';
                    }
                    count.innerText = response.data.likes_count;
                }
            } catch (e) {
                App.showToast('Erro ao registar gosto.', 'danger');
            }
        },

        openReelComments(postId) {
            let modal = document.getElementById('reel-comments-modal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'reel-comments-modal';
                modal.className = 'lightbox-overlay';
                modal.style.cssText = `
                    position: fixed;
                    inset: 0;
                    background: rgba(0,0,0,0.8);
                    z-index: 1000;
                    display: flex;
                    align-items: flex-end;
                    justify-content: center;
                    opacity: 0;
                    pointer-events: none;
                    transition: opacity 0.3s ease;
                `;
                document.body.appendChild(modal);
            }
            
            modal.innerHTML = `
                <div style="background:var(--bg-secondary); border-top-left-radius:16px; border-top-right-radius:16px; width:100%; max-width:480px; height:80vh; display:flex; flex-direction:column; padding:20px; box-shadow:0 -10px 30px rgba(0,0,0,0.5); transform:translateY(100%); transition:transform 0.3s ease;" id="reel-comments-content">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid var(--border-color); padding-bottom:12px;">
                        <h4 style="margin:0; font-size:16px;">Comentários</h4>
                        <button onclick="App.Feed.closeReelComments()" style="background:none; border:none; color:var(--text-primary); cursor:pointer;"><i data-lucide="x" style="width:20px; height:20px;"></i></button>
                    </div>
                    <div id="reel-comments-list-${postId}" style="flex:1; overflow-y:auto; display:flex; flex-direction:column; gap:12px; margin-bottom:16px;">
                        <div style="text-align:center; padding:20px; color:var(--text-muted);">A carregar...</div>
                    </div>
                    <form onsubmit="event.preventDefault(); App.Feed.submitReelComment(${postId});" style="display:flex; gap:10px;">
                        <input type="text" id="reel-comment-input-${postId}" class="post-card-fb__comment-input" placeholder="Comente este reel..." required style="flex:1;">
                        <button type="submit" class="post-card-fb__comment-send"><i data-lucide="send" style="width:16px; height:16px;"></i></button>
                    </form>
                </div>
            `;
            
            modal.style.opacity = '1';
            modal.style.pointerEvents = 'auto';
            setTimeout(() => {
                document.getElementById('reel-comments-content').style.transform = 'translateY(0)';
            }, 50);
            
            if (window.lucide) window.lucide.createIcons();
            this.loadReelComments(postId);
        },
        
        closeReelComments() {
            const modal = document.getElementById('reel-comments-modal');
            const content = document.getElementById('reel-comments-content');
            if (modal && content) {
                content.style.transform = 'translateY(100%)';
                setTimeout(() => {
                    modal.style.opacity = '0';
                    modal.style.pointerEvents = 'none';
                }, 300);
            }
        },
        
        async loadReelComments(postId) {
            try {
                const response = await App.get(`/api/posts/detail?post_id=${postId}`);
                const post = response.data.post;
                const comments = post.comments || [];
                
                const listEl = document.getElementById(`reel-comments-list-${postId}`);
                if (listEl) {
                    if (comments.length === 0) {
                        listEl.innerHTML = `<div style="text-align:center; padding:20px; color:var(--text-muted); font-size:13px;">Ainda não há comentários. Seja o primeiro!</div>`;
                        return;
                    }
                    
                    listEl.innerHTML = comments.map(c => `
                        <div class="post-card-fb__comment" style="padding:4px 0;">
                            <img src="${c.avatar_url}" class="post-card-fb__comment-avatar">
                            <div class="post-card-fb__comment-bubble" style="background:var(--bg-primary);">
                                <a href="${App.url}/profile/${c.username}" class="post-card-fb__comment-name">${c.name}</a>
                                <p class="post-card-fb__comment-text">${App.Feed.linkify(c.content)}</p>
                            </div>
                        </div>
                    `).join('');
                }
            } catch (e) {
                const listEl = document.getElementById(`reel-comments-list-${postId}`);
                if (listEl) listEl.innerHTML = `<div style="text-align:center; padding:20px; color:var(--accent-danger); font-size:13px;">Erro ao carregar comentários.</div>`;
            }
        },
        
        async submitReelComment(postId) {
            const inputEl = document.getElementById(`reel-comment-input-${postId}`);
            const listEl = document.getElementById(`reel-comments-list-${postId}`);
            if (!inputEl || !inputEl.value) return;
            
            const content = inputEl.value;
            inputEl.value = '';
            
            try {
                const response = await App.post('/api/posts/comment', { post_id: postId, content: content });
                const c = response.data.comment;
                
                if (listEl.querySelector('div[style*="text-align:center"]')) {
                    listEl.innerHTML = '';
                }
                
                const commCard = document.createElement('div');
                commCard.className = 'post-card-fb__comment';
                commCard.style.padding = '4px 0';
                commCard.innerHTML = `
                    <img src="${c.avatar_url}" class="post-card-fb__comment-avatar">
                    <div class="post-card-fb__comment-bubble" style="background:var(--bg-primary);">
                        <a href="${App.url}/profile/${c.username}" class="post-card-fb__comment-name">${c.name}</a>
                        <p class="post-card-fb__comment-text">${App.Feed.linkify(c.content)}</p>
                    </div>
                `;
                listEl.appendChild(commCard);
                listEl.scrollTop = listEl.scrollHeight;
                App.showToast('Comentário publicado!', 'success');
            } catch (e) {
                App.showToast('Erro ao publicar comentário.', 'danger');
            }
        },

        init() {
            const feedContainer = document.getElementById('social-feed-container') || document.getElementById('feed-posts-list');
            if (!feedContainer) return;
            
            // Verificar se existe parâmetro search na URL
            const urlParams = new URLSearchParams(window.location.search);
            const searchParam = urlParams.get('search');
            if (searchParam) {
                this.searchQuery = searchParam;
                const indicator = document.getElementById('feed-search-indicator');
                const tagEl = document.getElementById('feed-search-tag');
                if (indicator && tagEl) {
                    tagEl.textContent = searchParam;
                    indicator.style.display = 'flex';
                }
            }

            // Carregar posts iniciais
            this.loadMore();

            // Configurar Infinite Scroll com Intersection Observer
            const sentinel = document.getElementById('feed-sentinel');
            if (sentinel) {
                const observer = new IntersectionObserver((entries) => {
                    if (entries[0].isIntersecting && this.hasMore && !this.loading) {
                        this.loadMore();
                    }
                }, { threshold: 1.0 });
                observer.observe(sentinel);
            }
        },

        async loadMore() {
            if (this.loading || !this.hasMore) return;
            this.loading = true;
            
            const listEl = document.getElementById('feed-posts-list');
            const skeleton = document.getElementById('feed-skeleton');
            if (skeleton) skeleton.style.display = 'block';

            try {
                const filter = document.getElementById('feed-filter')?.value || 'all';
                let url = `/api/posts?page=${this.page}&filter=${filter}`;
                if (this.searchQuery) {
                    url += `&search=${encodeURIComponent(this.searchQuery)}`;
                }
                const response = await App.get(url);
                
                if (skeleton) skeleton.style.display = 'none';
                
                const posts = response.data.posts || [];
                
                if (posts.length === 0) {
                    this.hasMore = false;
                    if (this.page === 1 && listEl) {
                        listEl.innerHTML = `<div class="card text-center" style="color:var(--text-secondary);">Ainda não existem publicações no feed. Seja o primeiro a partilhar!</div>`;
                    }
                    return;
                }

                posts.forEach(post => {
                    const card = (filter === 'reels') ? this.renderReelCard(post) : this.renderPostCard(post);
                    listEl?.appendChild(card);
                });
                
                if (window.lucide) window.lucide.createIcons();
                this.page++;
            } catch (error) {
                App.showToast('Erro ao carregar publicações.', 'danger');
            } finally {
                this.loading = false;
            }
        },

        renderPostCard(post) {
            const card = document.createElement('div');
            card.className = 'post-card-fb slideUp';
            card.id = `post-card-${post.id}`;
            
            // Verificar anexo de imagem/PDF/Video
            let fileHTML = '';
            if (post.file_url) {
                if (post.file_type && post.file_type.includes('image')) {
                    fileHTML = `<img src="${App.url}/${post.file_url}" alt="Imagem do Post" class="post-card-fb__image" style="cursor:pointer;" onclick="App.Feed.openImageLightbox(this.src)">`;
                } else if (post.file_type && post.file_type.includes('pdf')) {
                    fileHTML = `
                        <a href="${App.url}/${post.file_url}" target="_blank" class="post-card-fb__pdf">
                            <i data-lucide="file-text" style="color:var(--accent-danger); width:20px; height:20px;"></i>
                            <span>Documento Anexo.pdf</span>
                            <i data-lucide="external-link" style="width:14px; height:14px; color:var(--text-muted); margin-left:auto;"></i>
                        </a>
                    `;
                } else if (post.file_type && post.file_type.includes('video')) {
                    const q = post.video_qualities;
                    if (q && q.status === 'processing') {
                        fileHTML = `
                            <div class="video-processing-indicator" style="width:100%; padding:40px 20px; background:rgba(255,255,255,0.02); border:1px dashed var(--border-color); border-radius:8px; text-align:center; margin:10px 0;">
                                <div class="spinner" style="display:inline-block; width:24px; height:24px; border:3px solid rgba(249,115,22,0.3); border-top-color:var(--accent-primary); border-radius:50%; animation: spin 1s linear infinite; margin-bottom:12px;"></div>
                                <p style="font-size:13px; color:var(--text-secondary); margin:0;">O seu vídeo está a ser processado e otimizado...</p>
                            </div>
                        `;
                    } else {
                        const defaultUrl = this.getDefaultVideoUrl(q, post.file_url);
                        let options = `<option value="auto" style="background:#151d30; color:#fff;">Auto (Detetado)</option>`;
                        if (q) {
                            if (q['720p']) options += `<option value="720p" style="background:#151d30; color:#fff;">720p (Alta)</option>`;
                            if (q['360p']) options += `<option value="360p" style="background:#151d30; color:#fff;">360p (Média)</option>`;
                            if (q['original']) options += `<option value="original" style="background:#151d30; color:#fff;">Original</option>`;
                        }
                        
                        fileHTML = `
                            <div class="custom-video-player" id="video-container-${post.id}" style="position:relative; width:100%; border-radius:8px; overflow:hidden; background:#000; margin:10px 0;">
                                <video id="video-el-${post.id}" class="post-video-element" style="width:100%; display:block; max-height:500px;" controls playsinline data-qualities='${JSON.stringify(q || {})}'>
                                    <source src="${App.url}/${defaultUrl}" type="${post.file_type}">
                                </video>
                                <div class="video-quality-hud" style="position:absolute; top:10px; right:10px; z-index:10; background:rgba(0,0,0,0.6); padding:4px 8px; border-radius:4px; font-size:12px; color:#fff; display:flex; align-items:center; gap:6px;">
                                    <i data-lucide="settings" style="width:12px; height:12px;"></i>
                                    <select onchange="App.Feed.changeVideoQuality(${post.id}, this.value)" style="background:none; border:none; color:#fff; font-size:12px; font-weight:700; cursor:pointer; outline:none;" id="video-quality-select-${post.id}">
                                        ${options}
                                    </select>
                                </div>
                            </div>
                        `;
                    }
                }
            }

            // Scan for YouTube video links to embed inline
            let youtubeHTML = '';
            const ytRegex = /(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/;
            const ytMatch = (post.content || '').match(ytRegex);
            if (ytMatch && ytMatch[1]) {
                const videoId = ytMatch[1];
                youtubeHTML = `
                    <div class="youtube-embed-container" style="position:relative; padding-bottom:56.25%; height:0; overflow:hidden; margin:12px 0; border-radius:8px; border:1px solid var(--border-color); background:#000;">
                        <iframe 
                            src="https://www.youtube.com/embed/${videoId}" 
                            style="position:absolute; top:0; left:0; width:100%; height:100%; border:0;" 
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                            allowfullscreen>
                        </iframe>
                    </div>
                `;
            }

            const isLiked = parseInt(post.is_liked || 0) === 1;
            const likeColor = isLiked ? 'var(--accent-primary)' : 'var(--text-muted)';
            const likeFill = isLiked ? 'var(--accent-primary)' : 'none';

            // Menu de ações (só para o dono do post ou administrador)
            const isOwner = App.userId && (parseInt(post.user_id) === parseInt(App.userId) || App.isAdmin);
            const menuHTML = isOwner ? `
                <div class="post-card-fb__menu">
                    <button onclick="App.Feed.togglePostMenu(${post.id})" class="post-card-fb__menu-btn" title="Opções">
                        <i data-lucide="more-horizontal" style="width:20px; height:20px;"></i>
                    </button>
                    <div id="post-menu-${post.id}" class="post-card-fb__dropdown" style="display:none;">
                        <button onclick="App.Feed.editPost(${post.id})">
                            <i data-lucide="edit-3" style="width:16px; height:16px;"></i> Editar publicação
                        </button>
                        <button onclick="App.Feed.deletePost(${post.id})" style="color:var(--accent-danger);">
                            <i data-lucide="trash-2" style="width:16px; height:16px;"></i> Eliminar publicação
                        </button>
                    </div>
                </div>
            ` : '';

            card.innerHTML = `
                <div class="post-card-fb__header">
                    <a href="${App.url}/profile/${post.username}" class="post-card-fb__author">
                        <img src="${post.avatar_url}" class="post-card-fb__avatar">
                        <div>
                            <span class="post-card-fb__name">${post.name}</span>
                            <span class="post-card-fb__time">${App.timeAgo(post.created_at)}</span>
                        </div>
                    </a>
                    ${menuHTML}
                </div>
                <div id="post-content-${post.id}" class="post-card-fb__body">${this.formatPostBody(post.id, post.content || '')}</div>
                ${fileHTML}
                ${youtubeHTML}
                <div class="post-card-fb__stats">
                    <span id="like-count-label-${post.id}">${post.likes_count || 0} gosto(s)</span>
                    <span>${post.comments_count || 0} comentário(s)</span>
                </div>
                <div class="post-card-fb__actions">
                    <button onclick="App.Feed.toggleLike(${post.id})" id="like-btn-${post.id}" class="post-card-fb__action-btn" style="color:${likeColor};">
                        <i data-lucide="thumbs-up" style="width:18px; height:18px; fill:${likeFill};"></i>
                        <span id="like-count-${post.id}">Gosto</span>
                    </button>
                    <button onclick="document.getElementById('comments-section-${post.id}').style.display = 'block'" class="post-card-fb__action-btn">
                        <i data-lucide="message-circle" style="width:18px; height:18px;"></i>
                        <span>Comentar</span>
                    </button>
                </div>
                <div id="comments-section-${post.id}" style="display:none; padding:0 16px 16px;">
                    <div id="comments-list-${post.id}" class="post-card-fb__comments">
                        ${(post.comments || []).map(c => `
                            <div class="post-card-fb__comment">
                                <img src="${c.avatar_url}" class="post-card-fb__comment-avatar">
                                <div class="post-card-fb__comment-bubble">
                                    <a href="${App.url}/profile/${c.username}" class="post-card-fb__comment-name">${c.name}</a>
                                    <p class="post-card-fb__comment-text">${App.Feed.linkify(c.content)}</p>
                                </div>
                                <span class="post-card-fb__comment-time">${App.timeAgo(c.created_at)}</span>
                            </div>
                        `).join('')}
                    </div>
                    <form onsubmit="event.preventDefault(); App.Feed.submitComment(${post.id});" class="post-card-fb__comment-form">
                        <input type="text" id="comment-input-${post.id}" class="post-card-fb__comment-input" placeholder="Escreva um comentário..." required>
                        <button type="submit" class="post-card-fb__comment-send"><i data-lucide="send" style="width:16px; height:16px;"></i></button>
                    </form>
                </div>
            `;
            return card;
        },

        async toggleLike(postId) {
            try {
                const response = await App.post('/api/posts/like', { post_id: postId });
                const btn = document.getElementById(`like-btn-${postId}`);
                const count = document.getElementById(`like-count-${postId}`);
                
                if (btn && count) {
                    if (response.data.liked) {
                        btn.style.color = 'var(--accent-primary)';
                    } else {
                        btn.style.color = 'var(--text-secondary)';
                    }
                    count.innerText = response.data.likes_count;
                }
            } catch (e) {
                App.showToast('Erro ao registar gosto.', 'danger');
            }
        },

        async submitComment(postId) {
            const inputEl = document.getElementById(`comment-input-${postId}`);
            const listEl = document.getElementById(`comments-list-${postId}`);
            if (!inputEl || !inputEl.value) return;

            const content = inputEl.value;
            inputEl.value = '';

            try {
                const response = await App.post('/api/posts/comment', { post_id: postId, content: content });
                const c = response.data.comment;
                
                const commCard = document.createElement('div');
                commCard.className = 'post-card-fb__comment';
                commCard.innerHTML = `
                    <img src="${c.avatar_url}" class="post-card-fb__comment-avatar">
                    <div class="post-card-fb__comment-bubble">
                        <a href="${App.url}/profile/${c.username}" class="post-card-fb__comment-name">${c.name}</a>
                        <p class="post-card-fb__comment-text">${App.Feed.linkify(c.content)}</p>
                    </div>
                    <span class="post-card-fb__comment-time">${App.timeAgo(c.created_at)}</span>
                `;
                listEl.appendChild(commCard);
                if (window.lucide) window.lucide.createIcons();
                App.showToast('Comentário publicado!', 'success');
            } catch (e) {
                App.showToast('Erro ao publicar comentário.', 'danger');
            }
        },

        // --- AÇÕES DE POST (EDITAR / ELIMINAR) ---

        togglePostMenu(postId) {
            // Fechar todos os outros menus abertos
            document.querySelectorAll('.post-card-fb__dropdown').forEach(el => { el.style.display = 'none'; });
            const menu = document.getElementById(`post-menu-${postId}`);
            if (menu) {
                menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
            }
        },

        async deletePost(postId) {
            const menu = document.getElementById(`post-menu-${postId}`);
            if (menu) menu.style.display = 'none';

            if (!confirm('Tem a certeza que deseja eliminar esta publicação?')) return;

            try {
                await App.post('/api/posts/delete', { post_id: postId });
                const card = document.getElementById(`post-card-${postId}`);
                if (card) {
                    card.style.transition = 'opacity 0.3s, transform 0.3s';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.95)';
                    setTimeout(() => card.remove(), 300);
                }
                App.showToast('Publicação eliminada com sucesso.', 'success');
            } catch (e) {
                App.showToast(e.message || 'Erro ao eliminar publicação.', 'danger');
            }
        },

        async editPost(postId) {
            const menu = document.getElementById(`post-menu-${postId}`);
            if (menu) menu.style.display = 'none';

            const contentEl = document.getElementById(`post-content-${postId}`);
            if (!contentEl) return;

            const fullTextEl = contentEl.querySelector('.post-content-full');
            const currentText = fullTextEl ? fullTextEl.innerText : contentEl.innerText;
            const newText = prompt('Editar publicação:', currentText);

            if (newText === null || newText.trim() === currentText.trim()) return;

            try {
                await App.post('/api/posts/edit', { post_id: postId, content: newText.trim() });
                contentEl.innerHTML = App.Feed.formatPostBody(postId, newText.trim());
                App.showToast('Publicação atualizada!', 'success');
            } catch (e) {
                App.showToast(e.message || 'Erro ao editar publicação.', 'danger');
            }
        },

        openImageLightbox(src) {
            let lightbox = document.getElementById('feed-image-lightbox');
            if (!lightbox) {
                lightbox = document.createElement('div');
                lightbox.id = 'feed-image-lightbox';
                lightbox.className = 'lightbox-overlay';
                lightbox.innerHTML = `
                    <div class="lightbox-close-btn" onclick="App.Feed.closeImageLightbox()">
                        <i data-lucide="x" style="width:28px; height:28px; color:#ffffff;"></i>
                    </div>
                    <img id="lightbox-image" src="" alt="Imagem Ampliada">
                `;
                document.body.appendChild(lightbox);
                // Fechar ao premir ESC
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') App.Feed.closeImageLightbox();
                });
                // Fechar ao clicar no fundo
                lightbox.addEventListener('click', (e) => {
                    if (e.target === lightbox) App.Feed.closeImageLightbox();
                });
            }
            const img = document.getElementById('lightbox-image');
            if (img) img.src = src;
            lightbox.classList.add('active');
            if (window.lucide) window.lucide.createIcons();
        },

        closeImageLightbox() {
            const lightbox = document.getElementById('feed-image-lightbox');
            if (lightbox) {
                lightbox.classList.remove('active');
            }
        }
    },

    // --- PROJETOS & DESPESAS ---
    Projects: {
        init() {
            // Inicializar scripts de formulários ou timelines se na página de detalhes
        },

        async deleteExpense(expenseId) {
            App.openConfirm('Tem a certeza de que deseja anular esta compra/despesa? O valor correspondente será estornado do consumo real.', async () => {
                try {
                    await App.delete('/api/expenses/delete', { id: expenseId });
                    App.showToast('Compra anulada com sucesso!', 'success');
                    // Recarregar página para atualizar dashboards e totais
                    setTimeout(() => window.location.reload(), 1000);
                } catch (e) {
                    App.showToast('Erro ao anular compra.', 'danger');
                }
            });
        }
    },

    // --- DRAG & DROP UPLOAD PROCESSO ---
    Upload: {
        init() {
            const zones = document.querySelectorAll('.upload-zone');
            zones.forEach(zone => {
                this.dragDrop(zone);
            });
        },

        preview(inputEl, previewElId) {
            const previewEl = document.getElementById(previewElId);
            if (!inputEl.files || !inputEl.files[0] || !previewEl) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                if (inputEl.files[0].type.includes('image')) {
                    previewEl.innerHTML = `<img src="${e.target.result}" style="max-height:200px; border-radius:var(--radius-sm);">`;
                } else {
                    previewEl.innerHTML = `<div class="card" style="padding:10px; font-size:13px;"><i data-lucide="file-text"></i> ${inputEl.files[0].name}</div>`;
                }
                if (window.lucide) window.lucide.createIcons();
            };
            reader.readAsDataURL(inputEl.files[0]);
        },

        dragDrop(zone) {
            const input = zone.querySelector('input[type=file]');
            if (!input) return;

            zone.addEventListener('click', () => input.click());

            ['dragenter', 'dragover'].forEach(eventName => {
                zone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    zone.classList.add('dragover');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                zone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    zone.classList.remove('dragover');
                }, false);
            });

            zone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                input.files = files;
                this.preview(input, zone.dataset.previewId);
            });

            input.addEventListener('change', () => {
                this.preview(input, zone.dataset.previewId);
            });
        }
    },

    // --- MENSAGENS / CHAT ---
    Messages: {
        activeConversationId: null,
        pollingInterval: null,

        init() {
            const chatList = document.getElementById('chat-list-container');
            if (!chatList) return;

            this.pollMessages();
            this.pollingInterval = setInterval(() => this.pollMessages(), 3000); // Polling a cada 3s
        },

        async loadConversation(conversationId) {
            this.activeConversationId = conversationId;
            const messagesArea = document.getElementById('chat-messages-container');
            const items = document.querySelectorAll('.msg-convo-item');
            
            items.forEach(it => it.classList.remove('msg-convo-item--active'));
            
            const convoItem = document.getElementById(`convo-item-${conversationId}`);
            if (convoItem) {
                convoItem.classList.add('msg-convo-item--active');
                
                // Atualizar dinamicamente o cabeçalho do chat ativo
                const name = convoItem.getAttribute('data-name');
                const avatar = convoItem.querySelector('img')?.src;
                const headerInfo = document.getElementById('msg-chat-header-info');
                if (headerInfo) {
                    headerInfo.innerHTML = `
                        <img src="${avatar}" style="width:36px; height:36px; border-radius:50%; object-fit:cover; flex-shrink:0;">
                        <span style="font-weight:700; color:var(--text-primary); font-size:15px;">${name}</span>
                    `;
                }
            }

            // Ativar inputs
            const recipientInput = document.getElementById('chat-recipient-id');
            if (recipientInput) recipientInput.value = '';

            if (messagesArea) {
                messagesArea.innerHTML = `<div style="display:flex; justify-content:center; align-items:center; height:100%;"><div class="spinner"></div></div>`;
            }

            try {
                const response = await App.get(`/api/messages/messages?conversation_id=${conversationId}`);
                this.renderMessages(response.data.messages || []);
            } catch (e) {
                App.showToast('Erro ao carregar mensagens.', 'danger');
            }
        },

        renderMessages(messages) {
            const area = document.getElementById('chat-messages-container');
            if (!area) return;
            area.innerHTML = '';

            if (messages.length === 0) {
                area.innerHTML = `
                    <div class="msg-chat__placeholder">
                        <i data-lucide="message-circle" style="width:40px; height:40px; color:var(--accent-secondary); margin-bottom:12px;"></i>
                        <h3>Sem mensagens anteriores</h3>
                        <p>Envia a primeira mensagem para começar a conversa.</p>
                    </div>
                `;
                if (window.lucide) window.lucide.createIcons();
                return;
            }

            messages.forEach(msg => {
                const bubble = document.createElement('div');
                const isMe = parseInt(msg.sender_id) === parseInt(App.userId);
                bubble.className = `message-bubble ${isMe ? 'message-sent' : 'message-received'}`;
                bubble.innerHTML = `
                    <div>${msg.message}</div>
                    <small class="msg-bubble__time" style="text-align:${isMe ? 'right' : 'left'};">${App.timeAgo(msg.created_at)}</small>
                `;
                area.appendChild(bubble);
            });

            // Scroll para o fim
            area.scrollTop = area.scrollHeight;
        },

        async send() {
            const inputEl = document.getElementById('chat-message-input');
            if (!inputEl || !inputEl.value || !this.activeConversationId) return;

            const text = inputEl.value;
            inputEl.value = '';

            try {
                const response = await App.post('/api/messages/send', {
                    conversation_id: this.activeConversationId,
                    message: text
                });
                
                // Adicionar localmente
                const area = document.getElementById('chat-messages-container');
                const bubble = document.createElement('div');
                bubble.className = 'message-bubble message-sent slideUp';
                bubble.innerHTML = `
                    <div>${response.data.message.message}</div>
                    <small class="msg-bubble__time" style="text-align:right;">Agora mesmo</small>
                `;
                area?.appendChild(bubble);
                if (area) area.scrollTop = area.scrollHeight;
            } catch (e) {
                App.showToast('Erro ao enviar mensagem.', 'danger');
            }
        },

        async pollMessages() {
            if (!this.activeConversationId || !App.isLoggedIn) return;

            try {
                // Obter novas mensagens sem resetar a tela
                const response = await App.get(`/api/messages/messages?conversation_id=${this.activeConversationId}`);
                const area = document.getElementById('chat-messages-container');
                
                // Guardar scroll
                const isAtBottom = area ? (area.scrollHeight - area.scrollTop - area.clientHeight < 50) : false;
                
                this.renderMessages(response.data.messages || []);
                
                if (isAtBottom && area) {
                    area.scrollTop = area.scrollHeight;
                }
            } catch (e) {
                console.error("Mensagens polling falhou", e);
            }
        }
    },

    // --- NOTIFICAÇÕES ---
    Notifications: {
        init() {
            if (!App.isLoggedIn) return;
            this.poll();
            setInterval(() => this.poll(), 30000); // Polling a cada 30s
        },

        async poll() {
            try {
                const response = await App.get('/api/notifications');
                const unreadCount = parseInt(response.data.unread_count || 0);
                
                const badges = document.querySelectorAll('.notification-badge');
                badges.forEach(b => {
                    if (unreadCount > 0) {
                        b.style.display = 'inline-flex';
                        b.innerText = unreadCount;
                    } else {
                        b.style.display = 'none';
                    }
                });
            } catch (e) {
                console.error("Notificações polling falhou", e);
            }
        },

        async markAllRead() {
            try {
                await App.post('/api/notifications/read', { all: true });
                App.showToast('Todas as notificações foram marcadas como lidas.', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } catch (e) {
                App.showToast('Erro ao marcar notificações.', 'danger');
            }
        }
    },

    // --- PESQUISA GLOBAL ---
    Search: {
        init() {
            const inputEl = document.getElementById('global-search-input');
            const resultsEl = document.getElementById('global-search-results');
            if (!inputEl) return;

            inputEl.addEventListener('input', this.debounce(async (e) => {
                const query = e.target.value;
                if (query.length < 2) {
                    if (resultsEl) resultsEl.style.display = 'none';
                    return;
                }

                try {
                    const response = await App.get(`/api/search?q=${encodeURIComponent(query)}`);
                    if (resultsEl) {
                        resultsEl.style.display = 'block';
                        this.renderSearchResults(resultsEl, response.data);
                    }
                } catch (err) {
                    console.error("Falha na pesquisa", err);
                }
            }, 300));
        },

        debounce(fn, ms) {
            let timer;
            return function(...args) {
                clearTimeout(timer);
                timer = setTimeout(() => fn.apply(this, args), ms);
            };
        },

        renderSearchResults(el, data) {
            el.innerHTML = '';
            
            const users = data.users || [];
            const projects = data.projects || [];
            const posts = data.posts || [];
            
            if (users.length === 0 && projects.length === 0 && posts.length === 0) {
                el.innerHTML = `<div style="padding:15px; text-align:center; color:var(--text-secondary);">Sem resultados encontrados.</div>`;
                return;
            }

            let html = '';
            if (users.length > 0) {
                html += `<div style="padding:8px 12px; font-weight:700; color:var(--accent-primary); font-size:12px; text-transform:uppercase;">Utilizadores</div>`;
                users.forEach(u => {
                    html += `
                        <a href="${App.url}/profile/${u.username}" style="display:flex; align-items:center; gap:8px; padding:8px 12px; border-bottom:1px solid var(--border-color);">
                            <img src="${u.avatar_url || ''}" class="avatar avatar-sm" style="width:24px; height:24px;">
                            <span>${u.name} (@${u.username})</span>
                        </a>
                    `;
                });
            }

            if (projects.length > 0) {
                html += `<div style="padding:8px 12px; font-weight:700; color:var(--accent-secondary); font-size:12px; text-transform:uppercase; margin-top:8px;">Projetos</div>`;
                projects.forEach(p => {
                    html += `
                        <a href="${App.url}/projects/detail?id=${p.id}" style="display:block; padding:8px 12px; border-bottom:1px solid var(--border-color);">
                            <div style="font-weight:600; font-size:14px; color:var(--text-primary);">${p.title}</div>
                            <small style="color:var(--text-secondary);">${p.location || 'Localização não definida'}</small>
                        </a>
                    `;
                });
            }

            el.innerHTML = html;
        }
    },

    // --- CHARTS (INTEGRAÇÃO COM CHART.JS) ---
    Charts: {
        renderBudget(canvasId, spent, total) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return;

            const remaining = Math.max(0, total - spent);
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Gasto', 'Orçamento Disponível'],
                    datasets: [{
                        data: [spent, remaining],
                        backgroundColor: ['#f97316', '#1f2d40'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    cutout: '80%'
                }
            });
        },

        renderByPhase(canvasId, phaseData) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return;

            const labels = Object.keys(phaseData);
            const values = Object.values(phaseData);

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Gasto por Fase (Kz)',
                        data: values,
                        backgroundColor: '#3b82f6',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            grid: { color: 'rgba(255, 255, 255, 0.05)' },
                            ticks: { color: '#94a3b8' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#94a3b8' }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        },

        renderByType(canvasId, typeData) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return;

            const labels = Object.keys(typeData);
            const values = Object.values(typeData);

            const colors = ['#f97316', '#3b82f6', '#10b981', '#f59e0b', '#ef4444'];

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors.slice(0, labels.length),
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { color: '#94a3b8', boxWidth: 12 }
                        }
                    }
                }
            });
        }
    }
};

// Autoinicialização de componentes comuns ao carregar o DOM
document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide) window.lucide.createIcons();
    App.Upload.init();
    App.Search.init();
    App.Notifications.init();
});
