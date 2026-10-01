<script setup lang="ts">
import { ref, nextTick } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    Bot,
    Brain,
    Calendar,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    Clock,
    DollarSign,
    FileText,
    Gamepad2,
    Layers,
    Loader2,
    MessageSquare,
    Pencil,
    RotateCcw,
    Send,
    Shield,
    Sparkles,
    Trash2,
    Trophy,
    UserCheck,
    Users,
} from '@lucide/vue';
import MarkdownIt from 'markdown-it';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface Game {
    id: number;
    name: string;
    slug: string;
    genre: string;
    platform: string;
    team_size: number;
}

interface User {
    id: number;
    name: string;
}

interface Registration {
    id: number;
    team_name: string;
    captain_name: string;
    status: string;
    created_at: string;
}

interface Tournament {
    id: number;
    title: string;
    slug: string;
    description: string;
    rules: string | null;
    max_teams: number;
    registration_fee: number;
    prize_pool: string | null;
    registration_deadline: string;
    start_date: string;
    status: 'draft' | 'open' | 'closed' | 'ongoing' | 'completed';
    approved_teams_count: number;
    total_registrations_count: number;
    game: Game;
    organizer: User;
    registrations: Registration[];
}

const props = defineProps<{
    tournament: Tournament;
    userRegistration: Registration | null;
    canManage: boolean;
}>();

const formatRupiah = (amount: number) => {
    if (amount === 0) return 'Gratis';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);
};

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const deleteTournament = () => {
    if (confirm(`Yakin ingin menghapus turnamen "${props.tournament.title}"?`)) {
        router.delete(`/tournaments/${props.tournament.id}`);
    }
};

const isFull = () => props.tournament.approved_teams_count >= props.tournament.max_teams;

const isDeadlinePassed = () => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const deadline = new Date(props.tournament.registration_deadline);
    return today > deadline;
};

const canRegister = () => {
    return props.tournament.status === 'open' && !isFull() && !isDeadlinePassed() && !props.userRegistration;
};

// AI Live Streaming Assistant State
interface ChatMessage {
    role: 'user' | 'assistant';
    content: string;
    thinking?: string;
    isThinkingExpanded?: boolean;
    isTokenLimit?: boolean;
}

const chatMessages = ref<ChatMessage[]>([]);
const inputQuery = ref('');
const isStreaming = ref(false);
const chatContainerRef = ref<HTMLDivElement | null>(null);

// Token Limit & Compaction State
const compactContext = ref<string>('');
const isCompacting = ref(false);
const isResetting = ref(false);
const isTokenLimitReached = ref(false);

const md = new MarkdownIt({
    html: false,
    breaks: true,
    linkify: true,
    typographer: false,
});

const defaultLinkRender =
    md.renderer.rules.link_open ||
    function (tokens, idx, options, _env, self) {
        return self.renderToken(tokens, idx, options);
    };

md.renderer.rules.link_open = function (tokens, idx, options, env, self) {
    tokens[idx].attrSet('target', '_blank');
    tokens[idx].attrSet('rel', 'noopener noreferrer');
    return defaultLinkRender(tokens, idx, options, env, self);
};

const renderMarkdown = (content: string): string => {
    if (!content) return '';
    return md.render(content);
};

const scrollToBottom = () => {
    nextTick(() => {
        if (chatContainerRef.value) {
            chatContainerRef.value.scrollTop = chatContainerRef.value.scrollHeight;
        }
    });
};

