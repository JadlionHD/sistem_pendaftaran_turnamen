<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Calendar,
    ChevronLeft,
    ChevronRight,
    Gamepad2,
    Plus,
    Search,
    Trash2,
    Trophy,
    Users,
} from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';

interface Game {
    id: number;
    name: string;
    slug: string;
    genre: string;
    platform: string;
    team_size: number;
}

interface Tournament {
    id: number;
    title: string;
    slug: string;
    description: string;
    max_teams: number;
    registration_fee: number;
    prize_pool: string | null;
    registration_deadline: string;
    start_date: string;
    status: 'draft' | 'open' | 'closed' | 'ongoing' | 'completed';
    approved_teams_count: number;
    game: Game;
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
    tournaments: PaginatedData<Tournament>;
    games: Game[];
    filters: {
        game_id: string | null;
        status: string;
    };
    canManage: boolean;
}>();

const selectedGame = ref(props.filters.game_id || '');
const selectedStatus = ref(props.filters.status || 'all');

const applyFilter = () => {
    router.get(
        '/tournaments',
        {
            game_id: selectedGame.value || undefined,
            status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
        },
        { preserveState: true, replace: true },
    );
};

const resetFilter = () => {
    selectedGame.value = '';
    selectedStatus.value = 'all';
    router.get('/tournaments', {}, { preserveState: true, replace: true });
};

const deleteTournament = (id: number, title: string) => {
    if (confirm(`Yakin ingin menghapus turnamen "${title}"? Data pendaftaran yang terhubung juga akan dihapus.`)) {
        router.delete(`/tournaments/${id}`);
    }
};

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
        month: 'short',
        year: 'numeric',
    });
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'open':
            return { label: 'Pendaftaran Buka', class: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' };
        case 'ongoing':
            return { label: 'Sedang Berjalan', class: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20' };
        case 'completed':
            return { label: 'Selesai', class: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20' };
        case 'closed':
            return { label: 'Ditutup', class: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20' };
        default:
            return { label: 'Draft', class: 'bg-neutral-500/10 text-neutral-600 dark:text-neutral-400 border-neutral-500/20' };
    }
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Turnamen Game',
                href: '/tournaments',
            },
        ],
    },
});
</script>

