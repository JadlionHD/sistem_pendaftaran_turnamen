<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    Bot,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    Coins,
    Cpu,
    Eye,
    Gamepad2,
    Loader2,
    Pencil,
    Search,
    ShieldCheck,
    Sparkles,
    Trash2,
    Users,
    X,
    XCircle,
} from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface Game {
    id: number;
    name: string;
    genre: string;
}

interface Tournament {
    id: number;
    title: string;
    game: Game;
}

interface User {
    id: number;
    name: string;
    email: string;
}

interface ChecklistItem {
    rule: string;
    passed: boolean;
    note: string;
}

interface Registration {
    id: number;
    tournament_id: number;
    user_id: number;
    team_name: string;
    captain_name: string;
    captain_whatsapp: string;
    captain_email: string;
    team_members: string;
    status: 'pending' | 'approved' | 'rejected';
    admin_notes: string | null;
    ai_status?: 'passed' | 'flagged' | 'rejected' | null;
    ai_score?: number | null;
    ai_summary?: string | null;
    ai_checklist?: ChecklistItem[] | null;
    ai_recommendation?: string | null;
    ai_cost?: string | null;
    ai_tokens_used?: number | null;
    ai_checked_at?: string | null;
    created_at: string;
    tournament: Tournament;
    user: User;
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
}

const props = defineProps<{
    registrations: PaginatedData<Registration>;
    tournaments: { id: number; title: string }[];
    filters: {
        status: string;
        tournament_id: string | null;
    };
    isAdmin: boolean;
}>();

const selectedStatus = ref(props.filters.status || 'all');
const selectedTournament = ref(props.filters.tournament_id || '');

const applyFilter = () => {
    router.get(
        '/registrations',
        {
            status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
            tournament_id: selectedTournament.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const resetFilter = () => {
    selectedStatus.value = 'all';
    selectedTournament.value = '';
    router.get('/registrations', {}, { preserveState: true, replace: true });
};

// Detail modal preview
const activeModalRegistration = ref<Registration | null>(null);

const openDetailModal = (reg: Registration) => {
    activeModalRegistration.value = reg;
};

const closeDetailModal = () => {
    activeModalRegistration.value = null;
};

// Admin status update action
const updateStatus = (registrationId: number, newStatus: 'approved' | 'rejected') => {
    const actionLabel = newStatus === 'approved' ? 'menyetujui' : 'menolak';
    let adminNotes = '';
    if (newStatus === 'rejected') {
        const input = prompt('Alasan penolakan (opsional):');
        if (input === null) return; // Batal
        adminNotes = input;
    } else {
        if (!confirm(`Yakin ingin ${actionLabel} pendaftaran tim ini?`)) {
            return;
        }
    }

    router.patch(`/registrations/${registrationId}/status`, {
        status: newStatus,
        admin_notes: adminNotes,
    });
};

const deleteRegistration = (reg: Registration) => {
    if (confirm(`Yakin ingin membatalkan pendaftaran tim "${reg.team_name}"?`)) {
        router.delete(`/registrations/${reg.id}`);
    }
};

const auditingId = ref<number | null>(null);

const runAiAudit = (reg: Registration) => {
    auditingId.value = reg.id;
    router.post(
        `/registrations/${reg.id}/audit-ai`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                auditingId.value = null;
                // Perbarui data modal jika sedang terbuka untuk registrasi ini
                if (activeModalRegistration.value && activeModalRegistration.value.id === reg.id) {
                    const fresh = props.registrations.data.find(r => r.id === reg.id);
                    if (fresh) {
                        activeModalRegistration.value = fresh;
                    }
                }
            },
        },
    );
};

const getAiBadge = (status?: string | null, score?: number | null) => {
    if (!status) {
        return {
            label: 'AI: Belum Dicek',
            class: 'bg-muted text-muted-foreground border-border/60 hover:bg-muted/80',
        };
    }
    switch (status) {
        case 'passed':
            return {
                label: `AI: Lolos (${score ?? 100})`,
                class: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/20',
            };
        case 'flagged':
            return {
                label: `AI: Perlu Cek (${score ?? 50})`,
                class: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30 hover:bg-amber-500/20',
            };
        case 'rejected':
            return {
                label: `AI: Ditolak (${score ?? 0})`,
                class: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/30 hover:bg-rose-500/20',
            };
        default:
            return {
                label: 'AI: Memproses',
                class: 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/30 hover:bg-sky-500/20',
            };
    }
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'approved':
            return { label: 'Disetujui', class: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' };
        case 'rejected':
            return { label: 'Ditolak', class: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20' };
        default:
            return { label: 'Menunggu Verifikasi', class: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20' };
    }
};

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Pendaftaran Tim',
                href: '/registrations',
            },
        ],
    },
});
</script>