const sendQuery = async (customText?: string) => {
    const text = (customText || inputQuery.value).trim();
    if (!text || isStreaming.value || isTokenLimitReached.value) return;

    inputQuery.value = '';
    chatMessages.value.push({ role: 'user', content: text });

    const assistantIndex = chatMessages.value.length;
    chatMessages.value.push({
        role: 'assistant',
        content: '',
        thinking: '',
        isThinkingExpanded: false,
    });
    isStreaming.value = true;
    scrollToBottom();

    try {
        const response = await fetch(`/tournaments/${props.tournament.id}/ai-chat-stream`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream',
            },
            body: JSON.stringify({
                message: text,
                history: chatMessages.value.slice(0, -2).slice(-6),
                compact_context: compactContext.value || undefined,
            }),
        });

        if (!response.ok || !response.body) {
            chatMessages.value[assistantIndex].content = 'Terjadi kendala saat menghubungi AI Agent.';
            isStreaming.value = false;
            return;
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split('\n');
            buffer = lines.pop() || '';

            for (const line of lines) {
                const trimmed = line.trim();
                if (!trimmed || !trimmed.startsWith('data:')) continue;

                const payloadText = trimmed.slice(5).trim();
                if (payloadText === '[DONE]') {
                    break;
                }

                try {
                    const parsed = JSON.parse(payloadText);
                    if (parsed.token_limit_reached) {
                        isTokenLimitReached.value = true;
                        chatMessages.value[assistantIndex].isTokenLimit = true;
                    }

                    const deltaThinking = parsed.choices?.[0]?.delta?.reasoning_content;
                    const deltaContent = parsed.choices?.[0]?.delta?.content;

                    if (deltaThinking) {
                        chatMessages.value[assistantIndex].thinking = (chatMessages.value[assistantIndex].thinking || '') + deltaThinking;
                        scrollToBottom();
                    }

                    if (deltaContent) {
                        chatMessages.value[assistantIndex].content += deltaContent;
                        scrollToBottom();
                    }
                } catch {
                    // Abaikan baris partial chunk
                }
            }
        }
    } catch (e: any) {
        chatMessages.value[assistantIndex].content = 'Gagal memproses stream: ' + (e.message || 'Koneksi terputus.');
    } finally {
        isStreaming.value = false;
        const currentMsg = chatMessages.value[assistantIndex];
        if (currentMsg && currentMsg.content && currentMsg.content.includes('<think>')) {
            const thinkMatch = currentMsg.content.match(/<think>([\s\S]*?)<\/think>/);
            if (thinkMatch) {
                currentMsg.thinking = (currentMsg.thinking ? currentMsg.thinking + '\n' : '') + thinkMatch[1].trim();
                currentMsg.content = currentMsg.content.replace(/<think>[\s\S]*?<\/think>/, '').trim();
            }
        }
        scrollToBottom();
    }
};

const resetChat = async () => {
    isResetting.value = true;
    try {
        await fetch(`/tournaments/${props.tournament.id}/ai-chat-reset`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
            },
        });
    } catch {
        // Fallback jika offline
    } finally {
        isResetting.value = false;
        chatMessages.value = [];
        isTokenLimitReached.value = false;
    }
};

const compactChat = async () => {
    if (chatMessages.value.length === 0 || isCompacting.value) return;

    isCompacting.value = true;
    try {
        const response = await fetch(`/tournaments/${props.tournament.id}/ai-chat-compact`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                history: chatMessages.value.map(m => ({ role: m.role, content: m.content })),
                previous_compact: compactContext.value || undefined,
            }),
        });

        if (response.ok) {
            const data = await response.json();
            if (data.compacted_context) {
                compactContext.value = data.compacted_context;
                chatMessages.value = [];
                isTokenLimitReached.value = false;
            }
        }
    } catch (e: any) {
        const summaries = chatMessages.value
            .filter(m => m.role === 'user')
            .map(m => m.content.slice(0, 50))
            .join('; ');
        compactContext.value = `Percakapan sebelumnya membahas: ${summaries}`;
        chatMessages.value = [];
        isTokenLimitReached.value = false;
    } finally {
        isCompacting.value = false;
    }
};

const clearCompactContext = () => {
    compactContext.value = '';
};

