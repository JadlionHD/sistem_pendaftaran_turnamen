<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save, Trophy } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Game {
    id: number;
    name: string;
    genre: string;
    platform: string;
}

interface Tournament {
    id: number;
    game_id: number;
    title: string;
    description: string;
    rules: string | null;
    max_teams: number;
    registration_fee: number;
    prize_pool: string | null;
    registration_deadline: string;
    start_date: string;
    status: 'draft' | 'open' | 'closed' | 'ongoing' | 'completed';
}

const props = defineProps<{
    tournament: Tournament;
    games: Game[];
}>();

const form = useForm({
    game_id: props.tournament.game_id,
    title: props.tournament.title,
    description: props.tournament.description,
    rules: props.tournament.rules || '',
    max_teams: props.tournament.max_teams,
    registration_fee: props.tournament.registration_fee,
    prize_pool: props.tournament.prize_pool || '',
    registration_deadline: props.tournament.registration_deadline.substring(0, 10),
    start_date: props.tournament.start_date.substring(0, 10),
    status: props.tournament.status,
});

const submit = () => {
    form.put(`/tournaments/${props.tournament.id}`);
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Turnamen Game',
                href: '/tournaments',
            },
            {
                title: 'Edit Turnamen',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <div class="flex flex-col gap-6 p-4 md:p-6 max-w-4xl mx-auto w-full">
        <Head :title="`Edit: ${tournament.title}`" />

        <div class="flex items-center gap-3">
            <Button as-child variant="ghost" size="icon">
                <Link :href="`/tournaments/${tournament.id}`">
                    <ArrowLeft class="size-4" />
                </Link>
            </Button>
            <div>
                <h1 class="text-2xl font-bold tracking-tight flex items-center gap-2">
                    <Trophy class="size-6 text-primary" />
                    Edit Turnamen: {{ tournament.title }}
                </h1>
                <p class="text-muted-foreground text-sm">
                    Ubah rincian, batas pendaftaran, atau status pelaksanaan turnamen.
                </p>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Perbarui Informasi Turnamen</CardTitle>
                <CardDescription>
                    Nilai sebelumnya telah dimuat secara otomatis ke dalam formulir.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Pilihan Game -->
                    <div class="space-y-2">
                        <Label for="game_id">Pilih Game (Data Pendukung) <span class="text-rose-500">*</span></Label>
                        <select
                            id="game_id"
                            v-model="form.game_id"
                            class="w-full h-10 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus:outline-hidden focus:ring-2 focus:ring-ring"
                            :class="{ 'border-rose-500': form.errors.game_id }"
                        >
                            <option v-for="game in games" :key="game.id" :value="game.id">
                                {{ game.name }} ({{ game.genre }} - {{ game.platform }})
                            </option>
                        </select>
                        <p v-if="form.errors.game_id" class="text-xs text-rose-500 font-medium">
                            {{ form.errors.game_id }}
                        </p>
                    </div>

                    <!-- Judul Turnamen -->
                    <div class="space-y-2">
                        <Label for="title">Judul Turnamen <span class="text-rose-500">*</span></Label>
                        <Input
                            id="title"
                            v-model="form.title"
                            :class="{ 'border-rose-500': form.errors.title }"
                        />
                        <p v-if="form.errors.title" class="text-xs text-rose-500 font-medium">
                            {{ form.errors.title }}
                        </p>
                    </div>

                    <!-- Deskripsi -->
                    <div class="space-y-2">
                        <Label for="description">Deskripsi Turnamen <span class="text-rose-500">*</span></Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="w-full rounded-md border border-input bg-background p-3 text-sm shadow-xs focus:outline-hidden focus:ring-2 focus:ring-ring"
                            :class="{ 'border-rose-500': form.errors.description }"
                        />
                        <p v-if="form.errors.description" class="text-xs text-rose-500 font-medium">
                            {{ form.errors.description }}
                        </p>
                    </div>

                    <!-- Rules -->
                    <div class="space-y-2">
                        <Label for="rules">Peraturan & Ketentuan</Label>
                        <textarea
                            id="rules"
                            v-model="form.rules"
                            rows="4"
                            class="w-full rounded-md border border-input bg-background p-3 text-sm shadow-xs focus:outline-hidden focus:ring-2 focus:ring-ring font-mono text-xs"
                            :class="{ 'border-rose-500': form.errors.rules }"
                        />
                        <p v-if="form.errors.rules" class="text-xs text-rose-500 font-medium">
                            {{ form.errors.rules }}
                        </p>
                    </div>

                    <!-- Kuota Tim & Biaya -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="space-y-2">
                            <Label for="max_teams">Kuota Slot Tim <span class="text-rose-500">*</span></Label>
                            <Input
                                id="max_teams"
                                type="number"
                                min="2"
                                max="128"
                                v-model.number="form.max_teams"
                                :class="{ 'border-rose-500': form.errors.max_teams }"
                            />
                            <p v-if="form.errors.max_teams" class="text-xs text-rose-500 font-medium">
                                {{ form.errors.max_teams }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="registration_fee">Biaya Pendaftaran (Rp) <span class="text-rose-500">*</span></Label>
                            <Input
                                id="registration_fee"
                                type="number"
                                min="0"
                                v-model.number="form.registration_fee"
                                :class="{ 'border-rose-500': form.errors.registration_fee }"
                            />
                            <p v-if="form.errors.registration_fee" class="text-xs text-rose-500 font-medium">
                                {{ form.errors.registration_fee }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="prize_pool">Total Hadiah (Prize Pool)</Label>
                            <Input
                                id="prize_pool"
                                v-model="form.prize_pool"
                                :class="{ 'border-rose-500': form.errors.prize_pool }"
                            />
                            <p v-if="form.errors.prize_pool" class="text-xs text-rose-500 font-medium">
                                {{ form.errors.prize_pool }}
                            </p>
                        </div>
                    </div>

                    <!-- Tanggal Pelaksanaan -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="space-y-2">
                            <Label for="registration_deadline">Batas Waktu Pendaftaran <span class="text-rose-500">*</span></Label>
                            <Input
                                id="registration_deadline"
                                type="date"
                                v-model="form.registration_deadline"
                                :class="{ 'border-rose-500': form.errors.registration_deadline }"
                            />
                            <p v-if="form.errors.registration_deadline" class="text-xs text-rose-500 font-medium">
                                {{ form.errors.registration_deadline }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="start_date">Tanggal Mulai Turnamen <span class="text-rose-500">*</span></Label>
                            <Input
                                id="start_date"
                                type="date"
                                v-model="form.start_date"
                                :class="{ 'border-rose-500': form.errors.start_date }"
                            />
                            <p v-if="form.errors.start_date" class="text-xs text-rose-500 font-medium">
                                {{ form.errors.start_date }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="status">Status Turnamen <span class="text-rose-500">*</span></Label>
                            <select
                                id="status"
                                v-model="form.status"
                                class="w-full h-10 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus:outline-hidden focus:ring-2 focus:ring-ring"
                                :class="{ 'border-rose-500': form.errors.status }"
                            >
                                <option value="open">Pendaftaran Buka (Open)</option>
                                <option value="ongoing">Sedang Berjalan (Ongoing)</option>
                                <option value="closed">Ditutup (Closed)</option>
                                <option value="completed">Selesai (Completed)</option>
                                <option value="draft">Draft (Belum Publik)</option>
                            </select>
                            <p v-if="form.errors.status" class="text-xs text-rose-500 font-medium">
                                {{ form.errors.status }}
                            </p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                        <Button as-child variant="outline">
                            <Link :href="`/tournaments/${tournament.id}`">Batal</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            <Save class="size-4 mr-2" />
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
