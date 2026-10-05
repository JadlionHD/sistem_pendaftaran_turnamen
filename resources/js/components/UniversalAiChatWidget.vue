<script setup lang="ts">
import { ref, watch, nextTick } from 'vue';
import {
    AlertCircle,
    Bot,
    Brain,
    ChevronDown,
    ChevronUp,
    Layers,
    Loader2,
    MessageCircle,
    MessageSquare,
    RotateCcw,
    Send,
    Shield,
    Sparkles,
    Trophy,
    X,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    useUniversalAiChat,
    renderMarkdown,
} from '@/composables/useUniversalAiChat';

const {
    isOpen,
    chatMessages,
    inputQuery,
    isStreaming,
    compactContext,
    isCompacting,
    isResetting,
    isTokenLimitReached,
    getCurrentTournament,
    toggleChat,
    closeChat,
    sendQuery,
    resetChat,
    compactChat,
    clearCompactContext,
} = useUniversalAiChat();

const chatContainerRef = ref<HTMLDivElement | null>(null);

const scrollToBottom = () => {
    nextTick(() => {
        if (chatContainerRef.value) {
            chatContainerRef.value.scrollTop = chatContainerRef.value.scrollHeight;
        }
    });
};

const handleSend = () => {
    sendQuery(undefined, scrollToBottom);
};

const handleQuickPrompt = (type: 'tournaments' | 'roster' | 'register' | 'rules') => {
    const currentTournament = getCurrentTournament();
    if (type === 'tournaments') {
        sendQuery('Turnamen game apa saja yang saat ini sedang buka pendaftaran di platform?', scrollToBottom);
    } else if (type === 'roster') {
        if (currentTournament) {
            sendQuery(
                `Halo AI, tolong cek kelayakan roster tim saya untuk turnamen "${currentTournament.title}":\n\nNama Tim: Phoenix Esports\n1. Alex (Kapten) - ID: 28491823, Zone: 2041\n2. Bima - ID: 83921821, Zone: 2041\n3. Kevin - ID: 92837192, Zone: 2041\n4. Rio - ID: 19283719, Zone: 2041\n5. Daniel - ID: 48192831, Zone: 2041\nCadangan: Bayu - ID: 57291823, Zone: 2041\n\nApakah format pemain ini sudah valid dan sesuai aturan?`,
                scrollToBottom,
            );
        } else {
            sendQuery(
                'Halo AI, saya ingin mengecek kelayakan roster tim saya:\n\nGame: Mobile Legends\nNama Tim: Garuda Esports\n1. Rian (Kapten) - ID: 19827382, Zone: 2102\n2. Dodi - ID: 83920192, Zone: 2102\n3. Reza - ID: 48392019, Zone: 2102\n4. Fajar - ID: 58392019, Zone: 2102\n5. Gilang - ID: 38291029, Zone: 2102\n\nApakah susunan roster ini sudah valid?',
                scrollToBottom,
            );
        }
    } else if (type === 'register') {
        sendQuery('Bagaimana langkah-langkah mendaftarkan tim untuk mengikuti turnamen di platform ini?', scrollToBottom);
    } else if (type === 'rules') {
        if (currentTournament) {
            sendQuery(`Apa saja regulasi, sistem pertandingan, dan larangan untuk turnamen "${currentTournament.title}"?`, scrollToBottom);
        } else {
            sendQuery('Apa saja aturan umum dan hal-hal yang dilarang saat bertanding dalam turnamen game di platform ini?', scrollToBottom);
        }
    }
};

watch(
    () => isOpen.value,
    (opened) => {
        if (opened) {
            scrollToBottom();
        }
    },
);
</script>

