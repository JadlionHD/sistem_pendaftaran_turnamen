<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    Eye,
    Gamepad2,
    Pencil,
    Search,
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
                            <th class="px-4 py-3 font-medium">Status Verifikasi</th>
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

                            <!-- Status -->
                            <td class="px-4 py-3.5">
                                <span
                                    :class="[
                                        'text-xs px-2.5 py-1 rounded-full font-medium border inline-flex items-center gap-1',
                                        getStatusBadge(reg.status).class,
                                    ]"
                                >
                                    <CheckCircle2 v-if="reg.status === 'approved'" class="size-3" />
                                    <XCircle v-else-if="reg.status === 'rejected'" class="size-3" />
                                    <Clock v-else class="size-3" />
                                    {{ getStatusBadge(reg.status).label }}
                                </span>
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
                                    <!-- Lihat Anggota -->
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-8 text-xs gap-1"
                                        @click="openDetailModal(reg)"
                                    >
                                        <Eye class="size-3.5" /> Detail Roster
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

        <!-- Detail Modal Roster Pemain -->
        <div
            v-if="activeModalRegistration"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        >
            <div class="bg-card border border-border rounded-2xl p-6 max-w-lg w-full shadow-xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border">
                    <div>
                        <h3 class="font-bold text-lg text-foreground">
                            {{ activeModalRegistration.team_name }}
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Turnamen: {{ activeModalRegistration.tournament.title }}
                        </p>
                    </div>
                    <Button variant="ghost" size="icon" @click="closeDetailModal">
                        <X class="size-4" />
                    </Button>
                </div>

                <div class="space-y-3 text-sm">
                    <div class="grid grid-cols-2 gap-2 text-xs bg-muted/40 p-3 rounded-lg">
                        <div>
                            <span class="text-muted-foreground block">Kapten:</span>
                            <span class="font-semibold">{{ activeModalRegistration.captain_name }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground block">WhatsApp:</span>
                            <span class="font-mono">{{ activeModalRegistration.captain_whatsapp }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground block">Email:</span>
                            <span>{{ activeModalRegistration.captain_email }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground block">Status:</span>
                            <span class="capitalize font-semibold">{{ activeModalRegistration.status }}</span>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-muted-foreground">Daftar Roster Pemain:</label>
                        <div class="bg-muted/30 p-3 rounded-lg border border-border whitespace-pre-line font-mono text-xs max-h-48 overflow-y-auto">
                            {{ activeModalRegistration.team_members }}
                        </div>
                    </div>

                    <div v-if="activeModalRegistration.admin_notes" class="p-3 bg-amber-500/10 rounded-lg text-xs text-amber-700 dark:text-amber-400">
                        <strong>Catatan Panitia:</strong> {{ activeModalRegistration.admin_notes }}
                    </div>
                </div>

                <div class="flex justify-end pt-3 border-t border-border">
                    <Button variant="outline" size="sm" @click="closeDetailModal">
                        Tutup
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
