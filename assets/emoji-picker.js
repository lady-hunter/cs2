// ============================================
// EMOJI PICKER - Dùng cho Messages và Random Chat
// ============================================

const EmojiPicker = {
    // Danh sách emoji theo category
    emojis: {
        smileys: [
            '😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃',
            '😉', '😊', '😇', '🥰', '😍', '🤩', '😘', '😗', '😚', '😙',
            '🥲', '😋', '😛', '😜', '🤪', '😝', '🤑', '🤗', '🤭', '🤫',
            '🤔', '🤐', '🤨', '😐', '😑', '😶', '😏', '😒', '🙄', '😬',
            '😮‍💨', '🤥', '😌', '😔', '😪', '🤤', '😴', '😷', '🤒', '🤕',
            '🤢', '🤮', '🥴', '😵', '🤯', '🤠', '🥳', '🥸', '😎', '🤓',
            '🧐', '😕', '😟', '🙁', '☹️', '😮', '😯', '😲', '😳', '🥺',
            '😦', '😧', '😨', '😰', '😥', '😢', '😭', '😱', '😖', '😣',
            '😞', '😓', '😩', '😫', '🥱', '😤', '😡', '😠', '🤬', '😈',
            '👿', '💀', '☠️', '💩', '🤡', '👹', '👺', '👻', '👽', '👾'
        ],
        gestures: [
            '👋', '🤚', '🖐️', '✋', '🖖', '👌', '🤌', '🤏', '✌️', '🤞',
            '🤟', '🤘', '🤙', '👈', '👉', '👆', '🖕', '👇', '☝️', '👍',
            '👎', '✊', '👊', '🤛', '🤜', '👏', '🙌', '👐', '🤲', '🤝',
            '🙏', '✍️', '💅', '🤳', '💪', '🦾', '🦿', '🦵', '🦶', '👂',
            '🦻', '👃', '🧠', '🫀', '🫁', '🦷', '🦴', '👀', '👁️', '👅',
            '👄', '👶', '🧒', '👦', '👧', '🧑', '👱', '👨', '🧔', '👩'
        ],
        hearts: [
            '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔',
            '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '💟', '♥️',
            '💌', '💋', '👄', '🫦', '💏', '💑', '🥰', '😍', '😘', '😻'
        ],
        animals: [
            '🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐻‍❄️', '🐨',
            '🐯', '🦁', '🐮', '🐷', '🐽', '🐸', '🐵', '🙈', '🙉', '🙊',
            '🐒', '🐔', '🐧', '🐦', '🐤', '🐣', '🐥', '🦆', '🦅', '🦉',
            '🦇', '🐺', '🐗', '🐴', '🦄', '🐝', '🪱', '🐛', '🦋', '🐌',
            '🐞', '🐜', '🪰', '🪲', '🪳', '🦟', '🦗', '🕷️', '🕸️', '🦂',
            '🐢', '🐍', '🦎', '🦖', '🦕', '🐙', '🦑', '🦐', '🦞', '🦀'
        ],
        food: [
            '🍕', '🍔', '🍟', '🌭', '🍿', '🧂', '🥓', '🥚', '🍳', '🧇',
            '🥞', '🧈', '🍞', '🥐', '🥨', '🥯', '🥖', '🫓', '🧀', '🥗',
            '🥙', '🫔', '🌮', '🌯', '🥪', '🍖', '🍗', '🥩', '🥣', '🍲',
            '🍝', '🍜', '🍛', '🍚', '🍙', '🍣', '🍱', '🍤', '🍩', '🍪',
            '🎂', '🍰', '🧁', '🥧', '🍫', '🍬', '🍭', '🍮', '🍯', '🍼',
            '🥛', '☕', '🫖', '🍵', '🍶', '🍺', '🍻', '🥂', '🍷', '🥃'
        ],
        activities: [
            '⚽', '🏀', '🏈', '⚾', '🥎', '🎾', '🏐', '🏉', '🥏', '🎱',
            '🪀', '🏓', '🏸', '🏒', '🏑', '🥍', '🏏', '🪃', '🥅', '⛳',
            '🪁', '🏹', '🎣', '🤿', '🥊', '🥋', '🎽', '🛹', '🛼', '🛷',
            '⛸️', '🥌', '🎿', '⛷️', '🏂', '🪂', '🏋️', '🤼', '🤸', '⛹️',
            '🤺', '🤾', '🏌️', '🏇', '⛑️', '🎖️', '🏆', '🏅', '🥇', '🥈',
            '🥉', '🎮', '🎯', '🎲', '🧩', '🎭', '🎨', '🎬', '🎤', '🎧'
        ],
        objects: [
            '💡', '🔦', '🏮', '🪔', '📱', '📲', '💻', '🖥️', '🖨️', '⌨️',
            '🖱️', '🖲️', '💾', '💿', '📀', '📼', '📷', '📸', '📹', '🎥',
            '📽️', '🎞️', '📞', '☎️', '📟', '📠', '📺', '📻', '🎙️', '🎚️',
            '🎛️', '🧭', '⏱️', '⏲️', '⏰', '🕰️', '⌚', '📡', '🔋', '🔌',
            '💎', '💰', '💵', '💴', '💶', '💷', '💳', '💸', '🎁', '🎀',
            '🎊', '🎉', '🎈', '🪄', '🔮', '🧿', '🎐', '🏷️', '📦', '📫'
        ]
    },

    // Khởi tạo
    init() {
        this.emojiBtn = document.getElementById('emojiBtn');
        this.emojiPicker = document.getElementById('emojiPicker');
        this.emojiContent = document.getElementById('emojiContent');
        this.messageInput = document.getElementById('messageInput');

        if (!this.emojiBtn || !this.emojiPicker) return;

        this.bindEvents();
        this.renderEmojis('smileys');
    },

    // Bind events
    bindEvents() {
        // Toggle emoji picker
        this.emojiBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.togglePicker();
        });

        // Tab switching
        document.querySelectorAll('.emoji-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.emoji-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                this.renderEmojis(tab.dataset.category);
            });
        });

        // Close picker when clicking outside
        document.addEventListener('click', (e) => {
            if (!this.emojiPicker.contains(e.target) && e.target !== this.emojiBtn && !this.emojiBtn.contains(e.target)) {
                this.closePicker();
            }
        });

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closePicker();
            }
        });
    },

    // Toggle picker visibility
    togglePicker() {
        this.emojiPicker.classList.toggle('active');
    },

    // Close picker
    closePicker() {
        this.emojiPicker.classList.remove('active');
    },

    // Render emojis for a category
    renderEmojis(category) {
        const emojis = this.emojis[category] || [];
        this.emojiContent.innerHTML = '';
        
        emojis.forEach(emoji => {
            const span = document.createElement('span');
            span.className = 'emoji-item';
            span.textContent = emoji;
            span.addEventListener('click', () => this.insertEmoji(emoji));
            this.emojiContent.appendChild(span);
        });
    },

    // Insert emoji into input
    insertEmoji(emoji) {
        if (this.messageInput) {
            const start = this.messageInput.selectionStart;
            const end = this.messageInput.selectionEnd;
            const text = this.messageInput.value;
            
            this.messageInput.value = text.substring(0, start) + emoji + text.substring(end);
            this.messageInput.focus();
            
            // Set cursor position after emoji
            const newPos = start + emoji.length;
            this.messageInput.setSelectionRange(newPos, newPos);
        }
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => EmojiPicker.init());