<template>
    <!-- Single Root Element -->
    <div class="universal-ai-chat-root">
        <!-- Floating Action Button (FAB) in Bottom-Right Corner -->
        <button
            type="button"
            class="fixed bottom-6 right-6 z-50 size-14 rounded-full bg-linear-to-tr from-indigo-600 via-indigo-500 to-violet-500 hover:from-indigo-500 hover:to-violet-400 text-white shadow-xl hover:shadow-2xl hover:scale-105 active:scale-95 transition-all duration-300 flex items-center justify-center cursor-pointer select-none focus:outline-hidden focus:ring-4 focus:ring-indigo-500/30 group"
            :aria-label="isOpen ? 'Tutup AI Assistant' : 'Buka AI Assistant'"
            :title="isOpen ? 'Tutup AI Assistant' : 'Tanya AI Tournament Assistant'"
            @click="toggleChat"
        >
            <span class="sr-only">{{ isOpen ? 'Tutup AI Assistant' : 'Buka AI Assistant' }}</span>

            <!-- Subtle Pulse Ring Effect when closed -->
            <span
                v-if="!isOpen"
                class="absolute -inset-1 rounded-full bg-indigo-500/25 animate-ping -z-10 opacity-75"
            />

            <!-- Toggle Icon with smooth transition -->
            <X v-if="isOpen" class="size-6 transition-transform duration-300 rotate-90 group-hover:rotate-0" />
            <MessageCircle v-else class="size-6.5 transition-transform duration-300 group-hover:scale-110" />

            <!-- Mini Online Indicator Dot -->
            <span
                v-if="!isOpen"
                class="absolute top-1 right-1 size-3 rounded-full bg-emerald-400 border-2 border-white dark:border-zinc-900"
            />
        </button>

        <!-- Floating Chat Popup Window -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-4 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-4 scale-95"
        >
            <div
                v-if="isOpen"
                class="fixed bottom-22 right-4 sm:right-6 z-50 w-[calc(100vw-2rem)] sm:w-[420px] max-w-[440px] h-[590px] max-h-[calc(100vh-7rem)] flex flex-col rounded-2xl border border-border/80 bg-card/95 backdrop-blur-md shadow-2xl overflow-hidden animate-in"
            >
                <!-- Window Header -->
                <div class="px-4 py-3 border-b border-border/70 bg-muted/30 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="relative p-2 rounded-xl bg-linear-to-tr from-indigo-600 to-violet-500 text-white shadow-xs">
                            <Bot class="size-4.5" />
                            <span class="absolute -bottom-0.5 -right-0.5 size-2.5 rounded-full bg-emerald-400 border-2 border-card" />
                        </div>
                        <div>
                            <div class="flex items-center gap-1.5">
                                <h3 class="text-sm font-bold text-foreground tracking-tight">
                                    AI Tournament Assistant
                                </h3>
                                <Badge variant="secondary" class="text-[10px] h-4.5 px-1.5 font-normal">
                                    Universal
                                </Badge>
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                Asisten cerdas turnamen & pre-check roster
                            </p>
                        </div>
                    </div>

                    <!-- Header Action Buttons -->
                    <div class="flex items-center gap-1">
                        <Button
                            v-if="chatMessages.length >= 2"
                            variant="ghost"
                            size="icon"
                            class="size-7 text-xs text-indigo-600 dark:text-indigo-400 hover:bg-indigo-500/10"
                            :disabled="isCompacting || isStreaming"
                            title="Ringkas percakapan untuk menghemat token dan menyimpan memori"
                            @click="compactChat"
                        >
                            <Loader2 v-if="isCompacting" class="size-3.5 animate-spin" />
                            <Layers v-else class="size-3.5" />
                        </Button>
                        <Button
                            v-if="chatMessages.length || compactContext"
                            variant="ghost"
                            size="icon"
                            class="size-7 text-xs text-muted-foreground hover:text-foreground"
                            :disabled="isResetting || isStreaming"
                            title="Reset percakapan"
                            @click="resetChat"
                        >
                            <Loader2 v-if="isResetting" class="size-3.5 animate-spin" />
                            <RotateCcw v-else class="size-3.5" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-7 text-muted-foreground hover:text-foreground rounded-lg"
                            title="Tutup Chat"
                            @click="closeChat"
                        >
                            <X class="size-4" />
                        </Button>
                    </div>
                </div>

                <!-- Active Tournament Context Banner (Auto-detected if user is viewing a tournament) -->
                <div
                    v-if="getCurrentTournament()"
                    class="px-3.5 py-1.5 bg-indigo-500/10 border-b border-indigo-500/20 text-[11px] flex items-center justify-between text-indigo-950 dark:text-indigo-200 shrink-0"
                >
                    <div class="flex items-center gap-1.5 overflow-hidden">
                        <Trophy class="size-3.5 text-indigo-600 dark:text-indigo-400 shrink-0" />
                        <span class="truncate font-medium">
                            Konteks: <strong>{{ getCurrentTournament()?.title }}</strong>
                        </span>
                    </div>
                    <span class="text-[10px] text-indigo-600 dark:text-indigo-400 shrink-0">
                        {{ getCurrentTournament()?.gameName }}
                    </span>
                </div>

                <!-- Compact Memory Banner -->
                <div
                    v-if="compactContext"
                    class="px-3.5 py-2 bg-indigo-500/5 border-b border-indigo-500/20 text-xs flex items-center justify-between gap-2 shrink-0"
                >
                    <div class="flex items-start gap-1.5 overflow-hidden">
                        <Brain class="size-3.5 text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5" />
                        <div class="overflow-hidden">
                            <span class="font-semibold text-indigo-900 dark:text-indigo-200 block text-[10px]">
                                Memori Konteks Tersimpan:
                            </span>
                            <p class="text-muted-foreground text-[10px] leading-tight line-clamp-2">
                                {{ compactContext }}
                            </p>
                        </div>
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-5 text-[10px] text-muted-foreground hover:text-foreground shrink-0 px-1.5"
                        @click="clearCompactContext"
                        title="Hapus memori percakapan sebelumnya"
                    >
                        Hapus
                    </Button>
                </div>

                <!-- Quick Prompt Chips -->
                <div class="px-3 py-2 border-b border-border/50 bg-muted/10 flex items-center gap-1.5 overflow-x-auto no-scrollbar shrink-0 text-xs">
                    <span class="text-[11px] text-muted-foreground shrink-0 flex items-center gap-1">
                        <Sparkles class="size-3 text-indigo-500" /> Tanya:
                    </span>
                    <button
                        type="button"
                        class="text-[11px] px-2 py-0.5 rounded-full border border-border bg-card hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer shrink-0 inline-flex items-center gap-1"
                        :disabled="isStreaming || isTokenLimitReached"
                        @click="handleQuickPrompt('tournaments')"
                    >
                        <Trophy class="size-3 text-indigo-500" /> Turnamen Buka
                    </button>
                    <button
                        type="button"
                        class="text-[11px] px-2 py-0.5 rounded-full border border-border bg-card hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer shrink-0 inline-flex items-center gap-1"
                        :disabled="isStreaming || isTokenLimitReached"
                        @click="handleQuickPrompt('roster')"
                    >
                        <Shield class="size-3 text-indigo-500" /> Cek Roster
                    </button>
                    <button
                        type="button"
                        class="text-[11px] px-2 py-0.5 rounded-full border border-border bg-card hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer shrink-0 inline-flex items-center gap-1"
                        :disabled="isStreaming || isTokenLimitReached"
                        @click="handleQuickPrompt('register')"
                    >
                        📝 Cara Daftar
                    </button>
                    <button
                        type="button"
                        class="text-[11px] px-2 py-0.5 rounded-full border border-border bg-card hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer shrink-0 inline-flex items-center gap-1"
                        :disabled="isStreaming || isTokenLimitReached"
                        @click="handleQuickPrompt('rules')"
                    >
                        ⚖️ Regulasi
                    </button>
                </div>

                <!-- Chat Message List Container -->
                <div
                    ref="chatContainerRef"
                    class="flex-1 p-3.5 overflow-y-auto space-y-3 text-xs md:text-sm"
                >
                    <!-- Empty Initial State -->
                    <div v-if="chatMessages.length === 0" class="text-center py-8 px-2 space-y-3">
                        <div class="size-11 rounded-2xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 mx-auto flex items-center justify-center shadow-xs">
                            <Bot class="size-6" />
                        </div>
                        <div class="space-y-1">
                            <p class="text-xs font-semibold text-foreground">
                                Halo! Ada yang bisa saya bantu seputar turnamen?
                            </p>
                            <p class="text-[11px] text-muted-foreground leading-relaxed max-w-xs mx-auto">
                                Tanyakan jadwal turnamen, status pendaftaran, total hadiah, aturan game, atau minta evaluasi kelayakan susunan roster tim Anda.
                            </p>
                        </div>
                    </div>

                    <!-- Chat Messages Loop -->
                    <div
                        v-for="(msg, index) in chatMessages"
                        :key="index"
                        class="flex flex-col space-y-1"
                        :class="msg.role === 'user' ? 'items-end' : 'items-start'"
                    >
                        <span class="text-[10px] text-muted-foreground px-1">
                            {{ msg.role === 'user' ? 'Anda' : 'AI Assistant' }}
                        </span>
                        <div
                            class="p-3 rounded-2xl max-w-[88%] leading-relaxed text-xs shadow-xs"
                            :class="
                                msg.role === 'user'
                                    ? 'bg-primary text-primary-foreground rounded-tr-xs whitespace-pre-line'
                                    : 'bg-muted/40 border border-border/70 text-foreground rounded-tl-xs'
                            "
                        >
                            <div v-if="msg.role === 'user'">
                                {{ msg.content }}
                            </div>
                            <div v-else class="space-y-2 w-full">
                                <!-- DeepSeek Reasoning / Thinking Dropdown -->
                                <div
                                    v-if="msg.thinking"
                                    class="rounded-xl border border-indigo-500/20 bg-background/50 overflow-hidden text-xs"
                                >
                                    <button
                                        type="button"
                                        class="w-full flex items-center justify-between p-2 bg-muted/40 hover:bg-muted/70 transition-colors text-left font-medium text-foreground cursor-pointer select-none"
                                        @click="msg.isThinkingExpanded = !msg.isThinkingExpanded"
                                    >
                                        <div class="flex items-center gap-1.5">
                                            <Brain class="size-3 text-indigo-500" />
                                            <span class="font-medium text-[11px] flex items-center gap-1">
                                                <template v-if="isStreaming && index === chatMessages.length - 1 && !msg.content">
                                                    Sedang menganalisis...
                                                    <Loader2 class="size-2.5 animate-spin text-indigo-500 inline-block" />
                                                </template>
                                                <template v-else>
                                                    Proses Berpikir AI
                                                </template>
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-1 text-[10px] text-muted-foreground">
                                            <span>{{ msg.isThinkingExpanded ? 'Tutup' : 'Lihat' }}</span>
                                            <ChevronUp v-if="msg.isThinkingExpanded" class="size-3" />
                                            <ChevronDown v-else class="size-3" />
                                        </div>
                                    </button>

                                    <div
                                        v-show="msg.isThinkingExpanded"
                                        class="p-2.5 border-t border-border/40 text-[10px] text-muted-foreground leading-relaxed whitespace-pre-line max-h-44 overflow-y-auto font-mono bg-background/70"
                                    >
                                        {{ msg.thinking }}
                                    </div>
                                </div>

                                <!-- Loading spinner before delta arrival -->
                                <div
                                    v-if="!msg.content && !msg.thinking && isStreaming"
                                    class="flex items-center gap-2 text-muted-foreground py-0.5 text-xs"
                                >
                                    <Loader2 class="size-3.5 animate-spin text-indigo-500" />
                                    <span>Mempersiapkan jawaban...</span>
                                </div>

                                <!-- Markdown AI Content -->
                                <div
                                    v-if="msg.content"
                                    class="ai-markdown"
                                    v-html="renderMarkdown(msg.content)"
                                />

                                <!-- Token Limit Warning Box -->
                                <div
                                    v-if="msg.isTokenLimit"
                                    class="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs space-y-2 mt-2"
                                >
                                    <div class="flex items-start gap-1.5 text-amber-800 dark:text-amber-300">
                                        <AlertCircle class="size-3.5 shrink-0 mt-0.5" />
                                        <div class="space-y-0.5">
                                            <p class="font-semibold text-[11px]">
                                                Batas Kuota Chat Tercapai
                                            </p>
                                            <p class="text-[10px] text-muted-foreground leading-tight">
                                                Harap klik Reset atau gunakan Compact untuk merangkum percakapan.
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 pt-1 border-t border-amber-500/15">
                                        <Button
                                            size="sm"
                                            class="h-6 text-[11px] bg-amber-600 hover:bg-amber-700 text-white gap-1 px-2"
                                            :disabled="isResetting"
                                            @click="resetChat"
                                        >
                                            <RotateCcw class="size-2.5" /> Reset
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="h-6 text-[11px] border-indigo-500/30 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-500/10 gap-1 px-2"
                                            :disabled="isCompacting"
                                            @click="compactChat"
                                        >
                                            <Layers class="size-2.5" /> Compact
                                        </Button>
                                    </div>
                                </div>

                                <span
                                    v-if="isStreaming && index === chatMessages.length - 1 && msg.content"
                                    class="inline-block size-1.5 rounded-full bg-indigo-500 animate-ping ml-1 align-middle"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Input Footer -->
                <div class="p-3 border-t border-border/70 bg-card/90 shrink-0">
                    <form @submit.prevent="handleSend" class="flex gap-2 items-end">
                        <textarea
                            v-model="inputQuery"
                            :placeholder="
                                isTokenLimitReached
                                    ? 'Batas chat tercapai. Silakan reset atau compact di atas...'
                                    : 'Tanya info turnamen atau cek kelayakan roster tim...'
                            "
                            rows="2"
                            :disabled="isStreaming || isTokenLimitReached"
                            class="w-full resize-none rounded-xl border border-input bg-background p-2.5 text-xs shadow-xs focus:outline-hidden focus:ring-2 focus:ring-ring disabled:opacity-50"
                            @keydown.enter.exact.prevent="handleSend"
                        />
                        <Button
                            type="submit"
                            class="h-[46px] px-3.5 bg-indigo-600 hover:bg-indigo-700 text-white shrink-0 rounded-xl"
                            :disabled="!inputQuery.trim() || isStreaming || isTokenLimitReached"
                        >
                            <Loader2 v-if="isStreaming" class="size-4 animate-spin" />
                            <Send v-else class="size-4" />
                        </Button>
                    </form>
                    <p class="text-[9px] text-muted-foreground text-center mt-1.5">
                        Tekan <kbd class="px-1 py-0.5 bg-muted rounded text-[8px] font-mono">Enter</kbd> untuk kirim, <kbd class="px-1 py-0.5 bg-muted rounded text-[8px] font-mono">Shift+Enter</kbd> baris baru.
                    </p>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