const fillPrompt = (type: 'roster' | 'rules' | 'schedule') => {
    if (type === 'roster') {
        sendQuery(
            `Halo AI, tolong evaluasi kelayakan roster tim saya untuk turnamen ${props.tournament.title}:\n\nNama Tim: Phoenix Esports\n1. Alex (Kapten) - ID: 28491823, Zone: 2041\n2. Bima - ID: 83921821, Zone: 2041\n3. Kevin - ID: 92837192, Zone: 2041\n4. Rio - ID: 19283719, Zone: 2041\n5. Daniel - ID: 48192831, Zone: 2041\nCadangan: Bayu - ID: 57291823, Zone: 2041\n\nApakah format pemain ini sudah valid dan sesuai aturan?`
        );
    } else if (type === 'rules') {
        sendQuery('Tolong jelaskan ringkasan regulasi utama, sistem bracket, dan hal-hal yang dilarang pada turnamen ini.');
    } else if (type === 'schedule') {
        sendQuery('Kapan batas akhir pendaftaran, kapan turnamen dimulai, dan bagaimana ketentuan biaya pendaftarannya?');
    }
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Turnamen Game',
                href: '/tournaments',
            },
            {
                title: 'Detail Turnamen',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <div class="flex flex-col gap-6 p-4 md:p-6 max-w-6xl mx-auto w-full">
        <Head :title="tournament.title" />

        <!-- Top Banner / Details Header -->
        <div class="bg-card border border-border rounded-2xl p-6 md:p-8 shadow-xs relative overflow-hidden">
            <div class="flex flex-col md:flex-row md:items-start justify-between gap-6 relative z-10">
                <div class="space-y-3 max-w-3xl">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge variant="outline" class="text-xs flex items-center gap-1">
                            <Gamepad2 class="size-3" />
                            {{ tournament.game.name }} ({{ tournament.game.genre }})
                        </Badge>
                        <Badge variant="secondary" class="text-xs">
                            Format {{ tournament.game.team_size }} Pemain / Tim
                        </Badge>
                        <Badge
                            v-if="tournament.status === 'open'"
                            class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20"
                        >
                            Pendaftaran Dibuka
                        </Badge>
                        <Badge v-else class="bg-neutral-500/10 text-neutral-600 dark:text-neutral-400">
                            Status: {{ tournament.status.toUpperCase() }}
                        </Badge>
                    </div>

                    <h1 class="text-2xl md:text-4xl font-extrabold tracking-tight">
                        {{ tournament.title }}
                    </h1>

                    <p class="text-muted-foreground text-sm md:text-base leading-relaxed">
                        {{ tournament.description }}
                    </p>
                </div>

                <!-- Admin Action Buttons -->
                <div v-if="canManage" class="flex items-center gap-2 shrink-0">
                    <Button as-child variant="outline" size="sm">
                        <Link :href="`/tournaments/${tournament.id}/edit`">
                            <Pencil class="size-4 mr-1" /> Edit
                        </Link>
                    </Button>
                    <Button variant="destructive" size="sm" @click="deleteTournament">
                        <Trash2 class="size-4 mr-1" /> Hapus
                    </Button>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-8 pt-6 border-t border-border">
                <div class="space-y-1">
                    <span class="text-xs text-muted-foreground flex items-center gap-1">
                        <Trophy class="size-3.5 text-amber-500" /> Total Hadiah
                    </span>
                    <p class="text-base font-bold text-emerald-600 dark:text-emerald-400">
                        {{ tournament.prize_pool || 'Piala & Sertifikat' }}
                    </p>
                </div>

                <div class="space-y-1">
                    <span class="text-xs text-muted-foreground flex items-center gap-1">
                        <DollarSign class="size-3.5 text-blue-500" /> Biaya Registrasi
                    </span>
                    <p class="text-base font-semibold text-foreground">
                        {{ formatRupiah(tournament.registration_fee) }}
                    </p>
                </div>

                <div class="space-y-1">
                    <span class="text-xs text-muted-foreground flex items-center gap-1">
                        <Clock class="size-3.5 text-rose-500" /> Batas Pendaftaran
                    </span>
                    <p class="text-base font-semibold text-foreground">
                        {{ formatDate(tournament.registration_deadline) }}
                    </p>
                </div>

                <div class="space-y-1">
                    <span class="text-xs text-muted-foreground flex items-center gap-1">
                        <Calendar class="size-3.5 text-indigo-500" /> Tanggal Turnamen
                    </span>
                    <p class="text-base font-semibold text-foreground">
                        {{ formatDate(tournament.start_date) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Main Content Area: Grid 2 Column -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column: Rules & Info -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Aturan Turnamen -->
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-lg flex items-center gap-2">
                            <Shield class="size-5 text-primary" />
                            Regulasi & Aturan Turnamen
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm space-y-3 leading-relaxed text-muted-foreground">
                        <div v-if="tournament.rules" class="whitespace-pre-line bg-muted/30 p-4 rounded-xl border border-border">
                            {{ tournament.rules }}
                        </div>
                        <div v-else class="text-muted-foreground italic">
                            Aturan umum berlaku. Sportivitas dijunjung tinggi.
                        </div>
                    </CardContent>
                </Card>

                <!-- AI Assistant & Pengecek Turnamen (Live Streaming untuk Guest & Peserta) -->
                <Card class="border-indigo-500/30 bg-linear-to-b from-indigo-500/5 via-background to-background shadow-xs overflow-hidden">
                    <CardHeader class="pb-3 border-b border-border/60">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5">
                                <div class="p-2 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                                    <Bot class="size-5" />
                                </div>
                                <div>
                                    <CardTitle class="text-base md:text-lg flex items-center gap-2 text-foreground">
                                        AI Agent Turnamen
                                    </CardTitle>
                                    <CardDescription class="text-xs">
                                        Asisten cerdas untuk pengunjung & peserta: Tanya aturan atau cek kelayakan roster tim secara instan.
                                    </CardDescription>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <Button
                                    v-if="chatMessages.length >= 2"
                                    variant="outline"
                                    size="sm"
                                    class="h-7 text-xs border-indigo-500/30 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-500/10 gap-1"
                                    :disabled="isCompacting || isStreaming"
                                    @click="compactChat"
                                    title="Ringkas percakapan untuk menghemat token dan menyimpan memori konteks"
                                >
                                    <Loader2 v-if="isCompacting" class="size-3 animate-spin" />
                                    <Layers v-else class="size-3" />
                                    Compact
                                </Button>
                                <Button
                                    v-if="chatMessages.length || compactContext"
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 text-xs text-muted-foreground gap-1"
                                    :disabled="isResetting || isStreaming"
                                    @click="resetChat"
                                    title="Bersihkan Percakapan & Reset Kuota"
                                >
                                    <Loader2 v-if="isResetting" class="size-3 animate-spin" />
                                    <RotateCcw v-else class="size-3" />
                                    Reset
                                </Button>
                            </div>
                        </div>

                        <!-- Compact Context Memory Banner -->
                        <div
                            v-if="compactContext"
                            class="p-2.5 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-xs flex items-center justify-between gap-3 shadow-xs"
                        >
                            <div class="flex items-start gap-2 overflow-hidden">
                                <Brain class="size-4 text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5" />
                                <div class="space-y-0.5 overflow-hidden">
                                    <span class="font-semibold text-indigo-900 dark:text-indigo-200 block text-[11px]">
                                        Memori Konteks Percakapan Tersimpan (Compact):
                                    </span>
                                    <p class="text-muted-foreground text-[11px] leading-relaxed line-clamp-2">
                                        {{ compactContext }}
                                    </p>
                                </div>
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-6 text-[11px] text-muted-foreground hover:text-foreground shrink-0 px-2"
                                @click="clearCompactContext"
                                title="Hapus memori percakapan sebelumnya"
                            >
                                Hapus
                            </Button>
                        </div>

                        <!-- Quick Prompt Buttons -->
                        <div class="flex flex-wrap items-center gap-1.5 pt-3">
                            <span class="text-[11px] text-muted-foreground mr-1 flex items-center gap-1">
                                <Sparkles class="size-3 text-indigo-500" /> Contoh Tanya:
                            </span>
                            <button
                                type="button"
                                class="text-xs px-2.5 py-1 rounded-full border border-border bg-card hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer inline-flex items-center gap-1.5"
                                :disabled="isStreaming || isTokenLimitReached"
                                @click="fillPrompt('roster')"
                            >
                                <Shield class="size-3.5 text-indigo-500" /> Cek Kelayakan Roster Tim
                            </button>
                            <button
                                type="button"
                                class="text-xs px-2.5 py-1 rounded-full border border-border bg-card hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer inline-flex items-center gap-1.5"
                                :disabled="isStreaming || isTokenLimitReached"
                                @click="fillPrompt('rules')"
                            >
                                <FileText class="size-3.5 text-indigo-500" /> Regulasi & Larangan
                            </button>
                            <button
                                type="button"
                                class="text-xs px-2.5 py-1 rounded-full border border-border bg-card hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer inline-flex items-center gap-1.5"
                                :disabled="isStreaming || isTokenLimitReached"
                                @click="fillPrompt('schedule')"
                            >
                                <Clock class="size-3.5 text-indigo-500" /> Jadwal & Biaya
                            </button>
                        </div>
                    </CardHeader>

                    <CardContent class="p-4 space-y-4">
                        <!-- Chat Message Log Container -->
                        <div
                            ref="chatContainerRef"
                            class="rounded-xl border border-border bg-muted/20 p-4 min-h-[160px] max-h-[380px] overflow-y-auto space-y-3 text-sm"
                        >
                            <!-- Empty Initial State -->
                            <div v-if="chatMessages.length === 0" class="text-center py-6 space-y-2">
                                <div class="p-2.5 rounded-full bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 size-10 mx-auto flex items-center justify-center">
                                    <MessageSquare class="size-5" />
                                </div>
                                <p class="text-xs font-medium text-foreground">
                                    Punya pertanyaan tentang turnamen ini atau ingin mengecek kelayakan roster tim Anda?
                                </p>
                                <p class="text-[11px] text-muted-foreground max-w-md mx-auto">
                                    Sebagai pengunjung (guest) atau calon peserta, Anda dapat bertanya apa saja atau klik salah satu tombol contoh di atas untuk langsung dijawab oleh AI Agent.
                                </p>
                            </div>

                            <!-- Chat Messages Loop -->
                            <div
                                v-for="(msg, index) in chatMessages"
                                :key="index"
                                class="flex flex-col space-y-1"
                                :class="msg.role === 'user' ? 'items-end' : 'items-start'"
                            >
                                <span class="text-[10px] text-muted-foreground px-1">
                                    {{ msg.role === 'user' ? 'Anda (Pengunjung)' : 'AI Agent Turnamen' }}
                                </span>
                                <div
                                    class="p-3.5 rounded-2xl max-w-[92%] leading-relaxed text-xs md:text-sm shadow-xs"
                                    :class="
                                        msg.role === 'user'
                                            ? 'bg-primary text-primary-foreground rounded-tr-xs whitespace-pre-line'
                                            : 'bg-card border border-border text-foreground rounded-tl-xs'
                                    "
                                >
                                    <div v-if="msg.role === 'user'">
                                        {{ msg.content }}
                                    </div>
                                    <div v-else class="space-y-2.5 w-full">
                                        <!-- Thinking Box (Proses Berpikir AI DeepSeek) -->
                                        <div
                                            v-if="msg.thinking"
                                            class="rounded-xl border border-indigo-500/20 bg-muted/30 overflow-hidden text-xs"
                                        >
                                            <button
                                                type="button"
                                                class="w-full flex items-center justify-between p-2.5 bg-muted/40 hover:bg-muted/70 transition-colors text-left font-medium text-foreground cursor-pointer select-none"
                                                @click="msg.isThinkingExpanded = !msg.isThinkingExpanded"
                                            >
                                                <div class="flex items-center gap-2">
                                                    <Brain class="size-3.5 text-indigo-500" />
                                                    <span class="font-medium text-xs flex items-center gap-1.5">
                                                        <template v-if="isStreaming && index === chatMessages.length - 1 && !msg.content">
                                                            Sedang menganalisis & berpikir...
                                                            <Loader2 class="size-3 animate-spin text-indigo-500 inline-block" />
                                                        </template>
                                                        <template v-else>
                                                            Proses Berpikir AI
                                                        </template>
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                                    <span>{{ msg.isThinkingExpanded ? 'Sembunyikan' : 'Lihat Detail' }}</span>
                                                    <ChevronUp v-if="msg.isThinkingExpanded" class="size-3.5" />
                                                    <ChevronDown v-else class="size-3.5" />
                                                </div>
                                            </button>

                                            <div
                                                v-show="msg.isThinkingExpanded"
                                                class="p-3 border-t border-border/50 bg-background/50 text-[11px] text-muted-foreground leading-relaxed whitespace-pre-line max-h-56 overflow-y-auto font-mono"
                                            >
                                                {{ msg.thinking }}
                                            </div>
                                        </div>

                                        <!-- Loading state jika belum ada thinking dan belum ada content -->
                                        <div
                                            v-if="!msg.content && !msg.thinking && isStreaming"
                                            class="flex items-center gap-2 text-muted-foreground py-0.5 text-xs"
                                        >
                                            <Loader2 class="size-3.5 animate-spin text-indigo-500" />
                                            <span>Mempersiapkan respon...</span>
                                        </div>

                                        <!-- Respon Markdown -->
                                        <div
                                            v-if="msg.content"
                                            class="ai-markdown"
                                            v-html="renderMarkdown(msg.content)"
                                        />

                                        <!-- Token Limit Warning Box with Action Button -->
                                        <div
                                            v-if="msg.isTokenLimit"
                                            class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs space-y-2.5 mt-2"
                                        >
                                            <div class="flex items-start gap-2 text-amber-800 dark:text-amber-300">
                                                <AlertCircle class="size-4 shrink-0 mt-0.5" />
                                                <div class="space-y-0.5">
                                                    <p class="font-semibold text-xs">
                                                        Batas Kuota Chat Tercapai
                                                    </p>
                                                    <p class="text-[11px] text-muted-foreground leading-relaxed">
                                                        Kamu telah melewati batas chat dengan AI nya, harap melakukan Reset atau gunakan Compact untuk mengingat percakapan sebelumnya.
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-amber-500/15">
                                                <Button
                                                    size="sm"
                                                    class="h-7 text-xs bg-amber-600 hover:bg-amber-700 text-white gap-1"
                                                    :disabled="isResetting"
                                                    @click="resetChat"
                                                >
                                                    <Loader2 v-if="isResetting" class="size-3 animate-spin" />
                                                    <RotateCcw v-else class="size-3" />
                                                    Reset
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    class="h-7 text-xs border-indigo-500/30 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-500/10 gap-1"
                                                    :disabled="isCompacting"
                                                    @click="compactChat"
                                                >
                                                    <Loader2 v-if="isCompacting" class="size-3 animate-spin" />
                                                    <Layers v-else class="size-3" />
                                                    Compact & Ingat Percakapan
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

                        <!-- Chat Input Box -->
                        <form @submit.prevent="sendQuery()" class="flex gap-2 items-end">
                            <textarea
                                v-model="inputQuery"
                                :placeholder="
                                    isTokenLimitReached
                                        ? 'Batas chat tercapai. Silakan klik tombol Reset atau Compact di atas untuk melanjutkan...'
                                        : 'Tanyakan aturan turnamen atau tempel susunan roster tim Anda di sini...'
                                "
                                rows="2"
                                :disabled="isStreaming || isTokenLimitReached"
                                class="w-full resize-none rounded-xl border border-input bg-background p-3 text-xs md:text-sm shadow-xs focus:outline-hidden focus:ring-2 focus:ring-ring disabled:opacity-50"
                                @keydown.enter.exact.prevent="sendQuery()"
                            />
                            <Button
                                type="submit"
                                class="h-[52px] px-4 bg-indigo-600 hover:bg-indigo-700 text-white shrink-0 rounded-xl"
                                :disabled="!inputQuery.trim() || isStreaming || isTokenLimitReached"
                            >
                                <Loader2 v-if="isStreaming" class="size-4 animate-spin" />
                                <Send v-else class="size-4" />
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <!-- Daftar Tim yang Sudah Lolos / Approved -->
                <Card>
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-lg flex items-center gap-2">
                                <Users class="size-5 text-primary" />
                                Daftar Tim Terdaftar
                            </CardTitle>
                            <Badge variant="secondary">
                                {{ tournament.registrations.length }} Tim Disetujui
                            </Badge>
                        </div>
                        <CardDescription>
                            Daftar tim yang telah diverifikasi dan siap bertanding.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="tournament.registrations.length === 0" class="text-center py-8 text-sm text-muted-foreground">
                            Belum ada tim yang disetujui untuk turnamen ini. Jadilah pendaftar pertama!
                        </div>
                        <div v-else class="divide-y divide-border border rounded-xl overflow-hidden">
                            <div
                                v-for="(team, idx) in tournament.registrations"
                                :key="team.id"
                                class="flex items-center justify-between p-3.5 hover:bg-muted/20"
                            >
                                <div class="flex items-center gap-3">
                                    <span class="size-7 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center">
                                        {{ idx + 1 }}
                                    </span>
                                    <div>
                                        <p class="font-semibold text-sm">{{ team.team_name }}</p>
                                        <p class="text-xs text-muted-foreground">Kapten: {{ team.captain_name }}</p>
                                    </div>
                                </div>
                                <Badge class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-xs">
                                    Terverifikasi
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right Column: Registration Box / Call to Action -->
            <div class="space-y-6">
                <Card class="border-primary/30 shadow-sm">
                    <CardHeader>
                        <CardTitle class="text-lg">Slot & Pendaftaran</CardTitle>
                        <CardDescription>
                            Informasi ketersediaan kuota tim peserta turnamen.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <!-- Progress Slot -->
                        <div>
                            <div class="flex justify-between text-sm mb-1.5 font-medium">
                                <span>Slot Terisi</span>
                                <span>{{ tournament.approved_teams_count }} / {{ tournament.max_teams }} Tim</span>
                            </div>
                            <div class="w-full bg-secondary h-3 rounded-full overflow-hidden">
                                <div
                                    class="bg-primary h-full transition-all duration-300"
                                    :style="{
                                        width: `${Math.min(100, Math.round((tournament.approved_teams_count / tournament.max_teams) * 100))}%`
                                    }"
                                />
                            </div>
                            <p v-if="isFull()" class="text-xs text-rose-500 font-medium mt-1">
                                Kuota telah penuh! Pendaftaran baru tidak dapat diterima.
                            </p>
                            <p v-else class="text-xs text-muted-foreground mt-1">
                                Tersisa {{ tournament.max_teams - tournament.approved_teams_count }} slot tim lagi.
                            </p>
                        </div>

                        <!-- Status Pendaftaran User Saat Ini -->
                        <div v-if="userRegistration" class="p-4 rounded-xl border border-primary/20 bg-primary/5 space-y-2">
                            <div class="flex items-center gap-2 text-primary font-semibold text-sm">
                                <CheckCircle2 class="size-4" /> Tim Anda Terdaftar
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Anda telah mendaftarkan <strong>{{ userRegistration.team_name }}</strong>.
                            </p>
                            <div class="flex items-center justify-between pt-2">
                                <span class="text-xs">Status:</span>
                                <Badge
                                    :variant="userRegistration.status === 'approved' ? 'default' : 'outline'"
                                    class="text-xs capitalize"
                                >
                                    {{ userRegistration.status }}
                                </Badge>
                            </div>
                            <Button as-child variant="outline" size="sm" class="w-full mt-2 text-xs">
                                <Link href="/registrations">
                                    Lihat di Menu Pendaftaran
                                </Link>
                            </Button>
                        </div>

                        <!-- Tombol Pendaftaran -->
                        <div v-else class="space-y-3">
                            <Button
                                v-if="canRegister()"
                                as-child
                                class="w-full font-semibold py-6 text-base"
                            >
                                <Link :href="`/tournaments/${tournament.id}/register`">
                                    <UserCheck class="size-5 mr-2" />
                                    Daftarkan Tim Sekarang
                                </Link>
                            </Button>

                            <div v-else class="p-3 bg-muted rounded-xl text-center text-xs text-muted-foreground space-y-1">
                                <AlertCircle class="size-5 mx-auto text-muted-foreground" />
                                <p class="font-medium">Pendaftaran Tidak Tersedia</p>
                                <p v-if="isDeadlinePassed()">Batas waktu pendaftaran telah lewat.</p>
                                <p v-else-if="isFull()">Kuota slot pendaftaran sudah terisi penuh.</p>
                                <p v-else>Status turnamen sedang tidak membuka pendaftaran.</p>
                            </div>
                        </div>

                        <div class="text-xs text-muted-foreground pt-2 border-t border-border">
                            Penyelenggara: <strong>{{ tournament.organizer.name }}</strong>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep(.ai-markdown) {
    line-height: 1.6;
}

:deep(.ai-markdown p) {
    margin-bottom: 0.5rem;
}

:deep(.ai-markdown p:last-child) {
    margin-bottom: 0;
}

:deep(.ai-markdown ul) {
    list-style-type: disc;
    margin-left: 1.25rem;
    margin-top: 0.25rem;
    margin-bottom: 0.5rem;
}

:deep(.ai-markdown ol) {
    list-style-type: decimal;
    margin-left: 1.25rem;
    margin-top: 0.25rem;
    margin-bottom: 0.5rem;
}

:deep(.ai-markdown li) {
    margin-bottom: 0.2rem;
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
    margin-top: 0.65rem;
    margin-bottom: 0.35rem;
    line-height: 1.3;
}

:deep(.ai-markdown h1) {
    font-size: 1.15em;
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
    padding-left: 0.75rem;
    margin: 0.5rem 0;
    opacity: 0.9;
    font-style: italic;
}

:deep(.ai-markdown code) {
    background-color: rgba(120, 120, 120, 0.15);
    padding: 0.125rem 0.35rem;
    border-radius: 0.25rem;
    font-size: 0.85em;
    font-family: monospace;
}

:deep(.ai-markdown pre) {
    background-color: rgba(120, 120, 120, 0.15);
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    overflow-x: auto;
    margin: 0.5rem 0;
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
    margin: 0.5rem 0;
    font-size: 0.85em;
}

:deep(.ai-markdown th),
:deep(.ai-markdown td) {
    border: 1px solid rgba(120, 120, 120, 0.2);
    padding: 0.375rem 0.5rem;
    text-align: left;
}

:deep(.ai-markdown th) {
    background-color: rgba(120, 120, 120, 0.1);
    font-weight: 600;
}
</style>
