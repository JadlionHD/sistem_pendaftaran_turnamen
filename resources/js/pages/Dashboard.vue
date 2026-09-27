<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Calendar, ClipboardList, Gamepad2, Plus, Trophy, Users } from '@lucide/vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import type { DashboardInvitation, Team } from '@/types';

interface Tournament {
    id: number;
    title: string;
    start_date: string;
    registration_fee: number;
    prize_pool: string | null;
    game: {
        name: string;
        genre: string;
    };
}

defineProps<{
    pendingInvitations?: DashboardInvitation[];
    stats?: {
        openTournaments: number;
        myRegistrations: number;
        gamesCount: number;
    };
    recentTournaments?: Tournament[];
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: props.currentTeam
                    ? dashboard(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
        <Head title="Dashboard Turnamen Game" />

        <PendingInvitationsModal
            v-if="pendingInvitations && pendingInvitations.length > 0"
            :invitations="pendingInvitations"
        />

        <!-- Welcome Banner -->
        <div class="bg-card border border-border rounded-2xl p-6 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold tracking-tight flex items-center gap-2">
                    <Trophy class="size-6 text-primary" />
                    Sistem Pendaftaran Turnamen Game
                </h1>
                <p class="text-sm text-muted-foreground">
                    Selamat datang di portal turnamen e-sport. Kelola kompetisi, pantau slot tim, dan ikuti pertandingan game bergengsi.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Button as-child>
                    <Link href="/tournaments">
                        <Trophy class="size-4 mr-1.5" />
                        Jelajahi Turnamen
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Metric Stat Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Card class="border-border">
                <CardHeader class="pb-2">
                    <CardDescription class="flex items-center justify-between text-xs">
                        <span>Turnamen Pendaftaran Buka</span>
                        <Trophy class="size-4 text-emerald-500" />
                    </CardDescription>
                    <CardTitle class="text-3xl font-extrabold text-foreground">
                        {{ stats?.openTournaments ?? 0 }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="pt-0">
                    <Link href="/tournaments" class="text-xs text-primary hover:underline flex items-center gap-1">
                        Lihat daftar turnamen <ArrowRight class="size-3" />
                    </Link>
                </CardContent>
            </Card>

            <Card class="border-border">
                <CardHeader class="pb-2">
                    <CardDescription class="flex items-center justify-between text-xs">
                        <span>Pendaftaran Tim Saya</span>
                        <ClipboardList class="size-4 text-blue-500" />
                    </CardDescription>
                    <CardTitle class="text-3xl font-extrabold text-foreground">
                        {{ stats?.myRegistrations ?? 0 }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="pt-0">
                    <Link href="/registrations" class="text-xs text-primary hover:underline flex items-center gap-1">
                        Cek status pendaftaran tim <ArrowRight class="size-3" />
                    </Link>
                </CardContent>
            </Card>

            <Card class="border-border">
                <CardHeader class="pb-2">
                    <CardDescription class="flex items-center justify-between text-xs">
                        <span>Kategori Game Tersedia</span>
                        <Gamepad2 class="size-4 text-purple-500" />
                    </CardDescription>
                    <CardTitle class="text-3xl font-extrabold text-foreground">
                        {{ stats?.gamesCount ?? 0 }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="pt-0">
                    <span class="text-xs text-muted-foreground">MLBB, Valorant, PUBG, Free Fire, dll.</span>
                </CardContent>
            </Card>
        </div>

        <!-- Recent Tournaments Section -->
        <Card>
            <CardHeader class="flex flex-row items-center justify-between pb-3">
                <div>
                    <CardTitle class="text-lg">Turnamen Terbaru</CardTitle>
                    <CardDescription>Kompetisi yang baru saja dipublikasikan.</CardDescription>
                </div>
                <Button as-child variant="ghost" size="sm">
                    <Link href="/tournaments">
                        Lihat Semua <ArrowRight class="size-4 ml-1" />
                    </Link>
                </Button>
            </CardHeader>
            <CardContent>
                <div v-if="!recentTournaments || recentTournaments.length === 0" class="text-center py-6 text-sm text-muted-foreground">
                    Belum ada turnamen yang ditambahkan.
                </div>
                <div v-else class="divide-y divide-border border rounded-xl overflow-hidden">
                    <div
                        v-for="t in recentTournaments"
                        :key="t.id"
                        class="p-4 flex flex-col md:flex-row md:items-center justify-between gap-3 hover:bg-muted/30 transition-colors"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <Badge variant="outline" class="text-xs">
                                    {{ t.game.name }}
                                </Badge>
                                <span class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
                                    {{ t.prize_pool || 'Piala' }}
                                </span>
                            </div>
                            <h4 class="font-semibold text-base">
                                <Link :href="`/tournaments/${t.id}`" class="hover:underline">
                                    {{ t.title }}
                                </Link>
                            </h4>
                        </div>

                        <div class="flex items-center gap-3">
                            <span class="text-xs text-muted-foreground flex items-center gap-1">
                                <Calendar class="size-3.5" /> {{ t.start_date }}
                            </span>
                            <Button as-child size="sm" variant="outline">
                                <Link :href="`/tournaments/${t.id}`">
                                    Detail
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