:deep(.ai-markdown) {
    line-height: 1.5;
}

:deep(.ai-markdown p) {
    margin-bottom: 0.4rem;
}

:deep(.ai-markdown p:last-child) {
    margin-bottom: 0;
}

:deep(.ai-markdown ul) {
    list-style-type: disc;
    margin-left: 1rem;
    margin-top: 0.2rem;
    margin-bottom: 0.4rem;
}

:deep(.ai-markdown ol) {
    list-style-type: decimal;
    margin-left: 1rem;
    margin-top: 0.2rem;
    margin-bottom: 0.4rem;
}

:deep(.ai-markdown li) {
    margin-bottom: 0.15rem;
}

:deep(.ai-markdown li:last-child) {
    margin-bottom: 0;
}

:deep(.ai-markdown strong) {
    font-weight: 600;
}

:deep(.ai-markdown em) {
    font-style: italic;
}

:deep(.ai-markdown h1),
:deep(.ai-markdown h2),
:deep(.ai-markdown h3),
:deep(.ai-markdown h4) {
    font-weight: 700;
    margin-top: 0.5rem;
    margin-bottom: 0.25rem;
    line-height: 1.25;
}

:deep(.ai-markdown h1) {
    font-size: 1.1em;
}

:deep(.ai-markdown h2) {
    font-size: 1.05em;
}

