<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, Gamepad2, Send, ShieldAlert, Users } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Game {
    id: number;
    name: string;
    genre: string;
    team_size: number;
    platform: string;
}

interface Tournament {
    id: number;
    title: string;
    registration_fee: number;
    prize_pool: string | null;
    game: Game;
}

interface UserAuth {
    name: string;
    email: string;
}

const props = defineProps<{
    tournament: Tournament;
    user: UserAuth;
}>();

const form = useForm({
    tournament_id: props.tournament.id,
    team_name: '',
    captain_name: props.user.name,
    captain_whatsapp: '',
    captain_email: props.user.email,
    team_members: `1. ${props.user.name} (Kapten)\n2. \n3. \n4. \n5. `,
});

const submit = () => {
    form.post('/registrations');
};

const formatRupiah = (amount: number) => {
    if (amount === 0) return 'Gratis (Rp 0)';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Turnamen Game',
                href: '/tournaments',
            },
            {
                title: 'Pendaftaran Tim',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <div class="flex flex-col gap-6 p-4 md:p-6 max-w-4xl mx-auto w-full">
        <Head :title="`Daftar: ${tournament.title}`" />

        <div class="flex items-center gap-3">
            <Button as-child variant="ghost" size="icon">
                <Link :href="`/tournaments/${tournament.id}`">
                    <ArrowLeft class="size-4" />
                </Link>
            </Button>
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Formulir Pendaftaran Tim</h1>
                <p class="text-muted-foreground text-sm">
                    Daftarkan skuad timmu untuk berlaga di turnamen {{ tournament.title }}.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Summary Info Kiri -->
            <div class="space-y-4">
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base flex items-center gap-2">
                            <Gamepad2 class="size-4 text-primary" />
                            Turnamen Pilihan
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-xs">
                        <div>
                            <span class="text-muted-foreground block">Judul Turnamen:</span>
                            <span class="font-semibold text-sm block">{{ tournament.title }}</span>
                        </div>

                        <div>
                            <span class="text-muted-foreground block">Game:</span>
                            <Badge variant="outline" class="mt-1">
                                {{ tournament.game.name }} ({{ tournament.game.genre }})
                            </Badge>
                        </div>

                        <div>
                            <span class="text-muted-foreground block">Format Tim:</span>
                            <span class="font-medium text-foreground">
                                {{ tournament.game.team_size }} Pemain Inti
                            </span>
                        </div>

                        <div>
                            <span class="text-muted-foreground block">Biaya Registrasi:</span>
                            <span class="font-semibold text-emerald-600 dark:text-emerald-400">
                                {{ formatRupiah(tournament.registration_fee) }}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <div class="p-4 rounded-xl border border-amber-500/20 bg-amber-500/5 text-amber-700 dark:text-amber-400 text-xs space-y-2">
                    <div class="flex items-center gap-1.5 font-semibold">
                        <ShieldAlert class="size-4" /> Perhatian Kapten
                    </div>
                    <p>
                        Pastikan kontak WhatsApp aktif untuk konfirmasi jadwal tanding dari panitia.
                        Data anggota dapat diedit sebelum disetujui panitia.
                    </p>
                </div>
            </div>

            <!-- Form Utama Kanan -->
            <Card class="md:col-span-2">
                <CardHeader>
                    <CardTitle>Data Tim & Anggota</CardTitle>
                    <CardDescription>
                        Lengkapi seluruh informasi yang bertanda bintang merah (*).
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-5">
                        <!-- Nama Tim -->
                        <div class="space-y-1.5">
                            <Label for="team_name">Nama Tim / Clan / Skuad <span class="text-rose-500">*</span></Label>
                            <Input
                                id="team_name"
                                v-model="form.team_name"
                                placeholder="Contoh: Garuda Esports Reborn"
                                :class="{ 'border-rose-500': form.errors.team_name }"
                            />
                            <p v-if="form.errors.team_name" class="text-xs text-rose-500 font-medium">
                                {{ form.errors.team_name }}
                            </p>
                        </div>

                        <!-- Data Kapten -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="captain_name">Nama Kapten Tim <span class="text-rose-500">*</span></Label>
                                <Input
                                    id="captain_name"
                                    v-model="form.captain_name"
                                    :class="{ 'border-rose-500': form.errors.captain_name }"
                                />
                                <p v-if="form.errors.captain_name" class="text-xs text-rose-500 font-medium">
                                    {{ form.errors.captain_name }}
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="captain_whatsapp">Nomor WhatsApp Kapten <span class="text-rose-500">*</span></Label>
                                <Input
                                    id="captain_whatsapp"
                                    v-model="form.captain_whatsapp"
                                    placeholder="Contoh: 081234567890"
                                    :class="{ 'border-rose-500': form.errors.captain_whatsapp }"
                                />
                                <p v-if="form.errors.captain_whatsapp" class="text-xs text-rose-500 font-medium">
                                    {{ form.errors.captain_whatsapp }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="captain_email">Email Aktif Kapten <span class="text-rose-500">*</span></Label>
                            <Input
                                id="captain_email"
                                type="email"
                                v-model="form.captain_email"
                                :class="{ 'border-rose-500': form.errors.captain_email }"
                            />
                            <p v-if="form.errors.captain_email" class="text-xs text-rose-500 font-medium">
                                {{ form.errors.captain_email }}
                            </p>
                        </div>

                        <!-- Daftar Anggota Tim -->
                        <div class="space-y-1.5">
                            <Label for="team_members">
                                Daftar Roster / Nama Anggota Tim <span class="text-rose-500">*</span>
                            </Label>
                            <textarea
                                id="team_members"
                                v-model="form.team_members"
                                rows="6"
                                class="w-full rounded-md border border-input bg-background p-3 text-sm shadow-xs focus:outline-hidden focus:ring-2 focus:ring-ring font-mono text-xs"
                                :class="{ 'border-rose-500': form.errors.team_members }"
                                placeholder="Tuliskan nama asli dan in-game nickname setiap pemain..."
                            />
                            <p v-if="form.errors.team_members" class="text-xs text-rose-500 font-medium">
                                {{ form.errors.team_members }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Format: Nomor. Nama Pemain (In-Game Name / Role)
                            </p>
                        </div>

                        <!-- Error global turnamen_id jika ada -->
                        <div v-if="form.errors.tournament_id" class="p-3 bg-rose-500/10 text-rose-600 rounded-lg text-xs font-medium">
                            {{ form.errors.tournament_id }}
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                            <Button as-child variant="outline">
                                <Link :href="`/tournaments/${tournament.id}`">Batal</Link>
                            </Button>
                            <Button type="submit" :disabled="form.processing">
                                <Send class="size-4 mr-2" />
                                {{ form.processing ? 'Mengirim Pendaftaran...' : 'Kirim Pendaftaran Tim' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