<template>
    <div class="flex flex-col gap-6 p-4 md:p-6 max-w-7xl mx-auto w-full">
        <Head :title="isAdmin ? 'Manajemen Pendaftaran Tim' : 'Pendaftaran Tim Saya'" />

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-border pb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold tracking-tight flex items-center gap-2">
                    <Users class="size-7 text-primary" />
                    {{ isAdmin ? 'Semua Pendaftaran Tim Turnamen' : 'Riwayat Pendaftaran Tim Saya' }}
                </h1>
                <p class="text-muted-foreground mt-1 text-sm">
                    {{ isAdmin
                        ? 'Kelola dan verifikasi pendaftaran tim dari seluruh turnamen yang diselenggarakan.'
                        : 'Pantau status verifikasi dan data tim yang telah Anda daftarkan.'
                    }}
                </p>
            </div>

            <Button as-child>
                <Link href="/tournaments">
                    <Gamepad2 class="size-4 mr-2" />
                    Lihat Turnamen Lain
                </Link>
            </Button>
        </div>

        <!-- Filter Bar -->
        <div class="bg-card border border-border rounded-xl p-4 flex flex-col md:flex-row gap-4 items-center justify-between shadow-xs">
            <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                    <Search class="size-4" />
                    Filter:
                </div>

                <!-- Filter Turnamen -->
                <select
                    v-model="selectedTournament"
                    @change="applyFilter"
                    class="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus:outline-hidden focus:ring-2 focus:ring-ring max-w-xs truncate"
                >
                    <option value="">Semua Turnamen</option>
                    <option v-for="t in tournaments" :key="t.id" :value="t.id">
                        {{ t.title }}
                    </option>
                </select>

                <!-- Filter Status -->
                <select
                    v-model="selectedStatus"
                    @change="applyFilter"
                    class="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus:outline-hidden focus:ring-2 focus:ring-ring"
                >
                    <option value="all">Semua Status</option>
                    <option value="pending">Menunggu Verifikasi</option>
                    <option value="approved">Disetujui</option>
                    <option value="rejected">Ditolak</option>
                </select>

                <Button
                    v-if="selectedStatus !== 'all' || selectedTournament"
                    variant="ghost"
                    size="sm"
                    @click="resetFilter"
                    class="text-xs text-muted-foreground"
                >
                    Reset Filter
                </Button>
            </div>

            <div class="text-xs text-muted-foreground">
                Total: <strong>{{ registrations.total }}</strong> Pendaftaran
            </div>
        </div>

        <!-- Empty State (TC-02) -->
        <div
            v-if="registrations.data.length === 0"
            class="flex flex-col items-center justify-center p-12 text-center rounded-xl border border-dashed border-border bg-card/50"
        >
            <div class="p-4 rounded-full bg-muted text-muted-foreground mb-4">
                <Users class="size-10 stroke-1" />
            </div>
            <h3 class="text-lg font-semibold">Belum Ada Data Pendaftaran</h3>
            <p class="text-sm text-muted-foreground max-w-sm mt-1 mb-6">
                {{ isAdmin
                    ? 'Belum ada pendaftaran tim yang masuk untuk kriteria filter ini.'
                    : 'Anda belum mendaftarkan tim ke turnamen manapun. Cari turnamen dan daftarkan tim Anda sekarang!'
                }}
            </p>
            <Button as-child size="sm">
                <Link href="/tournaments">
                    <Gamepad2 class="size-4 mr-1.5" /> Jelajahi Turnamen
                </Link>
            </Button>
        </div>

        <!-- Registration Table / Cards -->
        <div v-else class="border border-border rounded-xl bg-card overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-muted/50 text-muted-foreground uppercase text-xs border-b border-border">
                        <tr>
                            <th class="px-4 py-3 font-medium">Tim & Kapten</th>
                            <th class="px-4 py-3 font-medium">Turnamen & Game</th>
                            <th class="px-4 py-3 font-medium">Kontak WhatsApp</th>
                            <th class="px-4 py-3 font-medium">Status & AI Audit</th>
                            <th class="px-4 py-3 font-medium">Waktu Daftar</th>
                            <th class="px-4 py-3 font-medium text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="reg in registrations.data"
                            :key="reg.id"
                            class="hover:bg-muted/30 transition-colors"
                        >
                            <!-- Tim & Kapten -->
                            <td class="px-4 py-3.5">
                                <div class="font-semibold text-foreground flex items-center gap-1.5">
                                    {{ reg.team_name }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    Kapten: {{ reg.captain_name }}
                                </div>
                            </td>

                            <!-- Turnamen -->
                            <td class="px-4 py-3.5">
                                <div class="font-medium text-foreground line-clamp-1">
                                    <Link :href="`/tournaments/${reg.tournament.id}`" class="hover:underline">
                                        {{ reg.tournament.title }}
                                    </Link>
                                </div>
                                <div class="text-xs text-muted-foreground flex items-center gap-1 mt-0.5">
                                    <Badge variant="outline" class="text-[10px] py-0 px-1.5 font-normal">
                                        {{ reg.tournament.game.name }}
                                    </Badge>
                                </div>
                            </td>

                            <!-- Kontak WA -->
                            <td class="px-4 py-3.5 text-xs font-mono text-muted-foreground">
                                {{ reg.captain_whatsapp }}
                            </td>

                            <!-- Status & AI Audit -->
                            <td class="px-4 py-3.5">
                                <div class="flex flex-col gap-1.5 items-start">
                                    <span
                                        :class="[
                                            'text-xs px-2.5 py-0.5 rounded-full font-medium border inline-flex items-center gap-1',
                                            getStatusBadge(reg.status).class,
                                        ]"
                                    >
                                        <CheckCircle2 v-if="reg.status === 'approved'" class="size-3" />
                                        <XCircle v-else-if="reg.status === 'rejected'" class="size-3" />
                                        <Clock v-else class="size-3" />
                                        {{ getStatusBadge(reg.status).label }}
                                    </span>

                                    <!-- AI Audit Badge -->
                                    <button
                                        type="button"
                                        class="text-[11px] px-2 py-0.5 rounded-md border inline-flex items-center gap-1 cursor-pointer transition-opacity"
                                        :class="getAiBadge(reg.ai_status, reg.ai_score).class"
                                        @click="openDetailModal(reg)"
                                        title="Klik untuk melihat rincian laporan AI"
                                    >
                                        <Sparkles class="size-3 text-indigo-500" />
                                        {{ getAiBadge(reg.ai_status, reg.ai_score).label }}
                                    </button>
                                </div>
                                <div v-if="reg.admin_notes" class="text-[11px] text-muted-foreground mt-1 max-w-xs italic">
                                    "{{ reg.admin_notes }}"
                                </div>
                            </td>

                            <!-- Waktu -->
                            <td class="px-4 py-3.5 text-xs text-muted-foreground whitespace-nowrap">
                                {{ formatDate(reg.created_at) }}
                            </td>

                            <!-- Aksi -->
                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <!-- Tombol Audit AI untuk Admin -->
                                    <Button
                                        v-if="isAdmin"
                                        variant="outline"
                                        size="sm"
                                        class="h-8 text-xs text-indigo-600 dark:text-indigo-400 border-indigo-500/30 hover:bg-indigo-500/10 gap-1"
                                        :disabled="auditingId === reg.id"
                                        @click="runAiAudit(reg)"
                                        :title="reg.ai_status ? 'Jalankan Audit AI Ulang' : 'Jalankan Audit AI'"
                                    >
                                        <Loader2 v-if="auditingId === reg.id" class="size-3.5 animate-spin" />
                                        <Bot v-else class="size-3.5" />
                                        <span class="hidden lg:inline">{{ reg.ai_status ? 'Re-Audit' : 'Audit AI' }}</span>
                                    </Button>

                                    <!-- Lihat Anggota & Laporan AI -->
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-8 text-xs gap-1"
                                        @click="openDetailModal(reg)"
                                    >
                                        <Eye class="size-3.5" /> Roster & AI
                                    </Button>

                                    <!-- Edit untuk Peserta (jika masih pending) -->
                                    <Button
                                        v-if="!isAdmin && reg.status === 'pending'"
                                        as-child
                                        variant="secondary"
                                        size="sm"
                                        class="h-8 text-xs gap-1"
                                    >
                                        <Link :href="`/registrations/${reg.id}/edit`">
                                            <Pencil class="size-3.5" /> Ubah
                                        </Link>
                                    </Button>

                                    <!-- Batalkan untuk Peserta (jika masih pending) -->
                                    <Button
                                        v-if="!isAdmin && reg.status === 'pending'"
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 text-xs text-rose-500 hover:bg-rose-500/10"
                                        @click="deleteRegistration(reg)"
                                        title="Batalkan Pendaftaran"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>

                                    <!-- Action Panitia / Admin: Approve & Reject -->
                                    <template v-if="isAdmin">
                                        <Button
                                            v-if="reg.status !== 'approved'"
                                            variant="outline"
                                            size="sm"
                                            class="h-8 text-xs text-emerald-600 border-emerald-500/30 hover:bg-emerald-500/10"
                                            @click="updateStatus(reg.id, 'approved')"
                                            title="Setujui Tim"
                                        >
                                            <Check class="size-3.5 mr-1" /> Approve
                                        </Button>

                                        <Button
                                            v-if="reg.status !== 'rejected'"
                                            variant="outline"
                                            size="sm"
                                            class="h-8 text-xs text-rose-600 border-rose-500/30 hover:bg-rose-500/10"
                                            @click="updateStatus(reg.id, 'rejected')"
                                            title="Tolak Tim"
                                        >
                                            <X class="size-3.5 mr-1" /> Reject
                                        </Button>

                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 text-xs text-destructive hover:bg-destructive/10"
                                            @click="deleteRegistration(reg)"
                                            title="Hapus Record Pendaftaran"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="registrations.last_page > 1" class="flex items-center justify-center gap-2 pt-4">
            <Button
                variant="outline"
                size="sm"
                :disabled="!registrations.prev_page_url"
                as-child
            >
                <Link v-if="registrations.prev_page_url" :href="registrations.prev_page_url">
                    <ChevronLeft class="size-4 mr-1" /> Sebelumnya
                </Link>
                <span v-else class="flex items-center">
                    <ChevronLeft class="size-4 mr-1" /> Sebelumnya
                </span>
            </Button>

            <span class="text-xs text-muted-foreground px-2">
                Halaman {{ registrations.current_page }} dari {{ registrations.last_page }}
            </span>

            <Button
                variant="outline"
                size="sm"
                :disabled="!registrations.next_page_url"
                as-child
            >
                <Link v-if="registrations.next_page_url" :href="registrations.next_page_url">
                    Selanjutnya <ChevronRight class="size-4 ml-1" />
                </Link>
                <span v-else class="flex items-center">
                    Selanjutnya <ChevronRight class="size-4 ml-1" />
                </span>
            </Button>
        </div>

        <!-- Detail Modal Roster Pemain & Laporan AI -->
        <div
            v-if="activeModalRegistration"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs overflow-y-auto"
        >
            <div class="bg-card border border-border rounded-2xl p-6 max-w-2xl w-full shadow-2xl space-y-5 my-8 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-border">
                    <div class="flex items-center gap-2.5">
                        <div class="p-2 rounded-xl bg-primary/10 text-primary">
                            <Gamepad2 class="size-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-lg text-foreground flex items-center gap-2">
                                {{ activeModalRegistration.team_name }}
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                Turnamen: {{ activeModalRegistration.tournament.title }} ({{ activeModalRegistration.tournament.game.name }})
                            </p>
                        </div>
                    </div>
                    <Button variant="ghost" size="icon" @click="closeDetailModal">
                        <X class="size-4" />
                    </Button>
                </div>

                <div class="space-y-4 text-sm">
                    <!-- Data Kapten & Kontak -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs bg-muted/40 p-3 rounded-xl border border-border/50">
                        <div>
                            <span class="text-muted-foreground block text-[11px]">Kapten:</span>
                            <span class="font-semibold text-foreground truncate block">{{ activeModalRegistration.captain_name }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground block text-[11px]">WhatsApp:</span>
                            <span class="font-mono text-foreground">{{ activeModalRegistration.captain_whatsapp }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground block text-[11px]">Email:</span>
                            <span class="truncate block text-foreground">{{ activeModalRegistration.captain_email }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground block text-[11px]">Status Verifikasi:</span>
                            <span
                                :class="[
                                    'text-[10px] px-2 py-0.5 rounded-full font-medium border inline-flex items-center gap-1 mt-0.5',
                                    getStatusBadge(activeModalRegistration.status).class,
                                ]"
                            >
                                {{ getStatusBadge(activeModalRegistration.status).label }}
                            </span>
                        </div>
                    </div>

                    <!-- AI AUDIT CARD -->
                    <div class="rounded-xl border border-indigo-500/20 bg-linear-to-br from-indigo-500/5 via-background to-purple-500/5 p-4 space-y-3.5">
                        <div class="flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-indigo-500/10">
                            <div class="flex items-center gap-2">
                                <div class="p-1.5 rounded-lg bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                                    <Bot class="size-4" />
                                </div>
                                <div>
                                    <h4 class="font-semibold text-xs md:text-sm text-foreground flex items-center gap-1.5">
                                        Audit AI Agent
                                    </h4>
                                    <p class="text-[11px] text-muted-foreground">
                                        Pemeriksaan otomatis data tim, format ID akun game, duplikasi, & etika.
                                    </p>
                                </div>
                            </div>

                            <div v-if="activeModalRegistration.ai_status" class="flex items-center gap-2">
                                <span
                                    :class="[
                                        'text-xs px-2.5 py-1 rounded-full font-semibold border inline-flex items-center gap-1.5',
                                        getAiBadge(activeModalRegistration.ai_status, activeModalRegistration.ai_score).class,
                                    ]"
                                >
                                    <ShieldCheck v-if="activeModalRegistration.ai_status === 'passed'" class="size-3.5" />
                                    <AlertTriangle v-else-if="activeModalRegistration.ai_status === 'flagged'" class="size-3.5" />
                                    <XCircle v-else class="size-3.5" />
                                    Skor: {{ activeModalRegistration.ai_score }}/100
                                </span>
                            </div>
                        </div>

                        <!-- Jika Sudah Diaudit AI -->
                        <div v-if="activeModalRegistration.ai_status" class="space-y-3">
                            <!-- Ringkasan AI -->
                            <div class="p-3 rounded-lg bg-background/80 border border-border/80 text-xs">
                                <span class="font-semibold text-foreground block mb-1">Evaluasi AI:</span>
                                <p class="text-muted-foreground leading-relaxed">
                                    {{ activeModalRegistration.ai_summary }}
                                </p>
                            </div>

                            <!-- Checklist AI -->
                            <div v-if="activeModalRegistration.ai_checklist && activeModalRegistration.ai_checklist.length" class="space-y-1.5">
                                <span class="text-xs font-semibold text-foreground block">Poin Verifikasi Regulasi:</span>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <div
                                        v-for="(item, idx) in activeModalRegistration.ai_checklist"
                                        :key="idx"
                                        class="p-2.5 rounded-lg border text-xs flex items-start gap-2 bg-background/60"
                                        :class="item.passed ? 'border-emerald-500/20' : 'border-rose-500/20'"
                                    >
                                        <CheckCircle2 v-if="item.passed" class="size-4 text-emerald-500 shrink-0 mt-0.5" />
                                        <XCircle v-else class="size-4 text-rose-500 shrink-0 mt-0.5" />
                                        <div>
                                            <div class="font-medium text-foreground">{{ item.rule }}</div>
                                            <div class="text-[11px] text-muted-foreground mt-0.5">{{ item.note }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Rekomendasi AI -->
                            <div v-if="activeModalRegistration.ai_recommendation" class="p-3 rounded-lg bg-indigo-500/10 border border-indigo-500/20 text-xs flex items-start gap-2">
                                <Sparkles class="size-4 text-indigo-500 shrink-0 mt-0.5" />
                                <div>
                                    <strong class="text-indigo-700 dark:text-indigo-300">Rekomendasi AI untuk Panitia:</strong>
                                    <p class="text-indigo-950 dark:text-indigo-200 mt-0.5">
                                        {{ activeModalRegistration.ai_recommendation }}
                                    </p>
                                </div>
                            </div>

                            <!-- AtmoRouter Metrics -->
                            <div class="flex flex-wrap items-center justify-between text-[11px] text-muted-foreground pt-1 border-t border-border/50">
                                <div class="flex items-center gap-3">
                                    <span v-if="activeModalRegistration.ai_cost" class="inline-flex items-center gap-1 font-mono text-emerald-600 dark:text-emerald-400">
                                        <Coins class="size-3" /> Biaya: {{ activeModalRegistration.ai_cost }}
                                    </span>
                                    <span v-if="activeModalRegistration.ai_tokens_used" class="inline-flex items-center gap-1 font-mono">
                                        <Cpu class="size-3" /> {{ activeModalRegistration.ai_tokens_used }} tokens
                                    </span>
                                </div>
                                <div v-if="activeModalRegistration.ai_checked_at" class="text-[10px]">
                                    Diaudit: {{ formatDate(activeModalRegistration.ai_checked_at) }}
                                </div>
                            </div>
                        </div>

                        <!-- Jika Belum Diaudit AI -->
                        <div v-else class="flex flex-col sm:flex-row items-center justify-between gap-3 p-3.5 rounded-lg bg-muted/40 border border-dashed border-border text-center sm:text-left">
                            <div class="space-y-0.5">
                                <div class="font-medium text-xs text-foreground">Pendaftaran ini belum diaudit oleh AI.</div>
                                <div class="text-[11px] text-muted-foreground">
                                    Jalankan audit untuk mengecek kelayakan roster, format ID akun, dan duplikasi secara otomatis.
                                </div>
                            </div>

                            <Button
                                v-if="isAdmin"
                                size="sm"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs gap-1.5 shrink-0"
                                :disabled="auditingId === activeModalRegistration.id"
                                @click="runAiAudit(activeModalRegistration)"
                            >
                                <Loader2 v-if="auditingId === activeModalRegistration.id" class="size-3.5 animate-spin" />
                                <Sparkles v-else class="size-3.5" />
                                Jalankan Audit AI
                            </Button>
                        </div>
                    </div>

                    <!-- Roster Pemain Asli -->
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-muted-foreground">Daftar Roster Pemain (Input Pendaftar):</label>
                        <div class="bg-muted/30 p-3 rounded-xl border border-border whitespace-pre-line font-mono text-xs max-h-40 overflow-y-auto leading-relaxed">
                            {{ activeModalRegistration.team_members }}
                        </div>
                    </div>

                    <!-- Catatan Panitia -->
                    <div v-if="activeModalRegistration.admin_notes" class="p-3 bg-amber-500/10 border border-amber-500/20 rounded-xl text-xs text-amber-700 dark:text-amber-400">
                        <strong>Catatan Panitia:</strong> {{ activeModalRegistration.admin_notes }}
                    </div>
                </div>

                <!-- Footer Modal -->
                <div class="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-border">
                    <div v-if="isAdmin" class="flex items-center gap-1.5">
                        <Button
                            v-if="activeModalRegistration.status !== 'approved'"
                            size="sm"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1"
                            @click="updateStatus(activeModalRegistration.id, 'approved'); closeDetailModal();"
                        >
                            <Check class="size-3.5" /> Setujui Tim
                        </Button>

                        <Button
                            v-if="activeModalRegistration.status !== 'rejected'"
                            size="sm"
                            variant="destructive"
                            class="text-xs gap-1"
                            @click="updateStatus(activeModalRegistration.id, 'rejected'); closeDetailModal();"
                        >
                            <X class="size-3.5" /> Tolak Tim
                        </Button>

                        <Button
                            v-if="activeModalRegistration.ai_status"
                            variant="outline"
                            size="sm"
                            class="text-xs text-indigo-600 dark:text-indigo-400 border-indigo-500/30 hover:bg-indigo-500/10 gap-1"
                            :disabled="auditingId === activeModalRegistration.id"
                            @click="runAiAudit(activeModalRegistration)"
                        >
                            <Loader2 v-if="auditingId === activeModalRegistration.id" class="size-3.5 animate-spin" />
                            <Bot v-else class="size-3.5" />
                            Re-Audit AI
                        </Button>
                    </div>

                    <Button variant="outline" size="sm" @click="closeDetailModal" class="ml-auto">
                        Tutup
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