<template>
    <div class="flex flex-col gap-6 p-4 md:p-6 max-w-7xl mx-auto w-full">
        <Head title="Katalog Turnamen Game" />

        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-border pb-6">
            <div>
                <div class="flex items-center gap-2">
                    <span class="p-2 rounded-lg bg-primary/10 text-primary">
                        <Trophy class="size-6" />
                    </span>
                    <h1 class="text-2xl md:text-3xl font-bold tracking-tight">Katalog Turnamen Game</h1>
                </div>
                <p class="text-muted-foreground mt-1 text-sm md:text-base">
                    Pilih turnamen e-sport favoritmu, daftarkan skuad terbaikmu, dan raih total prize pool!
                </p>
            </div>

            <div v-if="canManage" class="flex items-center gap-3">
                <Button as-child class="gap-2">
                    <Link href="/tournaments/create">
                        <Plus class="size-4" />
                        Tambah Turnamen
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bg-card border border-border rounded-xl p-4 flex flex-col md:flex-row gap-4 items-center justify-between shadow-xs">
            <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                    <Search class="size-4" />
                    Filter:
                </div>

                <!-- Filter Game -->
                <select
                    v-model="selectedGame"
                    @change="applyFilter"
                    class="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus:outline-hidden focus:ring-2 focus:ring-ring"
                >
                    <option value="">Semua Game</option>
                    <option v-for="game in games" :key="game.id" :value="game.id">
                        {{ game.name }} ({{ game.genre }})
                    </option>
                </select>

                <!-- Filter Status -->
                <select
                    v-model="selectedStatus"
                    @change="applyFilter"
                    class="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus:outline-hidden focus:ring-2 focus:ring-ring"
                >
                    <option value="all">Semua Status</option>
                    <option value="open">Pendaftaran Buka</option>
                    <option value="ongoing">Sedang Berjalan</option>
                    <option value="closed">Ditutup</option>
                    <option value="completed">Selesai</option>
                </select>

                <Button
                    v-if="selectedGame || selectedStatus !== 'all'"
                    variant="ghost"
                    size="sm"
                    @click="resetFilter"
                    class="text-xs text-muted-foreground"
                >
                    Reset Filter
                </Button>
            </div>

            <div class="text-xs text-muted-foreground">
                Menampilkan <strong>{{ tournaments.data.length }}</strong> dari <strong>{{ tournaments.total }}</strong> turnamen
            </div>
        </div>

        <!-- Empty State (TC-02) -->
        <div
            v-if="tournaments.data.length === 0"
            class="flex flex-col items-center justify-center p-12 text-center rounded-xl border border-dashed border-border bg-card/50"
        >
            <div class="p-4 rounded-full bg-muted text-muted-foreground mb-4">
                <Gamepad2 class="size-10 stroke-1" />
            </div>
            <h3 class="text-lg font-semibold">Belum Ada Turnamen yang Sesuai</h3>
            <p class="text-sm text-muted-foreground max-w-sm mt-1 mb-6">
                Tidak ada data turnamen yang sesuai dengan filter pencarian yang Anda pilih saat ini.
            </p>
            <div class="flex gap-3">
                <Button variant="outline" size="sm" @click="resetFilter">
                    Tampilkan Semua Turnamen
                </Button>
                <Button v-if="canManage" as-child size="sm">
                    <Link href="/tournaments/create">
                        <Plus class="size-4 mr-1" /> Buat Turnamen Baru
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Tournament Grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <Card
                v-for="item in tournaments.data"
                :key="item.id"
                class="flex flex-col justify-between hover:border-primary/50 transition-all duration-200 overflow-hidden shadow-xs hover:shadow-md"
            >
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <Badge variant="outline" class="font-normal text-xs flex items-center gap-1">
                            <Gamepad2 class="size-3" />
                            {{ item.game.name }}
                        </Badge>
                        <span
                            :class="[
                                'text-xs px-2.5 py-0.5 rounded-full font-medium border',
                                getStatusBadge(item.status).class,
                            ]"
                        >
                            {{ getStatusBadge(item.status).label }}
                        </span>
                    </div>

                    <CardTitle class="text-lg line-clamp-1 hover:text-primary transition-colors">
                        <Link :href="`/tournaments/${item.id}`">
                            {{ item.title }}
                        </Link>
                    </CardTitle>

                    <CardDescription class="line-clamp-2 text-xs text-muted-foreground mt-1 min-h-[2rem]">
                        {{ item.description }}
                    </CardDescription>
                </CardHeader>

                <CardContent class="py-2 space-y-3 text-sm border-t border-b border-border/50 bg-muted/20">
                    <!-- Prize & Fee -->
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <span class="text-muted-foreground block">Prize Pool:</span>
                            <span class="font-semibold text-emerald-600 dark:text-emerald-400">
                                {{ item.prize_pool || 'Piala & Sertifikat' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-muted-foreground block">Biaya Daftar:</span>
                            <span class="font-medium text-foreground">
                                {{ formatRupiah(item.registration_fee) }}
                            </span>
                        </div>
                    </div>

                    <!-- Slot Progress -->
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-muted-foreground flex items-center gap-1">
                                <Users class="size-3" /> Slot Tim:
                            </span>
                            <span class="font-semibold">
                                {{ item.approved_teams_count }} / {{ item.max_teams }} Tim
                            </span>
                        </div>
                        <div class="w-full bg-secondary h-2 rounded-full overflow-hidden">
                            <div
                                class="bg-primary h-full transition-all duration-300"
                                :style="{
                                    width: `${Math.min(100, Math.round((item.approved_teams_count / item.max_teams) * 100))}%`
                                }"
                            />
                        </div>
                    </div>

                    <!-- Date Info -->
                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <Calendar class="size-3.5 shrink-0" />
                        <span>Mulai: {{ formatDate(item.start_date) }}</span>
                    </div>
                </CardContent>

                <CardFooter class="pt-3 flex items-center justify-between gap-2">
                    <Button as-child variant="default" size="sm" class="flex-1">
                        <Link :href="`/tournaments/${item.id}`">
                            Lihat Detail
                        </Link>
                    </Button>

                    <div v-if="canManage" class="flex items-center gap-1">
                        <Button as-child variant="outline" size="sm">
                            <Link :href="`/tournaments/${item.id}/edit`">
                                Edit
                            </Link>
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="text-destructive hover:bg-destructive/10 size-8"
                            @click="deleteTournament(item.id, item.title)"
                            title="Hapus Turnamen"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </CardFooter>
            </Card>
        </div>

        <!-- Pagination -->
        <div v-if="tournaments.last_page > 1" class="flex items-center justify-center gap-2 pt-4">
            <Button
                variant="outline"
                size="sm"
                :disabled="!tournaments.prev_page_url"
                as-child
            >
                <Link v-if="tournaments.prev_page_url" :href="tournaments.prev_page_url">
                    <ChevronLeft class="size-4 mr-1" /> Sebelumnya
                </Link>
                <span v-else class="flex items-center">
                    <ChevronLeft class="size-4 mr-1" /> Sebelumnya
                </span>
            </Button>

            <span class="text-xs text-muted-foreground px-2">
                Halaman {{ tournaments.current_page }} dari {{ tournaments.last_page }}
            </span>

            <Button
                variant="outline"
                size="sm"
                :disabled="!tournaments.next_page_url"
                as-child
            >
                <Link v-if="tournaments.next_page_url" :href="tournaments.next_page_url">
                    Selanjutnya <ChevronRight class="size-4 ml-1" />
                </Link>
                <span v-else class="flex items-center">
                    Selanjutnya <ChevronRight class="size-4 ml-1" />
                </span>
            </Button>
        </div>
    </div>
</template>