:deep(.ai-markdown h3),
:deep(.ai-markdown h4) {
    font-size: 0.95em;
}

:deep(.ai-markdown blockquote) {
    border-left: 3px solid #6366f1;
    padding-left: 0.5rem;
    margin: 0.4rem 0;
    opacity: 0.9;
    font-style: italic;
}

:deep(.ai-markdown code) {
    background-color: rgba(120, 120, 120, 0.15);
    padding: 0.1rem 0.25rem;
    border-radius: 0.25rem;
    font-size: 0.85em;
    font-family: monospace;
}

:deep(.ai-markdown pre) {
    background-color: rgba(120, 120, 120, 0.15);
    padding: 0.4rem 0.6rem;
    border-radius: 0.5rem;
    overflow-x: auto;
    margin: 0.4rem 0;
}

:deep(.ai-markdown pre code) {
    background-color: transparent;
    padding: 0;
}

:deep(.ai-markdown a) {
    color: #4f46e5;
    text-decoration: underline;
}

:deep(.ai-markdown table) {
    width: 100%;
    border-collapse: collapse;
    margin: 0.4rem 0;
    font-size: 0.85em;
}

:deep(.ai-markdown th),
:deep(.ai-markdown td) {
    border: 1px solid rgba(120, 120, 120, 0.2);
    padding: 0.25rem 0.4rem;
    text-align: left;
}

:deep(.ai-markdown th) {
    background-color: rgba(120, 120, 120, 0.1);
    font-weight: 600;
}
</style>
