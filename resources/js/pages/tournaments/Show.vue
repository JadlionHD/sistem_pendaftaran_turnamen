<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    Calendar,
    CheckCircle2,
    Clock,
    DollarSign,
    Gamepad2,
    Pencil,
    Shield,
    Trash2,
    Trophy,
    UserCheck,
    Users,
} from '@lucide/vue';
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
